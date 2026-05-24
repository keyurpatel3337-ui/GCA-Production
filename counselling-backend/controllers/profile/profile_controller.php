<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * User Profile Controller
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
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "My Profile";
$page_breadcrumb = "Profile";

// For API, user_id can come from query param
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    if ($is_api_call) {
        sendErrorResponse('User ID is required', 400);
    }
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get user details
try {
    $sql = "SELECT u.*, r.role_name 
            FROM tbl_users u 
            INNER JOIN tbl_roles r ON u.role_id = r.id 
            WHERE u.id = ?";
    $user_result = $dbOps->customSelect($sql, [$user_id]);
    $user = $user_result[0] ?? null;

    if (!$user) {
        if ($is_api_call) {
            sendErrorResponse('User not found', 404);
        }
        set_flash_message('error', 'User not found');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch User Profile");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', 'Unable to load profile');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// If API call, return JSON response
if ($is_api_call) {
    // Remove sensitive data
    unset($user['password']);
    sendSuccessResponse([
        'user' => $user
    ]);
}
