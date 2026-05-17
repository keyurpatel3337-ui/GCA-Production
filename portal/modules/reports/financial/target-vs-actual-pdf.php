<?php
/**
 * Target vs Actual PDF Export
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

    // Expected target
    $expected = $dbOps->customSelect("SELECT SUM(sfa.allocated_amount) as target FROM tbl_student_fee_allocation sfa JOIN tbl_enrolled_students es ON sfa.student_id = es.registration_id WHERE es.is_active = 1", []);
    $target = $expected[0]['target'] ?? 0;

    // Actual collected
    $actual = $dbOps->customSelect("SELECT SUM(amount) as collected FROM tbl_payments WHERE status = 'paid'", []);
    $collected = $actual[0]['collected'] ?? 0;

    $pending = $target - $collected;
    $percentage = $target > 0 ? ($collected / $target) * 100 : 0;

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Target vs Actual Collection');
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
                <span style="font-size:12pt; font-weight:bold; background-color:#11998e; color:#fff;">TARGET VS ACTUAL COLLECTION REPORT</span><br>
                <span style="font-size:10pt;">Generated on: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(5);

    $html = '
    <table nobr="true" cellpadding="10" style="width:100%; border:0.5px solid #ddd; font-size:11pt;">
        <tr>
            <td width="60%" style="border-right:0.5px solid #ddd;">
                <span style="font-size:14pt; font-weight:bold; color:#11998e;">' . round($percentage, 1) . '% Achieved</span><br>
                <span style="color:#666;">Current progress towards 100% collection target.</span>
            </td>
            <td width="40%" style="text-align:right;">
                Target: <b>' . formatIndianCurrency($target) . '</b><br>
                Collected: <b style="color:#28a745;">' . formatIndianCurrency($collected) . '</b><br>
                Pending: <b style="color:#dc3545;">' . formatIndianCurrency($pending) . '</b>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->Ln(10);

    $tableHtml = '
    <table border="0.5" cellpadding="8" style="width:100%; font-size:10pt;">
        <tr style="background-color:#eee; font-weight:bold;">
            <th width="70%">Collection Metric</th>
            <th width="30%" style="text-align:right;">Amount / Rate</th>
        </tr>
        <tr nobr="true">
            <td width="70%">Total Fee Expected (Target)</td>
            <td width="30%" style="text-align:right;"><b>' . formatIndianCurrency($target) . '</b></td>
        </tr>
        <tr nobr="true">
            <td width="70%">Total Amount Collected (Actual)</td>
            <td width="30%" style="text-align:right; color:#28a745;"><b>' . formatIndianCurrency($collected) . '</b></td>
        </tr>
        <tr nobr="true">
            <td width="70%">Total Amount Pending</td>
            <td width="30%" style="text-align:right; color:#dc3545;"><b>' . formatIndianCurrency($pending) . '</b></td>
        </tr>
        <tr style="background-color:#f8f9fa;">
            <td width="70%"><b>Collection Achievement Rate</b></td>
            <td width="30%" style="text-align:right; font-size:12pt; font-weight:bold;">' . round($percentage, 2) . '%</td>
        </tr>
    </table>';
    $pdf->writeHTML($tableHtml, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Target_vs_Actual_' . date('Y-m-d') . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
