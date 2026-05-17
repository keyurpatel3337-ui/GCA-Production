<?php
/**
 * Division Request Approve Handler
 * Approves a division change request
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method', 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? null;

if (!$id) {
    sendErrorResponse('Request ID is required', 400);
}

try {
    $conn->beginTransaction();
    
    // Get the request details
    $getStmt = $conn->prepare("SELECT * FROM tbl_division_requests WHERE id = ? AND status = 'pending'");
    $getStmt->execute([$id]);
    $request = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        sendErrorResponse('Request not found or already processed', 404);
    }
    
    // Update the student's division
    $updateStudent = $conn->prepare("UPDATE tbl_enrolled_students SET division_id = ? WHERE enrollment_id = ?");
    $updateStudent->execute([$request['new_division_id'], $request['enrollment_id']]);
    
    // Update request status
    $updateRequest = $conn->prepare("UPDATE tbl_division_requests SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
    $updateRequest->execute([$_SESSION['user_id'] ?? null, $id]);
    
    $conn->commit();
    sendSuccessResponse(null, 'Division change request approved successfully');
} catch (PDOException $e) {
    $conn->rollBack();
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
