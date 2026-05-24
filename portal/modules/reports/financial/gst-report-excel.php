<?php
/**
 * GST Report Excel Export (Full Data)
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    die("Unauthorized access!");
}

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

$dbOps = new DatabaseOperations();

$whereConditions = ["p.status = 'paid'", "p.payment_date BETWEEN ? AND ?", "(p.payment_type LIKE '%Tuition Fee Part 1%' OR p.payment_type LIKE '%Tuition Fee Part 2%')"];
$params = [$from_date, $to_date];

if (!empty($search)) {
    $searchTerm = "%$search%";
    $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.receipt_no LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSql = implode(" AND ", $whereConditions);

$sql = "SELECT 
            p.payment_date,
            p.payment_type,
            p.payment_mode,
            SUM(p.amount) as total_amount,
            COUNT(*) as txn_count
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        WHERE $whereSql
        GROUP BY p.payment_date, p.payment_type, p.payment_mode
        ORDER BY p.payment_date DESC, p.payment_type ASC";

$payments = $dbOps->customSelect($sql, $params);

// Headers for Excel
$filename = "GST_Report_" . $from_date . "_to_" . $to_date . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo '<table border="1">';
echo '<thead>
        <tr class="css-gst-report-excel-2ec452">
            <th>#</th>
            <th>Date</th>
            <th>Fee Type</th>
            <th>Payment Mode</th>
            <th>Count</th>
            <th>Total Amount</th>
            <th>Taxable Value</th>
            <th>CGST (9%)</th>
            <th>SGST (9%)</th>
            <th>Total GST</th>
        </tr>
    </thead>';
echo '<tbody>';

if (empty($payments)) {
    echo '<tr><td colspan="10" class="css-gst-report-excel-539b04">No records found</td></tr>';
} else {
    $i = 1;
    $totalCollection = 0;
    $totalTaxable = 0;
    $totalGST = 0;
    $totalCGST = 0;
    $totalSGST = 0;

    foreach ($payments as $row) {
        $amount = floatval($row['total_amount']);
        $taxable = $amount / 1.18;
        $gst = $amount - $taxable;
        $cgst = $gst / 2;
        $sgst = $gst / 2;

        $types = [];
        if (stripos($row['payment_type'], 'Tuition Fee Part 1') !== false) {
            $types[] = "Token Fee (Part 1)";
        } else if (stripos($row['payment_type'], 'Token Fee') !== false) {
            $types[] = "Token Fee (Part 1)";
        }
        if (stripos($row['payment_type'], 'Tuition Fee Part 2') !== false) {
            $types[] = "Tuition Fee Part 2";
        }
        $displayType = implode(', ', $types);
        if (empty($displayType)) {
            $displayType = $row['payment_type'];
        }

        echo '<tr>
                <td>' . $i++ . '</td>
                <td>' . date('d-m-Y', strtotime($row['payment_date'])) . '</td>
                <td>' . $displayType . '</td>
                <td>' . ucfirst($row['payment_mode'] ?? '') . '</td>
                <td>' . $row['txn_count'] . '</td>
                <td>' . round($amount, 2) . '</td>
                <td>' . round($taxable, 2) . '</td>
                <td>' . round($cgst, 2) . '</td>
                <td>' . round($sgst, 2) . '</td>
                <td>' . round($gst, 2) . '</td>
            </tr>';

        $totalCollection += $amount;
        $totalTaxable += $taxable;
        $totalGST += $gst;
        $totalCGST += $cgst;
        $totalSGST += $sgst;
    }

    echo '<tr class="css-gst-report-excel-3b62fc">
            <td colspan="5" align="right">GRAND TOTAL</td>
            <td>' . round($totalCollection, 2) . '</td>
            <td>' . round($totalTaxable, 2) . '</td>
            <td>' . round($totalCGST, 2) . '</td>
            <td>' . round($totalSGST, 2) . '</td>
            <td>' . round($totalGST, 2) . '</td>
          </tr>';
}

echo '</tbody>';
echo '</table>';
