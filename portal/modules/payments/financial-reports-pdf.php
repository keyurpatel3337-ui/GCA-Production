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
    <table cellpadding="2" class="css-financial-reports-pdf-8588e4">
        <tr>
            <td class="css-financial-reports-pdf-539b04">
                <span class="css-financial-reports-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-financial-reports-pdf-1b8847">' . htmlspecialchars($config['address'] ?? '') . '</span><br>
                <span class="css-financial-reports-pdf-b898e5">COLLECTION SUMMARY REPORT</span><br>
                <span class="css-financial-reports-pdf-1b8847">Period: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    // Stats Row
    $statsHtml = '
    <table cellpadding="4" class="css-financial-reports-pdf-9f2285">
        <tr class="css-financial-reports-pdf-6eb74d">
            <td width="50%" class="css-financial-reports-pdf-2b03f1">
                <span class="css-financial-reports-pdf-0ed7c9">Total Transactions</span><br>
                <span class="css-financial-reports-pdf-e3ba8d">' . $collection_stats['count'] . '</span>
            </td>
            <td width="50%" class="css-financial-reports-pdf-2b03f1">
                <span class="css-financial-reports-pdf-0ed7c9">Total Collection</span><br>
                <span class="css-financial-reports-pdf-e3ba8d">' . formatIndianCurrency($collection_stats['total']) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($statsHtml, true, false, false, false, '');
    $pdf->Ln(2);

    // Breakdown Tables (Two columns)
    $breakdownHtml = '
    <table cellpadding="0" class="css-financial-reports-pdf-8588e4">
        <tr>
            <td width="48%">
                <table border="0.5" cellpadding="4" class="css-financial-reports-pdf-6eb086">
                    <tr class="css-financial-reports-pdf-5f2273"><td colspan="3">Mode Breakdown</td></tr>
                    <tr class="css-financial-reports-pdf-697d1c"><th>Mode</th><th>Txns</th><th class="css-financial-reports-pdf-08a0ed">Amount</th></tr>';
    foreach ($mode_breakdown as $m) {
        $breakdownHtml .= '<tr><td>' . strtoupper($m['payment_mode']) . '</td><td>' . $m['count'] . '</td><td class="css-financial-reports-pdf-08a0ed">' . formatIndianCurrency($m['total']) . '</td></tr>';
    }
    $breakdownHtml .= '</table>
            </td>
            <td width="4%"></td>
            <td width="48%">
                <table border="0.5" cellpadding="4" class="css-financial-reports-pdf-6eb086">
                    <tr class="css-financial-reports-pdf-5f2273"><td colspan="3">Type Breakdown</td></tr>
                    <tr class="css-financial-reports-pdf-697d1c"><th>Type</th><th>Txns</th><th class="css-financial-reports-pdf-08a0ed">Amount</th></tr>';
    foreach (array_slice($type_breakdown, 0, 10) as $t) {
        $breakdownHtml .= '<tr><td>' . htmlspecialchars($t['payment_type'] ?? '') . '</td><td>' . $t['count'] . '</td><td class="css-financial-reports-pdf-08a0ed">' . formatIndianCurrency($t['total']) . '</td></tr>';
    }
    $breakdownHtml .= '</table>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($breakdownHtml, true, false, false, false, '');
    $pdf->Ln(5);

    // Daily Breakdown
    $dailyHtml = '
    <div class="css-financial-reports-pdf-c9735f">Daily Collection Breakdown</div>
    <table border="0.5" cellpadding="4" class="css-financial-reports-pdf-6eb086">
        <thead>
            <tr class="css-financial-reports-pdf-bf35f5">
                <th width="11%">Date</th>
                <th width="11%" class="css-financial-reports-pdf-08a0ed">Online</th>
                <th width="11%" class="css-financial-reports-pdf-08a0ed">Offline</th>';
    $p_types = $daily_breakdown['payment_types'] ?? [];
    $type_width = count($p_types) > 0 ? (40 / count($p_types)) : 0;
    foreach ($p_types as $pt) {
        $dailyHtml .= '<th width="' . $type_width . '%" class="css-financial-reports-pdf-08a0ed">' . strtoupper(substr($pt, 0, 8)) . '</th>';
    }
    $dailyHtml .= '<th width="15%" class="css-financial-reports-pdf-08a0ed">Total</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($daily_breakdown['data'] ?? [] as $row) {
        $dailyHtml .= '<tr>
            <td>' . date('d-m-Y', strtotime($row['date'])) . '</td>
            <td class="css-financial-reports-pdf-08a0ed">' . formatIndianCurrency($row['online_total']) . '</td>
            <td class="css-financial-reports-pdf-08a0ed">' . formatIndianCurrency($row['offline_total']) . '</td>';
        foreach ($p_types as $pt) {
            $dailyHtml .= '<td class="css-financial-reports-pdf-08a0ed">' . formatIndianCurrency($row['types'][$pt] ?? 0) . '</td>';
        }
        $dailyHtml .= '<td class="css-financial-reports-pdf-714e9d">' . formatIndianCurrency($row['day_total']) . '</td>
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
