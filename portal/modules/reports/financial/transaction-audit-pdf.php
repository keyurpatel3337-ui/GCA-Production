<?php
/**
 * Transaction Audit PDF Export
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
    $from_date = $_GET['from_date'] ?? date('Y-m-d');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');

    $dbOps = new DatabaseOperations();
    $audit = $dbOps->customSelect(
        "SELECT pt.*, CONCAT(r.surname, ' ', r.student_name) as student_name, u.name as user_name
         FROM tbl_payment_transactions pt
         LEFT JOIN tbl_gm_std_registration r ON pt.student_id = r.id
         LEFT JOIN tbl_users u ON pt.processed_by = u.id
         WHERE DATE(pt.created_at) BETWEEN ? AND ?
         ORDER BY pt.created_at ASC",
        [$from_date, $to_date]
    );

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Transaction Audit Trail');
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
                <span style="font-size:12pt; font-weight:bold; background-color:#343a40; color:#fff;">TRANSACTION AUDIT TRAIL</span><br>
                <span style="font-size:10pt;">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#eee; font-weight:bold;">
                <th width="12%">Time</th>
                <th width="12%">Action</th>
                <th width="10%">Payment ID</th>
                <th width="24%">Student Name</th>
                <th width="12%" style="text-align:right;">Amount</th>
                <th width="15%">Processed By</th>
                <th width="15%">IP Address</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($audit)) {
        $html .= '<tr><td colspan="7" style="text-align:center;">No records found</td></tr>';
    } else {
        foreach ($audit as $a) {
            $html .= '
            <tr nobr="true">
                <td width="12%">' . date('d-m-Y H:i:s', strtotime($a['created_at'])) . '</td>
                <td width="12%"><span style="font-weight:bold;">' . strtoupper($a['action'] ?: '-') . '</span></td>
                <td width="10%"><code>' . htmlspecialchars($a['payment_id'] ?: '-' ?? '') . '</code></td>
                <td width="24%"><b>' . htmlspecialchars($a['student_name'] ?: '-' ?? '') . '</b></td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($a['amount'] ?: 0) . '</td>
                <td width="15%">' . htmlspecialchars($a['user_name'] ?: 'System' ?? '') . '</td>
                <td width="15%"><small style="color:#666;">' . htmlspecialchars($a['ip_address'] ?: '-' ?? '') . '</small></td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Transaction_Audit_' . date('Y-m-d') . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}


