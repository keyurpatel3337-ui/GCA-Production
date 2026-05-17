<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$topic_id = $_POST['id'] ?? 0;
$subject_id = $_POST['subject_id'] ?? 0;

try {
    $op = new Operation();

    // Get current status
    $topic = $op->selectOne('tbl_topics', ['*'], ['id' => $topic_id]);

    if ($topic) {
        // Toggle status
        $new_status = $topic['status'] == 'active' ? 'inactive' : 'active';
        $op->update('tbl_topics', ['status' => $new_status], ['id' => $topic_id]);

        set_flash_message('success', 'Topic status updated successfully!');
    } else {
        set_flash_message('error', 'Topic not found!');
    }
} catch (Exception $e) {
    set_flash_message('error', 'Error: ' . $e->getMessage());
}

header('Location: subjects-topics.php?subject_id=' . $subject_id);
exit;
