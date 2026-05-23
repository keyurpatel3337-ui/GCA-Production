<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

// Use standard hasAnyRole check
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($subject_id > 0) {
    $sql = "SELECT id, question_text, marks FROM tbl_oes_questions WHERE subject_id = ? AND status = 1 ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$subject_id]);
    $result = $stmt->fetchAll();
    
    $questions = [];
    foreach($result as $row) {
        $row['question_text'] = strip_tags($row['question_text']); 
        $questions[] = $row;
    }
    echo json_encode($questions);
} else {
    echo json_encode([]);
}
$conn = null;
?>
