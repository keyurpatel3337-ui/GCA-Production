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
    <table cellpadding="2" class="css-term-wise-pdf-8588e4">
        <tr>
            <td class="css-term-wise-pdf-539b04">
                <span class="css-term-wise-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-term-wise-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-term-wise-pdf-f53435">TERM-WISE FEE COLLECTION SUMMARY</span><br>
                <span class="css-term-wise-pdf-1b8847">Academic Cycle: ' . $year . '-' . ($year + 1) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(5);

    $html = '
    <table border="0.5" cellpadding="8" class="css-term-wise-pdf-0f0770">
        <thead>
            <tr class="css-term-wise-pdf-5f2273">
                <th width="40%">Academic Term</th>
                <th width="20%" class="css-term-wise-pdf-539b04">Transactions</th>
                <th width="25%" class="css-term-wise-pdf-08a0ed">Collection Amount</th>
                <th width="15%" class="css-term-wise-pdf-539b04">Share (%)</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($termData as $term) {
        $share = $grandTotal > 0 ? ($term['total'] / $grandTotal) * 100 : 0;
        $html .= '
        <tr nobr="true">
            <td width="40%"><b>' . htmlspecialchars($term['name'] ?? '') . '</b></td>
            <td width="20%" class="css-term-wise-pdf-539b04">' . number_format($term['count']) . '</td>
            <td width="25%" class="css-term-wise-pdf-f3cba8">' . formatIndianCurrency($term['total']) . '</td>
            <td width="15%" class="css-term-wise-pdf-539b04">' . round($share, 1) . '%</td>
        </tr>';
    }

    $html .= '
        <tr class="css-term-wise-pdf-5f2273">
            <td width="40%">GRAND TOTAL</td>
            <td width="20%" class="css-term-wise-pdf-539b04">' . number_format($totalCount) . '</td>
            <td width="25%" class="css-term-wise-pdf-08a0ed">' . formatIndianCurrency($grandTotal) . '</td>
            <td width="15%" class="css-term-wise-pdf-539b04">100%</td>
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
