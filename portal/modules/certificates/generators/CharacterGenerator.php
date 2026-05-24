<?php
require_once 'AbstractGenerator.php';

class CharacterGenerator extends AbstractGenerator
{
    public function generate($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'CHAR/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Character', $serial_number, $issued_by, $student['academic_year_id'] ?? null);

        // --- Self-Contained PDF Logic ---
        $pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle('Character Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 5);
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

        // --- Background Image Selection ---
        $school_id = $student['school_id'] ?? 1;
        $bg_image = '';
        if ($school_id == 1) {
            $bg_image = dirname(__DIR__, 4) . '/docs/files/GM character certificate.png';
        } else if ($school_id == 2) {
            $bg_image = dirname(__DIR__, 4) . '/docs/files/SGM character certificate.png';
        }

        if ($bg_image && file_exists($bg_image)) {
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->Image($bg_image, 0, 0, 210, 148, '', '', '', false, 300, '', false, false, 0);
            $pdf->SetAutoPageBreak(true, 5);
        }

        $pdf->SetFont('times', '', 12);

        // Sr No position
        $pdf->SetXY(140, 65);
        $pdf->Cell(55, 8, 'Sr. No. ' . $serial_number, 0, 1, 'R');

        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $course = $student['course_name'] ?? 'N/A';
        $enrollment_no = $student['gr_no'] ?? ($student['enrollment']['enrollment_no'] ?? 'N/A');
        $roll_no = $student['enrollment']['roll_no'] ?? 'N/A';

        // Class and Roll No position
        $pdf->SetXY(15, 75);
        $html_class = <<<EOD
        <div style="text-align: right; font-family: times; font-size: 11pt;">
            CLASS. <u><b>$course</b></u> ROLLNO. <u><b>$roll_no</b></u>
        </div>
EOD;
        $pdf->writeHTML($html_class, true, false, true, false, '');

        // Main content starting position
        $html_content = <<<EOD
        <div style="text-align: justify; line-height: 1.8; font-family: times; font-size: 13pt;">
            <p style="text-indent: 40px;">
                This is to Certify that, Master/Miss. <u><b>$name</b></u> is a 
                bonafide Student of <u><b>$course</b></u>. of this Institute. 
                his / her general registration no is.<u><b>$enrollment_no</b></u> 
                During his / her Educational period in this institute 
                he / she is beared good moral character. We wish him / her good 
                and prosperous future.
            </p>
        </div>
EOD;

        $pdf->SetY(70);
        $pdf->writeHTML($html_content, true, false, true, false, '');

        // Date only at bottom left - matched with BonafideGenerator.php
        $pdf->SetXY(25, 125);
        $pdf->SetFont('times', '', 11);
        $pdf->Cell(40, 5, date('d/m/Y'), 0, 0, 'L');

        $pdf->Output('Character_' . $student_id . '.pdf', 'I');
        return true;
    }
}
