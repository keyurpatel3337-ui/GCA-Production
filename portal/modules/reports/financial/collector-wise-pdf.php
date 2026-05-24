<?php
/**
 * Collector-wise Report PDF Export
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
    $selected_collector = $_GET['collector'] ?? '';
    $dbOps = new DatabaseOperations();

    $sql = "SELECT 
                u.name as collector_name,
                r.role_name as collector_role,
                COUNT(p.id) as transaction_count,
                COUNT(DISTINCT p.student_id) as students_served,
                IFNULL(SUM(p.amount), 0) as total_collected,
                IFNULL(SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END), 0) as cash_amount,
                IFNULL(SUM(CASE WHEN p.payment_mode IN ('online', 'upi', 'card') THEN p.amount ELSE 0 END), 0) as online_amount,
                IFNULL(SUM(CASE WHEN p.payment_mode = 'cheque' THEN p.amount ELSE 0 END), 0) as cheque_amount,
                MIN(p.payment_date) as first_collection_date,
                MAX(p.payment_date) as last_collection_date
            FROM tbl_payments p
            JOIN tbl_users u ON p.created_by = u.id
            LEFT JOIN tbl_roles r ON u.role_id = r.id
            WHERE p.status = 'paid'
            AND p.payment_date BETWEEN ? AND ?";

    $params = [$from_date, $to_date];
    if (!empty($selected_collector)) {
        $sql .= " AND p.created_by = ?";
        $params[] = $selected_collector;
    }
    $sql .= " GROUP BY u.id, u.name, r.role_name ORDER BY total_collected ASC";
    $collectorData = $dbOps->customSelect($sql, $params);

    $grandTotal = ['transactions' => 0, 'students' => 0, 'collected' => 0, 'cash' => 0, 'online' => 0, 'cheque' => 0];
    foreach ($collectorData as $row) {
        $grandTotal['transactions'] += $row['transaction_count'];
        $grandTotal['students'] += $row['students_served'];
        $grandTotal['collected'] += $row['total_collected'];
        $grandTotal['cash'] += $row['cash_amount'];
        $grandTotal['online'] += $row['online_amount'];
        $grandTotal['cheque'] += $row['cheque_amount'];
    }

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Collector-wise Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-collector-wise-pdf-8588e4">
        <tr>
            <td class="css-collector-wise-pdf-539b04">
                <span class="css-collector-wise-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-collector-wise-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-collector-wise-pdf-0c5bfa">COLLECTOR-WISE REPORT</span><br>
                <span class="css-collector-wise-pdf-1b8847">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-collector-wise-pdf-6eb086">
        <thead>
            <tr class="css-collector-wise-pdf-de3663">
                <th width="15%">Collector</th>
                <th width="10%">Role</th>
                <th width="10%" class="css-collector-wise-pdf-539b04">Transactions</th>
                <th width="10%" class="css-collector-wise-pdf-539b04">Students</th>
                <th width="12%" class="css-collector-wise-pdf-08a0ed">Cash</th>
                <th width="12%" class="css-collector-wise-pdf-08a0ed">Online/UPI</th>
                <th width="12%" class="css-collector-wise-pdf-08a0ed">Cheque</th>
                <th width="12%" class="css-collector-wise-pdf-08a0ed">Total</th>
                <th width="7%" class="css-collector-wise-pdf-539b04">Period</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($collectorData)) {
        $html .= '<tr><td colspan="9" class="css-collector-wise-pdf-539b04">No data found</td></tr>';
    } else {
        foreach ($collectorData as $row) {
            $html .= '
            <tr nobr="true">
                <td width="15%"><b>' . htmlspecialchars($row['collector_name'] ?? '') . '</b></td>
                <td width="10%">' . htmlspecialchars($row['collector_role'] ?? 'Staff') . '</td>
                <td width="10%" class="css-collector-wise-pdf-539b04">' . $row['transaction_count'] . '</td>
                <td width="10%" class="css-collector-wise-pdf-539b04">' . $row['students_served'] . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($row['cash_amount']) . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($row['online_amount']) . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($row['cheque_amount']) . '</td>
                <td width="12%" class="css-collector-wise-pdf-714e9d">' . formatIndianCurrency($row['total_collected']) . '</td>
                <td width="7%" class="css-collector-wise-pdf-539b04"><small>' . date('d M', strtotime($row['first_collection_date'])) . '-' . date('d M', strtotime($row['last_collection_date'])) . '</small></td>
            </tr>';
        }
    }

    $html .= '
            <tr class="css-collector-wise-pdf-bf35f5">
                <td width="25%" colspan="2">GRAND TOTAL</td>
                <td width="10%" class="css-collector-wise-pdf-539b04">' . $grandTotal['transactions'] . '</td>
                <td width="10%" class="css-collector-wise-pdf-539b04">' . $grandTotal['students'] . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($grandTotal['cash']) . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($grandTotal['online']) . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($grandTotal['cheque']) . '</td>
                <td width="12%" class="css-collector-wise-pdf-08a0ed">' . formatIndianCurrency($grandTotal['collected']) . '</td>
                <td width="7%"></td>
            </tr>
        </tbody>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Collector_wise_Report_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}


