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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        sendErrorResponse('Invalid configuration ID', 400);
    }

    try {
        // Here we could check if any student fee allocations use this configuration,
        // but since this is hostel settings, it's typically safe to delete or soft delete.
        
        $stmt = $conn->prepare("DELETE FROM tbl_hostel_fee_settings WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse(['id' => $id], 'Configuration deleted successfully!');
        } else {
            sendErrorResponse('Configuration not found or already deleted.', 404);
        }
    } catch (PDOException $e) {
        if (function_exists('logDatabaseError')) {
            logDatabaseError($e, "Delete Hostel Fee Configuration");
        }
        sendErrorResponse('Error deleting configuration. It might be in use.', 500);
    }
}

sendErrorResponse('Invalid request method', 405);
