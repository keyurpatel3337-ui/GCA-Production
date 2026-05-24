<?php
/**
 * Payment Type Breakdown PDF Export
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
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $school_filter = $_GET['school_id'] ?? '';
    $medium_filter = $_GET['medium_id'] ?? '';
    $group_filter = $_GET['group_id'] ?? '';
    $course_filter = $_GET['course_id'] ?? '';

    $dbOps = new DatabaseOperations();

    $sql = "SELECT 
                p.payment_type, p.payment_mode, COUNT(*) as transaction_count, SUM(p.amount) as total_amount
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            WHERE p.status = 'paid' AND p.payment_date BETWEEN ? AND ?";

    $params = [$from_date, $to_date];
    if (!empty($school_filter)) {
        $sql .= " AND (r.school_id = ? OR r.school_id = ?)";
        $params[] = $school_filter;
        $params[] = $school_filter;
    }
    if (!empty($medium_filter)) {
        $sql .= " AND (r.medium_id = ? OR r.medium_id = ?)";
        $params[] = $medium_filter;
        $params[] = $medium_filter;
    }
    if (!empty($group_filter)) {
        $sql .= " AND (r.group_id = ? OR r.group_id = ?)";
        $params[] = $group_filter;
        $params[] = $group_filter;
    }
    if (!empty($course_filter)) {
        $sql .= " AND (r.course_id = ? OR r.course_id = ?)";
        $params[] = $course_filter;
        $params[] = $course_filter;
    }

    $sql .= " GROUP BY p.payment_type, p.payment_mode ORDER BY p.payment_type, p.payment_mode";
    $results = $dbOps->customSelect($sql, $params);

    $breakdown = [];
    $grandTotal = 0;
    $grandCount = 0;
    foreach ($results as $row) {
        $type = $row['payment_type'] ?: 'Other';
        if (!isset($breakdown[$type]))
            $breakdown[$type] = ['total_amount' => 0, 'total_count' => 0, 'modes' => []];
        $breakdown[$type]['total_amount'] += $row['total_amount'];
        $breakdown[$type]['total_count'] += $row['transaction_count'];
        $breakdown[$type]['modes'][strtoupper($row['payment_mode'])] = ['amount' => $row['total_amount'], 'count' => $row['transaction_count']];
        $grandTotal += $row['total_amount'];
        $grandCount += $row['transaction_count'];
    }
    uasort($breakdown, function ($a, $b) {
        return $b['total_amount'] - $a['total_amount'];
    });

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Payment Type Breakdown');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-payment-type-breakdown-pdf-8588e4">
        <tr>
            <td class="css-payment-type-breakdown-pdf-539b04">
                <span class="css-payment-type-breakdown-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-payment-type-breakdown-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-payment-type-breakdown-pdf-0c5bfa">PAYMENT TYPE BREAKDOWN REPORT</span><br>
                <span class="css-payment-type-breakdown-pdf-1b8847">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table cellpadding="4" class="css-payment-type-breakdown-pdf-9f2285">
        <tr class="css-payment-type-breakdown-pdf-6eb74d">
            <td width="33%" class="css-payment-type-breakdown-pdf-2b03f1">
                <small>Total Transactions</small><br><b>' . $grandCount . '</b>
            </td>
            <td width="34%" class="css-payment-type-breakdown-pdf-2b03f1">
                <small>Total Collection</small><br><b>' . formatIndianCurrency($grandTotal) . '</b>
            </td>
            <td width="33%" class="css-payment-type-breakdown-pdf-2b03f1">
                <small>Average/Txn</small><br><b>' . ($grandCount > 0 ? formatIndianCurrency($grandTotal / $grandCount) : 0) . '</b>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($html, true, false, false, false, '');

    $tbody = '<table border="0.5" cellpadding="4" class="css-payment-type-breakdown-pdf-c6ad08">
        <thead>
            <tr class="css-payment-type-breakdown-pdf-de3663">
                <th width="40%">Payment Type / Mode</th>
                <th width="15%" class="css-payment-type-breakdown-pdf-539b04">Txns</th>
                <th width="25%" class="css-payment-type-breakdown-pdf-08a0ed">Amount</th>
                <th width="20%" class="css-payment-type-breakdown-pdf-539b04">% Share</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($breakdown as $type => $data) {
        $typePercentage = $grandTotal > 0 ? ($data['total_amount'] / $grandTotal * 100) : 0;
        $tbody .= '<tr nobr="true" class="css-payment-type-breakdown-pdf-6a2c79">
            <td width="40%">' . htmlspecialchars($type ?? '') . '</td>
            <td width="15%" class="css-payment-type-breakdown-pdf-539b04">' . $data['total_count'] . '</td>
            <td width="25%" class="css-payment-type-breakdown-pdf-08a0ed">' . formatIndianCurrency($data['total_amount']) . '</td>
            <td width="20%" class="css-payment-type-breakdown-pdf-539b04">' . round($typePercentage, 1) . '%</td>
        </tr>';

        foreach ($data['modes'] as $mode => $modeData) {
            $modePercentage = $data['total_amount'] > 0 ? ($modeData['amount'] / $data['total_amount'] * 100) : 0;
            $tbody .= '<tr nobr="true">
                <td width="40%" class="css-payment-type-breakdown-pdf-77d93f">  • ' . $mode . '</td>
                <td width="15%" class="css-payment-type-breakdown-pdf-539b04">' . $modeData['count'] . '</td>
                <td width="25%" class="css-payment-type-breakdown-pdf-08a0ed">' . formatIndianCurrency($modeData['amount']) . '</td>
                <td width="20%" class="css-payment-type-breakdown-pdf-249255">' . round($modePercentage, 0) . '%</td>
            </tr>';
        }
    }

    $tbody .= '</tbody></table>';
    $pdf->writeHTML($tbody, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Payment_Type_Breakdown_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
