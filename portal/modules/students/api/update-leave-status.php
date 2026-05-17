<?php
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check role
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $leave_id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $reviewer_id = $_SESSION['user_id'];

    if (empty($leave_id) || !in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    if ($status === 'rejected' && empty($remarks)) {
        echo json_encode(['success' => false, 'message' => 'Remarks are required for rejection']);
        exit;
    }

    // Check if the request is still pending
    $stmt = $conn->prepare("SELECT id, status FROM tbl_student_leaves WHERE id = ?");
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch();

    if (!$leave) {
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit;
    }

    if ($leave['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'This request has already been processed']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tbl_student_leaves 
                            SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
                            WHERE id = ?");
    if ($stmt->execute([$status, $remarks, $reviewer_id, $leave_id])) {
        echo json_encode(['success' => true, 'message' => "Leave request $status successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update request']);
    }

} catch (Exception $e) {
    logError("Update Leave Status API Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred', 'debug' => $e->getMessage()]);
}
