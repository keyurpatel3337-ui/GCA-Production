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

if ($data) {
    $attempt_id = (int)$data['attempt_id'];

    // 1. Fetch Exam ID and Questions with Correct Options
    $stmt = $conn->prepare("SELECT e.id, e.total_marks FROM tbl_oes_exams e JOIN tbl_oes_student_exams se ON e.id = se.exam_id WHERE se.id = ?");
    $stmt->execute([$attempt_id]);
    $exam = $stmt->fetch();

    $q_stmt = $conn->prepare("SELECT q.id, q.correct_option, q.marks, q.negative_marks 
                             FROM tbl_oes_questions q 
                             JOIN tbl_oes_exam_questions eq ON q.id = eq.question_id 
                             WHERE eq.exam_id = ?");
    $q_stmt->execute([$exam['id']]);
    $questions = $q_stmt->fetchAll();

    // 2. Fetch Student Responses
    $r_stmt = $conn->prepare("SELECT question_id, selected_option FROM tbl_oes_responses WHERE student_exam_id = ?");
    $r_stmt->execute([$attempt_id]);
    $responses = [];
    while ($row = $r_stmt->fetch()) {
        $responses[$row['question_id']] = $row['selected_option'];
    }

    // 3. Calculate Score
    $score = 0;
    $correct_count = 0;
    $incorrect_count = 0;

    foreach ($questions as $q) {
        if (isset($responses[$q['id']])) {
            if ($responses[$q['id']] === $q['correct_option']) {
                $score += (float)$q['marks'];
                $correct_count++;
            } else {
                $score -= (float)$q['negative_marks'];
                $incorrect_count++;
            }
        }
    }

    // 4. Update Student Exam Status
    $upd = $conn->prepare("UPDATE tbl_oes_student_exams SET status = 'Submitted', submit_timestamp = NOW(), total_score = ?, correct_answers = ?, wrong_answers = ? WHERE id = ?");
    $upd->execute([$score, $correct_count, $incorrect_count, $attempt_id]);

    echo json_encode(['success' => true, 'score' => $score]);
}
$conn = null;
?>
