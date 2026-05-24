<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $paper_set_id = $_POST['paper_set_id'];
        $answer_key_id = $_POST['answer_key_id'] ?? 0;
        $test_name = $_POST['test_name'];
        $test_date = $_POST['test_date'] ?? null;
        $questions = $_POST['questions'] ?? [];
        $answers = $_POST['answers'] ?? [];

        // Validate
        if (count($questions) !== count($answers)) {
            set_flash_message('error', 'Questions and answers count mismatch!');
            header('Location: ' . BASE_URL . '/modules/test-management/answer-key-manual-entry.php?paper_set_id=' . $paper_set_id . ($answer_key_id > 0 ? '&id=' . $answer_key_id : ''));
            exit;
        }

        // Convert answers to JSON format
        $answers_json = [];
        foreach ($questions as $index => $question_num) {
            $answer = strtoupper(trim($answers[$index] ?? ''));
            if (!empty($answer)) {
                $answers_json[] = [
                    'q' => (int) $question_num,
                    'ans' => $answer
                ];
            }
        }

        // Sort by question number
        usort($answers_json, function ($a, $b) {
            return $a['q'] - $b['q'];
        });

        $answers_json_string = json_encode($answers_json);
        $total_questions = count($answers_json);

        if ($answer_key_id > 0) {
            // Update existing answer key
            $stmt = $conn->prepare("UPDATE tbl_answer_keys 
                                   SET test_name = ?, 
                                       test_date = ?, 
                                       total_questions = ?, 
                                       answers_json = ?,
                                       updated_at = NOW()
                                   WHERE id = ?");

            $result = $stmt->execute([
                $test_name,
                $test_date,
                $total_questions,
                $answers_json_string,
                $answer_key_id
            ]);

            if ($result) {
                set_flash_message('success', 'Answer key updated successfully! Total answers: ' . $total_questions);
            } else {
                set_flash_message('error', 'Failed to update answer key.');
            }
        } else {
            // Insert new answer key
            $stmt = $conn->prepare("INSERT INTO tbl_answer_keys 
                                   (paper_set_id, test_name, test_date, total_questions, answers_json, uploaded_by, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'active')");

            $result = $stmt->execute([
                $paper_set_id,
                $test_name,
                $test_date,
                $total_questions,
                $answers_json_string,
                $_SESSION['user_id']
            ]);

            if ($result) {
                set_flash_message('success', 'Answer key created successfully! Total answers: ' . $total_questions);
            } else {
                set_flash_message('error', 'Failed to create answer key.');
            }
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Manual Answer Key");
        set_flash_message('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/answer-keys.php?paper_set_id=' . $paper_set_id);
exit;