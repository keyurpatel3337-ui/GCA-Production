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

    if ($id <= 0) {
        sendErrorResponse('Invalid ID', 400);
    }

    // Check if configuration exists
    $stmt = $conn->prepare("SELECT logo_path, signature_path FROM tbl_receipt_configuration WHERE id = ?");
    $stmt->execute([$id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        sendErrorResponse('Receipt configuration not found', 404);
    }

    // Delete uploaded files
    if (!empty($config['logo_path']) && file_exists($config['logo_path'])) {
        unlink($config['logo_path']);
    }
    if (!empty($config['signature_path']) && file_exists($config['signature_path'])) {
        unlink($config['signature_path']);
    }

    // Delete configuration
    $stmt = $conn->prepare("DELETE FROM tbl_receipt_configuration WHERE id = ?");
    $stmt->execute([$id]);

    sendSuccessResponse(['id' => $id], 'Receipt configuration deleted successfully');
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
