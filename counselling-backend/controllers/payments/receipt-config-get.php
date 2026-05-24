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
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendErrorResponse('Invalid ID', 400);
    }

    $stmt = $conn->prepare("SELECT * FROM tbl_receipt_configuration WHERE id = ?");
    $stmt->execute([$id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        sendErrorResponse('Receipt configuration not found', 404);
    }

    sendSuccessResponse($config, 'Receipt configuration retrieved successfully');
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
