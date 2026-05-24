<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_id = intval($_POST['request_id'] ?? 0);
$student_id = $_SESSION['student_id'];

try {
    // Verify request belongs to student and is pending
    $stmt = $conn->prepare("SELECT status FROM tbl_group_change_requests 
                            WHERE id = ? AND student_id = ?");
    $stmt->execute([$request_id, $student_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Request not found!");
    }

    if ($request['status'] !== 'pending') {
        throw new Exception("Only pending requests can be cancelled!");
    }

    $conn->beginTransaction();

    // Update request status
    $stmt = $conn->prepare("UPDATE tbl_group_change_requests 
                            SET status = 'cancelled'
                            WHERE id = ?");
    $stmt->execute([$request_id]);

    // Log in history
    $stmt = $conn->prepare("INSERT INTO tbl_group_change_history 
                            (request_id, action_type, action_by, action_by_role, old_status, new_status, remarks)
                            VALUES (?, 'cancelled', ?, 'student', 'pending', 'cancelled', 'Cancelled by student')");
    $stmt->execute([$request_id, $student_id]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Cancel Group Change Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
