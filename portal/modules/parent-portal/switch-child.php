<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';

if (!isset($_SESSION['is_parent_login']) || $_SESSION['is_parent_login'] !== true) {
    header('Location: ../../parent-login.php');
    exit;
}

if (isset($_GET['student_id'])) {
    $requested_id = (int) $_GET['student_id'];
    $is_valid_child = false;

    foreach ($_SESSION['children'] as $child) {
        if ((int) $child['id'] === $requested_id) {
            $is_valid_child = true;
            break;
        }
    }

    if ($is_valid_child) {
        $_SESSION['active_student_id'] = $requested_id;
        $_SESSION['student_id'] = $requested_id; // Sync student_id for child modules
        set_flash_message('success', 'Switched to child: ' . $child['student_name']);
    } else {
        set_flash_message('error', 'Invalid child selection.');
    }
}

header('Location: dashboard.php');
exit;
