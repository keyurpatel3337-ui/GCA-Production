<?php
/**
 * Division Request Reject Handler
 * Rejects a division change request
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method', 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? null;
$rejection_reason = $input['rejection_reason'] ?? $input['reason'] ?? '';

if (!$id) {
    sendErrorResponse('Request ID is required', 400);
}

try {
    // Get the request details
    $getStmt = $conn->prepare("SELECT * FROM tbl_division_requests WHERE id = ? AND status = 'pending'");
    $getStmt->execute([$id]);
    $request = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        sendErrorResponse('Request not found or already processed', 404);
    }
    
    // Update request status
    $stmt = $conn->prepare("UPDATE tbl_division_requests SET 
        status = 'rejected', 
        rejection_reason = ?,
        processed_at = NOW(), 
        processed_by = ? 
        WHERE id = ?");
    $stmt->execute([$rejection_reason, $_SESSION['user_id'] ?? null, $id]);
    
    sendSuccessResponse(null, 'Division change request rejected');
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
