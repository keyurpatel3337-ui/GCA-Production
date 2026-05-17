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
    <table cellpadding="2" style="width:100%;">
        <tr>
            <td style="text-align:center;">
                <span style="font-size:16pt; font-weight:bold;">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span style="font-size:10pt;">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span style="font-size:12pt; font-weight:bold; background-color:#dc3545; color:#fff;">REFUND REPORT</span><br>
                <span style="font-size:10pt;">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" style="width:100%; margin-bottom:10px; border:0.5px solid #ddd;">
        <tr style="background-color:#f8f9fa;">
            <td width="50%">Total Transactions: <b>' . count($refunds) . '</b></td>
            <td width="50%" style="text-align:right;">Total Refund Amount: <b>' . formatIndianCurrency($totalRefunds) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#eee; font-weight:bold;">
                <th width="5%" style="text-align:center;">#</th>
                <th width="15%">Date</th>
                <th width="25%">Student</th>
                <th width="15%" style="text-align:right;">Amount</th>
                <th width="25%">Reason</th>
                <th width="15%" style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($refunds)) {
        $html .= '<tr><td colspan="6" style="text-align:center;">No refunds found</td></tr>';
    } else {
        $i = 1;
        foreach ($refunds as $row) {
            $html .= '
            <tr nobr="true">
                <td width="5%" style="text-align:center;">' . $i++ . '</td>
                <td width="15%">' . date('d-m-Y', strtotime($row['created_at'])) . '</td>
                <td width="25%"><b>' . htmlspecialchars($row['student_name'] ?? '') . '</b></td>
                <td width="15%" style="text-align:right; font-weight:bold; color:#dc3545;">' . formatIndianCurrency($row['amount']) . '</td>
                <td width="25%">' . htmlspecialchars($row['reason'] ?: '-' ?? '') . '</td>
                <td width="15%" style="text-align:center;">' . strtoupper($row['status'] ?: 'pending') . '</td>
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


