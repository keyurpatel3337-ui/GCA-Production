<?php
require_once 'AbstractGenerator.php';

class FeesPaidGenerator extends AbstractGenerator
{
    public function generate($student_id, $issued_by)
    {
        $student = $this->fetchStudentDetails($student_id);
        if (!$student)
            return false;

        $serial_number = 'FEE/' . date('Y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
        $this->saveCertificateRecord($student_id, 'Fees Paid', $serial_number, $issued_by, $student['academic_year_id'] ?? null);

        // --- Self-Contained PDF Logic ---
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('GCA');
        $pdf->SetTitle('Fees Paid Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'FEES PAID CERTIFICATE', 0, 1, 'C');
        $pdf->Ln(20);

        $html = "This is to certify that all fees for the academic year " . ($student['academic_year'] ?? 'N/A') . " have been paid by " . strtoupper($student['student_name'] . ' ' . $student['surname']) . ".";
        $pdf->SetFont('helvetica', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Signatures
        $pdf->Ln(20);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(90, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
        $pdf->Cell(90, 10, 'Principal Signature', 0, 1, 'R');
        $pdf->Cell(90, 10, 'Place: ______________', 0, 0, 'L');
        $pdf->Cell(90, 10, '(School Seal)', 0, 1, 'R');

        $pdf->Output('FeesPaid_' . $student_id . '.pdf', 'I');
        return true;
    }
}
