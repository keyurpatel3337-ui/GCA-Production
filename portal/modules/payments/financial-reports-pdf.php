<?php
/**
 * Collection Summary PDF Export
 */

ob_start();
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $chart_view = $_GET['chart_view'] ?? 'daily';

    $api = new APIClient();
    $response = $api->get('payments/financial-reports', [
        'from_date' => $from_date,
        'to_date' => $to_date,
        'chart_view' => $chart_view
    ]);

    if (!$response || !isset($response['success']) || !$response['success']) {
        throw new Exception("Failed to fetch report data");
    }

    $data = $response['data'];
    $collection_stats = $data['collection_stats'];
    $mode_breakdown = $data['mode_breakdown'];
    $type_breakdown = $data['type_breakdown'];
    $daily_breakdown = $data['daily_breakdown'];

    // Get org details
    require_once DB_CONNECT_FILE;
    require_once OPERATION_FILE;
    $dbOps = new DatabaseOperations();
    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Collection Summary Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    $headerHtml = '
    <table cellpadding="2" style="width:100%;">
        <tr>
            <td style="text-align:center;">
                <span style="font-size:16pt; font-weight:bold;">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span style="font-size:10pt;">' . htmlspecialchars($config['address'] ?? '') . '</span><br>
                <span style="font-size:12pt; font-weight:bold; background-color:#fefefe;">COLLECTION SUMMARY REPORT</span><br>
                <span style="font-size:10pt;">Period: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    // Stats Row
    $statsHtml = '
    <table cellpadding="4" style="width:100%; margin-bottom:10px;">
        <tr style="background-color:#f8f9fa;">
            <td width="50%" style="border:0.5px solid #ddd; text-align:center;">
                <span style="font-size:9pt; color:#666;">Total Transactions</span><br>
                <span style="font-size:12pt; font-weight:bold;">' . $collection_stats['count'] . '</span>
            </td>
            <td width="50%" style="border:0.5px solid #ddd; text-align:center;">
                <span style="font-size:9pt; color:#666;">Total Collection</span><br>
                <span style="font-size:12pt; font-weight:bold;">' . formatIndianCurrency($collection_stats['total']) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($statsHtml, true, false, false, false, '');
    $pdf->Ln(2);

    // Breakdown Tables (Two columns)
    $breakdownHtml = '
    <table cellpadding="0" style="width:100%;">
        <tr>
            <td width="48%">
                <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
                    <tr style="background-color:#eee; font-weight:bold;"><td colspan="3">Mode Breakdown</td></tr>
                    <tr style="background-color:#f9f9f9;"><th>Mode</th><th>Txns</th><th style="text-align:right;">Amount</th></tr>';
    foreach ($mode_breakdown as $m) {
        $breakdownHtml .= '<tr><td>' . strtoupper($m['payment_mode']) . '</td><td>' . $m['count'] . '</td><td style="text-align:right;">' . formatIndianCurrency($m['total']) . '</td></tr>';
    }
    $breakdownHtml .= '</table>
            </td>
            <td width="4%"></td>
            <td width="48%">
                <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
                    <tr style="background-color:#eee; font-weight:bold;"><td colspan="3">Type Breakdown</td></tr>
                    <tr style="background-color:#f9f9f9;"><th>Type</th><th>Txns</th><th style="text-align:right;">Amount</th></tr>';
    foreach (array_slice($type_breakdown, 0, 10) as $t) {
        $breakdownHtml .= '<tr><td>' . htmlspecialchars($t['payment_type'] ?? '') . '</td><td>' . $t['count'] . '</td><td style="text-align:right;">' . formatIndianCurrency($t['total']) . '</td></tr>';
    }
    $breakdownHtml .= '</table>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($breakdownHtml, true, false, false, false, '');
    $pdf->Ln(5);

    // Daily Breakdown
    $dailyHtml = '
    <div style="font-size:10pt; font-weight:bold; background-color:#333; color:#fff; padding:5px;">Daily Collection Breakdown</div>
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#f0f0f0; font-weight:bold;">
                <th width="11%">Date</th>
                <th width="11%" style="text-align:right;">Online</th>
                <th width="11%" style="text-align:right;">Offline</th>';
    $p_types = $daily_breakdown['payment_types'] ?? [];
    $type_width = count($p_types) > 0 ? (40 / count($p_types)) : 0;
    foreach ($p_types as $pt) {
        $dailyHtml .= '<th width="' . $type_width . '%" style="text-align:right;">' . strtoupper(substr($pt, 0, 8)) . '</th>';
    }
    $dailyHtml .= '<th width="15%" style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($daily_breakdown['data'] ?? [] as $row) {
        $dailyHtml .= '<tr>
            <td>' . date('d-m-Y', strtotime($row['date'])) . '</td>
            <td style="text-align:right;">' . formatIndianCurrency($row['online_total']) . '</td>
            <td style="text-align:right;">' . formatIndianCurrency($row['offline_total']) . '</td>';
        foreach ($p_types as $pt) {
            $dailyHtml .= '<td style="text-align:right;">' . formatIndianCurrency($row['types'][$pt] ?? 0) . '</td>';
        }
        $dailyHtml .= '<td style="text-align:right; font-weight:bold;">' . formatIndianCurrency($row['day_total']) . '</td>
        </tr>';
    }
    $dailyHtml .= '</tbody></table>';
    $pdf->writeHTML($dailyHtml, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Collection_Summary_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
