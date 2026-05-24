<?php
/**
 * Term-wise Fee PDF Export
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
    $year = date('Y');
    $terms = [
        ['name' => 'Term 1 (Apr-Jul)', 'start' => $year . '-04-01', 'end' => $year . '-07-31'],
        ['name' => 'Term 2 (Aug-Nov)', 'start' => $year . '-08-01', 'end' => $year . '-11-30'],
        ['name' => 'Term 3 (Dec-Mar)', 'start' => $year . '-12-01', 'end' => ($year + 1) . '-03-31'],
    ];

    $termData = [];
    foreach ($terms as $term) {
        $result = $dbOps->customSelect("SELECT COUNT(*) as count, IFNULL(SUM(amount), 0) as total FROM tbl_payments WHERE status = 'paid' AND payment_date BETWEEN ? AND ?", [$term['start'], $term['end']]);
        $termData[] = ['name' => $term['name'], 'count' => $result[0]['count'], 'total' => $result[0]['total']];
    }
    $grandTotal = array_sum(array_column($termData, 'total'));
    $totalCount = array_sum(array_column($termData, 'count'));

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Term-wise Fee Report');
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
                <span style="font-size:12pt; font-weight:bold; background-color:#6f42c1; color:#fff;">TERM-WISE FEE COLLECTION SUMMARY</span><br>
                <span style="font-size:10pt;">Academic Cycle: ' . $year . '-' . ($year + 1) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(5);

    $html = '
    <table border="0.5" cellpadding="8" style="width:100%; font-size:10pt;">
        <thead>
            <tr style="background-color:#eee; font-weight:bold;">
                <th width="40%">Academic Term</th>
                <th width="20%" style="text-align:center;">Transactions</th>
                <th width="25%" style="text-align:right;">Collection Amount</th>
                <th width="15%" style="text-align:center;">Share (%)</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($termData as $term) {
        $share = $grandTotal > 0 ? ($term['total'] / $grandTotal) * 100 : 0;
        $html .= '
        <tr nobr="true">
            <td width="40%"><b>' . htmlspecialchars($term['name'] ?? '') . '</b></td>
            <td width="20%" style="text-align:center;">' . number_format($term['count']) . '</td>
            <td width="25%" style="text-align:right; font-weight:bold; color:#28a745;">' . formatIndianCurrency($term['total']) . '</td>
            <td width="15%" style="text-align:center;">' . round($share, 1) . '%</td>
        </tr>';
    }

    $html .= '
        <tr style="background-color:#eee; font-weight:bold;">
            <td width="40%">GRAND TOTAL</td>
            <td width="20%" style="text-align:center;">' . number_format($totalCount) . '</td>
            <td width="25%" style="text-align:right;">' . formatIndianCurrency($grandTotal) . '</td>
            <td width="15%" style="text-align:center;">100%</td>
        </tr>
    </tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('TermWise_Report_' . $year . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
