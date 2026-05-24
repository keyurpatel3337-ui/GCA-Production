<?php
/**
 * Discounts given PDF Export
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
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $dbOps = new DatabaseOperations();

    $baseQuery = "SELECT * FROM (
        SELECT d.created_at as discount_date, d.discount_amount as amount, d.discount_type, 'approved' as status, d.remarks,
               CONCAT(r.surname, ' ', r.student_name,' ',r.fathers_name) as student_name, 
               CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
               r.mob, r.id as student_id
        FROM tbl_post_admission_discounts d
        JOIN tbl_gm_std_registration r ON d.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        UNION ALL
        SELECT COALESCE(r.admission_confirmed_date, r.created_at) as discount_date, r.additional_scholarship_amount as amount,
               COALESCE(r.additional_scholarship_type, 'Additional Scholarship') as discount_type, 'approved' as status, r.additional_scholarship_remarks as remarks,
               CONCAT(r.surname, ' ', r.student_name,' ',r.fathers_name) as student_name, 
               CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
               r.mob, r.id as student_id
        FROM tbl_gm_std_registration r
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON (r.course_id = c.id)
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        WHERE r.additional_scholarship_amount > 0
        UNION ALL
        SELECT es.updated_at as discount_date, es.post_admission_discount_amount as amount,
               'Payment Discount' as discount_type, 'approved' as status, es.post_admission_discount_remarks as remarks,
               CONCAT(r.surname, ' ', r.student_name,' ',r.fathers_name) as student_name, 
               CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
               r.mob, r.id as student_id
        FROM tbl_enrolled_students es
        JOIN tbl_gm_std_registration r ON es.registration_id = r.id
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        WHERE es.is_active = 1 AND es.post_admission_discount_amount > 0 AND es.post_admission_discount_remarks LIKE '%| Discount:%'
    ) AS report";

    $filterConditions = [];
    $params = [];
    if (!empty($from_date) && !empty($to_date)) {
        $filterConditions[] = "DATE(discount_date) BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
    }
    if (!empty($search)) {
        $searchTerm = "%" . $search . "%";
        $filterConditions[] = "(student_name LIKE ? OR remarks LIKE ? OR discount_type LIKE ? OR current_class LIKE ? OR mob LIKE ? OR student_id LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    $whereClause = !empty($filterConditions) ? "WHERE " . implode(" AND ", $filterConditions) : "";

    $sql = "$baseQuery $whereClause ORDER BY discount_date ASC";
    $discounts = $dbOps->customSelect($sql, $params);
    $totalDiscount = array_sum(array_column($discounts, 'amount'));

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Discount Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 11));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-discounts-pdf-8588e4">
        <tr>
            <td class="css-discounts-pdf-539b04">
                <span class="css-discounts-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-discounts-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-discounts-pdf-0aca67">DISCOUNT GIVEN REPORT</span><br>
                <span class="css-discounts-pdf-1b8847">' . ($from_date ? "Period: $from_date to $to_date" : "All Record Snapshot") . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" class="css-discounts-pdf-f6238b">
        <tr>
            <td width="30%">Records: <b>' . count($discounts) . '</b></td>
            <td width="40%" class="css-discounts-pdf-539b04">Search: <b>' . ($search ?: 'None') . '</b></td>
            <td width="30%" class="css-discounts-pdf-08a0ed">Total Discount: <b>' . formatIndianCurrency($totalDiscount) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-discounts-pdf-6eb086">
        <thead>
            <tr class="css-discounts-pdf-5f2273">
                <th width="4%" class="css-discounts-pdf-539b04">#</th>
                <th width="10%">Date</th>
                <th width="20%">Student Name</th>
                <th width="10%">Class</th>
                <th width="20%">Discount Type</th>
                <th width="12%" class="css-discounts-pdf-08a0ed">Amount</th>
                <th width="16%">Remarks</th>
                <th width="8%">Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($discounts)) {
        $html .= '<tr><td colspan="8" class="css-discounts-pdf-539b04">No discounts found</td></tr>';
    } else {
        $i = 1;
        foreach ($discounts as $d) {
            $html .= '
            <tr nobr="true">
                <td width="4%" class="css-discounts-pdf-539b04">' . $i++ . '</td>
                <td width="10%">' . date('d-m-Y', strtotime($d['discount_date'])) . '</td>
                <td width="20%"><b>' . htmlspecialchars($d['student_name'] ?? '') . '</b></td>
                <td width="10%">' . htmlspecialchars($d['current_class'] ?: '-' ?? '') . '</td>
                <td width="20%">' . htmlspecialchars($d['discount_type'] ?? '') . '</td>
                <td width="12%" class="css-discounts-pdf-f3cba8">' . formatIndianCurrency($d['amount']) . '</td>
                <td width="16%">' . htmlspecialchars($d['remarks'] ?: '-' ?? '') . '</td>
                <td width="8%">' . strtoupper($d['status']) . '</td>
            </tr>';
        }
    }
    $html .= '
        <tr class="css-discounts-pdf-5f2273">
            <td colspan="5" class="css-discounts-pdf-08a0ed">TOTAL DISCOUNT GIVEN</td>
            <td class="css-discounts-pdf-08a0ed">' . formatIndianCurrency($totalDiscount) . '</td>
            <td colspan="2"></td>
        </tr>
    </tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Discount_Report_' . date('Y-m-d') . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}


