<?php
/**
 * Portal Receipt Report Cron Job
 * 
 * Usage:
 * php receipt_report_cron.php --type=daily
 * php receipt_report_cron.php --type=monthly
 * php receipt_report_cron.php --type=yearly
 */

if (php_sapi_name() !== 'cli') {
    die('Access denied');
}

// Configuration
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Include TCPDF (adjust path if needed)
$tcpdf_path = dirname(__DIR__) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdf_path)) {
    require_once $tcpdf_path;
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Arguments
$options = getopt("", ["type:"]);
$type = $options['type'] ?? 'daily';

// Paths
$backup_root = 'D:/portal_backups';
$report_dir = "$backup_root/receipt_reports/$type";
$log_dir = "$backup_root/logs";

if (!file_exists($report_dir))
    mkdir($report_dir, 0777, true);
if (!file_exists($log_dir))
    mkdir($log_dir, 0777, true);

// Logger
$log_file = "$log_dir/report_" . date('Y-m-d') . ".log";
function logMsg($msg)
{
    global $log_file;
    echo "[$msg]\n";
    file_put_contents($log_file, "[" . date('H:i:s') . "] $msg" . PHP_EOL, FILE_APPEND);
}

logMsg("=== Starting $type Receipt Report ===");

// ---------------------------------------------------------
// 1. DETERMINE DATE RANGE
// ---------------------------------------------------------
$start_date = '';
$end_date = '';

if ($type === 'daily') {
    // Yesterday (assuming cron runs at midnight for previous day, OR run at 11:59PM for today)
    // Let's assume run at 11:59 PM for TODAY
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    $filename_suffix = date('Y-m-d');
} elseif ($type === 'monthly') {
    // Last month
    $start_date = date('Y-m-01', strtotime('last month'));
    $end_date = date('Y-m-t', strtotime('last month'));
    $filename_suffix = date('Y-m', strtotime('last month'));
} elseif ($type === 'yearly') {
    // Last year
    $start_date = date('Y-01-01', strtotime('last year'));
    $end_date = date('Y-12-31', strtotime('last year'));
    $filename_suffix = date('Y', strtotime('last year'));
}

logMsg("Range: $start_date to $end_date");

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
try {
    // Adjust table/column names based on your actual schema
    $sql = "SELECT 
                p.id,
                p.receipt_no,
                p.payment_date,
                s.full_name as student_name,
                e.enrollment_no,
                p.amount,
                p.payment_mode,
                p.transaction_id,
                p.status
            FROM tbl_student_payment_history p
            JOIN tbl_enrolled_students e ON p.enrollment_id = e.enrollment_id
            JOIN tbl_gm_std_registration s ON e.registration_id = s.id
            WHERE DATE(p.payment_date) BETWEEN ? AND ?
            ORDER BY p.payment_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMsg("Found " . count($receipts) . " receipts.");

    if (count($receipts) > 0) {

        // ---------------------------------------------------------
        // 3. GENERATE EXCEL (.xlsx)
        // ---------------------------------------------------------
        require_once dirname(dirname(__DIR__)) . '/portal/vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Receipt Report');

        // Headers
        $headers = ['ID', 'Date', 'Receipt No', 'Student Name', 'Enrollment', 'Amount', 'Mode', 'Trans ID', 'Status'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }

        $rowNum = 2;
        $total_amount = 0;
        foreach ($receipts as $r) {
            $sheet->setCellValue('A' . $rowNum, $r['id']);
            $sheet->setCellValue('B' . $rowNum, $r['payment_date']);
            $sheet->setCellValue('C' . $rowNum, $r['receipt_no']);
            $sheet->setCellValue('D' . $rowNum, $r['student_name']);
            $sheet->setCellValue('E' . $rowNum, $r['enrollment_no']);
            $sheet->setCellValue('F' . $rowNum, $r['amount']);
            $sheet->setCellValue('G' . $rowNum, $r['payment_mode']);
            $sheet->setCellValue('H' . $rowNum, $r['transaction_id']);
            $sheet->setCellValue('I' . $rowNum, $r['status']);
            $total_amount += $r['amount'];
            $rowNum++;
        }

        // Total
        $sheet->setCellValue('E' . $rowNum, 'TOTAL');
        $sheet->getStyle('E' . $rowNum)->getFont()->setBold(true);
        $sheet->setCellValue('F' . $rowNum, $total_amount);
        $sheet->getStyle('F' . $rowNum)->getFont()->setBold(true);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $excel_file = "$report_dir/ReceiptReport_{$filename_suffix}.xlsx";
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($excel_file);
        logMsg("Excel Created: $excel_file");

        // ---------------------------------------------------------
        // 4. GENERATE PDF (Basic Table)
        // ---------------------------------------------------------
        if (class_exists('TCPDF')) {
            $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Portal Cron');
            $pdf->SetTitle("Receipt Report - $filename_suffix");
            $pdf->SetMargins(10, 10, 10);
            $pdf->AddPage();

            $html = '<h1>Receipt Report (' . $start_date . ' to ' . $end_date . ')</h1>';
            $html .= '<table border="1" cellpadding="4">
                        <thead>
                            <tr class="css-receipt_report_cron-bf35f5">
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Student Name</th>
                                <th>Amount</th>
                                <th>Mode</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach ($receipts as $r) {
                $html .= '<tr>
                            <td>' . date('d-m-Y', strtotime($r['payment_date'])) . '</td>
                            <td>' . $r['receipt_no'] . '</td>
                            <td>' . $r['student_name'] . '</td>
                            <td>' . formatIndianCurrency($r['amount']) . '</td>
                            <td>' . $r['payment_mode'] . '</td>
                          </tr>';
            }

            $html .= '<tr class="css-receipt_report_cron-043cda">
                        <td colspan="3" align="right">TOTAL</td>
                        <td>' . formatIndianCurrency($total_amount) . '</td>
                        <td></td>
                      </tr>';

            $html .= '</tbody></table>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf_file = "$report_dir/ReceiptReport_{$filename_suffix}.pdf";
            $pdf->Output($pdf_file, 'F');
            logMsg("PDF Created: $pdf_file");
        } else {
            logMsg("WARNING: TCPDF class not found, skipping PDF generation.");
        }

    } else {
        logMsg("No receipts found for this period.");
    }

} catch (Exception $e) {
    logMsg("ERROR: " . $e->getMessage());
}

logMsg("=== Completed ===");


