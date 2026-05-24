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
    // Get form data
    $mob = trim($_POST['mob'] ?? '');
    $amob = trim($_POST['amob'] ?? '');
    $addr = trim($_POST['addr'] ?? '');
    $district = trim($_POST['district'] ?? '');

    try {
        // Update student profile
        $stmt = $conn->prepare("
            UPDATE tbl_gm_std_registration 
            SET mob = ?, amob = ?, addr = ?, district = ?
            WHERE id = ?
        ");

        $stmt->execute([$mob, $amob, $addr, $district, $student_id]);

        // Handle profile image upload if provided
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Update profile image in database if you have such column
                    // For now, we'll just save the filename
                }
            }
        }

        $_SESSION['success_message'] = 'Profile updated successfully.';
    } catch (PDOException $e) {
        logDatabaseError($e, "Update Profile");
        $_SESSION['error_message'] = 'Failed to update profile. Please try again.';
    }

    header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
    exit;
} else {
    header('Location: ' . BASE_URL . '/modules/student-portal/profile.php');
    exit;
}
