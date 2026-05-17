<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Accountant Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    if (!hasRole(ROLE_ACCOUNTANT)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Accountant Dashboard";

// Get statistics - Using Operation.php
$stats = [];
try {
    // Total enrolled students
    $stats['total_students'] = $dbOps->count('tbl_enrolled_students');

    // Pending token fees (only admission confirmed students who haven't paid token)
    $result = $dbOps->customSelect(
        "SELECT COUNT(*) as total FROM tbl_gm_std_registration 
         WHERE token_fees_paid = 0 AND status = 1 AND admission_confirmed = 1"
    );
    $stats['pending_fees'] = ($result !== false && isset($result[0]['total'])) ? $result[0]['total'] : 0;

    // 1. Unified Real-time Financial Logic (Aligned with Fee Due Report)
    $h_set = $dbOps->customSelect("SELECT security_deposit FROM tbl_hostel_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['security_deposit' => 0];
    $t_set = $dbOps->customSelect("SELECT transport_fee, gst_rate FROM tbl_transport_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['transport_fee' => 0, 'gst_rate' => 0];
    $sec_dep = floatval($h_set['security_deposit']);
    $trans_fee = floatval($t_set['transport_fee']) * (1 + floatval($t_set['gst_rate']) / 100);

    $academic_base = "COALESCE(fc.total_fees, 0)";
    $hostel_base = "(IF(r.hostel_required = 'Yes', $sec_dep, 0) + COALESCE(fc.hostel_fee, 0))";
    $transport_base = "IF(r.transport_required = 'Yes', $trans_fee, 0)";
    $total_base = "($academic_base + $hostel_base + $transport_base)";

    $scholarship = "(COALESCE(r.scholarship_amount, 0) + COALESCE(r.additional_scholarship_amount, 0))";
    $discount = "COALESCE(es.post_admission_discount_amount, 0)";
    $waiver = "($scholarship + $discount)";

    $universal_comps = "'hostel_security', 'transport_fee', 'admission_fee', 'security_deposit', 'token_fee', 'tuition_fee_part1', 'registration_fee', 'school_fee', 'trust_facilities_fee', 'tuition_fee_part2'";

    $academic_paid = "(SELECT COALESCE(SUM(p.amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component NOT IN ('hostel_fee', 'hostel_security', 'transport_fee') AND (p.term_id = es.current_term_id OR p.fee_component IN ($universal_comps)))";
    $hostel_paid = "(SELECT COALESCE(SUM(p.amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component IN ('hostel_fee', 'hostel_security'))";
    $transport_paid = "(SELECT COALESCE(SUM(p.amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component = 'transport_fee')";

    $pending_academic = "GREATEST(0, $academic_base - $academic_paid - $waiver)";
    $hostel_base_dyn = "GREATEST($hostel_base, $hostel_paid)";
    $pending_hostel = "GREATEST(0, $hostel_base_dyn - $hostel_paid)";
    $pending_transport = "GREATEST(0, $transport_base - $transport_paid)";
    $total_pending = "($pending_academic + $pending_hostel + $pending_transport)";
    $total_paid = "($academic_paid + $hostel_paid + $transport_paid)";

    $summary_sql = "SELECT 
                        COUNT(CASE WHEN pending <= 0 THEN 1 END) as fully_paid,
                        COUNT(CASE WHEN pending > 0 THEN 1 END) as pending_payments,
                        SUM(paid) as total_revenue
                    FROM (
                        SELECT 
                            ($total_pending) as pending,
                            ($total_paid) as paid
                        FROM tbl_gm_std_registration r
                        JOIN tbl_enrolled_students es ON r.id = es.registration_id
                        LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id AND r.medium_id = fc.medium_id AND r.group_id = fc.group_id AND r.school_id = fc.school_id AND fc.is_active = 1
                        WHERE es.is_active = 1
                    ) as finance_summary";

    $fin_result = $dbOps->customSelect($summary_sql, []);
    $stats['fully_paid'] = $fin_result[0]['fully_paid'] ?? 0;
    $stats['pending_payments'] = $fin_result[0]['pending_payments'] ?? 0;
    $stats['total_revenue'] = $fin_result[0]['total_revenue'] ?? 0;

    // Monthly collection (current month) - Only regular payments
    $result = $dbOps->customSelect(
        "SELECT SUM(amount) as total FROM tbl_payments
         WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
         AND YEAR(payment_date) = YEAR(CURRENT_DATE())
         AND status = 'paid'"
    );
    $stats['monthly_collection'] = ($result !== false && isset($result[0]['total'])) ? $result[0]['total'] : 0;

    // Total receipts count
    $stats['total_receipts'] = $dbOps->count('tbl_payments', ['status' => 'paid']);

    // Recent transactions (last 10) - Only regular payments
    $recent = $dbOps->customSelect(
        "SELECT p.*, r.student_name, r.id as student_id 
         FROM tbl_payments p
         LEFT JOIN tbl_gm_std_registration r ON p.student_id = r.id
         ORDER BY p.payment_date DESC, p.created_at desc
         LIMIT 10"
    );
    $stats['recent_transactions'] = $recent !== false ? $recent : [];

    // 1. Standard-wise detailed metrics
    $standard_details = [];
    $std_categories = [
        '11th' => 'course_id IN (1, 2)',
        '12th' => 'course_id IN (4, 5)',
        'Reneet' => 'course_id = 6'
    ];

    foreach ($std_categories as $name => $condition) {
        // Fetch fee status counts using a more inclusive query
        $fee_counts_query = "
            SELECT 
                CASE 
                    WHEN sfa.status = 'paid' THEN 'paid'
                    WHEN sfa.status = 'partial' THEN 'partial'
                    WHEN sfa.status = 'pending' AND (r.token_amount > 0 OR p.amount > 0) THEN 'partial'
                    WHEN sfa.status = 'pending' THEN 'pending'
                    WHEN r.token_amount > 0 OR p.amount > 0 THEN 'paid'
                    ELSE 'pending'
                END as status_group,
                COUNT(DISTINCT r.id) as count
            FROM tbl_gm_std_registration r
            LEFT JOIN tbl_student_fee_allocation sfa ON r.id = sfa.student_id
            LEFT JOIN (SELECT student_id, SUM(amount) as amount FROM tbl_payments GROUP BY student_id) p ON r.id = p.student_id
            WHERE $condition
            GROUP BY status_group";

        $fee_counts_raw = $dbOps->customSelect($fee_counts_query);
        $fee_counts = ['paid' => 0, 'pending' => 0, 'partial' => 0];
        foreach ($fee_counts_raw as $row) {
            $grp = strtolower($row['status_group']);
            if (isset($fee_counts[$grp])) {
                $fee_counts[$grp] = (int) $row['count'];
            }
        }

        $standard_details[$name] = [
            'registered' => $dbOps->customSelect("SELECT COUNT(*) as count FROM tbl_gm_std_registration WHERE $condition")[0]['count'] ?? 0,
            'enrolled' => $dbOps->customSelect("SELECT COUNT(*) as count FROM tbl_enrolled_students WHERE registration_id IN (SELECT id FROM tbl_gm_std_registration WHERE $condition)")[0]['count'] ?? 0,
            'paid' => $fee_counts['paid'],
            'pending' => $fee_counts['pending'],
            'partial' => $fee_counts['partial'],
            'groups' => $dbOps->customSelect("SELECT g.group_name as name, COUNT(*) as count 
                                            FROM tbl_gm_std_registration r 
                                            JOIN tbl_group g ON r.group_id = g.id 
                                            WHERE $condition 
                                            GROUP BY r.group_id"),
            'mediums' => $dbOps->customSelect("SELECT m.medium_name as name, COUNT(*) as count 
                                             FROM tbl_gm_std_registration r 
                                             JOIN tbl_medium m ON r.medium_id = m.id 
                                             WHERE $condition 
                                             GROUP BY r.medium_id")
        ];
    }
    $stats['standard_details'] = $standard_details;

    // 2. Group-wise count
    $stats['groups'] = $dbOps->customSelect("SELECT g.group_name as name, COUNT(*) as count 
                                            FROM tbl_gm_std_registration r 
                                            JOIN tbl_group g ON r.group_id = g.id 
                                            GROUP BY r.group_id");

    // 3. Medium-wise count
    $stats['mediums'] = $dbOps->customSelect("SELECT m.medium_name as name, COUNT(*) as count 
                                             FROM tbl_gm_std_registration r 
                                             JOIN tbl_medium m ON r.medium_id = m.id 
                                             GROUP BY r.medium_id");

    // 4. Fee status-wise count - Inclusive query
    $stats['fee_status'] = $dbOps->customSelect("
        SELECT 
            CASE 
                WHEN sfa.status = 'paid' THEN 'paid'
                WHEN sfa.status = 'partial' THEN 'partial'
                WHEN sfa.status = 'pending' AND (r.token_amount > 0 OR p.amount > 0) THEN 'partial'
                WHEN sfa.status = 'pending' THEN 'pending'
                WHEN r.token_amount > 0 OR p.amount > 0 THEN 'paid'
                ELSE 'pending'
            END as name,
            COUNT(DISTINCT r.id) as count
        FROM tbl_gm_std_registration r
        LEFT JOIN tbl_student_fee_allocation sfa ON r.id = sfa.student_id
        LEFT JOIN (SELECT student_id, SUM(amount) as amount FROM tbl_payments GROUP BY student_id) p ON r.id = p.student_id
        GROUP BY name");

    $success = true;
    $error = null;
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Accountant Dashboard Stats");
    }
    $success = false;
    $error = $e->getMessage();
}

// If API call, return JSON response
if ($is_api_call) {
    sendJsonResponse([
        'success' => $success,
        'data' => $stats,
        'error' => $error
    ]);
}

// For direct inclusion
$total_students = $stats['total_students'] ?? 0;
$pending_fees = $stats['pending_fees'] ?? 0;
$fully_paid = $stats['fully_paid'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$pending_token_fees = $stats['pending_fees'] ?? 0;
$pending_payments = $stats['pending_payments'] ?? 0;
$monthly_collection = $stats['monthly_collection'] ?? 0;
$total_receipts = $stats['total_receipts'] ?? 0;
$recent_transactions = $stats['recent_transactions'] ?? [];
$chart_data = [];

$page_title = "Accountant Dashboard";
$page_breadcrumb = "Dashboard";


