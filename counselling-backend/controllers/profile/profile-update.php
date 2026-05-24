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
    $mob = trim($_POST['mob'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        // Update user profile
        $stmt = $conn->prepare("
            UPDATE tbl_users 
            SET phone = ?, address = ?
            WHERE id = ?
        ");

        $stmt->execute([$phone, $address, $user_id]);

        // Handle profile image upload if provided
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Update profile image in database
                    $stmt = $conn->prepare("UPDATE tbl_users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $user_id]);
                }
            }
        }

        sendSuccessResponse(['user_id' => $user_id], 'Profile updated successfully.');
    } catch (PDOException $e) {
        logDatabaseError($e, "Update Profile");
        sendErrorResponse('Failed to update profile. Please try again.', 500);
    }
} else {
    sendErrorResponse('Invalid request method', 405);
}
