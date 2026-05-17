<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $standard_id = (int)$_POST['standard_id']; // Added
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $duration_mins = (int)$_POST['duration_mins'];
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $display_result_immediately = isset($_POST['display_result_immediately']) ? 1 : 0;
    $created_by = (int)$_SESSION['user_id'];
    $question_ids = $_POST['question_ids'] ?? [];

    if (empty($question_ids)) {
        die("Please select at least one question.");
    }

    // Start Transaction
    $conn->beginTransaction();

    try {
        // Calculate total marks from selected questions
        $total_marks = 0;
        $placeholders = str_repeat('?,', count($question_ids) - 1) . '?';
        $res = $conn->prepare("SELECT SUM(marks) as total FROM tbl_oes_questions WHERE id IN ($placeholders)");
        $res->execute($question_ids);
        if ($row = $res->fetch()) {
            $total_marks = (float)$row['total'];
        }

        // 1. Insert Exam (Included standard_id)
        $stmt = $conn->prepare("INSERT INTO tbl_oes_exams (title, description, standard_id, start_time, end_time, duration_mins, total_marks, shuffle_questions, display_result_immediately, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)");
        $stmt->execute([$title, $description, $standard_id, $start_time, $end_time, $duration_mins, $total_marks, $shuffle_questions, $display_result_immediately, $created_by]);
        $exam_id = $conn->lastInsertId();

        // 2. Insert Exam Questions
        $stmt_q = $conn->prepare("INSERT INTO tbl_oes_exam_questions (exam_id, question_id, order_no) VALUES (?, ?, ?)");
        foreach ($question_ids as $index => $q_id) {
            $order_no = $index + 1;
            $stmt_q->execute([$exam_id, $q_id, $order_no]);
        }

        $conn->commit();
        header("Location: manage-exams.php?msg=exam_created");
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}
$conn = null;
?>
