<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
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
    $question_text_guj = isset($_POST['question_text_guj']) ? $_POST['question_text_guj'] : null;
    $option_a = $_POST['option_a'];
    $option_a_guj = isset($_POST['option_a_guj']) ? $_POST['option_a_guj'] : null;
    $option_b = $_POST['option_b'];
    $option_b_guj = isset($_POST['option_b_guj']) ? $_POST['option_b_guj'] : null;
    $option_c = $_POST['option_c'];
    $option_c_guj = isset($_POST['option_c_guj']) ? $_POST['option_c_guj'] : null;
    $option_d = $_POST['option_d'];
    $option_d_guj = isset($_POST['option_d_guj']) ? $_POST['option_d_guj'] : null;
    $question_type_id = (int)$_POST['question_type_id'];
    $correct_option = ($question_type_id == 1) ? $_POST['correct_option'] : null;
    $explanation = $_POST['explanation'];
    $explanation_guj = isset($_POST['explanation_guj']) ? $_POST['explanation_guj'] : null;
    $subject_id = (int)$_POST['subject_id'];
    $standard_id = !empty($_POST['standard_id']) ? (int)$_POST['standard_id'] : null;
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $chapter_id = !empty($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : null;
    $topic_id = !empty($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;
    $marks = (float)$_POST['marks'];
    $negative_marks = (float)$_POST['negative_marks'];
    $video_solution_url = !empty($_POST['video_solution_url']) ? $_POST['video_solution_url'] : null;
    $difficulty = $_POST['difficulty'];
    $exam_type = isset($_POST['exam_type']) ? $_POST['exam_type'] : (isset($_SESSION['oes_active_config']['exam_type']) ? $_SESSION['oes_active_config']['exam_type'] : 'both');
    $created_by = (int)$_SESSION['user_id'];

    // Options are only for MCQ
    if ($question_type_id != 1) {
        $option_a = $option_b = $option_c = $option_d = "";
        $option_a_guj = $option_b_guj = $option_c_guj = $option_d_guj = "";
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
        // If creating a new descriptive question (Bulk Multi-Insert)
        if ($question_id === 0 && $_POST['question_type_id'] === 'descriptive') {
            $inserted_count = 0;
            for ($m = 1; $m <= 5; $m++) {
                $desc_count = isset($_SESSION['oes_active_config']['desc_count_' . $m]) 
                    ? (int)$_SESSION['oes_active_config']['desc_count_' . $m] 
                    : 0;
                if ($desc_count <= 0) continue;

                for ($idx = 1; $idx <= $desc_count; $idx++) {
                    $q_text = isset($_POST['desc_question_' . $m . '_' . $idx]) ? $_POST['desc_question_' . $m . '_' . $idx] : '';
                    $q_text_guj = isset($_POST['desc_question_' . $m . '_' . $idx . '_guj']) ? $_POST['desc_question_' . $m . '_' . $idx . '_guj'] : '';
                    
                    // Skip if both English and Gujarati questions are empty
                    $stripped_en = trim(strip_tags($q_text, '<img><br><p>'));
                    $stripped_gu = trim(strip_tags($q_text_guj, '<img><br><p>'));
                    if ($stripped_en === '' && $stripped_gu === '') {
                        continue;
                    }
                    
                    $q_sol = isset($_POST['desc_solution_' . $m . '_' . $idx]) ? $_POST['desc_solution_' . $m . '_' . $idx] : '';
                    $q_sol_guj = isset($_POST['desc_solution_' . $m . '_' . $idx . '_guj']) ? $_POST['desc_solution_' . $m . '_' . $idx . '_guj'] : '';
                    $q_video = !empty($_POST['desc_video_' . $m . '_' . $idx]) ? $_POST['desc_video_' . $m . '_' . $idx] : null;
                    
                    // Process solution image upload for this specific descriptive question card
                    $q_image = null;
                    $file_key = 'desc_image_' . $m . '_' . $idx;
                    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/oes/solutions/';
                        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                        
                        $file_ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                        $file_name = 'sol_desc_' . $m . '_' . $idx . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                        $target_file = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                            $q_image = 'uploads/oes/solutions/' . $file_name;
                        }
                    }
                    
                    // Map mark to question_type_id: 1-mark => id 2, 2-mark => id 3, 3-mark => id 4, etc.
                    $q_type_id = $m + 1; 

                    // Custom Marks & Negative Marks per descriptive card
                    $q_marks = isset($_POST['desc_marks_' . $m . '_' . $idx]) ? (float)$_POST['desc_marks_' . $m . '_' . $idx] : (float)$m;
                    $q_neg_marks = isset($_POST['desc_negative_marks_' . $m . '_' . $idx]) ? (float)$_POST['desc_negative_marks_' . $m . '_' . $idx] : 0.0;
                    
                    $sql = "INSERT INTO tbl_oes_questions 
                            (subject_id, standard_id, group_id, chapter_id, topic_id, question_type_id, 
                             difficulty, exam_type, marks, negative_marks, question_text, question_text_guj, 
                             option_a, option_a_guj, option_b, option_b_guj, 
                             option_c, option_c_guj, option_d, option_d_guj, 
                             correct_option, explanation, explanation_guj, video_solution_url, 
                             solution_image, created_by, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', '', '', '', '', '', '', NULL, ?, ?, ?, ?, ?, 1)";
                            
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $subject_id, $standard_id, $group_id, $chapter_id, $topic_id, $q_type_id,
                        $difficulty, $exam_type, $q_marks, $q_neg_marks, $q_text, $q_text_guj,
                        $q_sol, $q_sol_guj, $q_video, $q_image, $created_by
                    ]);
                    $inserted_count++;
                }
            }
            
            $_SESSION['oes_flash_saved'] = true;
            header("Location: index.php");
            exit();
        }

        // If creating a new MCQ question in bulk configuration mode
        if ($question_id === 0 && $question_type_id == 1 && isset($_SESSION['oes_active_config']['mcq_question_count'])) {
            $mcq_count = (int)$_SESSION['oes_active_config']['mcq_question_count'];
            $inserted_count = 0;
            for ($idx = 1; $idx <= $mcq_count; $idx++) {
                $q_text = isset($_POST['question_text_' . $idx]) ? $_POST['question_text_' . $idx] : '';
                $q_text_guj = isset($_POST['question_text_guj_' . $idx]) ? $_POST['question_text_guj_' . $idx] : '';
                
                // Skip if both English and Gujarati questions are empty
                $stripped_en = trim(strip_tags($q_text, '<img><br><p>'));
                $stripped_gu = trim(strip_tags($q_text_guj, '<img><br><p>'));
                if ($stripped_en === '' && $stripped_gu === '') {
                    continue;
                }
                
                $opt_a = isset($_POST['option_a_' . $idx]) ? $_POST['option_a_' . $idx] : '';
                $opt_a_guj = isset($_POST['option_a_guj_' . $idx]) ? $_POST['option_a_guj_' . $idx] : '';
                $opt_b = isset($_POST['option_b_' . $idx]) ? $_POST['option_b_' . $idx] : '';
                $opt_b_guj = isset($_POST['option_b_guj_' . $idx]) ? $_POST['option_b_guj_' . $idx] : '';
                $opt_c = isset($_POST['option_c_' . $idx]) ? $_POST['option_c_' . $idx] : '';
                $opt_c_guj = isset($_POST['option_c_guj_' . $idx]) ? $_POST['option_c_guj_' . $idx] : '';
                $opt_d = isset($_POST['option_d_' . $idx]) ? $_POST['option_d_' . $idx] : '';
                $opt_d_guj = isset($_POST['option_d_guj_' . $idx]) ? $_POST['option_d_guj_' . $idx] : '';
                
                $corr_opt = isset($_POST['correct_option_' . $idx]) ? $_POST['correct_option_' . $idx] : 'A';
                $expl = isset($_POST['explanation_' . $idx]) ? $_POST['explanation_' . $idx] : '';
                $expl_guj = isset($_POST['explanation_guj_' . $idx]) ? $_POST['explanation_guj_' . $idx] : '';
                $video = !empty($_POST['video_solution_url_' . $idx]) ? $_POST['video_solution_url_' . $idx] : null;
                $q_marks = isset($_POST['marks_' . $idx]) ? (float)$_POST['marks_' . $idx] : 1.0;
                $q_neg_marks = isset($_POST['negative_marks_' . $idx]) ? (float)$_POST['negative_marks_' . $idx] : 0.0;
                
                // Process solution image upload for this specific MCQ question card
                $q_image = null;
                $file_key = 'solution_image_' . $idx;
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/oes/solutions/';
                    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                    
                    $file_ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                    $file_name = 'sol_mcq_' . $idx . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                        $q_image = 'uploads/oes/solutions/' . $file_name;
                    }
                }
                
                $sql = "INSERT INTO tbl_oes_questions 
                        (subject_id, standard_id, group_id, chapter_id, topic_id, question_type_id, 
                         difficulty, exam_type, marks, negative_marks, question_text, question_text_guj, 
                         option_a, option_a_guj, option_b, option_b_guj, 
                         option_c, option_c_guj, option_d, option_d_guj, 
                         correct_option, explanation, explanation_guj, video_solution_url, 
                         solution_image, created_by, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                        
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $subject_id, $standard_id, $group_id, $chapter_id, $topic_id, 1,
                    $difficulty, $exam_type, $q_marks, $q_neg_marks, $q_text, $q_text_guj,
                    $opt_a, $opt_a_guj, $opt_b, $opt_b_guj,
                    $opt_c, $opt_c_guj, $opt_d, $opt_d_guj,
                    $corr_opt, $expl, $expl_guj, $video,
                    $q_image, $created_by
                ]);
                $inserted_count++;
            }
            
            $_SESSION['oes_flash_saved'] = true;
            header("Location: index.php");
            exit();
        }

        if ($question_id > 0) {
            // UPDATE existing question in tbl_oes_questions
            $sql = "UPDATE tbl_oes_questions SET 
                    subject_id = ?, standard_id = ?, group_id = ?, chapter_id = ?, topic_id = ?, 
                    question_type_id = ?, difficulty = ?, exam_type = ?, marks = ?, negative_marks = ?,
                    question_text = ?, question_text_guj = ?, 
                    option_a = ?, option_a_guj = ?, 
                    option_b = ?, option_b_guj = ?, 
                    option_c = ?, option_c_guj = ?, 
                    option_d = ?, option_d_guj = ?,
                    correct_option = ?, explanation = ?, explanation_guj = ?, video_solution_url = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            $params = [
                $subject_id, $standard_id, $group_id, $chapter_id, $topic_id,
                $question_type_id, $difficulty, $exam_type, $marks, $negative_marks,
                $question_text, $question_text_guj,
                $option_a, $option_a_guj,
                $option_b, $option_b_guj,
                $option_c, $option_c_guj,
                $option_d, $option_d_guj,
                $correct_option, $explanation, $explanation_guj, $video_solution_url,
                $question_id
            ];

            if ($solution_image) {
                // Add solution_image to update
                $sql = "UPDATE tbl_oes_questions SET 
                        subject_id = ?, standard_id = ?, group_id = ?, chapter_id = ?, topic_id = ?, 
                        question_type_id = ?, difficulty = ?, exam_type = ?, marks = ?, negative_marks = ?,
                        question_text = ?, question_text_guj = ?, 
                        option_a = ?, option_a_guj = ?, 
                        option_b = ?, option_b_guj = ?, 
                        option_c = ?, option_c_guj = ?, 
                        option_d = ?, option_d_guj = ?,
                        correct_option = ?, explanation = ?, explanation_guj = ?, video_solution_url = ?,
                        solution_image = ?, updated_at = NOW()
                        WHERE id = ?";
                $params = [
                    $subject_id, $standard_id, $group_id, $chapter_id, $topic_id,
                    $question_type_id, $difficulty, $exam_type, $marks, $negative_marks,
                    $question_text, $question_text_guj,
                    $option_a, $option_a_guj,
                    $option_b, $option_b_guj,
                    $option_c, $option_c_guj,
                    $option_d, $option_d_guj,
                    $correct_option, $explanation, $explanation_guj, $video_solution_url,
                    $solution_image, $question_id
                ];
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $msg = "updated";
        } else {
            // INSERT new question into tbl_oes_questions
            $sql = "INSERT INTO tbl_oes_questions 
                    (subject_id, standard_id, group_id, chapter_id, topic_id, question_type_id, 
                     difficulty, exam_type, marks, negative_marks, question_text, question_text_guj, 
                     option_a, option_a_guj, option_b, option_b_guj, 
                     option_c, option_c_guj, option_d, option_d_guj, 
                     correct_option, explanation, explanation_guj, video_solution_url, 
                     solution_image, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $subject_id, $standard_id, $group_id, $chapter_id, $topic_id, $question_type_id,
                $difficulty, $exam_type, $marks, $negative_marks, $question_text, $question_text_guj,
                $option_a, $option_a_guj, $option_b, $option_b_guj,
                $option_c, $option_c_guj, $option_d, $option_d_guj,
                $correct_option, $explanation, $explanation_guj, $video_solution_url,
                $solution_image, $created_by
            ]);
            $msg = "success";
        }
    } catch (Exception $e) {
        die("Error saving question: " . $e->getMessage());
    }

    if (isset($_SESSION['oes_active_config']) && $question_id == 0) {
        $_SESSION['oes_flash_saved'] = true;
        header("Location: index.php");
    } else {
        header("Location: question-bank.php");
    }
}
$conn = null;
?>
