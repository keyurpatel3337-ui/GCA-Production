<?php
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $leave_id = $_POST['id'] ?? '';
    $student_id = $_POST['student_id'] ?? $_SESSION['student_id'];

    if (empty($leave_id) || empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    // Verify ownership and status
    $stmt = $conn->prepare("SELECT id, status FROM tbl_student_leaves WHERE id = ? AND student_id = ?");
    $stmt->execute([$leave_id, $student_id]);
    $leave = $stmt->fetch();

    if (!$leave) {
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit;
    }

    if ($leave['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending requests can be cancelled']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tbl_student_leaves SET status = 'cancelled' WHERE id = ?");
    if ($stmt->execute([$leave_id])) {
        echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel the request']);
    }

} catch (Exception $e) {
    logError("Cancel Leave API Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred', 'debug' => $e->getMessage()]);
}
