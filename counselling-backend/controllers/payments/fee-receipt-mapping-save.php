<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method', 405);
}

$fee_type = trim($_POST['fee_type'] ?? '');
$school_id = !empty($_POST['school_id']) ? intval($_POST['school_id']) : null;
$receipt_config_id = intval($_POST['receipt_config_id'] ?? 0);

// Validation
if (empty($fee_type) || $receipt_config_id <= 0) {
    sendErrorResponse('Please fill in all required fields', 400);
}

// Validate fee type
$valid_fee_types = array_keys(getFeeTypes());
if (!in_array($fee_type, $valid_fee_types)) {
    sendErrorResponse('Invalid fee type', 400);
}

// Verify receipt config exists and is active
$stmt = $conn->prepare("SELECT id FROM tbl_receipt_configuration WHERE id = ? AND is_active = 1");
$stmt->execute([$receipt_config_id]);
if (!$stmt->fetch()) {
    sendErrorResponse('Invalid or inactive receipt configuration', 400);
}

// If school_id provided, verify it exists
if ($school_id) {
    $stmt = $conn->prepare("SELECT id FROM tbl_schools WHERE id = ? AND is_active = 1");
    $stmt->execute([$school_id]);
    if (!$stmt->fetch()) {
        sendErrorResponse('Invalid or inactive school', 400);
    }
}

try {
    if (saveFeeReceiptMapping($conn, $fee_type, $receipt_config_id, $school_id)) {
        sendSuccessResponse(['fee_type' => $fee_type], 'Fee-Receipt mapping saved successfully');
    } else {
        sendErrorResponse('Failed to save mapping', 500);
    }
} catch (Exception $e) {
    sendErrorResponse('Error saving mapping: ' . $e->getMessage(), 500);
}
