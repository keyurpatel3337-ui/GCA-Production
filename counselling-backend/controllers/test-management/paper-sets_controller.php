<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Paper Sets Controller
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Manage Paper Sets";
$page_breadcrumb = "Paper Sets";

// Get all paper sets
try {
    $sql = "SELECT ps.*, u.name as creator_name 
            FROM tbl_paper_sets ps 
            LEFT JOIN tbl_users u ON ps.created_by = u.id 
            ORDER BY ps.id DESC";
    $paper_sets = $dbOps->customSelect($sql);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Paper Sets for Admin");
    $paper_sets = [];
}

