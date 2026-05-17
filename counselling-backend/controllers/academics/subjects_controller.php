<?php
/**
 * Academic Subjects Controller
 * Lists all subjects from tbl_subjects
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/../common/db_connect.php';
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

try {
    // Fetch all active subjects
    $subjects = $dbOps->select('tbl_subjects', ['id', 'subject_name', 'subject_code'], ['status' => 'active'], 'subject_name ASC');

    if ($subjects === false) {
        $subjects = [];
    }

    sendSuccessResponse($subjects);
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
