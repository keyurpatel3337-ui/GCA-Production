<?php
/**
 * Student Ledger Export to PDF
 * Generates a professional PDF statement for the student ledger
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
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_COUNSELLOR)) {
    exit('Access Denied');
}

$student_id = $_REQUEST['student_id'] ?? '';
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

$student_full_name = trim(($studentData['surname'] ?? '') . ' ' . ($studentData['student_name'] ?? '') . ' ' . ($studentData['fathers_name'] ?? ''));

// Get active receipt configuration for header
$config = $dbOps->selectOne('tbl_receipt_configuration', ['*'], ['is_active' => 1]);

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Counselling Management System');
$pdf->SetAuthor(htmlspecialchars_decode($config['organization_name'] ?? 'System'));
$pdf->SetTitle('Student Ledger - ' . $student_id);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));

// Set margins
$pdf->SetMargins(5, 5, 5);
$pdf->SetAutoPageBreak(true, 10);

// Add a page
$pdf->AddPage();

// --- Header Section ---
if ($config) {
    // Logo
    if (!empty($config['logo_path'])) {
        $logo_path = str_replace('../', '', $config['logo_path']);
        $full_logo_path = dirname(__DIR__, 4) . '/counselling-backend/' . $logo_path;
        if (file_exists($full_logo_path)) {
            @$pdf->Image($full_logo_path, 5, 5, 20, 20, '', '', '', false, 300, '', false, false, 0);
        }
    }

    // Org Name & Address
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY(28, 5);
    $pdf->Cell(0, 7, htmlspecialchars_decode($config['organization_name']), 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX(28);
    $org_address = htmlspecialchars_decode($config['address'] ?? '') . ', ' . htmlspecialchars_decode($config['city'] ?? '');
    $pdf->Cell(0, 4, $org_address, 0, 1, 'L');

    if (!empty($config['contact_number'])) {
        $pdf->SetX(28);
        $pdf->Cell(0, 4, 'Contact: ' . $config['contact_number'], 0, 1, 'L');
    }
}

$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 7, 'STUDENT LEDGER STATEMENT', 'B', 1, 'C');
$pdf->Ln(3);

// --- Student Info Section ---
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(25, 7, 'Student Name:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(80, 7, strtoupper(htmlspecialchars_decode($student_full_name)), 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(20, 7, 'Student ID:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, $student_id, 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(25, 7, 'Class / Group:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(86.5, 7, ($studentData['current_class'] ?? 'N/A') . ' (' . ($studentData['group_name'] ?? 'N/A') . ')', 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(14.5, 7, 'Mobile:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, $studentData['mob'] ?? 'N/A', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(25, 7, 'School:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, htmlspecialchars_decode($studentData['school_name'] ?? 'N/A'), 0, 1, 'L');

$pdf->Ln(5);

// --- Financial Summary Section ---
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(200, 8, ' FINANCIAL SUMMARY', 1, 1, 'L', true);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(155, 7, 'Grand Total Allocated Amount', 1, 0, 'L');
$pdf->Cell(45, 7, 'Rs. ' . formatIndianCurrency($summary['total_allocated'] ?? 0), 1, 1, 'R');

$pdf->Cell(155, 7, 'Total Scholarship / Waivers', 1, 0, 'L');
$pdf->Cell(45, 7, 'Rs. ' . formatIndianCurrency($summary['total_scholarship'] ?? 0), 1, 1, 'R');

$pending = $summary['total_pending'] ?? 0;
$overpayment = $summary['overpayment'] ?? 0;

// Fallback logic if overpayment is not explicitly sent but pending is negative
if ($overpayment == 0 && $pending < 0) {
    $overpayment = abs($pending);
    $pending = 0;
}

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(155, 7, 'Total Amount Paid', 1, 0, 'L');
$pdf->Cell(45, 7, 'Rs. ' . formatIndianCurrency($summary['total_paid'] ?? 0), 1, 1, 'R');

$pdf->SetTextColor(200, 0, 0);
$pdf->Cell(155, 7, 'Balance Outstanding', 1, 0, 'L');
$pdf->Cell(45, 7, 'Rs. ' . formatIndianCurrency($pending), 1, 1, 'R');
$pdf->SetTextColor(0, 0, 0);

if ($overpayment > 0) {
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(155, 7, 'Advance / Overpayment', 1, 0, 'L');
    $pdf->Cell(45, 7, 'Rs. ' . formatIndianCurrency($overpayment), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(8);

// --- Allocation Breakdown Section ---
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(200, 8, ' FEE ALLOCATION BREAKDOWN', 1, 1, 'L', true);

// Column widths: 60+22+23+23+23+23+26 = 200
$col_w = [60, 22, 23, 23, 23, 23, 26];

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell($col_w[0], 7, 'Component', 1, 0, 'C', true);
$pdf->Cell($col_w[1], 7, 'Category', 1, 0, 'C', true);
$pdf->Cell($col_w[2], 7, 'Allocated', 1, 0, 'C', true);
$pdf->Cell($col_w[3], 7, 'Waiver', 1, 0, 'C', true);
$pdf->Cell($col_w[4], 7, 'Payable', 1, 0, 'C', true);
$pdf->Cell($col_w[5], 7, 'Paid', 1, 0, 'C', true);
$pdf->Cell($col_w[6], 7, 'Balance', 1, 1, 'C', true);

foreach ($ledger_terms as $term) {
    if ($pdf->GetY() > 250) $pdf->AddPage();

    // Term heading row
    $pdf->SetFont('helvetica', 'BI', 9);
    $pdf->SetFillColor(245, 245, 245);
    $term_label = ($term['term_name'] ?? '') . '  |  ' . ($term['course_name'] ?? '') . '  |  AY: ' . ($term['academic_year'] ?? '');
    $pdf->Cell(200, 6, '  ' . $term_label, 1, 1, 'L', true);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    foreach ($term['summary']['allocations'] ?? [] as $alloc) {
        if ($pdf->GetY() > 245) $pdf->AddPage();

        $rx   = $pdf->GetX();
        $ry   = $pdf->GetY();
        $lh   = 6;

        $comp_text = $alloc['label'] ?? '';
        $cat_text  = $alloc['category'] ?? 'Academic';

        // Calculate row height based on whichever text wraps more
        $comp_lines = max(1, $pdf->getNumLines($comp_text, $col_w[0] - 2));
        $cat_lines  = max(1, $pdf->getNumLines($cat_text,  $col_w[1] - 2));
        $row_h = max($comp_lines, $cat_lines) * $lh;

        // Draw full-height borders for the two wrapping columns
        $pdf->Rect($rx,               $ry, $col_w[0], $row_h, 'D');
        $pdf->Rect($rx + $col_w[0],   $ry, $col_w[1], $row_h, 'D');

        // Draw wrapped text (no border — border already drawn above)
        $pdf->MultiCell($col_w[0], $lh, $comp_text, 0, 'L', false, 0, $rx,             $ry);
        $pdf->MultiCell($col_w[1], $lh, $cat_text,  0, 'C', false, 0, $rx + $col_w[0], $ry);

        // Numeric cells — single height matching the full row
        $pdf->SetXY($rx + $col_w[0] + $col_w[1], $ry);
        $pdf->Cell($col_w[2], $row_h, formatIndianCurrency($alloc['gross_amount']   ?? 0), 1, 0, 'R');
        $pdf->Cell($col_w[3], $row_h, formatIndianCurrency($alloc['waived_amount']  ?? 0), 1, 0, 'R');
        $pdf->Cell($col_w[4], $row_h, formatIndianCurrency($alloc['payable_amount'] ?? 0), 1, 0, 'R');
        $pdf->Cell($col_w[5], $row_h, formatIndianCurrency($alloc['paid_amount']    ?? 0), 1, 0, 'R');
        $pdf->Cell($col_w[6], $row_h, formatIndianCurrency($alloc['pending_amount'] ?? 0), 1, 1, 'R');

        // Advance cursor to the next row
        $pdf->SetXY($rx, $ry + $row_h);
    }

    // Term subtotal row
    $ts = $term['summary'];
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell($col_w[0] + $col_w[1], 6, '  Term Total', 1, 0, 'L', true);
    $pdf->Cell($col_w[2], 6, formatIndianCurrency($ts['total_allocated'] ?? 0), 1, 0, 'R', true);
    $pdf->Cell($col_w[3], 6, formatIndianCurrency($ts['total_waiver'] ?? 0), 1, 0, 'R', true);
    $pdf->Cell($col_w[4], 6, formatIndianCurrency(($ts['total_allocated'] ?? 0) - ($ts['total_waiver'] ?? 0)), 1, 0, 'R', true);
    $pdf->Cell($col_w[5], 6, formatIndianCurrency($ts['total_paid'] ?? 0), 1, 0, 'R', true);
    $pdf->Cell($col_w[6], 6, formatIndianCurrency($ts['total_pending'] ?? 0), 1, 1, 'R', true);
    $pdf->Ln(2);
}

$pdf->Ln(6);

// --- Transaction History Section ---
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(200, 8, ' TRANSACTION HISTORY', 1, 1, 'L', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 7, 'Receipt No', 1, 0, 'C');
$pdf->Cell(35, 7, 'Date', 1, 0, 'C');
$pdf->Cell(30, 7, 'Mode', 1, 0, 'C');
$pdf->Cell(65, 7, 'Remarks', 1, 0, 'C');
$pdf->Cell(35, 7, 'Amount (Rs.)', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
if (!empty($payments)) {
    foreach ($payments as $pay) {
        if ($pdf->GetY() > 250)
            $pdf->AddPage();

        $is_cancelled = ($pay['payment_status'] ?? '') === 'cancelled' || ($pay['is_cancelled'] ?? 0) == 1;
        $is_without_gst = ($pay['is_without_gst'] ?? 0) == 1;
        $receipt = $pay['receipt_no'] . ($is_cancelled ? ' (VOID)' : '');

        $remarks_text = $pay['remarks'] ?? '';
        $num_lines = $pdf->getNumLines($remarks_text, 65);
        $estimated_height = max(6, $num_lines * 6);

        // Pre-emptive page break to prevent MultiCell from splitting across pages
        if ($pdf->GetY() + $estimated_height > 270) {
            $pdf->AddPage();
        }

        if ($num_lines <= 1) {
            $pdf->Cell(35, 6, $receipt, 1, 0, 'L');
            $pdf->Cell(35, 6, date('d-M-Y', strtotime($pay['payment_date'])), 1, 0, 'C');
            $pdf->Cell(30, 6, strtoupper($pay['payment_mode'] ?? 'N/A'), 1, 0, 'C');
            $pdf->Cell(65, 6, $remarks_text, 1, 0, 'L');
            $pdf->Cell(35, 6, formatIndianCurrency($pay['amount']), 1, 1, 'R');
        } else {
            $x = $pdf->GetX();
            $y = $pdf->GetY();

            // Draw multi-line text and capture exact height
            $pdf->MultiCell(65, 6, $remarks_text, 1, 'L', false, 1, $x + 100, $y);
            $end_y = $pdf->GetY();
            $actual_height = $end_y - $y;

            // Draw the rest of the row with matching height
            $pdf->SetXY($x, $y);
            $pdf->Cell(35, $actual_height, $receipt, 1, 0, 'L');
            $pdf->Cell(35, $actual_height, date('d-M-Y', strtotime($pay['payment_date'])), 1, 0, 'C');
            $pdf->Cell(30, $actual_height, strtoupper($pay['payment_mode'] ?? 'N/A'), 1, 0, 'C');

            // Skip the remarks column, jump to amount
            $pdf->SetXY($x + 165, $y);
            $pdf->Cell(35, $actual_height, formatIndianCurrency($pay['amount']), 1, 1, 'R');

            // Advance to the bottom of the row
            $pdf->SetY($end_y);
        }
    }
} else {
    $pdf->Cell(200, 10, 'No payment history found', 1, 1, 'C');
}

$pdf->Ln(15);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 5, 'This is a computer-generated statement and does not require a physical signature.', 0, 1, 'C');
$pdf->Cell(0, 5, 'Generated on: ' . date('d-M-Y H:i:s'), 0, 1, 'C');

// Output PDF
$filename = "Ledger_" . str_replace(' ', '_', ($studentData['student_name'] ?? 'student')) . "_" . date('Y-m-d') . ".pdf";
$pdf->Output($filename, 'D');
exit;
