<?php
require_once 'AbstractGenerator.php';

class AttemptGenerator extends AbstractGenerator
{
    public function generate($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'ATT/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Attempt', $serial_number, $issued_by, $student['academic_year_id'] ?? null);

        // --- Self-Contained PDF Logic ---
        $pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle('Attempt Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 5);
        $pdf->SetMargins(15, 10, 15);
        $pdf->AddPage();

        $school_id = $student['school_id'] ?? 0;
        $background_path = '';
        if ($school_id == 1) {
            $background_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/docs/files/gm first attempt.png';
        } elseif ($school_id == 2) {
            $background_path = dirname(dirname(dirname(dirname(__DIR__)))) . '/docs/files/sgm first attempt.png';
        }

        if ($background_path && file_exists($background_path)) {
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->Image($background_path, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
            $pdf->SetAutoPageBreak(true, 5);
        }

        $pdf->SetFont('times', '', 12);

        // Sr. No. Box
        $pdf->SetXY(140, 68);
        $pdf->Cell(45, 7, 'Sr. No. ' . $serial_number, 1, 0, 'C');

        // G.R. NO
        $gr_no = $student['enrollment']['enrollment_no'] ?? 'N/A';
        $pdf->SetXY(20, 68);
        $pdf->SetFont('times', 'B', 12);
        $pdf->Cell(60, 7, 'G.R. NO: ' . $gr_no, 0, 1, 'L');

        $pdf->Ln(5);

        $name = strtoupper($student['student_name'] . ' ' . $student['fathers_name'] . ' ' . $student['surname']);
        $exam_session = "H.S.C March " . date('Y');

        $pdf->SetY(82);
        $pdf->SetFont('times', '', 12);
        $html = <<<EOD
        <div class="css-AttemptGenerator-78bb21">
            <p class="css-AttemptGenerator-999526">
                This is to certify that, Master/Miss <span class="css-AttemptGenerator-f6bf23"><b>$name</b></span> is 
                a bonafide student of this School. He/She appeared at the <b>$exam_session</b> 
                Examination Of Gujarat Secondary & Higher Secondary Education Board and passed of 
                <u><b>FIRST ATTEMPT</b></u>. To the best of my knowledge & belief He / She bears a good moral character.
            </p>
        </div>
EOD;
        $pdf->writeHTML($html, true, false, true, false, '');

        // Signatures
        if ($background_path) {
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetFont('times', '', 11);
            $pdf->SetXY(25, 125);
            $pdf->Cell(40, 5, date('d/m/Y'), 0, 0, 'L');
        } else {
            $pdf->Ln(10);
            $pdf->SetFont('times', '', 11);
            $pdf->Cell(90, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
            $pdf->Cell(90, 10, 'Principal Signature', 0, 1, 'R');
            $pdf->Cell(90, 10, 'Place: ______________', 0, 0, 'L');
            $pdf->Cell(90, 10, '(School Seal)', 0, 1, 'R');
        }

        $pdf->Output('Attempt_' . $student_id . '.pdf', 'I');
        return true;
    }
}
