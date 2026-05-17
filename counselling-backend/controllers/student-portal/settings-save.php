<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student (either regular login or student-specific login)
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$student_id = $is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null);

if (!$is_student_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $theme = $_POST['theme'] ?? 'light';
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $email_updates = isset($_POST['email_updates']) ? 1 : 0;

    try {
        // Update settings in database (you may need to create a settings table)
        // For now, we'll just update session
        $_SESSION['theme'] = $theme;
        $_SESSION['notifications'] = $notifications;
        $_SESSION['email_updates'] = $email_updates;

        // Optionally save to database
        // Check if settings table exists and update accordingly

        $_SESSION['success_message'] = 'Settings updated successfully.';
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Settings");
        $_SESSION['error_message'] = 'Failed to save settings. Please try again.';
    }

    header('Location: ' . BASE_URL . '/modules/student-portal/settings.php');
    exit;
} else {
    header('Location: ' . BASE_URL . '/modules/student-portal/settings.php');
    exit;
}
