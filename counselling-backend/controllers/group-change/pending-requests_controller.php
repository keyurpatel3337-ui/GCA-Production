<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Pending Group Change Requests Controller
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
    // Check for principal role
    if (!hasRole(ROLE_PRINCIPLE)) {
        header("Location: ../dashboard/principle_dashboard.php");
        exit;
    }
}

$page_title = "Pending Group Change Requests";
$page_breadcrumb = "Requests -";
$current_page = 'group-change-requests';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

try {
    // Build query - simplified to match actual table structure
    $query = "SELECT 
            gcr.*,
            s.student_name,
            s.id as student_reg_id,
            cg.group_name as current_group_name,
            ng.group_name as requested_group_name
          FROM tbl_group_change_requests gcr
          LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
          LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
          LEFT JOIN tbl_group ng ON gcr.requested_group_id = ng.id
          WHERE 1=1";

    $params = [];

    if ($status_filter !== 'all') {
        $query .= " AND gcr.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($search)) {
        $query .= " AND (s.student_name LIKE ? OR CONCAT('REQ-', gcr.id) LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $query .= " ORDER BY 
            CASE gcr.status 
                WHEN 'pending' THEN 1 
                WHEN 'under_review' THEN 2 
                ELSE 3 
            END,
            gcr.request_date ASC";

    $requests = $dbOps->customSelect($query, $params);

    // Get counts for badges
    $count_query = "SELECT status, COUNT(*) as count FROM tbl_group_change_requests GROUP BY status";
    $status_counts_result = $dbOps->customSelect($count_query);
    $status_counts = [];
    foreach ($status_counts_result as $row) {
        $status_counts[$row['status']] = $row['count'];
    }
    $total_count = array_sum($status_counts);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logError')) {
        logError("Pending Requests Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    }
    $requests = [];
    $status_counts = [];
    $total_count = 0;

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'requests' => $requests,
        'status_counts' => $status_counts,
        'total_count' => $total_count,
        'applied_filters' => [
            'status' => $status_filter,
            'search' => $search
        ]
    ]);
}
