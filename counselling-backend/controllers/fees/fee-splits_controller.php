<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Fee Splits Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if ($is_api_call) {
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
} else {
    require_once $base_path . '/../common/helpers/error_logger.php';

    // Check if user is Super Admin
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Fee Split Management";
$page_breadcrumb = "Management -";

// Get all fee splits
try {
    $splits = $dbOps->customSelect("SELECT fs.*, u.name as created_by_name 
                          FROM tbl_fee_split_configuration fs 
                          LEFT JOIN tbl_users u ON fs.created_by = u.id 
                          ORDER BY fs.split_order", []);

    // Calculate total percentage
    $result = $dbOps->customSelectOne("SELECT SUM(split_percentage) as total FROM tbl_fee_split_configuration WHERE is_active = 1", []);
    $total_percentage = $result['total'] ?? 0;
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Fee Splits");
    }
    $splits = [];
    $total_percentage = 0;

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'splits' => $splits,
        'total_percentage' => $total_percentage
    ]);
}
