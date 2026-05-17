<?php

/**
 * Computer Operator Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$base_path = dirname(dirname(__DIR__));

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Computer Operator or Super Admin
    if (!hasRole(ROLE_COMPUTER_OPERATOR) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$stats = [];
try {
    // Basic Stats for Computer Operator
    $stats['today_registrations'] = $dbOps->customSelect("SELECT COUNT(*) as count FROM tbl_gm_std_registration WHERE DATE(created_at) = CURDATE()")[0]['count'] ?? 0;
    $stats['yesterday_registrations'] = $dbOps->customSelect("SELECT COUNT(*) as count FROM tbl_gm_std_registration WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")[0]['count'] ?? 0;

    // Overall metrics
    $stats['total_registrations'] = $dbOps->count('tbl_gm_std_registration');
    $stats['total_enrolled'] = $dbOps->count('tbl_enrolled_students');

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

    // 5. Recently Modified Records
    $stats['recent_modified'] = $dbOps->customSelect("SELECT id, surname, student_name, fathers_name, mob, updated_at FROM tbl_gm_std_registration ORDER BY updated_at ASC LIMIT 5");

    $success = true;
    $error = null;
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Computer Operator Dashboard Stats");
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
