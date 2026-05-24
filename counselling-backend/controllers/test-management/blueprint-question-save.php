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
        $question_number = $_POST['question_number'];
        $blueprint_topic_id = $_POST['blueprint_topic_id'];
        $difficulty_level = !empty($_POST['difficulty_level']) ? $_POST['difficulty_level'] : 'low';
        $marks = $_POST['marks'] ?? 1.00;

        // Get total blueprint questions allowed
        $stmt = $conn->prepare("SELECT SUM(total_questions) as total FROM tbl_blueprint_topics WHERE paper_set_id = ?");
        $stmt->execute([$paper_set_id]);
        $blueprint_total = $stmt->fetch()['total'] ?? 0;

        // Count existing questions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_blueprint_questions WHERE paper_set_id = ?");
        $stmt->execute([$paper_set_id]);
        $existing_count = $stmt->fetch()['count'];

        // Check if blueprint is complete
        if ($existing_count >= $blueprint_total) {
            set_flash_message('error', 'Cannot add more questions. Blueprint allows only ' . $blueprint_total . ' questions.');
            header('Location: ' . BASE_URL . '/modules/test-management/blueprint-questions.php?paper_set_id=' . $paper_set_id);
            exit;
        }

        // Check if question number already exists for this paper set
        $stmt = $conn->prepare("SELECT id FROM tbl_blueprint_questions 
                               WHERE paper_set_id = ? AND question_number = ?");
        $stmt->execute([$paper_set_id, $question_number]);

        if ($stmt->fetch()) {
            set_flash_message('error', 'Question number ' . $question_number . ' already exists for this paper set!');
            header('Location: ' . BASE_URL . '/modules/test-management/blueprint-questions.php?paper_set_id=' . $paper_set_id);
            exit;
        }

        // Insert new question
        $stmt = $conn->prepare("INSERT INTO tbl_blueprint_questions 
                               (blueprint_topic_id, paper_set_id, question_number, difficulty_level, marks) 
                               VALUES (?, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $blueprint_topic_id,
            $paper_set_id,
            $question_number,
            $difficulty_level,
            $marks
        ]);

        if ($result) {
            set_flash_message('success', 'Question added successfully!');
        } else {
            set_flash_message('error', 'Failed to add question.');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Add Blueprint Question");
        set_flash_message('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/blueprint-questions.php?paper_set_id=' . $paper_set_id);
exit;
