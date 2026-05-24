<?php
/**
 * Scholarship applied PDF Export
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
    $filters = $_SESSION['scholarship_filters'] ?? ['scholarship_type' => '', 'course_id' => '', 'search' => ''];
    $scholarship_type_id = $filters['scholarship_type'];
    $course_id = $filters['course_id'];
    $search = $filters['search'];

    $dbOps = new DatabaseOperations();
    $where_conditions = ["(r.scholarship_rule_id IS NOT NULL OR r.scholarship_amount > 0)"];
    $params = [];
    if (!empty($scholarship_type_id)) {
        $where_conditions[] = "sr.scholarship_type_id = ?";
        $params[] = $scholarship_type_id;
    }
    if (!empty($course_id)) {
        $where_conditions[] = "r.course_id = ?";
        $params[] = $course_id;
    }
    if (!empty($search)) {
        $where_conditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR es.enrollment_no LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    $where_sql = implode(' AND ', $where_conditions);

    $sql = "SELECT r.surname, r.student_name, r.fathers_name, r.mob, r.scholarship_amount, r.scholarship_percentage,
                   st.type_name as scholarship_type, c.course_name as current_class, m.medium_name, es.enrollment_no
            FROM tbl_gm_std_registration r
            LEFT JOIN tbl_scholarship_rules sr ON r.scholarship_rule_id = sr.id
            LEFT JOIN tbl_scholarship_types st ON sr.scholarship_type_id = st.id
            LEFT JOIN tbl_enrolled_students es ON es.enrollment_id = r.enrollment_id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_medium m ON r.medium_id = m.id
            WHERE $where_sql ORDER BY st.type_name, r.surname, r.student_name";

    $students = $dbOps->customSelect($sql, $params);
    $totalAmount = array_sum(array_column($students, 'scholarship_amount'));

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Scholarship Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-scholarships-pdf-8588e4">
        <tr>
            <td class="css-scholarships-pdf-539b04">
                <span class="css-scholarships-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-scholarships-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-scholarships-pdf-72668f">SCHOLARSHIP APPLIED REPORT</span><br>
                <span class="css-scholarships-pdf-1b8847">Generated on: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" class="css-scholarships-pdf-f6238b">
        <tr>
            <td width="30%">Total Students: <b>' . count($students) . '</b></td>
            <td width="40%" class="css-scholarships-pdf-539b04">Filter: <b>' . ($search ?: 'All') . '</b></td>
            <td width="30%" class="css-scholarships-pdf-08a0ed">Total Scholarship: <b>' . formatIndianCurrency($totalAmount) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-scholarships-pdf-6eb086">
        <thead>
            <tr class="css-scholarships-pdf-5f2273">
                <th width="4%" class="css-scholarships-pdf-539b04">#</th>
                <th width="20%">Student Name</th>
                <th width="12%">Enrollment No</th>
                <th width="12%">Class</th>
                <th width="20%">Scholarship Type</th>
                <th width="10%" class="css-scholarships-pdf-539b04">Benefit (%)</th>
                <th width="12%" class="css-scholarships-pdf-08a0ed">Amount</th>
                <th width="10%">Mobile</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($students)) {
        $html .= '<tr><td colspan="8" class="css-scholarships-pdf-539b04">No scholarship students found</td></tr>';
    } else {
        $i = 1;
        foreach ($students as $s) {
            $fullName = trim($s['surname'] . ' ' . $s['student_name'] . ' ' . $s['fathers_name']);
            $displayClass = $s['current_class'] ?: '-';
            if (!empty($s['medium_name'])) {
                $displayClass .= ' - ' . $s['medium_name'];
            }
            $html .= '
            <tr nobr="true">
                <td width="4%" class="css-scholarships-pdf-539b04">' . $i++ . '</td>
                <td width="20%"><b>' . htmlspecialchars($fullName ?? '') . '</b></td>
                <td width="12%">' . htmlspecialchars($s['enrollment_no'] ?: '-' ?? '') . '</td>
                <td width="12%">' . htmlspecialchars($displayClass ?? '') . '</td>
                <td width="20%">' . htmlspecialchars($s['scholarship_type'] ?: '-' ?? '') . '</td>
                <td width="10%" class="css-scholarships-pdf-539b04">' . ($s['scholarship_percentage'] > 0 ? $s['scholarship_percentage'] . '%' : '-') . '</td>
                <td width="12%" class="css-scholarships-pdf-f3cba8">' . formatIndianCurrency($s['scholarship_amount']) . '</td>
                <td width="10%">' . htmlspecialchars($s['mob'] ?? '') . '</td>
            </tr>';
        }
    }
    $html .= '
        <tr class="css-scholarships-pdf-5f2273">
            <td colspan="6" class="css-scholarships-pdf-08a0ed">TOTAL SCHOLARSHIP AMOUNT</td>
            <td class="css-scholarships-pdf-08a0ed">' . formatIndianCurrency($totalAmount) . '</td>
            <td></td>
        </tr>
    </tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Scholarship_Report_' . date('Y-m-d') . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
