<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

try {
    $id = intval($_POST['id'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 0);

    if ($id <= 0) {
        sendErrorResponse('Invalid ID', 400);
    }

    // If setting as active, deactivate all other receipts
    if ($is_active) {
        $stmt = $conn->prepare("UPDATE tbl_receipt_configuration SET is_active = 0 WHERE id != ?");
        $stmt->execute([$id]);
    }

    // Update the selected receipt
    $stmt = $conn->prepare("UPDATE tbl_receipt_configuration SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$is_active, $id]);

    $message = $is_active ? 'Receipt set as default successfully' : 'Receipt deactivated successfully';
    sendSuccessResponse(['id' => $id, 'is_active' => $is_active], $message);
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
