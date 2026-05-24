<?php
/**
 * Subject Toggle Status Handler
 * Toggles active/inactive status of a test subject
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
    // Toggle status
    $stmt = $conn->prepare("UPDATE tbl_subjects SET status = IF(status = 'active', 'inactive', 'active'), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        // Get new status
        $getStmt = $conn->prepare("SELECT status FROM tbl_subjects WHERE id = ?");
        $getStmt->execute([$id]);
        $newStatus = $getStmt->fetchColumn();

        sendSuccessResponse(['status' => $newStatus], 'Subject status updated successfully');
    } else {
        sendErrorResponse('Subject not found', 404);
    }
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
