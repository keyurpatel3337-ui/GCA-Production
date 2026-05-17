<?php
/**
 * Hostel Fee PDF Export
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
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $fee_type = $_GET['fee_type'] ?? '';
    $search = $_GET['search'] ?? '';

    $whereConditions = ["p.status = 'paid'"];
    
    // Fee Type Filter
    if ($fee_type === 'hostel_fee') {
        $whereConditions[] = "p.fee_component = 'hostel_fee'";
    } elseif ($fee_type === 'hostel_security') {
        $whereConditions[] = "p.fee_component = 'hostel_security'";
    } else {
        // Default: Show all hostel related
        $whereConditions[] = "(p.payment_type LIKE '%hostel%' OR p.fee_component LIKE '%hostel%')";
    }

    $params = [];
    if (!empty($from_date) && !empty($to_date)) {
        $whereConditions[] = "p.payment_date BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
    }
    if (!empty($gender)) {
        $whereConditions[] = "r.gender = ?";
        $params[] = $gender;
    }
    if (!empty($search)) {
        $searchTerm = "%" . $search . "%";
        $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR c.course_name LIKE ? OR p.receipt_no LIKE ? OR p.payment_mode LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);

    $sql = "SELECT p.*, r.surname, r.student_name, IFNULL(r.fathers_name, '') as fathers_name, r.gender, c.course_name as current_class
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            $whereClause ORDER BY p.payment_date ASC";
    $payments = $dbOps->customSelect($sql, $params);
    $totalCollected = array_sum(array_column($payments, 'amount'));

    // Specifically fetch Mahatma Seva Trust configuration
    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE receipt_title LIKE '%MST%' LIMIT 1", []);
    $config = $config[0] ?? null;

    if (!$config) {
        // Fallback to active config if MST config is missing
        $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
        $config = $config[0] ?? null;
    }

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Hostel Fee Report');
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
                <span style="font-size:16pt; font-weight:bold;">' . htmlspecialchars($config['organization_name'] ?? 'Mahatma Seva Trust') . '</span><br>
                <span style="font-size:10pt;">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span style="font-size:12pt; font-weight:bold; background-color:#17a2b8; color:#fff;">HOSTEL FEE COLLECTION REPORT</span><br>
                <span style="font-size:10pt;">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" style="width:100%; border:0.5px solid #ddd; background-color:#f8f9fa;">
        <tr>
            <td width="30%">Transactions: <b>' . count($payments) . '</b></td>
            <td width="40%" style="text-align:center;">Gender: <b>' . ($gender ?: 'All') . '</b></td>
            <td width="30%" style="text-align:right;">Total Collected: <b>' . formatIndianCurrency($totalCollected) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#eee; font-weight:bold;">
                <th width="4%" style="text-align:center;">#</th>
                <th width="9%">Date</th>
                <th width="9%">Receipt No</th>
                <th width="20%">Student Name</th>
                <th width="7%">Gender</th>
                <th width="10%">Class</th>
                <th width="11%">Fee Type</th>
                <th width="10%" style="text-align:right;">Amount</th>
                <th width="8%">Mode</th>
                <th width="12%">Remarks</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($payments)) {
        $html .= '<tr><td colspan="9" style="text-align:center;">No records found</td></tr>';
    } else {
        $i = 1;
        foreach ($payments as $row) {
            $fullName = trim($row['surname'] . ' ' . $row['student_name'] . ' ' . $row['fathers_name']);
            $feeType = ($row['fee_component'] == 'hostel_security' ? 'Security Deposit' : 'Hostel Fee');
            $html .= '
            <tr nobr="true">
                <td width="4%" style="text-align:center;">' . $i++ . '</td>
                <td width="9%">' . date('d-m-Y', strtotime($row['payment_date'])) . '</td>
                <td width="9%">' . htmlspecialchars($row['receipt_no'] ?? '') . '</td>
                <td width="20%"><b>' . htmlspecialchars($fullName ?? '') . '</b></td>
                <td width="7%">' . htmlspecialchars($row['gender'] ?? '') . '</td>
                <td width="10%">' . htmlspecialchars($row['current_class'] ?: '-' ?? '') . '</td>
                <td width="11%">' . $feeType . '</td>
                <td width="10%" style="text-align:right; font-weight:bold; color:#28a745;">' . formatIndianCurrency($row['amount']) . '</td>
                <td width="8%">' . strtoupper($row['payment_mode']) . '</td>
                <td width="12%"><small>' . htmlspecialchars($row['remarks'] ?: '-' ?? '') . '</small></td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Hostel_Fees_' . $from_date . '_to_' . $to_date . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}

