<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('Unauthorized access', 401);
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        sendErrorResponse('All fields are required.', 400);
    }

    if ($new_password !== $confirm_password) {
        sendErrorResponse('New password and confirm password do not match.', 400);
    }

    if (strlen($new_password) < 6) {
        sendErrorResponse('Password must be at least 6 characters long.', 400);
    }

    try {
        // Get current password from database
        $stmt = $conn->prepare("SELECT password FROM tbl_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendErrorResponse('User not found.', 404);
        }

        // Verify current password (assuming passwords are hashed with bcrypt)
        if (!password_verify($current_password, $user['password'])) {
            // Try plain text comparison as fallback
            if ($current_password !== $user['password']) {
                sendErrorResponse('Current password is incorrect.', 400);
            }
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE tbl_users SET password = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);

        sendSuccessResponse(['user_id' => $user_id], 'Password changed successfully.');
    } catch (PDOException $e) {
        logDatabaseError($e, "Change Password");
        sendErrorResponse('Failed to change password. Please try again.', 500);
    }
} else {
    sendErrorResponse('Invalid request method', 405);
}
