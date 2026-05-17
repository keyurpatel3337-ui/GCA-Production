<?php
require_once 'AbstractGenerator.php';

class ProvisionalGenerator extends AbstractGenerator
{
    public function generate($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'PROV/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Provisional', $serial_number, $issued_by, $student['academic_year_id'] ?? null);

        // --- Self-Contained PDF Logic ---
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle('Provisional Passing Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'PROVISIONAL PASSING CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(20);

        $html = "This is to certify that " . strtoupper($student['student_name'] . ' ' . $student['surname']) . " has Provisionally Passed the " . ($student['course_name'] ?? 'N/A') . " examination.";
        $pdf->SetFont('helvetica', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Signatures
        $pdf->Ln(20);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(90, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
        $pdf->Cell(90, 10, 'Principal Signature', 0, 1, 'R');
        $pdf->Cell(90, 10, 'Place: ______________', 0, 0, 'L');
        $pdf->Cell(90, 10, '(School Seal)', 0, 1, 'R');

        $pdf->Output('Provisional_' . $student_id . '.pdf', 'I');
        return true;
    }
}
