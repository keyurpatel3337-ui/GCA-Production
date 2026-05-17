<?php
/**
 * Student Ledger Export to Excel
 * Generates an Excel-compatible HTML table for the student ledger
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../common/api_client.php';
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_COUNSELLOR)) {
    exit('Access Denied');
}

$student_id = $_GET['student_id'] ?? '';
if (empty($student_id)) {
    exit('Student ID is required');
}

$dbOps = new DatabaseOperations();
$api = new APIClient();

// Fetch combined history and summary data via API
$response = $api->get('payments/history', ['student_id' => $student_id]);

if (!$response || !isset($response['success']) || !$response['success']) {
    exit('Failed to load student data');
}

$studentData = $response['data']['student'] ?? [];
$payments = $response['data']['payments'] ?? [];
$ledger_terms = $response['data']['ledger'] ?? [];
$summary = $response['data']['summary'] ?? [];

// Fetch additional labels (class, group, father name)
$extraInfo = $dbOps->customSelect(
    "SELECT r.fathers_name, c.course_name as current_class, g.group_name, s.school_name
     FROM tbl_gm_std_registration r
     LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
     LEFT JOIN tbl_group g ON r.group_id = g.id
     LEFT JOIN tbl_courses c ON r.course_id = c.id
     LEFT JOIN tbl_schools s ON r.school_id = s.id
     WHERE r.id = ?",
    [$student_id]
);
if (!empty($extraInfo)) {
    $studentData['fathers_name'] = $extraInfo[0]['fathers_name'];
    $studentData['current_class'] = $extraInfo[0]['current_class'];
    $studentData['group_name'] = $extraInfo[0]['group_name'];
    $studentData['school_name'] = $extraInfo[0]['school_name'];
}

$student_full_name = ($studentData['surname'] ?? '') . ' ' . ($studentData['student_name'] ?? '') . ' ' . ($studentData['fathers_name'] ?? '');

// Headers for Excel export
// Generate Excel (.xlsx)
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/portal/vendor/autoload.php';

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Student Ledger');

$rowNum = 1;

// Header
$sheet->setCellValue('A' . $rowNum, 'STUDENT LEDGER STATEMENT');
$sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(14);
$rowNum += 2;

// Student Info
$sheet->setCellValue('A' . $rowNum, 'Student Name:');
$sheet->setCellValue('B' . $rowNum, $student_full_name);
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'Student ID:');
$sheet->setCellValue('B' . $rowNum, $student_id);
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'Class / Group:');
$sheet->setCellValue('B' . $rowNum, ($studentData['current_class'] ?? 'N/A') . ' (' . ($studentData['group_name'] ?? 'N/A') . ')');
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'Mobile:');
$sheet->setCellValue('B' . $rowNum, $studentData['mob'] ?? 'N/A');
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'School:');
$sheet->setCellValue('B' . $rowNum, $studentData['school_name'] ?? 'N/A');
$rowNum += 2;

// Financial Summary
$sheet->setCellValue('A' . $rowNum, 'FINANCIAL SUMMARY');
$sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'Grand Total Allocated');
$sheet->setCellValue('E' . $rowNum, formatIndianCurrency($summary['total_allocated'] ?? 0));
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'Total Scholarship / Waiver');
$sheet->setCellValue('E' . $rowNum, formatIndianCurrency($summary['total_scholarship'] ?? 0));
$rowNum++;
$sheet->setCellValue('A' . $rowNum, 'Total Amount Paid');
$sheet->setCellValue('E' . $rowNum, formatIndianCurrency($summary['total_paid'] ?? 0));
$rowNum++;

$pending = $summary['total_pending'] ?? 0;
$overpayment = $summary['overpayment'] ?? 0;
if ($overpayment == 0 && $pending < 0) {
    $overpayment = abs($pending);
    $pending = 0;
}

$sheet->setCellValue('A' . $rowNum, 'Balance Outstanding');
$sheet->setCellValue('E' . $rowNum, formatIndianCurrency($pending));
$rowNum++;
if ($overpayment > 0) {
    $sheet->setCellValue('A' . $rowNum, 'Advance / Overpayment');
    $sheet->setCellValue('E' . $rowNum, formatIndianCurrency($overpayment));
    $rowNum++;
}
$rowNum += 2;

// Allocation breakdown
$sheet->setCellValue('A' . $rowNum, 'FEE ALLOCATION BREAKDOWN');
$sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
$rowNum++;

$alloc_headers = ['Component', 'Category', 'Allocated', 'Waiver/Scholarship', 'Payable', 'Paid', 'Balance'];
$col = 'A';
foreach ($alloc_headers as $h) {
    $sheet->setCellValue($col . $rowNum, $h);
    $sheet->getStyle($col . $rowNum)->getFont()->setBold(true);
    $col++;
}
$rowNum++;

foreach ($ledger_terms as $term) {
    // Term heading row
    $term_label = ($term['term_name'] ?? '') . ' — ' . ($term['course_name'] ?? '') . ' (AY: ' . ($term['academic_year'] ?? '') . ')';
    $sheet->setCellValue('A' . $rowNum, $term_label);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setItalic(true);
    $rowNum++;

    $allocations = $term['summary']['allocations'] ?? [];
    foreach ($allocations as $alloc) {
        $sheet->setCellValue('A' . $rowNum, $alloc['label'] ?? '');
        $sheet->setCellValue('B' . $rowNum, $alloc['category'] ?? 'Academic');
        $sheet->setCellValue('C' . $rowNum, formatIndianCurrency($alloc['gross_amount'] ?? 0));
        $sheet->setCellValue('D' . $rowNum, formatIndianCurrency($alloc['waived_amount'] ?? 0));
        $sheet->setCellValue('E' . $rowNum, formatIndianCurrency($alloc['payable_amount'] ?? 0));
        $sheet->setCellValue('F' . $rowNum, formatIndianCurrency($alloc['paid_amount'] ?? 0));
        $sheet->setCellValue('G' . $rowNum, formatIndianCurrency($alloc['pending_amount'] ?? 0));
        $rowNum++;
    }

    // Term subtotal
    $ts = $term['summary'];
    $sheet->setCellValue('A' . $rowNum, 'Term Total');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $sheet->setCellValue('C' . $rowNum, formatIndianCurrency($ts['total_allocated'] ?? 0));
    $sheet->setCellValue('D' . $rowNum, formatIndianCurrency($ts['total_waiver'] ?? 0));
    $sheet->setCellValue('E' . $rowNum, formatIndianCurrency(($ts['total_allocated'] ?? 0) - ($ts['total_waiver'] ?? 0)));
    $sheet->setCellValue('F' . $rowNum, formatIndianCurrency($ts['total_paid'] ?? 0));
    $sheet->setCellValue('G' . $rowNum, formatIndianCurrency($ts['total_pending'] ?? 0));
    $rowNum += 2;
}
$rowNum++;

// Transaction History
$sheet->setCellValue('A' . $rowNum, 'TRANSACTION HISTORY');
$sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
$rowNum++;
$headers = ['Receipt No', 'Payment Date', 'Mode', 'Remarks', 'Amount Paid'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $rowNum, $h);
    $sheet->getStyle($col . $rowNum)->getFont()->setBold(true);
    $col++;
}
$rowNum++;

if (!empty($payments)) {
    foreach ($payments as $pay) {
        $is_cancelled = ($pay['payment_status'] ?? '') === 'cancelled' || ($pay['is_cancelled'] ?? 0) == 1;
        $is_without_gst = ($pay['is_without_gst'] ?? 0) == 1;
        $amount = $pay['amount'] ?? $pay['amount_paid'] ?? 0;
        $receipt_label = $pay['receipt_no'] . ($is_cancelled ? ' (CANCELLED)' : '');
        $sheet->setCellValue('A' . $rowNum, $receipt_label);
        $sheet->setCellValue('B' . $rowNum, date('d-M-Y', strtotime($pay['payment_date'])));
        $sheet->setCellValue('C' . $rowNum, strtoupper($pay['payment_mode'] ?? 'N/A'));
        $sheet->setCellValue('D' . $rowNum, $pay['remarks'] ?? '');
        $sheet->setCellValue('E' . $rowNum, formatIndianCurrency((float) $amount));
        $rowNum++;
    }
} else {
    $sheet->setCellValue('A' . $rowNum, 'No payment history found');
}

// Auto-size columns
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Headers for Excel export
$filename = "Ledger_" . str_replace(' ', '_', ($studentData['student_name'] ?? 'student')) . "_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>