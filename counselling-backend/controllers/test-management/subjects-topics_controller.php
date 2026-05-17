<?php
/**
 * Subject Topics Controller
 * Lists all topics for a specific subject
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/../common/db_connect.php';
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

$subject_id = $_GET['subject_id'] ?? $_POST['subject_id'] ?? null;

if (!$subject_id) {
    sendErrorResponse('Subject ID is required', 400);
}

try {
    // Fetch subject details
    $subject = $dbOps->customSelect(
        "SELECT * FROM tbl_subjects WHERE id = ?",
        [$subject_id],
    );

    if (!$subject) {
        sendErrorResponse('Subject not found', 404);
    }

    // Fetch topics for this subject
    $topics = $dbOps->customSelect(
        "SELECT t.*, 
                (SELECT COUNT(*) FROM tbl_blueprint_questions WHERE topic_id = t.id) as question_count
         FROM tbl_topics t 
         WHERE t.subject_id = ? 
         ORDER BY t.topic_name_english DESC",
        [$subject_id]
    );

    if ($topics === false) {
        $topics = [];
    }

    sendSuccessResponse([
        'subject' => $subject,
        'topics' => $topics
    ]);
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}

