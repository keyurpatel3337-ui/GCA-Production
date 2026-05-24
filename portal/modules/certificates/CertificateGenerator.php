<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

class CertificateGenerator
{
    private $db;
    private $op;

    public function __construct()
    {
        global $conn;
        $this->db = $conn;
        $this->op = new Operation();
    }

    public function fetchStudentDetails($student_id)
    {
        $student = $this->op->readWithJoin(
            'tbl_gm_std_registration s',
            [
                's.*',
                'b.board_name',
                'm.medium_name',
                'g.group_name',
                'c.course_name',
                'ay.year_name as academic_year',
                'sch.school_name as current_school_name'
            ],
            [
                ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
                ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
                ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 's.school_id = sch.id']
            ],
            ['s.id' => $student_id]
        );

        if (!$student) {
            return false;
        }

        $enrollment = $this->op->readWithJoin(
            'tbl_enrolled_students e',
            ['e.*', 'sch.school_name', 'd.division_name'],
            [
                ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 'e.school_id = sch.id'],
                ['type' => 'LEFT', 'table' => 'tbl_division d', 'on' => 'e.division_id = d.id']
            ],
            ['e.registration_id' => $student_id]
        );

        $student['enrollment'] = $enrollment ? $enrollment : [];
        return $student;
    }

    public function saveCertificateRecord($student_id, $certificate_type, $serial_number, $issued_by)
    {
        $sql = "INSERT INTO tbl_issued_certificates (student_id, certificate_type, serial_number, issued_date, issued_by) VALUES (?, ?, ?, CURDATE(), ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$student_id, $certificate_type, $serial_number, $issued_by]);
    }

    private function initPDF($title, $format = 'A4', $orientation = 'P')
    {
        $pdf = new TCPDF($orientation, 'mm', $format, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle($title);
        $pdf->SetMargins(15, 20, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        return $pdf;
    }

    private function addBackground($pdf, $image_path)
    {
        if (file_exists($image_path)) {
            // Get page dimensions
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();

            // Disable auto-page-break to prevent it from moving to the next page when adding the image
            $pdf->SetAutoPageBreak(false, 0);

            // Add image as background covering the whole page
            $pdf->Image($image_path, 0, 0, $pageWidth, $pageHeight, '', '', '', false, 300, '', false, false, 0);

            // Re-enable auto-page-break with a very small margin
            $pdf->SetAutoPageBreak(true, 5);

            // Set margins to fit content within the background layout
            // For A5 Landscape (148mm height), we need more vertical space
            $pdf->SetMargins(20, 50, 20);
        }
    }

    private function addSignatures($pdf, $skipLabels = false)
    {
        $pdf->SetFont('times', '', 10); // Slightly smaller font for bottom details

        if ($skipLabels) {
            $pdf->SetAutoPageBreak(false, 0);

            // Get current page height
            $pageHeight = $pdf->getPageHeight();

            // Date value near "Date :" label (bottom left)
            // Template "Date :" is usually around 115mm from top in A5 Landscape
            $pdf->SetXY(25, 123);
            $pdf->Cell(40, 5, date('d/m/Y'), 0, 0, 'L');

            $pdf->SetAutoPageBreak(true, 5);
            return;
        }

        $pdf->Ln(10);
        $y = $pdf->GetY();
        // Prevent overflow to new page on A5 Landscape
        if ($y > 115) {
            $y = 115;
            $pdf->SetY($y);
        }

        $pdf->Cell(90, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
        $pdf->Cell(90, 10, 'Principal Signature', 0, 1, 'R');
        $pdf->Cell(90, 10, 'Place: ______________', 0, 0, 'L');
        $pdf->Cell(90, 10, '(School Seal)', 0, 1, 'R');
    }

    public function generateBonafide($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'BON/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Bonafide', $serial_number, $issued_by);

        $pdf = $this->initPDF('Bonafide Certificate', 'A5', 'L');

        // Background logic based on school_id
        $school_id = $student['school_id'] ?? 0;
        $background_path = '';
        if ($school_id == 1) {
            $background_path = dirname(dirname(dirname(__DIR__))) . '/docs/files/gm bonafide.png';
        } elseif ($school_id == 2) {
            $background_path = dirname(dirname(dirname(__DIR__))) . '/docs/files/sgm bonafide.png';
        }

        if ($background_path) {
            $this->addBackground($pdf, $background_path);
        }

        $pdf->SetFont('times', '', 14);
        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $course = $student['course_name'] ?? 'N/A';
        $academic_year = $student['academic_year'] ?? 'N/A';
        $enrollment_no = $student['enrollment']['enrollment_no'] ?? 'N/A';
        $dob = !empty($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : 'N/A';
        $religion_caste = ($student['religion'] ?? '') . ' ' . ($student['cast_name'] ?? '');

        $html = <<<EOD
        <div class="css-CertificateGenerator-c77641">
            <p>This is to certify that,</p>
            <p class="css-CertificateGenerator-999526">
                Mr. / Ms. <u><b>$name</b></u> a bonafide student of this school. 
                He / She is studying Std. <u><b>$course</b></u> Class with G.R.No./ Reg.No <u><b>G.R. No. $enrollment_no</b></u> 
                in the Academic year <u><b>$academic_year</b></u> & Date of Birth is <u><b>$dob</b></u> 
                Religion & Caste <u><b>$religion_caste</b></u> record. To the best of my knowledge & belief. 
                He / She bears a good moral character.
            </p>
        </div>
EOD;

        $pdf->SetY(75); // Moved further down to clear the header
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf, !empty($background_path));
        $pdf->Output('Bonafide_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateCharacter($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'CHAR/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Character', $serial_number, $issued_by);

        $pdf = $this->initPDF('Character Certificate', 'A5', 'L');

        $pdf->SetFont('times', '', 12);

        // Sr. No. in top right (matching Image 2)
        $pdf->SetXY(150, 20);
        $pdf->Cell(45, 8, 'Sr. No. ' . $serial_number, 1, 1, 'C');

        $pdf->Ln(10);

        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $course = $student['course_name'] ?? 'N/A';
        $enrollment_no = $student['enrollment']['enrollment_no'] ?? 'N/A';
        $roll_no = $student['enrollment']['roll_no'] ?? 'N/A';

        $html = <<<EOD
        <div class="css-CertificateGenerator-e9db39">
            CLASS. <u><b>$course</b></u> ROLLNO. <u><b>$roll_no</b></u>
        </div>
        <br>
        <div class="css-CertificateGenerator-c77641">
            <p class="css-CertificateGenerator-999526">
                This is to Certify that, Master/Miss. <u><b>$name</b></u> is a 
                bonafide Student of <u><b>$course</b></u>. of this Institute. 
                his / her general registration no is.<u><b>$enrollment_no</b></u> 
                During his / her Educational period in this institute 
                he / she is beared good moral character. We wish him / her good 
                and prosperous future.
            </p>
        </div>
EOD;

        $pdf->SetY(45);
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf, false); // Character certificate seems to have no background provided yet
        $pdf->Output('Character_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateSLC($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'SLC/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'SLC', $serial_number, $issued_by);

        $pdf = $this->initPDF('School Leaving Certificate');
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 15, 'SCHOOL LEAVING / TRANSFER CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'SLC No: ' . $serial_number, 0, 0, 'L');
        $pdf->Cell(0, 10, 'Date: ' . date('d/M/Y'), 0, 1, 'R');
        $pdf->Ln(10);

        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $father = strtoupper($student['fathers_name'] ?? 'N/A');
        $mother = strtoupper($student['mothername'] ?? 'N/A');

        $html = <<<EOD
        <table cellspacing="5" cellpadding="5" border="0" class="css-CertificateGenerator-8172fa">
            <tr><td width="5%">1.</td><td width="40%">Name of Pupil</td><td width="55%">: <b>$name</b></td></tr>
            <tr><td>2.</td><td>Father's / Guardian's Name</td><td>: <b>$father</b></td></tr>
            <tr><td>3.</td><td>Mother's Name</td><td>: <b>$mother</b></td></tr>
            <tr><td>4.</td><td>Nationality</td><td>: <b>Indian</b></td></tr>
            <tr><td>5.</td><td>Date of first admission</td><td>: <b>{$student['created_at']}</b></td></tr>
            <tr><td>6.</td><td>Date of Birth</td><td>: <b>{$student['dob']}</b></td></tr>
            <tr><td>7.</td><td>Class in which last studied</td><td>: <b>{$student['course_name']}</b></td></tr>
            <tr><td>8.</td><td>Enrollment / GR Number</td><td>: <b>{$student['enrollment']['enrollment_no']}</b></td></tr>
            <tr><td>9.</td><td>Medium of Instruction</td><td>: <b>{$student['medium_name']}</b></td></tr>
            <tr><td>10.</td><td>Reasons for leaving the school</td><td>: <b>Course Completed</b></td></tr>
        </table>
EOD;
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf);
        $pdf->Output('SLC_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateAttempt($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'ATT/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Attempt', $serial_number, $issued_by);

        $pdf = $this->initPDF('Attempt Certificate', 'A5', 'L');

        // Background logic based on school_id
        $school_id = $student['school_id'] ?? 0;
        $background_path = '';
        if ($school_id == 1) {
            $background_path = dirname(dirname(dirname(__DIR__))) . '/docs/files/gm first attempt.png';
        } elseif ($school_id == 2) {
            $background_path = dirname(dirname(dirname(__DIR__))) . '/docs/files/sgm first attempt.png';
        }

        if ($background_path) {
            $this->addBackground($pdf, $background_path);
        }

        $pdf->SetFont('times', '', 12);

        // Sr. No. Box below the FIRST ATTEMPT banner
        $pdf->SetXY(140, 68);
        $pdf->Cell(45, 7, 'Sr. No. ' . $serial_number, 1, 0, 'C');

        $pdf->Ln(5);

        // G.R. NO - Same row as Sr. No. (Left side)
        $gr_no = $student['enrollment']['enrollment_no'] ?? 'N/A';
        $pdf->SetXY(20, 68);
        $pdf->SetFont('times', 'B', 12);
        $pdf->Cell(60, 7, 'G.R. NO: ' . $gr_no, 0, 1, 'L');

        $pdf->Ln(5);

        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $exam_session = "H.S.C March " . date('Y'); // Dynamic but based on Image 1 context

        $pdf->SetY(82);
        $pdf->SetFont('times', '', 12);
        $html = <<<EOD
        <div class="css-CertificateGenerator-78bb21">
            <p class="css-CertificateGenerator-999526">
                This is to certify that, Master/Miss <span class="css-CertificateGenerator-f6bf23"><b>$name</b></span> is 
                a bonafide student of this School. He/She appeared at the <b>$exam_session</b> 
                Examination Of Gujarat Secondary & Higher Secondary Education Board and passed of 
                <u><b>FIRST ATTEMPT</b></u>. To the best of my knowledge & belief He / She bears a good moral character.
            </p>
        </div>
EOD;
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf, !empty($background_path));
        $pdf->Output('Attempt_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateFeesPaid($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'FEE/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Fees Paid', $serial_number, $issued_by);

        $pdf = $this->initPDF('Fees Paid Certificate');
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'FEES PAID CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(20);

        $html = "This is to certify that all fees for the academic year " . ($student['academic_year'] ?? 'N/A') . " have been paid by " . strtoupper($student['student_name']) . ".";
        $pdf->SetFont('helvetica', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf);
        $pdf->Output('FeesPaid_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateProvisional($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'PROV/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Provisional', $serial_number, $issued_by);

        $pdf = $this->initPDF('Provisional Passing Certificate');
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'PROVISIONAL PASSING CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(20);

        $html = "This is to certify that " . strtoupper($student['student_name']) . " has Provisionally Passed the " . ($student['course_name'] ?? 'N/A') . " examination.";
        $pdf->SetFont('helvetica', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf);
        $pdf->Output('Provisional_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateCourseCompletion($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'COMP/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Course Completion', $serial_number, $issued_by);

        $pdf = $this->initPDF('Course Completion Certificate');
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'COURSE COMPLETION CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(20);

        $html = "This is to certify that " . strtoupper($student['student_name']) . " has successfully completed the " . ($student['course_name'] ?? 'N/A') . " course.";
        $pdf->SetFont('helvetica', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf);
        $pdf->Output('CourseCompletion_' . $student_id . '.pdf', 'I');
        return true;
    }

    public function generateSports($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'SPORT/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Sports', $serial_number, $issued_by);

        $pdf = $this->initPDF('Sports Achievement Certificate');
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'SPORTS & EXTRA-CURRICULAR CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(20);

        $html = "This is to certify that " . strtoupper($student['student_name']) . " has shown outstanding performance in Sports/Extra-Curricular activities.";
        $pdf->SetFont('helvetica', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');
        $this->addSignatures($pdf);
        $pdf->Output('Sports_' . $student_id . '.pdf', 'I');
        return true;
    }
}