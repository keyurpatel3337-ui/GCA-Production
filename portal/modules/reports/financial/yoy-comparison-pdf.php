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
    <table cellpadding="2" class="css-yoy-comparison-pdf-8588e4">
        <tr>
            <td class="css-yoy-comparison-pdf-539b04">
                <span class="css-yoy-comparison-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-yoy-comparison-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-yoy-comparison-pdf-04c7a4">YEAR-ON-YEAR COMPARISON (' . $previousYear . ' vs ' . $currentYear . ')</span><br>
                <span class="css-yoy-comparison-pdf-1b8847">Generated on: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="5" class="css-yoy-comparison-pdf-14498d">
        <tr class="css-yoy-comparison-pdf-6eb74d">
            <td width="33%" class="css-yoy-comparison-pdf-539b04">Year ' . $previousYear . '<br><span class="css-yoy-comparison-pdf-e3ba8d">' . formatIndianCurrency($totalPrevious) . '</span></td>
            <td width="34%" class="css-yoy-comparison-pdf-539b04">YoY Change<br><span class="css-yoy-comparison-pdf-41a211">' . ($yoyChange >= 0 ? '+' : '') . round($yoyChange, 1) . '%</span></td>
            <td width="33%" class="css-yoy-comparison-pdf-539b04">Year ' . $currentYear . '<br><span class="css-yoy-comparison-pdf-e3ba8d">' . formatIndianCurrency($totalCurrent) . '</span></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(5);

    $html = '
    <table border="0.5" cellpadding="6" class="css-yoy-comparison-pdf-0f0770">
        <thead>
            <tr class="css-yoy-comparison-pdf-5f2273">
                <th width="25%">Month</th>
                <th width="25%" class="css-yoy-comparison-pdf-08a0ed">Year ' . $previousYear . '</th>
                <th width="25%" class="css-yoy-comparison-pdf-08a0ed">Year ' . $currentYear . '</th>
                <th width="25%" class="css-yoy-comparison-pdf-08a0ed">Change (%)</th>
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
            <td width="25%" class="css-yoy-comparison-pdf-08a0ed">' . formatIndianCurrency($prev) . '</td>
            <td width="25%" class="css-yoy-comparison-pdf-08a0ed">' . formatIndianCurrency($curr) . '</td>
            <td width="25%" class="css-yoy-comparison-pdf-99aed5">' . ($chg >= 0 ? '+' : '') . round($chg, 1) . '%</td>
        </tr>';
    }

    $html .= '
        <tr class="css-yoy-comparison-pdf-5f2273">
            <td width="25%">GRAND TOTAL</td>
            <td width="25%" class="css-yoy-comparison-pdf-08a0ed">' . formatIndianCurrency($totalPrevious) . '</td>
            <td width="25%" class="css-yoy-comparison-pdf-08a0ed">' . formatIndianCurrency($totalCurrent) . '</td>
            <td width="25%" class="css-yoy-comparison-pdf-66dc2d">' . ($yoyChange >= 0 ? '+' : '') . round($yoyChange, 1) . '%</td>
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
