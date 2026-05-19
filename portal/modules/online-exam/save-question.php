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
    file_put_contents(__DIR__ . '/scratch/last_post_log.txt', print_r($_POST, true));
    
    // Log the post parameters for debugging
    $log_dir = __DIR__ . '/scratch';
    if (!is_dir($log_dir)) { mkdir($log_dir, 0777, true); }
    file_put_contents($log_dir . '/edit_save_log.txt', "[" . date('Y-m-d H:i:s') . "] SAVE POST DATA FOR ID: " . (isset($_POST['question_id']) ? $_POST['question_id'] : 0) . "\nPost Params: " . print_r($_POST, true) . "\n\n", FILE_APPEND);
    
    $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
    $question_text = $_POST['question_text'];
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'];
    $option_d = $_POST['option_d'];
    $question_type_id = (int)$_POST['question_type_id'];
    $correct_option = ($question_type_id == 1) ? $_POST['correct_option'] : null;
    $explanation = $_POST['explanation'];
    $subject_id = (int)$_POST['subject_id'];
    $standard_id = !empty($_POST['standard_id']) ? (int)$_POST['standard_id'] : null;
    $chapter_id = !empty($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : null;
    $topic_id = !empty($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;
    $marks = (float)$_POST['marks'];
    $negative_marks = (float)$_POST['negative_marks'];
    $video_solution_url = !empty($_POST['video_solution_url']) ? $_POST['video_solution_url'] : null;
    $difficulty = $_POST['difficulty'];
    $created_by = (int)$_SESSION['user_id'];

    // Options are only for MCQ
    if ($question_type_id != 1) {
        $option_a = $option_b = $option_c = $option_d = "";
    }

    // Handle Solution Image Upload
    $solution_image = null;
    if (isset($_FILES['solution_image']) && $_FILES['solution_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/oes/solutions/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        $file_ext = pathinfo($_FILES['solution_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'sol_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['solution_image']['tmp_name'], $target_file)) {
            $solution_image = 'uploads/oes/solutions/' . $file_name;
        }
    }

    try {
        if ($question_id > 0) {
            // UPDATE existing question in tbl_oes_questions
            $sql = "UPDATE tbl_oes_questions SET 
                    subject_id = ?, standard_id = ?, chapter_id = ?, topic_id = ?, 
                    question_type_id = ?, difficulty = ?, marks = ?, negative_marks = ?,
                    question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                    correct_option = ?, explanation = ?, video_solution_url = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            $params = [
                $subject_id, $standard_id, $chapter_id, $topic_id,
                $question_type_id, $difficulty, $marks, $negative_marks,
                $question_text, $option_a, $option_b, $option_c, $option_d,
                $correct_option, $explanation, $video_solution_url,
                $question_id
            ];

            if ($solution_image) {
                // Add solution_image to update
                $sql = "UPDATE tbl_oes_questions SET 
                        subject_id = ?, standard_id = ?, chapter_id = ?, topic_id = ?, 
                        question_type_id = ?, difficulty = ?, marks = ?, negative_marks = ?,
                        question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                        correct_option = ?, explanation = ?, video_solution_url = ?,
                        solution_image = ?, updated_at = NOW()
                        WHERE id = ?";
                $params = [
                    $subject_id, $standard_id, $chapter_id, $topic_id,
                    $question_type_id, $difficulty, $marks, $negative_marks,
                    $question_text, $option_a, $option_b, $option_c, $option_d,
                    $correct_option, $explanation, $video_solution_url,
                    $solution_image, $question_id
                ];
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $msg = "updated";
        } else {
            // INSERT new question into tbl_oes_questions
            $sql = "INSERT INTO tbl_oes_questions 
                    (subject_id, standard_id, chapter_id, topic_id, question_type_id, 
                     difficulty, marks, negative_marks, question_text, option_a, option_b, 
                     option_c, option_d, correct_option, explanation, video_solution_url, 
                     solution_image, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $subject_id, $standard_id, $chapter_id, $topic_id, $question_type_id,
                $difficulty, $marks, $negative_marks, $question_text,
                $option_a, $option_b, $option_c, $option_d,
                $correct_option, $explanation, $video_solution_url,
                $solution_image, $created_by
            ]);
            $msg = "success";
        }
    } catch (Exception $e) {
        die("Error saving question: " . $e->getMessage());
    }

    header("Location: question-bank.php");
}
$conn = null;
?>
