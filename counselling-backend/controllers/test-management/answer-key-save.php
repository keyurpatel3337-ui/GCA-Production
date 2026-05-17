<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload
        $answer_key_file = null;
        if (isset($_FILES['answer_key_file']) && $_FILES['answer_key_file']['error'] == 0) {
            $upload_dir = ANSWER_KEY_PATH;
            $file_name = time() . '_' . basename($_FILES['answer_key_file']['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['answer_key_file']['tmp_name'], $target_file)) {
                $answer_key_file = $file_name;
            }
        }

        $stmt = $conn->prepare("INSERT INTO tbl_answer_keys 
                               (paper_set_id, test_name, test_date, total_questions, answer_key_file, answers_json, uploaded_by, status) 
                               VALUES (?, ?, ?, 100, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $_POST['paper_set_id'],
            $_POST['test_name'],
            $_POST['test_date'] ?? null,
            $answer_key_file,
            $_POST['answers_json'] ?? null,
            $user_id,
            $_POST['status'] ?? 'active'
        ]);

        if ($result) {
            set_flash_message('success', 'Answer key uploaded successfully!');
        } else {
            set_flash_message('error', 'Failed to upload answer key.');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/answer-keys.php');
exit;
