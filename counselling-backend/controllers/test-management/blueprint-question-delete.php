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

$question_id = $_GET['id'] ?? 0;
$paper_set_id = $_GET['paper_set_id'] ?? 0;

if ($question_id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM tbl_blueprint_questions WHERE id = ?");
        $result = $stmt->execute([$question_id]);

        if ($result) {
            set_flash_message('success', 'Question deleted successfully!');
        } else {
            set_flash_message('error', 'Failed to delete question.');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Delete Blueprint Question");
        set_flash_message('error', 'Database error: ' . $e->getMessage());
    }
} else {
    set_flash_message('error', 'Invalid question ID!');
}

header('Location: ' . BASE_URL . '/modules/test-management/blueprint-questions.php?paper_set_id=' . $paper_set_id);
exit;
