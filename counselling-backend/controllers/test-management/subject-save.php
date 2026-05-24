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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject_id = $_POST['subject_id'] ?? null;

        if ($subject_id) {
            // Update existing subject
            $result = $dbOps->update('tbl_subjects', [
                'subject_name' => $_POST['subject_name'],
                'subject_code' => $_POST['subject_code'] ?? null,
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? 'active'
            ], ['id' => $subject_id]);

            if ($result) {
                set_flash_message('success', 'Subject updated successfully!');
            } else {
                set_flash_message('error', 'Failed to update subject.');
            }
        } else {
            // Insert new subject
            $result = $dbOps->insert('tbl_subjects', [
                'subject_name' => $_POST['subject_name'],
                'subject_code' => $_POST['subject_code'] ?? null,
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? 'active',
                'created_by' => $_SESSION['user_id']
            ]);

            if ($result) {
                set_flash_message('success', 'Subject added successfully!');
            } else {
                set_flash_message('error', 'Failed to add subject.');
            }
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Subject");
        set_flash_message('error', 'Error: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/subjects.php');
exit;
