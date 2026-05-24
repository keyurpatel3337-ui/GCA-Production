<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($data['attempt_id'])) {
    $attempt_id = (int)$data['attempt_id'];
    
    // Update the last_active_timestamp
    $stmt = $conn->prepare("UPDATE tbl_oes_student_exams SET last_active_timestamp = NOW() WHERE id = ?");
    $stmt->execute([$attempt_id]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Invalid Request']);
}
$conn = null;
?>
