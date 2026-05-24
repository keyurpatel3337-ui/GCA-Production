<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
/**
 * Fee Config View Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        echo '<p class="text-danger">Unauthorized</p>';
        exit;
    }
}

$config_id = $_GET['id'] ?? 0;

if (!$config_id) {
    if ($is_api_call) {
        sendErrorResponse('Config ID is required', 400);
    }
    echo '<p class="text-danger">Config ID is required</p>';
    exit;
}

// Fetch configuration details
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_fee_config WHERE id = ?");
    $stmt->execute([$config_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        if ($is_api_call) {
            sendErrorResponse('Configuration not found', 404);
        }
        echo '<p class="text-danger">Configuration not found</p>';
        exit;
    }
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    echo '<p class="text-danger">Database error</p>';
    exit;
}

$payable_fees = $config['total_fees'] - $config['token_fee'];
$per_installment = $payable_fees / ($config['number_of_installments'] ?: 1);

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'config' => $config,
        'payable_fees' => $payable_fees,
        'per_installment' => $per_installment
    ]);
}
