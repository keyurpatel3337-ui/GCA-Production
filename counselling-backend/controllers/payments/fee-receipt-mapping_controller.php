<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Fee-Receipt Mapping Controller
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
    require_once $base_path . '/../common/helpers/receipt_mapping_functions.php';
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Fetch all mappings
$mappings = [];
$receipt_configs = [];
$schools = [];

try {
    if (function_exists('getAllFeeReceiptMappings')) {
        $mappings = getAllFeeReceiptMappings($conn);
    } else {
        $mappings = $dbOps->select('tbl_fee_receipt_mapping', ['*'], [], 'id');
    }

    if (function_exists('getAllReceiptConfigs')) {
        $receipt_configs = getAllReceiptConfigs($conn);
    } else {
        $receipt_configs = $dbOps->select('tbl_receipt_config', ['*'], [], 'config_name');
    }

    $schools = $dbOps->select('tbl_schools', ['id', 'school_name'], ['is_active' => 1], 'school_name ASC');
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'mappings' => $mappings,
        'receipt_configs' => $receipt_configs,
        'schools' => $schools
    ]);
}
