<?php
/**
 * Day Book PDF Export
 * Generates a PDF version of the daily transaction log
 */

ob_start();
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    // Get selected date
    $selected_date = $_GET['date'] ?? date('Y-m-d');
    $dbOps = new DatabaseOperations();

    // 1. Get Summary Stats
    $summarySql = "SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as grand_total,
                    SUM(CASE WHEN payment_mode = 'cash' THEN amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_mode IN ('online', 'upi', 'card') THEN amount ELSE 0 END) as online_total,
                    SUM(CASE WHEN payment_mode = 'cheque' THEN amount ELSE 0 END) as cheque_total,
                    SUM(CASE WHEN payment_mode NOT IN ('cash', 'online', 'upi', 'card', 'cheque') THEN amount ELSE 0 END) as other_total
                FROM tbl_payments 
                WHERE status = 'paid' AND DATE(payment_date) = ?";

    $summaryRes = $dbOps->customSelect($summarySql, [$selected_date]);
    $stats = $summaryRes[0] ?? [];

    $totalTransactions = $stats['total_count'] ?? 0;
    $grandTotal = $stats['grand_total'] ?? 0;
    $cashTotal = $stats['cash_total'] ?? 0;
    $onlineTotal = $stats['online_total'] ?? 0;
    $chequeTotal = $stats['cheque_total'] ?? 0;
    $otherTotal = $stats['other_total'] ?? 0;

    // Opening Balance
    $openingBalanceResult = $dbOps->customSelect(
        "SELECT SUM(amount) as opening_balance FROM tbl_payments WHERE payment_date < ? AND status = 'paid'",
        [$selected_date]
    );
    $openingBalance = $openingBalanceResult[0]['opening_balance'] ?? 0;
    $closingBalance = $openingBalance + $grandTotal;

    // 2. Get ALL Transactions
    $sql = "SELECT 
                p.id, p.receipt_no, p.amount, p.payment_date, p.payment_mode, p.payment_type,
                p.transaction_id, p.created_at, p.remarks,
                CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_name,
                CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
                u.name as collected_by
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_medium m ON r.medium_id = m.id
            LEFT JOIN tbl_users u ON p.created_by = u.id
            WHERE p.status = 'paid' AND DATE(p.payment_date) = ?
            ORDER BY p.created_at ASC";
    $transactions = $dbOps->customSelect($sql, [$selected_date]);

    // Get receipt configuration for the header
    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    // Create PDF
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Day Book - ' . $selected_date);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    // Header
    $headerHtml = '
    <table cellpadding="2" class="css-day-book-pdf-8588e4">
        <tr>
            <td class="css-day-book-pdf-539b04">
                <span class="css-day-book-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-day-book-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-day-book-pdf-0c5bfa">DAY BOOK REPORT</span><br>
                <span class="css-day-book-pdf-a93898">Date: ' . date('l, d F Y', strtotime($selected_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');

    // Summary Cards Section
    $summaryHtml = '
    <table cellpadding="4" class="css-day-book-pdf-b45b08">
        <tr>
            <td width="25%" class="css-day-book-pdf-12d00b">
                <span class="css-day-book-pdf-5d6f1c">Opening Balance</span><br>
                <span class="css-day-book-pdf-62eeb4">' . formatIndianCurrency($openingBalance) . '</span>
            </td>
            <td width="25%" class="css-day-book-pdf-12d00b">
                <span class="css-day-book-pdf-5d6f1c">Today\'s Collection</span><br>
                <span class="css-day-book-pdf-62eeb4">' . formatIndianCurrency($grandTotal) . '</span>
            </td>
            <td width="25%" class="css-day-book-pdf-12d00b">
                <span class="css-day-book-pdf-5d6f1c">Closing Balance</span><br>
                <span class="css-day-book-pdf-62eeb4">' . formatIndianCurrency($closingBalance) . '</span>
            </td>
            <td width="25%" class="css-day-book-pdf-12d00b">
                <span class="css-day-book-pdf-5d6f1c">Total Transactions</span><br>
                <span class="css-day-book-pdf-62eeb4">' . $totalTransactions . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');

    $modeHtml = '
    <table cellpadding="4" class="css-day-book-pdf-e5f328">
        <tr>
            <td width="25%" class="css-day-book-pdf-25bbb1"><b>Cash:</b> ' . formatIndianCurrency($cashTotal) . '</td>
            <td width="25%" class="css-day-book-pdf-25bbb1"><b>Online/UPI:</b> ' . formatIndianCurrency($onlineTotal) . '</td>
            <td width="25%" class="css-day-book-pdf-25bbb1"><b>Cheque:</b> ' . formatIndianCurrency($chequeTotal) . '</td>
            <td width="25%" class="css-day-book-pdf-25bbb1"><b>Other:</b> ' . formatIndianCurrency($otherTotal) . '</td>
        </tr>
    </table>';
    $pdf->writeHTML($modeHtml, true, false, false, false, '');
    $pdf->Ln(2);

    // Main Table
    $html = '
    <table border="0.5" cellpadding="4" class="css-day-book-pdf-6eb086">
        <thead>
            <tr class="css-day-book-pdf-de3663">
                <th width="5%" class="css-day-book-pdf-539b04">S.No</th>
                <th width="10%" class="css-day-book-pdf-539b04">Time</th>
                <th width="12%" class="css-day-book-pdf-539b04">Receipt No</th>
                <th width="25%">Student Name</th>
                <th width="15%">Class</th>
                <th width="13%">Payment Type</th>
                <th width="10%" class="css-day-book-pdf-539b04">Mode</th>
                <th width="10%" class="css-day-book-pdf-08a0ed">Amount</th>
            </tr>
        </thead>
        <tbody>';

    $sno = 1;
    if (empty($transactions)) {
        $html .= '<tr><td colspan="8" class="css-day-book-pdf-539b04">No transactions recorded on this date</td></tr>';
    } else {
        foreach ($transactions as $txn) {
            $html .= '
            <tr nobr="true">
                <td width="5%" class="css-day-book-pdf-539b04">' . $sno++ . '</td>
                <td width="10%" class="css-day-book-pdf-539b04">' . date('h:i A', strtotime($txn['created_at'])) . '</td>
                <td width="12%" class="css-day-book-pdf-539b04">' . htmlspecialchars($txn['receipt_no'] ?? '') . '</td>
                <td width="25%"><b>' . htmlspecialchars($txn['student_name'] ?? '') . '</b></td>
                <td width="15%">' . htmlspecialchars($txn['current_class'] ?: '-' ?? '') . '</td>
                <td width="13%">' . htmlspecialchars($txn['payment_type'] ?? '') . '</td>
                <td width="10%" class="css-day-book-pdf-539b04">' . strtoupper($txn['payment_mode']) . '</td>
                <td width="10%" class="css-day-book-pdf-714e9d">' . formatIndianCurrency($txn['amount']) . '</td>
            </tr>';
        }
    }

    $html .= '
            <tr class="css-day-book-pdf-bf35f5">
                <td colspan="7" class="css-day-book-pdf-08a0ed">DAY TOTAL</td>
                <td class="css-day-book-pdf-08a0ed">' . formatIndianCurrency($grandTotal) . '</td>
            </tr>
        </tbody>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    $pdf->Ln(10);
    $pdf->writeHTML('
    <table class="css-day-book-pdf-8588e4">
        <tr>
            <td class="css-day-book-pdf-539b04">_______________________<br>Prepared By</td>
            <td class="css-day-book-pdf-539b04">_______________________<br>Verified By</td>
        </tr>
    </table>', true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Day_Book_' . $selected_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}

