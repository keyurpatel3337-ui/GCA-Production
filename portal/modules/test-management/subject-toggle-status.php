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

$subject_id = $_POST['id'] ?? 0;

try {
    $op = new Operation();

    // Get current status
    $subject = $op->selectOne('tbl_subjects', ['*'], ['id' => $subject_id]);

    if ($subject) {
        // Toggle status
        $new_status = $subject['status'] == 'active' ? 'inactive' : 'active';
        $op->update('tbl_subjects', ['status' => $new_status], ['id' => $subject_id]);

        set_flash_message('success', 'Subject status updated successfully!');
    } else {
        set_flash_message('error', 'Subject not found!');
    }
} catch (Exception $e) {
    set_flash_message('error', 'Error: ' . $e->getMessage());
}

header('Location: subjects.php');
exit;
