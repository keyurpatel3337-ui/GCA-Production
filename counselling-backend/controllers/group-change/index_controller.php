<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Group Change Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Principal or Admin
    if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Group Change Requests - Dashboard";
$page_breadcrumb = "- Dashboard";

// Get summary statistics
try {
    $stats = [];

    // Total requests
    $stats['total'] = $dbOps->count('tbl_group_change_requests');

    // Pending requests
    $stats['pending'] = $dbOps->count('tbl_group_change_requests', ['status' => 'pending']);

    // Under review
    $stats['under_review'] = $dbOps->count('tbl_group_change_requests', ['status' => 'under_review']);

    // Approved
    $stats['approved'] = $dbOps->count('tbl_group_change_requests', ['status' => 'approved']);

    // Rejected
    $stats['rejected'] = $dbOps->count('tbl_group_change_requests', ['status' => 'rejected']);

    // Recent requests (last 10)
    $recent_requests = $dbOps->customSelect(
        "SELECT gcr.*, 
        s.surname, s.student_name, s.fathers_name,
        cg.group_name as current_group_name,
        rg.group_name as requested_group_name
        FROM tbl_group_change_requests gcr
        LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
        LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
        LEFT JOIN tbl_group rg ON gcr.requested_group_id = rg.id
        ORDER BY gcr.request_date ASC
        LIMIT 10"
    );
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logError')) {
        logError("Group Change Dashboard Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    }
    if (!$is_api_call) {
        set_flash_message('error', "An error occurred while loading dashboard data.");
    }
    $stats = ['total' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
    $recent_requests = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'stats' => $stats,
        'recent_requests' => $recent_requests
    ]);
}


