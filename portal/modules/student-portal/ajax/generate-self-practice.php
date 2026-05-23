<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in first.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$student_id = $_SESSION['student_id'];

// Retrieve and sanitize inputs
$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
$chapter_id = isset($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : 0;
$difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : '';
$question_count = isset($_POST['question_count']) ? (int)$_POST['question_count'] : 10;

// Validations
if ($subject_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid subject.']);
    exit();
}

if ($question_count < 1 || $question_count > 50) {
    echo json_encode(['success' => false, 'message' => 'Question count must be between 1 and 50.']);
    exit();
}

try {
    // 1. Fetch student standard and group mapping
    $std_stmt = $conn->prepare("
        SELECT r.course_id, r.standard as reg_standard_num, r.medium_id, r.group_id, e.division_id, s.stdid, s.stdnumber 
        FROM tbl_gm_std_registration r
        LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id AND e.is_active = 1
        LEFT JOIN standard s ON s.stdnumber = r.standard AND (
            (r.standard = 13)
            OR (r.medium_id = 1 AND s.stdtext LIKE '%Gujarati%')
            OR (r.medium_id = 2 AND s.stdtext LIKE '%English%')
        )
        WHERE r.id = ?
    ");
    $std_stmt->execute([$student_id]);
    $student = $std_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student enrollment record not found.']);
        exit();
    }

    $standard_id = $student['stdid'] ?? 0;
    $std_number = $student['stdnumber'] ?? 0;
    $group_id = $student['group_id'] ?? null;
    $division_id = $student['division_id'] ?? null;

    // 2. Fetch subject and chapter names for the exam title
    $sub_stmt = $conn->prepare("SELECT subject_name FROM tbl_subjects WHERE id = ?");
    $sub_stmt->execute([$subject_id]);
    $subject_name = $sub_stmt->fetchColumn() ?: 'Subject';

    $chapter_name = '';
    if ($chapter_id > 0) {
        $ch_stmt = $conn->prepare("SELECT chapter FROM tbl_chapters WHERE chpid = ?");
        $ch_stmt->execute([$chapter_id]);
        $chapter_name = $ch_stmt->fetchColumn() ?: '';
    }

    // 3. Build dynamic query to fetch randomized active practice questions
    $where = ["q.status = 1", "q.subject_id = :subject_id", "(q.exam_type = 'practice' OR q.exam_type = 'both')"];
    $params = [':subject_id' => $subject_id];

    if ($chapter_id > 0) {
        $where[] = "q.chapter_id = :chapter_id";
        $params[':chapter_id'] = $chapter_id;
    }

    if (!empty($difficulty)) {
        $where[] = "q.difficulty = :difficulty";
        $params[':difficulty'] = $difficulty;
    }

    if ($group_id > 0) {
        $where[] = "(q.group_id = :group_id OR q.group_id IS NULL OR q.group_id = 0)";
        $params[':group_id'] = $group_id;
    }

    $where_sql = implode(" AND ", $where);
    $q_sql = "SELECT q.id, q.marks FROM tbl_oes_questions q WHERE $where_sql ORDER BY RAND() LIMIT :limit";

    $stmt = $conn->prepare($q_sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $question_count, PDO::PARAM_INT);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        echo json_encode(['success' => false, 'message' => 'No active practice questions found matching your chosen filters. Please try another combination.']);
        exit();
    }

    // 4. Start Transaction
    $conn->beginTransaction();

    $total_marks = 0.0;
    $question_ids = [];
    foreach ($questions as $q) {
        $total_marks += (float)$q['marks'];
        $question_ids[] = (int)$q['id'];
    }

    // Dynamic Title & Description
    $title = "Practice: " . htmlspecialchars($subject_name);
    if (!empty($chapter_name)) {
        $title .= " - " . htmlspecialchars($chapter_name);
    }
    
    // Automatically allocate 2 minutes per question
    $duration_mins = count($question_ids) * 2;
    $description = "Self-Practice session generated dynamically by student.";

    // Insert transient exam
    $ins_exam = $conn->prepare("
        INSERT INTO tbl_oes_exams 
        (title, description, standard_id, division_id, group_id, start_time, end_time, duration_mins, total_marks, exam_mode, shuffle_questions, display_result_immediately, status, created_by, student_id) 
        VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), ?, ?, 'Practice', 1, 1, 'Live', -1, ?)
    ");
    
    $ins_exam->execute([
        $title,
        $description,
        $standard_id ?: null,
        $division_id ?: null,
        $group_id ?: null,
        $duration_mins,
        $total_marks,
        $student_id
    ]);
    
    $exam_id = $conn->lastInsertId();

    // Map questions to the new exam
    $stmt_q = $conn->prepare("INSERT INTO tbl_oes_exam_questions (exam_id, question_id, order_no) VALUES (?, ?, ?)");
    foreach ($question_ids as $index => $q_id) {
        $order_no = $index + 1;
        $stmt_q->execute([$exam_id, $q_id, $order_no]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'exam_id' => $exam_id,
        'message' => 'Self-practice test generated successfully!'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during generation: ' . $e->getMessage()
    ]);
}
