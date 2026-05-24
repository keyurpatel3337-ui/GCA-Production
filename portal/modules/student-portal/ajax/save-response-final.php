<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

// Check secure final exam isolated session namespace
if (!isset($_SESSION['final_student_id'])) {
    echo json_encode(['error' => 'Unauthorized Terminal Connection']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $attempt_id = (int)$data['attempt_id'];
    $question_id = (int)$data['question_id'];
    $selected_option = isset($data['selected_option']) ? $data['selected_option'] : null;
    $marked_for_review = isset($data['marked_for_review']) ? (int)$data['marked_for_review'] : 0;

    // Check if response already exists
    $stmt = $conn->prepare("SELECT id FROM tbl_oes_responses WHERE student_exam_id = ? AND question_id = ?");
    $stmt->execute([$attempt_id, $question_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upd = $conn->prepare("UPDATE tbl_oes_responses SET selected_option = ?, marked_for_review = ?, timestamp = NOW() WHERE id = ?");
        $upd->execute([$selected_option, $marked_for_review, $existing['id']]);
    } else {
        $ins = $conn->prepare("INSERT INTO tbl_oes_responses (student_exam_id, question_id, selected_option, marked_for_review, timestamp) VALUES (?, ?, ?, ?, NOW())");
        $ins->execute([$attempt_id, $question_id, $selected_option, $marked_for_review]);
    }
    echo json_encode(['success' => true]);
}
$conn = null;
?>
