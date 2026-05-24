<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
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

// Initialize Operation class
$dbOps = new Operation();

$subject_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $topic_id = $_POST['topic_id'] ?? null;
        $subject_id = $_POST['subject_id'];

        if ($topic_id) {
            // Update existing topic
            $result = $dbOps->update('tbl_topics', [
                'topic_name_english' => $_POST['topic_name_english'],
                'topic_name_gujarati' => $_POST['topic_name_gujarati'] ?? null,
                'topic_code' => $_POST['topic_code'] ?? null,
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? 'active'
            ], ['id' => $topic_id]);

            if ($result) {
                set_flash_message('success', 'Topic updated successfully!');
            } else {
                set_flash_message('error', 'Failed to update topic.');
            }
        } else {
            // Insert new topic
            $result = $dbOps->insert('tbl_topics', [
                'subject_id' => $subject_id,
                'topic_name_english' => $_POST['topic_name_english'],
                'topic_name_gujarati' => $_POST['topic_name_gujarati'] ?? null,
                'topic_code' => $_POST['topic_code'] ?? null,
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? 'active',
                'created_by' => $_SESSION['user_id']
            ]);

            if ($result) {
                set_flash_message('success', 'Topic added successfully!');
            } else {
                set_flash_message('error', 'Failed to add topic.');
            }
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Topic");
        set_flash_message('error', 'Error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/subjects-topics.php?subject_id=' . $subject_id);
exit;
