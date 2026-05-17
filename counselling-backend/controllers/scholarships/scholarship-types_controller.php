<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Scholarship Types Controller
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
    // Check if user is Super Admin
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Fetch all scholarship types
try {
    $sql = "SELECT st.*, u.name as created_by_name 
            FROM tbl_scholarship_types st 
            LEFT JOIN tbl_users u ON st.created_by = u.id 
            ORDER BY st.type_name";
    $scholarship_types = $dbOps->customSelect($sql);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Scholarship Types");
    }
    $scholarship_types = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'types' => $scholarship_types,
        'pagination' => [
            'current_page' => 1,
            'per_page' => count($scholarship_types),
            'total_records' => count($scholarship_types),
            'total_pages' => 1
        ]
    ]);
}

$page_title = "Manage Scholarship Types";
$page_breadcrumb = "Scholarship Types";
$current_page = 'scholarship-types';
