<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Receipt Configuration Controller
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
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Fetch all receipt configurations
try {
    $sql = "SELECT r.*, u.name as created_by_name 
        FROM tbl_receipt_configuration r
        LEFT JOIN tbl_users u ON r.created_by = u.id
        ORDER BY r.is_active ASC, r.id desc";
    $receipts = $dbOps->customSelect($sql);
} catch (PDOException $e) {
    $receipts = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'receipts' => $receipts
    ]);
}


