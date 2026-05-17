<?php
/**
 * Subjects Controller
 * Lists all test subjects
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/../common/db_connect.php';
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

try {
    // Fetch all subjects
    $subjects = $dbOps->customSelect(
        "SELECT s.*, 
                (SELECT COUNT(*) FROM tbl_topics WHERE subject_id = s.id) as topic_count
         FROM tbl_subjects s 
         ORDER BY s.subject_name ASC"
    );

    if ($subjects === false) {
        $subjects = [];
    }

    sendSuccessResponse([
        'subjects' => $subjects
    ]);
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}

