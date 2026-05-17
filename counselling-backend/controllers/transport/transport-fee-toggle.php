<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

header('Content-Type: application/json');

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        // Toggle is_active status
        $stmt = $conn->prepare("UPDATE tbl_transport_fee_settings SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccessResponse(['id' => $id], 'Transport fee configuration status updated successfully!');
    } catch (PDOException $e) {
        sendErrorResponse('Error updating status. Please try again.', 500);
    }
}

sendErrorResponse('Invalid request - ID required', 400);
