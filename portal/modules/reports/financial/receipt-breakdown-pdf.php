<?php
/**
 * Receipt Breakdown PDF Export - Fixed Version
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
    $payment_type_filter = $_GET['payment_type'] ?? '';

    $dbOps = new DatabaseOperations();

    $sql = "SELECT 
                rc.receipt_title, 
                p.payment_type, 
                p.payment_mode, 
                COUNT(*) as transaction_count, 
                SUM(p.amount) as total_amount
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_receipt_configuration rc ON p.receipt_config_id = rc.id
            WHERE p.status = 'paid' AND p.payment_date BETWEEN ? AND ?";

    $params = [$from_date, $to_date];

    // Filters (Standardized to single parameter check for performance)
    if (!empty($school_filter)) {
        $sql .= " AND r.school_id = ?";
        $params[] = $school_filter;
    }
    if (!empty($medium_filter)) {
        $sql .= " AND r.medium_id = ?";
        $params[] = $medium_filter;
    }
    if (!empty($group_filter)) {
        $sql .= " AND r.group_id = ?";
        $params[] = $group_filter;
    }
    if (!empty($course_filter)) {
        $sql .= " AND r.course_id = ?";
        $params[] = $course_filter;
    }
    if (!empty($payment_type_filter)) {
        $sql .= " AND p.payment_type = ?";
        $params[] = $payment_type_filter;
    }

    $sql .= " GROUP BY rc.receipt_title, p.payment_type, p.payment_mode 
              ORDER BY rc.receipt_title ASC, p.payment_type ASC";

    $results = $dbOps->customSelect($sql, $params);

    $breakdown = [];
    $grandTotal = 0;
    $grandCount = 0;

    foreach ($results as $row) {
        $title = !empty($row['receipt_title']) ? $row['receipt_title'] : 'Deduction';
        $type = !empty($row['payment_type']) ? $row['payment_type'] : 'General';
        $mode = strtoupper($row['payment_mode'] ?? 'N/A');

        if (!isset($breakdown[$title])) {
            $breakdown[$title] = ['total_amount' => 0, 'total_count' => 0, 'types' => []];
        }
        if (!isset($breakdown[$title]['types'][$type])) {
            $breakdown[$title]['types'][$type] = ['total_amount' => 0, 'total_count' => 0, 'modes' => []];
        }

        // Aggregate Totals
        $breakdown[$title]['total_amount'] += $row['total_amount'];
        $breakdown[$title]['total_count'] += $row['transaction_count'];

        $breakdown[$title]['types'][$type]['total_amount'] += $row['total_amount'];
        $breakdown[$title]['types'][$type]['total_count'] += $row['transaction_count'];

        // Mode specific data
        $breakdown[$title]['types'][$type]['modes'][$mode] = [
            'amount' => $row['total_amount'],
            'count' => $row['transaction_count']
        ];

        $grandTotal += $row['total_amount'];
        $grandCount += $row['transaction_count'];
    }

    // Sort by volume
    uasort($breakdown, function ($a, $b) {
        return $b['total_amount'] <=> $a['total_amount']; });

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('freeserif', '', 10);
    $pdf->AddPage();

    // Fix: Clean Address Formatting
    $addr_parts = array_filter([$config['address'] ?? '', $config['city'] ?? '', $config['pincode'] ?? '']);
    $org_address = implode(', ', $addr_parts);

    $headerHtml = '
    <div style="text-align:center;">
        <h1 style="margin-bottom:2px;">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</h1>
        <p style="font-size:10pt; margin-top:0;">' . htmlspecialchars($org_address ?? '') . '</p>
        <h3 style="background-color:#eee; padding:5px;">RECEIPT BREAKDOWN REPORT</h3>
        <p>Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</p>
    </div>';

    $pdf->writeHTML($headerHtml, true, false, false, false, '');

    $html = '<table border="0.5" cellpadding="5" style="width:100%;">
                <thead>
                    <tr style="background-color:#2d3436; color:#ffffff; font-weight:bold;">
                        <th width="45%">Receipt / Type / Mode</th>
                        <th width="10%" style="text-align:center;">Txns</th>
                        <th width="25%" style="text-align:right;">Amount</th>
                        <th width="20%" style="text-align:center;">Share (%)</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($breakdown as $title => $data) {
        $share = $grandTotal > 0 ? ($data['total_amount'] / $grandTotal * 100) : 0;

        // Level 1: Receipt Title (Main Category)
        $html .= '<tr style="background-color:#d1dce8; font-weight:bold;">
                    <td width="45%">' . htmlspecialchars($title ?? '') . '</td>
                    <td width="10%" style="text-align:center;">' . $data['total_count'] . '</td>
                    <td width="25%" style="text-align:right;">' . formatIndianCurrency($data['total_amount']) . '</td>
                    <td width="20%" style="text-align:center;">' . number_format($share, 1) . '%</td>
                  </tr>';

        foreach ($data['types'] as $typeName => $typeData) {
            $label = ucwords(str_replace('_', ' ', $typeName));
            
            // Level 2: Payment Type (Sub-category)
            $html .= '<tr style="background-color:#f9f9f9;">
                        <td width="45%">&nbsp;&nbsp;&nbsp;↳ ' . htmlspecialchars($label ?? '') . '</td>
                        <td width="10%" style="text-align:center;">' . $typeData['total_count'] . '</td>
                        <td width="25%" style="text-align:right;">' . formatIndianCurrency($typeData['total_amount']) . '</td>
                        <td width="20%"></td>
                      </tr>';

            foreach ($typeData['modes'] as $mode => $modeData) {
                // Level 3: Payment Mode (Cash/Cheque/etc)
                $html .= '<tr>
                            <td width="45%" style="color:#555;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&bull; ' . $mode . '</td>
                            <td style="text-align:center; color:#555;" width="10%">' . $modeData['count'] . '</td>
                            <td style="text-align:right; color:#555;" width="25%">' . formatIndianCurrency($modeData['amount']) . '</td>
                            <td width="20%"></td>
                          </tr>';
            }
        }
    }

    $html .= '<tr style="background-color:#2d3436; color:#ffffff; font-weight:bold;">
                <td width="45%">GRAND TOTAL</td>
                <td width="10%" style="text-align:center;">' . $grandCount . '</td>
                <td width="25%" style="text-align:right;">' . formatIndianCurrency($grandTotal) . '</td>
                <td width="20%" style="text-align:center;">100%</td>
              </tr></tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Receipt_Breakdown_' . date('Ymd') . '.pdf', 'I');
    exit;

}
catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("Export Error: " . $e->getMessage());
}