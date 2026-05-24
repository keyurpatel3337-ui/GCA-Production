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
    $standard_id = (int)$_POST['standard_id'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $duration_mins = (int)$_POST['duration_mins'];
    $exam_mode = $_POST['exam_mode'] ?? 'Practice';
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $display_result_immediately = isset($_POST['display_result_immediately']) ? 1 : 0;
    $created_by = (int)$_SESSION['user_id'];
    $question_ids = $_POST['question_ids'] ?? [];

    // OES targeting additions
    $target_type = $_POST['target_type'] ?? 'Common';
    $division_id = ($target_type === 'Division' && !empty($_POST['division_id'])) ? (int)$_POST['division_id'] : null;
    $student_ids = ($target_type === 'Students' && !empty($_POST['student_ids'])) ? $_POST['student_ids'] : [];

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

        // 1. Insert Exam with division_id and initial status
        $stmt = $conn->prepare("INSERT INTO tbl_oes_exams (title, description, standard_id, division_id, group_id, start_time, end_time, duration_mins, total_marks, exam_mode, shuffle_questions, display_result_immediately, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)");
        $stmt->execute([$title, $description, $standard_id, $division_id, $group_id, $start_time, $end_time, $duration_mins, $total_marks, $exam_mode, $shuffle_questions, $display_result_immediately, $created_by]);
        $exam_id = $conn->lastInsertId();

        // 2. Insert targeted student mappings if specific student targeting is active
        if ($target_type === 'Students' && !empty($student_ids)) {
            $stmt_stud = $conn->prepare("INSERT INTO tbl_oes_exam_students (exam_id, student_id) VALUES (?, ?)");
            foreach ($student_ids as $st_id) {
                $stmt_stud->execute([$exam_id, (int)$st_id]);
            }
        }

        // 3. Insert Exam Questions
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
