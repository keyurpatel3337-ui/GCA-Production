<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Initialize Operation class
$dbOps = new Operation();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = $dbOps->insert('tbl_paper_sets', [
            'paper_set_name' => $_POST['paper_set_name'],
            'paper_code' => $_POST['paper_code'],
            'description' => $_POST['description'] ?? null,
            'total_questions' => $_POST['total_questions'] ?? 100,
            'low_level_count' => $_POST['low_level_count'] ?? 0,
            'medium_level_count' => $_POST['medium_level_count'] ?? 0,
            'high_level_count' => $_POST['high_level_count'] ?? 0,
            'status' => $_POST['status'] ?? 'active',
            'created_by' => $user_id
        ]);

        if ($result) {
            set_flash_message('success', 'Paper set created successfully!');
        } else {
            set_flash_message('error', 'Failed to create paper set.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            set_flash_message('error', 'Paper code already exists!');
        } else {
            set_flash_message('error', 'Error: ' . $e->getMessage());
        }
    }
}

header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
exit;
