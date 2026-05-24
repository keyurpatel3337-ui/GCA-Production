<?php
require_once 'AbstractGenerator.php';

class SLCGenerator extends AbstractGenerator
{
    public function generate($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'SLC/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'SLC', $serial_number, $issued_by, $student['academic_year_id'] ?? null);

        // --- Self-Contained PDF Logic ---
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle('School Leaving Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

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
        $created_at = !empty($student['created_at']) ? date('d-m-Y', strtotime($student['created_at'])) : 'N/A';
        $dob = !empty($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : 'N/A';

        $html = <<<EOD
        <table cellspacing="5" cellpadding="5" border="0" class="css-SLCGenerator-8172fa">
            <tr><td width="5%">1.</td><td width="40%">Name of Pupil</td><td width="55%">: <b>$name</b></td></tr>
            <tr><td>2.</td><td>Father's / Guardian's Name</td><td>: <b>$father</b></td></tr>
            <tr><td>3.</td><td>Mother's Name</td><td>: <b>$mother</b></td></tr>
            <tr><td>4.</td><td>Nationality</td><td>: <b>Indian</b></td></tr>
            <tr><td>5.</td><td>Date of first admission</td><td>: <b>$created_at</b></td></tr>
            <tr><td>6.</td><td>Date of Birth</td><td>: <b>$dob</b></td></tr>
            <tr><td>7.</td><td>Class in which last studied</td><td>: <b>{$student['course_name']}</b></td></tr>
            <tr><td>8.</td><td>Enrollment / GR Number</td><td>: <b>{$student['enrollment']['enrollment_no']}</b></td></tr>
            <tr><td>9.</td><td>Medium of Instruction</td><td>: <b>{$student['medium_name']}</b></td></tr>
            <tr><td>10.</td><td>Reasons for leaving the school</td><td>: <b>Course Completed</b></td></tr>
        </table>
EOD;
        $pdf->writeHTML($html, true, false, true, false, '');

        // Signatures
        $pdf->Ln(20);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(90, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
        $pdf->Cell(90, 10, 'Principal Signature', 0, 1, 'R');
        $pdf->Cell(90, 10, 'Place: ______________', 0, 0, 'L');
        $pdf->Cell(90, 10, '(School Seal)', 0, 1, 'R');

        $pdf->Output('SLC_' . $student_id . '.pdf', 'I');
        return true;
    }
}
