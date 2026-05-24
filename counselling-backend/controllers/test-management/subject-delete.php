<?php
/**
 * Subject Delete Handler
 * Deletes a test subject
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
    sendErrorResponse('Subject ID is required', 400);
}

try {
    // Check if subject has any topics
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_topics WHERE subject_id = ?");
    $checkStmt->execute([$id]);

    if ($checkStmt->fetchColumn() > 0) {
        sendErrorResponse('Cannot delete subject with existing topics. Delete topics first.', 400);
    }

    $stmt = $conn->prepare("DELETE FROM tbl_subjects WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendSuccessResponse(null, 'Subject deleted successfully');
    } else {
        sendErrorResponse('Subject not found', 404);
    }
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
