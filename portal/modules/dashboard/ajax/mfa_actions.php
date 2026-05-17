<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$device_id = $_POST['device_id'] ?? '';

if (empty($device_id) || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE tbl_user_devices SET is_authorized = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$device_id, $user_id]);
        $message = "Device approved successfully.";
    } else {
        $stmt = $conn->prepare("DELETE FROM tbl_user_devices WHERE id = ? AND user_id = ? AND is_authorized = 0");
        $stmt->execute([$device_id, $user_id]);
        $message = "Login request rejected.";
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
