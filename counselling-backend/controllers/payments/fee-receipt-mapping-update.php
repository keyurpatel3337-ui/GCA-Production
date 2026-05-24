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

$id = intval($_POST['id'] ?? 0);
$fee_type = trim($_POST['fee_type'] ?? '');
$school_id = !empty($_POST['school_id']) ? intval($_POST['school_id']) : null;
$receipt_config_id = intval($_POST['receipt_config_id'] ?? 0);

// Validation
if ($id <= 0 || empty($fee_type) || $receipt_config_id <= 0) {
    sendErrorResponse('Please fill in all required fields', 400);
}

try {
    $stmt = $conn->prepare("
        UPDATE tbl_fee_receipt_mapping 
        SET fee_type = ?, school_id = ?, receipt_config_id = ?, updated_at = NOW()
        WHERE id = ?
    ");

    if ($stmt->execute([$fee_type, $school_id, $receipt_config_id, $id])) {
        sendSuccessResponse(['id' => $id], 'Mapping updated successfully');
    } else {
        sendErrorResponse('Failed to update mapping', 500);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        sendErrorResponse('Mapping already exists for this fee type and school combination', 400);
    } else {
        sendErrorResponse('Error updating mapping: ' . $e->getMessage(), 500);
    }
}
