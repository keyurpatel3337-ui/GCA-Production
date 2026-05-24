<?php
require_once 'AbstractGenerator.php';

class BonafideGenerator extends AbstractGenerator
{
    public function generate($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'BON/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Bonafide', $serial_number, $issued_by, $student['academic_year_id'] ?? null);

        // --- Self-Contained PDF Logic ---
        $pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle('Bonafide Certificate');

        // Disable auto page break temporarily to prevent accidental spillover
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 10, 15);
        $pdf->AddPage();

        $school_id = $student['school_id'] ?? 0;
        $background_path = '';
        if ($school_id == 1) {
            $background_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/docs/files/gm bonafide.png';
        } elseif ($school_id == 2) {
            $background_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/docs/files/sgm bonafide.png';
        }

        if ($background_path && file_exists($background_path)) {
            $pdf->Image($background_path, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
        }

        // Re-enable auto page break with small margin for content safety
        $pdf->SetAutoPageBreak(true, 5);

        $pdf->SetFont('times', '', 14);
        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $course = $student['course_name'] ?? 'N/A';
        $academic_year = $student['academic_year'] ?? 'N/A';
        $enrollment_no = $student['enrollment']['enrollment_no'] ?? 'N/A';
        $dob = !empty($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : 'N/A';
        $religion_caste = ($student['religion'] ?? '') . ' ' . ($student['cast_name'] ?? '');

        // Main Content - Positioned to clear header
        $pdf->SetY(60);
        $html = <<<EOD
        <div class="css-BonafideGenerator-6fdfdb">
            <p>This is to certify that,</p>
            <p class="css-BonafideGenerator-999526">
                Mr. / Ms. <u><b>$name</b></u> a bonafide student of this school. 
                He / She is studying Std. <u><b>$course</b></u> Class with G.R.No./ Reg.No <u><b>G.R. No. $enrollment_no</b></u> 
                in the Academic year <u><b>$academic_year</b></u> & Date of Birth is <u><b>$dob</b></u> 
                Religion & Caste <u><b>$religion_caste</b></u> record. To the best of my knowledge & belief. 
                He / She bears a good moral character.
            </p>
        </div>
EOD;
        $pdf->writeHTML($html, true, false, true, false, '');

        // Signatures / Date Area
        if ($background_path) {
            // Precise positioning for template
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetFont('times', '', 11);
            $pdf->SetXY(25, 123);
            $pdf->Cell(40, 5, date('d/m/Y'), 0, 0, 'L');

        } else {
            // Standard layout
            $pdf->Ln(5);
            $pdf->SetFont('times', '', 11);
            $pdf->Cell(90, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
            $pdf->Cell(90, 10, 'Principal Signature', 0, 1, 'R');
            $pdf->Cell(90, 10, 'Place: Bhavnagar', 0, 0, 'L');
            $pdf->Cell(90, 10, '(School Seal)', 0, 1, 'R');
        }

        $pdf->Output('Bonafide_' . $student_id . '.pdf', 'I');
        return true;
    }
}
