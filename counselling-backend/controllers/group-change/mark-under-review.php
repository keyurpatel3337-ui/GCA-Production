<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('Unauthorized', 401);
}

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != ROLE_PRINCIPLE) {
    sendErrorResponse('Access denied', 403);
}

$request_id = intval($_POST['request_id'] ?? 0);

try {
    // Fetch request
    $stmt = $conn->prepare("SELECT status FROM tbl_group_change_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Request not found!");
    }

    if ($request['status'] !== 'pending') {
        throw new Exception("Only pending requests can be marked as under review!");
    }

    $conn->beginTransaction();

    // Update status
    $stmt = $conn->prepare("UPDATE tbl_group_change_requests 
                            SET status = 'under_review'
                            WHERE id = ?");
    $stmt->execute([$request_id]);

    // Log in history
    $stmt = $conn->prepare("INSERT INTO tbl_group_change_history 
                            (request_id, action_type, action_by, action_by_role, old_status, new_status, remarks)
                            VALUES (?, 'under_review', ?, 'principal', 'pending', 'under_review', 'Request is now under review')");
    $stmt->execute([$request_id, $_SESSION['user_id']]);

    $conn->commit();

    sendSuccessResponse(['request_id' => $request_id], 'Request marked as under review');
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Mark Under Review Error: " . $e->getMessage());
    sendErrorResponse($e->getMessage(), 500);
}
