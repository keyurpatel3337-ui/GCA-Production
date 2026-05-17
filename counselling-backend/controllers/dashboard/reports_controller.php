<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
/**
 * Dashboard Reports Controller
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Check if user is Principle
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Reports";
$page_breadcrumb = [
    ['title' => 'Home', 'url' => 'dashboard.php'],
    ['title' => 'Reports', 'url' => '']
];
