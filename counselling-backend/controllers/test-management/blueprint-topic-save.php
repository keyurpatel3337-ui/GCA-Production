<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $paper_set_id = $_POST['paper_set_id'];
        $subject_id = $_POST['subject_id'];
        $topic_id = !empty($_POST['topic_id']) ? $_POST['topic_id'] : null;

        // Validate that subject exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_subjects WHERE id = ?");
        $stmt->execute([$subject_id]);

        if ($stmt->fetchColumn() == 0) {
            set_flash_message('error', 'Invalid subject selected.');
            header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
            exit;
        }

        // If topic is provided, validate it exists and belongs to the subject
        if ($topic_id !== null) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_topics WHERE id = ? AND subject_id = ?");
            $stmt->execute([$topic_id, $subject_id]);

            if ($stmt->fetchColumn() == 0) {
                set_flash_message('error', 'Invalid topic selected for the given subject.');
                header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
                exit;
            }
        }

        // Insert into blueprint_topics with references to master tables
        $stmt = $conn->prepare("INSERT INTO tbl_blueprint_topics 
                               (paper_set_id, sr_no, subject_id, topic_id, total_questions) 
                               VALUES (?, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $paper_set_id,
            $_POST['sr_no'],
            $subject_id,
            $topic_id,
            $_POST['total_questions']
        ]);

        if ($result) {
            set_flash_message('success', 'Topic added successfully!');
        } else {
            set_flash_message('error', 'Failed to add topic.');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Add Blueprint Topic");
        set_flash_message('error', 'Error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $_POST['paper_set_id']);
exit;
