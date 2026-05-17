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
        $question_id = $_POST['question_id'];
        $paper_set_id = $_POST['paper_set_id'];
        $question_number = $_POST['question_number'];
        $blueprint_topic_id = $_POST['blueprint_topic_id'];
        $difficulty_level = $_POST['difficulty_level'];
        $marks = $_POST['marks'] ?? 1.00;

        // Check if question number already exists for another question in this paper set
        $stmt = $conn->prepare("SELECT id FROM tbl_blueprint_questions 
                               WHERE paper_set_id = ? AND question_number = ? AND id != ?");
        $stmt->execute([$paper_set_id, $question_number, $question_id]);

        if ($stmt->fetch()) {
            set_flash_message('error', 'Question number ' . $question_number . ' already exists for this paper set!');
            header('Location: ' . BASE_URL . '/modules/test-management/blueprint-questions.php?paper_set_id=' . $paper_set_id);
            exit;
        }

        // Update question
        $stmt = $conn->prepare("UPDATE tbl_blueprint_questions 
                               SET blueprint_topic_id = ?, 
                                   question_number = ?, 
                                   difficulty_level = ?, 
                                   marks = ? 
                               WHERE id = ?");

        $result = $stmt->execute([
            $blueprint_topic_id,
            $question_number,
            $difficulty_level,
            $marks,
            $question_id
        ]);

        if ($result) {
            set_flash_message('success', 'Question updated successfully!');
        } else {
            set_flash_message('error', 'Failed to update question.');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Update Blueprint Question");
        set_flash_message('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/blueprint-questions.php?paper_set_id=' . $paper_set_id);
exit;
