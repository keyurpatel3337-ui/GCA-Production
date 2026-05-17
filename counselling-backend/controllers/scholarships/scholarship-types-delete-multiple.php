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
    if (!isset($_POST['ids']) || !is_array($_POST['ids']) || empty($_POST['ids'])) {
        sendErrorResponse('No scholarship types selected', 400);
    }

    $ids = array_map('intval', $_POST['ids']);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';

    // Check if any scholarship types are in use
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_scholarship_rules WHERE scholarship_type_id IN ($placeholders)");
    $checkStmt->execute($ids);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        sendErrorResponse('Cannot delete scholarship types that are linked to rules.', 400);
    }

    $stmt = $conn->prepare("DELETE FROM tbl_scholarship_types WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    sendSuccessResponse(['deleted_count' => $stmt->rowCount()], $stmt->rowCount() . ' scholarship type(s) deleted successfully');
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
