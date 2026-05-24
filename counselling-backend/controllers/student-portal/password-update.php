<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
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
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash_message('error', 'All fields are required.');
        header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
        exit;
    }

    if ($new_password !== $confirm_password) {
        set_flash_message('error', 'New password and confirm password do not match.');
        header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
        exit;
    }

    if (strlen($new_password) < 6) {
        set_flash_message('error', 'Password must be at least 6 characters long.');
        header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
        exit;
    }

    try {
        // Get current password from database
        $stmt = $conn->prepare("SELECT password, hash_password FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            set_flash_message('error', 'Student not found.');
            header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
            exit;
        }

        // Verify current password
        $password_verified = false;
        if (!empty($student['hash_password'])) {
            // Check hashed password
            $password_verified = password_verify($current_password, $student['hash_password']);
        } elseif (!empty($student['password'])) {
            // Check plain text password
            $password_verified = ($current_password === $student['password']);
        }

        if (!$password_verified) {
            set_flash_message('error', 'Current password is incorrect.');
            header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
            exit;
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET hash_password = ?, password = ? WHERE id = ?");
        $stmt->execute([$new_hash, $new_password, $student_id]);

        set_flash_message('success', 'Password changed successfully.');
    } catch (PDOException $e) {
        logDatabaseError($e, "Change Password");
        set_flash_message('error', 'Failed to change password. Please try again.');
    }

    header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
    exit;
} else {
    header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
    exit;
}
