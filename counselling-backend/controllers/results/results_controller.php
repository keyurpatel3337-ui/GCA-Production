<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Results Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user has appropriate role
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Test Results";
$page_breadcrumb = "Results";

// Get optional filter for counsellor
$counsellor_id = $_GET['counsellor_id'] ?? $_SESSION['user_id'] ?? null;

// Get results based on user role
try {
    if (!$is_api_call && function_exists('hasRole') && hasRole(ROLE_COUNSELLOR)) {
        // Counsellors see only their assigned students' results
        $sql = "SELECT tr.*, ps.paper_set_name, ps.paper_code, s.student_name, s.surname, s.mob as mobile_number 
               FROM tbl_test_results tr 
               INNER JOIN tbl_paper_sets ps ON tr.paper_set_id = ps.id 
               LEFT JOIN tbl_gm_std_registration s ON tr.student_id = s.id 
               WHERE s.counsellor_id = ?
               ORDER BY s.id ASC";
        $results = $dbOps->customSelect($sql, [$counsellor_id]);
    } else {
        // Admin and Principle see all results (or API sees all)
        $sql = "SELECT tr.*, ps.paper_set_name, ps.paper_code, s.student_name, s.surname, s.mob as mobile_number 
               FROM tbl_test_results tr 
               INNER JOIN tbl_paper_sets ps ON tr.paper_set_id = ps.id 
               LEFT JOIN tbl_gm_std_registration s ON tr.student_id = s.id 
               ORDER BY s.id ASC";
        $results = $dbOps->customSelect($sql);
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Test Results");
    }
    $results = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'results' => $results,
        'total' => count($results)
    ]);
}

