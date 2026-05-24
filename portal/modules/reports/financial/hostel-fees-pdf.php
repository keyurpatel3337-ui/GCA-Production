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
    $gender = $_GET['gender'] ?? '';
    $course_filter = $_GET['course_id'] ?? '';
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
    
    // Course/Standard Filter (map friendly names to course_id conditions)
    if (!empty($course_filter)) {
        if ($course_filter === '11th') {
            $whereConditions[] = "r.course_id IN (1)";
        } elseif ($course_filter === '12th') {
            $whereConditions[] = "r.course_id IN (2)";
        } elseif ($course_filter === 'Reneet') {
            $whereConditions[] = "r.course_id = 3";
        }
    }
    
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);

    $sql = "SELECT p.*, r.surname, r.student_name, IFNULL(r.fathers_name, '') as fathers_name, r.gender, r.mob, 
                   CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_medium m ON r.medium_id = m.id
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
    <table cellpadding="2" class="css-hostel-fees-pdf-8588e4">
        <tr>
            <td class="css-hostel-fees-pdf-539b04">
                <span class="css-hostel-fees-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? 'Mahatma Seva Trust') . '</span><br>
                <span class="css-hostel-fees-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-hostel-fees-pdf-269fc9">HOSTEL FEE COLLECTION REPORT</span><br>
                <span class="css-hostel-fees-pdf-1b8847">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" class="css-hostel-fees-pdf-f6238b">
        <tr>
            <td width="30%">Transactions: <b>' . count($payments) . '</b></td>
            <td width="40%" class="css-hostel-fees-pdf-539b04">Gender: <b>' . ($gender ?: 'All') . '</b> | Standard: <b>' . ($course_filter ?: 'All') . '</b></td>
            <td width="30%" class="css-hostel-fees-pdf-08a0ed">Total Collected: <b>' . formatIndianCurrency($totalCollected) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-hostel-fees-pdf-6eb086">
        <thead>
            <tr class="css-hostel-fees-pdf-5f2273">
                <th width="4%" class="css-hostel-fees-pdf-539b04">#</th>
                <th width="8%">Date</th>
                <th width="8%">Receipt No</th>
                <th width="17%">Student Name</th>
                <th width="10%">Mobile</th>
                <th width="6%">Gender</th>
                <th width="9%">Class</th>
                <th width="11%">Fee Type</th>
                <th width="9%" class="css-hostel-fees-pdf-08a0ed">Amount</th>
                <th width="8%">Mode</th>
                <th width="10%">Remarks</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($payments)) {
        $html .= '<tr><td colspan="11" class="css-hostel-fees-pdf-539b04">No records found</td></tr>';
    } else {
        $i = 1;
        foreach ($payments as $row) {
            $fullName = trim($row['surname'] . ' ' . $row['student_name'] . ' ' . $row['fathers_name']);
            $feeType = ($row['fee_component'] == 'hostel_security' ? 'Security Deposit' : 'Hostel Fee');
            $html .= '
            <tr nobr="true">
                <td width="4%" class="css-hostel-fees-pdf-539b04">' . $i++ . '</td>
                <td width="8%">' . date('d-m-Y', strtotime($row['payment_date'])) . '</td>
                <td width="8%">' . htmlspecialchars($row['receipt_no'] ?? '') . '</td>
                <td width="17%"><b>' . htmlspecialchars($fullName ?? '') . '</b></td>
                <td width="10%">' . htmlspecialchars($row['mob'] ?? '-') . '</td>
                <td width="6%">' . htmlspecialchars($row['gender'] ?? '') . '</td>
                <td width="9%">' . htmlspecialchars($row['current_class'] ?: '-' ?? '') . '</td>
                <td width="11%">' . $feeType . '</td>
                <td width="9%" class="css-hostel-fees-pdf-f3cba8">' . formatIndianCurrency($row['amount']) . '</td>
                <td width="8%">' . strtoupper($row['payment_mode']) . '</td>
                <td width="10%"><small>' . htmlspecialchars($row['remarks'] ?: '-' ?? '') . '</small></td>
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

