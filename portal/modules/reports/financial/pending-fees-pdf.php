<?php
/**
 * Fee Due Report PDF Export
 */

ob_start();
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../../common/helpers/fee_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    $dbOps = new DatabaseOperations();
    $filters = $_SESSION['pending_fees_filters'] ?? [];

    $filter_group = $filters['group'] ?? '';
    $filter_course = $filters['course'] ?? '';
    $filter_school = $filters['school'] ?? '';
    $filter_min_amount = $filters['min_amount'] ?? '';
    $filter_max_amount = $filters['max_amount'] ?? '';
    $search = $filters['search'] ?? '';

    // Transport & Hostel Base Definitions
    $h_set = $dbOps->customSelect("SELECT security_deposit FROM tbl_hostel_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['security_deposit' => 0];
    $t_set = $dbOps->customSelect("SELECT transport_fee, gst_rate FROM tbl_transport_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['transport_fee' => 0, 'gst_rate' => 0];
    $sec_dep = floatval($h_set['security_deposit']);
    $trans_fee = floatval($t_set['transport_fee']) * (1 + floatval($t_set['gst_rate']) / 100);

    $calc_base_academic_sql = "COALESCE(fc.total_fees, 0)";
    $calc_base_hostel_sql = "(IF(r.hostel_required = 'Yes', $sec_dep, 0) + COALESCE(fc.hostel_fee, 0))";
    $calc_base_transport_sql = "IF(r.transport_required = 'Yes', $trans_fee, 0)";
    $calc_base_fee_sql = "($calc_base_academic_sql + $calc_base_hostel_sql + $calc_base_transport_sql)";

    $universal_comps = "'hostel_security', 'transport_fee', 'admission_fee', 'security_deposit', 'token_fee', 'tuition_fee_part1', 'registration_fee', 'school_fee', 'trust_facilities_fee', 'tuition_fee_part2'";

    // Academic Paid
    $academic_paid = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component NOT IN ('hostel_fee', 'hostel_security', 'transport_fee') AND (p.term_id = es.current_term_id OR p.fee_component IN ($universal_comps)))";

    // Hostel Paid (Including historical security)
    $hostel_paid = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component IN ('hostel_fee', 'hostel_security'))";

    // Transport Paid
    $transport_paid = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component = 'transport_fee')";

    $scholarship_sql = "(COALESCE(r.scholarship_amount, 0) + COALESCE(r.additional_scholarship_amount, 0))";
    $discount_sql = "COALESCE(es.post_admission_discount_amount, 0)";
    $calc_waiver_sql = "($scholarship_sql + $discount_sql)";

    // Total Paid (Including Without GST for accurate pending calculation in SQL)
    $without_gst_paid = "(SELECT 0)"; // No longer using separate table
    $calc_paid_sql = "($academic_paid + $hostel_paid + $transport_paid + $without_gst_paid)";

    // Categorical Pending Calculation (Matches calculateStudentFeeSummary behavior)
    $pending_academic = "GREATEST(0, $calc_base_academic_sql - $academic_paid - $calc_waiver_sql)";

    // Hostel requires dynamic baseline matching for overpayments just like fee_helper
    $hostel_base_dynamic = "GREATEST($calc_base_hostel_sql, $hostel_paid)";
    $pending_hostel = "GREATEST(0, $hostel_base_dynamic - $hostel_paid)";

    $pending_transport = "GREATEST(0, $calc_base_transport_sql - $transport_paid)";

    // Final global pending is the sum of isolated categories ensuring no cross-offsets.
    $calc_pending_sql = "($pending_academic + $pending_hostel + $pending_transport)";
    $where_conditions = ["es.is_active = 1", "$calc_pending_sql > 0"];
    $params = [];

    if (!empty($filter_group)) {
        $where_conditions[] = "r.group_id = ?";
        $params[] = $filter_group;
    }
    if (!empty($filter_course)) {
        $where_conditions[] = "r.course_id = ?";
        $params[] = $filter_course;
    }
    if (!empty($filter_school)) {
        $where_conditions[] = "r.school_id = ?";
        $params[] = $filter_school;
    }
    if (!empty($filter_min_amount)) {
        $where_conditions[] = "$calc_pending_sql >= ?";
        $params[] = $filter_min_amount;
    }
    if (!empty($filter_max_amount)) {
        $where_conditions[] = "$calc_pending_sql <= ?";
        $params[] = $filter_max_amount;
    }
    if (!empty($search)) {
        $where_conditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_sql = implode(' AND ', $where_conditions);

    $sql = "SELECT 
                r.id as student_id,
                MAX(es.enrollment_id) as enrollment_id,
                MAX(CONCAT(r.surname, ' ', r.student_name, ' ', COALESCE(r.fathers_name, ''))) as student_name,
                MAX(r.mob) as mobile,
                MAX(c.course_name) as current_class,
                MAX(g.group_name) as group_name,
                MAX($calc_base_academic_sql) as course_fee_base,
                MAX($calc_base_hostel_sql) as hostel_fee_base,
                MAX($calc_base_transport_sql) as transport_fee_base,
                MAX($calc_base_fee_sql) as total_fee,
                MAX($academic_paid) as course_paid,
                MAX($hostel_paid) as hostel_paid,
                MAX($transport_paid) as transport_paid,
                MAX($calc_paid_sql) as total_paid,
                MAX($scholarship_sql) as scholarship_waiver,
                MAX($discount_sql) as discount_waiver,
                MAX($calc_waiver_sql) as total_waiver,
                MAX($pending_academic) as course_pending,
                MAX($pending_hostel) as hostel_pending,
                MAX($pending_transport) as transport_pending,
                MAX($calc_pending_sql) as pending_amount,
                MAX(sfa.due_date) as due_date
            FROM tbl_gm_std_registration r
            JOIN tbl_enrolled_students es ON r.id = es.registration_id
            LEFT JOIN tbl_group g ON r.group_id = g.id
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN (SELECT student_id, MAX(due_date) as due_date FROM tbl_student_fee_allocation GROUP BY student_id) sfa ON r.id = sfa.student_id
            LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id AND r.medium_id = fc.medium_id AND r.group_id = fc.group_id AND r.school_id = fc.school_id AND fc.is_active = 1
            WHERE $where_sql
            GROUP BY r.id
            ORDER BY pending_amount ASC";

    $resultsRaw = $dbOps->customSelect($sql, $params);
    $results = [];
    foreach ($resultsRaw as $row) {
        $summary = calculateStudentFeeSummary($conn, $row['student_id'], true);
        $row['total_fee'] = $summary['total_allocated'];
        $row['total_paid'] = $summary['total_paid'];
        $row['total_waiver'] = $summary['total_waiver'];
        $row['pending_amount'] = $summary['total_pending'];
        $results[] = $row;
    }

    $totalPending = 0;
    foreach ($results as $r)
        $totalPending += $r['pending_amount'];

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Detailed Fee Due Report');
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
                <span style="font-size:12pt; font-weight:bold; background-color:#f0f0f0;">FEE DUE (OUTSTANDING) REPORT</span><br>
                <span style="font-size:10pt;">Generated on: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" style="width:100%; margin-bottom:10px;">
        <tr style="background-color:#f8f9fa; border:0.5px solid #ddd;">
            <td width="50%">Total Defaulters: <b>' . count($results) . '</b></td>
            <td width="50%" style="text-align:right;">Total Outstanding: <b>' . formatIndianCurrency($totalPending) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#333; color:#fff; font-weight:bold; text-align:center;">
                <th width="5%">#</th>
                <th align="left" width="22%">Student Name</th>
                <th align="left" width="15%">Class/Group</th>
                <th align="left" width="12%">Mobile</th>
                <th align="right" width="12%">Total Allocated</th>
                <th align="right" width="12%">Total Paid</th>
                <th align="right" width="12%">Total Waiver</th>
                <th align="right" width="12%">Total Pending</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($results)) {
        $html .= '<tr><td colspan="21" style="text-align:center;">No pending fees found</td></tr>';
    }
    else {
        $i = 1;
        foreach ($results as $row) {
            $html .= '
            <tr nobr="true">
                <td align="center" width="5%">' . $i++ . '</td>
                <td width="22%"><b>' . htmlspecialchars($row['student_name'] ?? '') . '</b></td>
                <td width="15%">' . htmlspecialchars($row['current_class'] ?: '-' ?? '') . ' / ' . htmlspecialchars($row['group_name'] ?: '-' ?? '') . '</td>
                <td width="12%">' . htmlspecialchars($row['mobile'] ?: '-' ?? '') . '</td>
                <td align="right" width="12%"><b>' . formatIndianCurrency($row['total_fee']) . '</b></td>
                <td align="right" width="12%" style="color:green;"><b>' . formatIndianCurrency($row['total_paid']) . '</b></td>
                <td align="right" width="12%"><b>' . formatIndianCurrency($row['total_waiver']) . '</b></td>
                <td align="right" width="12%" style="color:#d9534f;"><b>' . formatIndianCurrency($row['pending_amount']) . '</b></td>
            </tr>';
        }
    }

    $html .= '</tbody>
        <tfoot style="background-color:#eee; font-weight:bold;">
            <tr>
                <td colspan="7" style="text-align:right;">GRAND TOTAL OUTSTANDING</td>
                <td style="text-align:right;">' . formatIndianCurrency($totalPending) . '</td>
            </tr>
        </tfoot>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Fee_Due_Report_' . date('Y-m-d') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

}
catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
