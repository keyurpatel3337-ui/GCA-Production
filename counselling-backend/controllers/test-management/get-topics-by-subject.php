<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$subject_id = $_GET['subject_id'] ?? 0;

header('Content-Type: application/json');

try {
    $topics = $dbOps->select(
        'tbl_topics',
        ['id', 'topic_name_english', 'topic_name_gujarati'],
        ['subject_id' => $subject_id, 'status' => 'active'],
        'topic_name_english'
    );

    echo json_encode([
        'success' => true,
        'topics' => $topics
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
