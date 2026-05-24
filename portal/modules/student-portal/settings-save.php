<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

// Check if user is Student (student-specific login)
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: student-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $result_notifications = isset($_POST['result_notifications']) ? 1 : 0;
    $appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
    $show_profile = isset($_POST['show_profile']) ? 1 : 0;
    $share_results = isset($_POST['share_results']) ? 1 : 0;

    try {
        $student_id = $_SESSION['student_id'] ?? null;
        
        if ($student_id) {
            // Update settings in database
            $stmt = $conn->prepare("UPDATE tbl_students_portals_settings SET 
                email_notifications = ?, 
                result_notifications = ?, 
                appointment_reminders = ?, 
                show_profile_to_counsellors = ?, 
                share_test_results = ?,
                updated_at = NOW()
                WHERE student_id = ?");
            
            $stmt->execute([
                $email_notifications,
                $result_notifications,
                $appointment_reminders,
                $show_profile,
                $share_results,
                $student_id
            ]);

            // If no row exists, insert it (simple upsert logic depends on DB version, using conventional check here)
            if ($stmt->rowCount() === 0) {
              $check = $conn->prepare("SELECT id FROM tbl_students_portals_settings WHERE student_id = ?");
              $check->execute([$student_id]);
              if (!$check->fetch()) {
                $ins = $conn->prepare("INSERT INTO tbl_students_portals_settings 
                  (student_id, email_notifications, result_notifications, appointment_reminders, show_profile_to_counsellors, share_test_results, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([
                  $student_id,
                  $email_notifications,
                  $result_notifications,
                  $appointment_reminders,
                  $show_profile,
                  $share_results
                ]);
              }
            }
        }

        $_SESSION['success_msg'] = 'Settings updated successfully.';
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'Failed to save settings: ' . $e->getMessage();
    }

    header('Location: settings.php');
    exit;
} else {
    header('Location: settings.php');
    exit;
}
