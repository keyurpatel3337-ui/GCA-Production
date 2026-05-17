<?php
/**
 * Comprehensive Financial Export
 * Handles bulk export of financial data in Excel (CSV) and PDF formats
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../common/api_client.php';
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    exit('Access Denied');
}

$format = $_GET['format'] ?? 'excel'; // 'excel' (actually CSV) or 'pdf'
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'all';

$api = new APIClient();
$dbOps = new DatabaseOperations();

// Fetch data from financial reports API
$response = $api->get('payments/financial-reports', [
    'from_date' => $from_date,
    'to_date' => $to_date,
    'chart_view' => 'daily'
]);

if (!$response || !isset($response['success']) || !$response['success']) {
    exit('Failed to load financial data for export');
}

$data = $response['data'];
$summary = $data['collection_stats'] ?? ['total' => 0, 'count' => 0];
$modes = $data['mode_breakdown'] ?? [];
$types = $data['type_breakdown'] ?? [];
$pivot = $data['daily_breakdown'] ?? [];

if ($format === 'pdf') {
    // Generate PDF using TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA System');
    $pdf->SetTitle('Financial Summary Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Report Header
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'FINANCIAL SUMMARY REPORT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, 'Period: ' . date('d-M-Y', strtotime($from_date)) . ' to ' . date('d-M-Y', strtotime($to_date)), 0, 1, 'C');
    $pdf->Ln(5);

    // Summary Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(0, 8, ' 1. OVERALL COLLECTION SUMMARY', 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(95, 8, 'Total Collection (Received):', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Rs. ' . formatIndianCurrency($summary['total']), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(95, 8, 'Total Transactions:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, $summary['count'], 0, 1, 'L');
    $pdf->Ln(5);

    // Mode Breakdown
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, ' 2. PAYMENT MODE BREAKDOWN', 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, ' Payment Mode', 1, 0, 'L');
    $pdf->Cell(40, 8, ' Transactions', 1, 0, 'C');
    $pdf->Cell(0, 8, ' Total Amount (Rs.)', 1, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    foreach ($modes as $m) {
        $pdf->Cell(60, 7, ' ' . strtoupper($m['payment_mode']), 1, 0, 'L');
        $pdf->Cell(40, 7, $m['count'], 1, 0, 'C');
        $pdf->Cell(0, 7, formatIndianCurrency($m['total']) . ' ', 1, 1, 'R');
    }
    $pdf->Ln(5);

    // Type Breakdown
    $pdf->SetFont('helvetica', 'B', 12);
    if ($pdf->GetY() > 240)
        $pdf->AddPage();
    $pdf->Cell(0, 8, ' 3. FEE TYPE BREAKDOWN', 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(100, 8, ' Fee Type', 1, 0, 'L');
    $pdf->Cell(30, 8, ' Count', 1, 0, 'C');
    $pdf->Cell(0, 8, ' Amount (Rs.)', 1, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    foreach ($types as $t) {
        if ($pdf->GetY() > 270)
            $pdf->AddPage();
        $pdf->Cell(100, 7, ' ' . $t['payment_type'], 1, 0, 'L');
        $pdf->Cell(30, 7, $t['count'], 1, 0, 'C');
        $pdf->Cell(0, 7, formatIndianCurrency($t['total']) . ' ', 1, 1, 'R');
    }
    $pdf->Ln(5);

    // Daily Pivot (Optional for PDF as it could be very wide)
    if (!empty($pivot['data'])) {
        $pdf->AddPage('L'); // Landscape for wide table
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, ' 4. DAILY TRANSACTION PIVOT TABLE', 0, 1, 'L', true);
        $pdf->Ln(2);

        $colWidth = 180 / (count($pivot['payment_types']) + 3);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(25, 8, ' Date', 1, 0, 'L');
        foreach ($pivot['payment_types'] as $pt) {
            $shortName = strlen($pt) > 15 ? substr($pt, 0, 12) . '..' : $pt;
            $pdf->Cell($colWidth, 8, $shortName, 1, 0, 'C');
        }
        $pdf->Cell(20, 8, ' Online', 1, 0, 'R');
        $pdf->Cell(20, 8, ' Offline', 1, 0, 'R');
        $pdf->Cell(0, 8, ' Total', 1, 1, 'R');

        $pdf->SetFont('helvetica', '', 8);
        foreach ($pivot['data'] as $row) {
            if ($pdf->GetY() > 180)
                $pdf->AddPage('L');
            $pdf->Cell(25, 7, ' ' . date('d-M-Y', strtotime($row['date'])), 1, 0, 'L');
            foreach ($pivot['payment_types'] as $pt) {
                $val = $row['types'][$pt] ?? 0;
                $pdf->Cell($colWidth, 7, $val > 0 ? number_format($val, 0) : '-', 1, 0, 'C');
            }
            $pdf->Cell(20, 7, number_format($row['online_total'], 0), 1, 0, 'R');
            $pdf->Cell(20, 7, number_format($row['offline_total'], 0), 1, 0, 'R');
            $pdf->Cell(0, 7, number_format($row['day_total'], 0), 1, 1, 'R');
        }
    }

    $pdf->Output('Financial_Report_' . date('Ymd') . '.pdf', 'I');
    exit;

    // Generate Excel (.xlsx)
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Financial Summary');

    $rowNum = 1;

    // Title
    $sheet->setCellValue('A' . $rowNum, 'COMPREHENSIVE FINANCIAL REPORT');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(14);
    $rowNum++;

    $sheet->setCellValue('A' . $rowNum, 'Period:');
    $sheet->setCellValue('B' . $rowNum, date('d-M-Y', strtotime($from_date)) . ' to ' . date('d-M-Y', strtotime($to_date)));
    $rowNum += 2;

    // SECTION 1: OVERALL SUMMARY
    $sheet->setCellValue('A' . $rowNum, 'SECTION 1: OVERALL SUMMARY');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'Total Collection');
    $sheet->setCellValue('B' . $rowNum, 'Rs. ' . formatIndianCurrency($summary['total']));
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'Total Transactions');
    $sheet->setCellValue('B' . $rowNum, $summary['count']);
    $rowNum += 2;

    // SECTION 2: PAYMENT MODE BREAKDOWN
    $sheet->setCellValue('A' . $rowNum, 'SECTION 2: PAYMENT MODE BREAKDOWN');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'Mode');
    $sheet->setCellValue('B' . $rowNum, 'Transactions');
    $sheet->setCellValue('C' . $rowNum, 'Amount (Rs.)');
    $sheet->getStyle('A' . $rowNum . ':C' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    foreach ($modes as $m) {
        $sheet->setCellValue('A' . $rowNum, strtoupper($m['payment_mode']));
        $sheet->setCellValue('B' . $rowNum, $m['count']);
        $sheet->setCellValue('C' . $rowNum, round($m['total']));
        $rowNum++;
    }
    $rowNum += 1;

    // SECTION 3: FEE TYPE BREAKDOWN
    $sheet->setCellValue('A' . $rowNum, 'SECTION 3: FEE TYPE BREAKDOWN');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'Fee Type');
    $sheet->setCellValue('B' . $rowNum, 'Count');
    $sheet->setCellValue('C' . $rowNum, 'Amount (Rs.)');
    $sheet->getStyle('A' . $rowNum . ':C' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    foreach ($types as $t) {
        $sheet->setCellValue('A' . $rowNum, $t['payment_type']);
        $sheet->setCellValue('B' . $rowNum, $t['count']);
        $sheet->setCellValue('C' . $rowNum, round($t['total']));
        $rowNum++;
    }
    $rowNum += 1;

    // SECTION 4: DAILY TRANSACTION PIVOT
    if (!empty($pivot['data'])) {
        $sheet->setCellValue('A' . $rowNum, 'SECTION 4: DAILY TRANSACTION PIVOT');
        $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
        $rowNum++;

        $header = ['Date'];
        foreach ($pivot['payment_types'] as $pt)
            $header[] = $pt;
        $header = array_merge($header, ['Online Total', 'Offline Total', 'Day Total']);

        $col = 'A';
        foreach ($header as $h) {
            $sheet->setCellValue($col . $rowNum, $h);
            $sheet->getStyle($col . $rowNum)->getFont()->setBold(true);
            $col++;
        }
        $rowNum++;

        foreach ($pivot['data'] as $p_row) {
            $sheet->setCellValue('A' . $rowNum, $p_row['date']);
            $col = 'B';
            foreach ($pivot['payment_types'] as $pt) {
                $sheet->setCellValue($col . $rowNum, $p_row['types'][$pt] ?? 0);
                $col++;
            }
            $sheet->setCellValue($col++ . $rowNum, round($p_row['online_total']));
            $sheet->setCellValue($col++ . $rowNum, round($p_row['offline_total']));
            $sheet->setCellValue($col++ . $rowNum, round($p_row['day_total']));
            $rowNum++;
        }
    }

    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Export as .xlsx
    $filename = "Financial_Report_" . date('Ymd') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
