<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Admin Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// For API calls, skip session/role checks and just return data
if (!$is_api_call) {
    require_once $base_path . '/../common/helpers/error_logger.php';
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Get statistics - Using Operation.php
$stats = [];
try {
    // Total users by role
    $stats['total_principles'] = $dbOps->count('tbl_users', ['role_id' => 2]); // ROLE_PRINCIPLE
    $stats['total_counsellors'] = $dbOps->count('tbl_users', ['role_id' => 3]); // ROLE_COUNSELLOR

    // Total student registrations
    $stats['total_registrations'] = $dbOps->count('tbl_gm_std_registration');

    // Total enrolled students
    $stats['total_enrolled'] = $dbOps->count('tbl_enrolled_students');

    // Total answer keys
    $stats['total_answer_keys'] = $dbOps->count('tbl_answer_keys');

    // Total OMR sheets
    $stats['total_omr_sheets'] = $dbOps->count('tbl_omr_sheets');

    // Total results
    $stats['total_results'] = $dbOps->count('tbl_test_results');

    // 1. Standard-wise detailed metrics
    $standard_details = [];
    $std_categories = [
        '11th' => 'course_id = 1',
        '12th' => 'course_id = 2',
        'Reneet' => 'course_id = 3'
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
    $success = false;
    $error = $e->getMessage();
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Admin Dashboard Stats");
    }
}

// If API call, return JSON response
if ($is_api_call) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $stats,
        'error' => $error
    ]);
    exit;
}

// For direct inclusion, set page variables
$total_principles = $stats['total_principles'] ?? 0;
$total_counsellors = $stats['total_counsellors'] ?? 0;
$total_registrations = $stats['total_registrations'] ?? 0;
$total_enrolled = $stats['total_enrolled'] ?? 0;
$total_answer_keys = $stats['total_answer_keys'] ?? 0;
$total_omr_sheets = $stats['total_omr_sheets'] ?? 0;
$total_results = $stats['total_results'] ?? 0;

// New detailed stats
$standard_details = $stats['standard_details'] ?? [];
$group_stats = $stats['groups'] ?? [];
$medium_stats = $stats['mediums'] ?? [];
$fee_stats = $stats['fee_status'] ?? [];

$page_title = "Super Admin Dashboard";
$page_breadcrumb = "Dashboard";
