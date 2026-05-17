<?php
/**
 * Year-on-Year Comparison PDF Export
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
    $currentYear = date('Y');
    $previousYear = $currentYear - 1;

    $currentYearData = $dbOps->customSelect(
        "SELECT MONTH(payment_date) as month, SUM(amount) as total FROM tbl_payments WHERE status = 'paid' AND YEAR(payment_date) = ? GROUP BY MONTH(payment_date)",
        [$currentYear]
    );
    $previousYearData = $dbOps->customSelect(
        "SELECT MONTH(payment_date) as month, SUM(amount) as total FROM tbl_payments WHERE status = 'paid' AND YEAR(payment_date) = ? GROUP BY MONTH(payment_date)",
        [$previousYear]
    );

    $currentByMonth = array_column($currentYearData, 'total', 'month');
    $previousByMonth = array_column($previousYearData, 'total', 'month');
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    $totalCurrent = array_sum($currentByMonth);
    $totalPrevious = array_sum($previousByMonth);
    $yoyChange = $totalPrevious > 0 ? (($totalCurrent - $totalPrevious) / $totalPrevious) * 100 : 0;

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('YoY Comparison Report');
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
                <span style="font-size:12pt; font-weight:bold; background-color:#1a1a2e; color:#fff;">YEAR-ON-YEAR COMPARISON (' . $previousYear . ' vs ' . $currentYear . ')</span><br>
                <span style="font-size:10pt;">Generated on: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="5" style="width:100%; border:0.5px solid #ddd;">
        <tr style="background-color:#f8f9fa;">
            <td width="33%" style="text-align:center;">Year ' . $previousYear . '<br><span style="font-size:12pt; font-weight:bold;">' . formatIndianCurrency($totalPrevious) . '</span></td>
            <td width="34%" style="text-align:center;">YoY Change<br><span style="font-size:12pt; font-weight:bold; color:' . ($yoyChange >= 0 ? '#28a745' : '#dc3545') . ';">' . ($yoyChange >= 0 ? '+' : '') . round($yoyChange, 1) . '%</span></td>
            <td width="33%" style="text-align:center;">Year ' . $currentYear . '<br><span style="font-size:12pt; font-weight:bold;">' . formatIndianCurrency($totalCurrent) . '</span></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(5);

    $html = '
    <table border="0.5" cellpadding="6" style="width:100%; font-size:10pt;">
        <thead>
            <tr style="background-color:#eee; font-weight:bold;">
                <th width="25%">Month</th>
                <th width="25%" style="text-align:right;">Year ' . $previousYear . '</th>
                <th width="25%" style="text-align:right;">Year ' . $currentYear . '</th>
                <th width="25%" style="text-align:right;">Change (%)</th>
            </tr>
        </thead>
        <tbody>';

    for ($i = 1; $i <= 12; $i++) {
        $curr = $currentByMonth[$i] ?? 0;
        $prev = $previousByMonth[$i] ?? 0;
        $chg = $prev > 0 ? (($curr - $prev) / $prev) * 100 : 0;
        $html .= '
        <tr nobr="true">
            <td width="25%"><b>' . $months[$i - 1] . '</b></td>
            <td width="25%" style="text-align:right;">' . formatIndianCurrency($prev) . '</td>
            <td width="25%" style="text-align:right;">' . formatIndianCurrency($curr) . '</td>
            <td width="25%" style="text-align:right; font-weight:bold; color:' . ($chg >= 0 ? '#28a745' : '#dc3545') . ';">' . ($chg >= 0 ? '+' : '') . round($chg, 1) . '%</td>
        </tr>';
    }

    $html .= '
        <tr style="background-color:#eee; font-weight:bold;">
            <td width="25%">GRAND TOTAL</td>
            <td width="25%" style="text-align:right;">' . formatIndianCurrency($totalPrevious) . '</td>
            <td width="25%" style="text-align:right;">' . formatIndianCurrency($totalCurrent) . '</td>
            <td width="25%" style="text-align:right; color:' . ($yoyChange >= 0 ? '#28a745' : '#dc3545') . ';">' . ($yoyChange >= 0 ? '+' : '') . round($yoyChange, 1) . '%</td>
        </tr>
    </tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('YoY_Comparison_' . $currentYear . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
