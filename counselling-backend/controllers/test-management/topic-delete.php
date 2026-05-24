<?php
/**
 * Topic Delete Handler
 * Deletes a test topic
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
    sendErrorResponse('Topic ID is required', 400);
}

try {
    // Check if topic has any blueprint questions
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_blueprint_questions WHERE topic_id = ?");
    $checkStmt->execute([$id]);

    if ($checkStmt->fetchColumn() > 0) {
        sendErrorResponse('Cannot delete topic with existing questions. Delete questions first.', 400);
    }

    $stmt = $conn->prepare("DELETE FROM tbl_topics WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendSuccessResponse(null, 'Topic deleted successfully');
    } else {
        sendErrorResponse('Topic not found', 404);
    }
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
