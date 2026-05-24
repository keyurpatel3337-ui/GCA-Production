<?php
/**
 * Refund Report PDF Export
 */

ob_start();
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    $dbOps = new DatabaseOperations();
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');

    $refunds = $dbOps->customSelect(
        "SELECT r.id, r.refund_amount as amount, r.refund_reason as reason,
                r.request_status as status, r.created_at, r.student_id,
                CONCAT(s.surname, ' ', s.student_name, ' ', IFNULL(s.fathers_name, '')) as student_name,
                s.mob as mobile
         FROM tbl_refund_requests r
         LEFT JOIN tbl_gm_std_registration s ON r.student_id = s.id
         WHERE DATE(r.created_at) BETWEEN ? AND ?
         ORDER BY r.created_at ASC",
        [$from_date, $to_date]
    );

    $totalRefunds = array_sum(array_column($refunds, 'amount'));
    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Refund Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-refunds-pdf-8588e4">
        <tr>
            <td class="css-refunds-pdf-539b04">
                <span class="css-refunds-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-refunds-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-refunds-pdf-20df51">REFUND REPORT</span><br>
                <span class="css-refunds-pdf-1b8847">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" class="css-refunds-pdf-0e766f">
        <tr class="css-refunds-pdf-6eb74d">
            <td width="50%">Total Transactions: <b>' . count($refunds) . '</b></td>
            <td width="50%" class="css-refunds-pdf-08a0ed">Total Refund Amount: <b>' . formatIndianCurrency($totalRefunds) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-refunds-pdf-6eb086">
        <thead>
            <tr class="css-refunds-pdf-5f2273">
                <th width="5%" class="css-refunds-pdf-539b04">#</th>
                <th width="15%">Date</th>
                <th width="25%">Student</th>
                <th width="15%" class="css-refunds-pdf-08a0ed">Amount</th>
                <th width="25%">Reason</th>
                <th width="15%" class="css-refunds-pdf-539b04">Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($refunds)) {
        $html .= '<tr><td colspan="6" class="css-refunds-pdf-539b04">No refunds found</td></tr>';
    } else {
        $i = 1;
        foreach ($refunds as $row) {
            $html .= '
            <tr nobr="true">
                <td width="5%" class="css-refunds-pdf-539b04">' . $i++ . '</td>
                <td width="15%">' . date('d-m-Y', strtotime($row['created_at'])) . '</td>
                <td width="25%"><b>' . htmlspecialchars($row['student_name'] ?? '') . '</b></td>
                <td width="15%" class="css-refunds-pdf-d2e3c5">' . formatIndianCurrency($row['amount']) . '</td>
                <td width="25%">' . htmlspecialchars($row['reason'] ?: '-' ?? '') . '</td>
                <td width="15%" class="css-refunds-pdf-539b04">' . strtoupper($row['status'] ?: 'pending') . '</td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Refund_Report_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}


