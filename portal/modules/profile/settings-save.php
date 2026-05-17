<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once '../../common/settings_helper.php';

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $success = false;
    $message = "Invalid request";

    try {
        if ($category === 'general') {
            setSetting($conn, 'system_name', $_POST['system_name'] ?? '');
            setSetting($conn, 'organization_name', $_POST['organization_name'] ?? '');
            setSetting($conn, 'contact_email', $_POST['contact_email'] ?? '');
            setSetting($conn, 'contact_phone', $_POST['contact_phone'] ?? '');
            setSetting($conn, 'address', $_POST['address'] ?? '');
            setSetting($conn, 'maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');

            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(dirname(dirname(__DIR__))) . '/assets/img/logo/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = 'logo_' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                    setSetting($conn, 'logo_path', 'assets/img/logo/' . $filename);
                }
            }
            $success = true;
            $message = "General settings updated successfully";

        } elseif ($category === 'security') {
            setSetting($conn, 'password_min_length', $_POST['password_min_length'] ?? 8);
            setSetting($conn, 'password_require_uppercase', isset($_POST['password_require_uppercase']) ? '1' : '0');
            setSetting($conn, 'password_require_number', isset($_POST['password_require_number']) ? '1' : '0');
            setSetting($conn, 'password_require_special', isset($_POST['password_require_special']) ? '1' : '0');
            setSetting($conn, 'session_timeout', $_POST['session_timeout'] ?? 30);
            setSetting($conn, 'max_login_attempts', $_POST['max_login_attempts'] ?? 5);
            setSetting($conn, 'lockout_duration', $_POST['lockout_duration'] ?? 15);
            $success = true;
            $message = "Security settings updated successfully";

        } elseif ($category === 'academic') {
            setSetting($conn, 'default_pass_marks', $_POST['default_pass_marks'] ?? 40);
            
            // Grade boundaries - rebuild the array
            $grades = ['A+' => 'grade_aplus', 'A' => 'grade_a', 'B+' => 'grade_bplus', 'B' => 'grade_b', 'C' => 'grade_c', 'D' => 'grade_d', 'F' => 'grade_f'];
            $gradeBoundaries = [];
            foreach ($grades as $label => $postKey) {
                $gradeBoundaries[$label] = (int)($_POST[$postKey] ?? 0);
            }
            setSetting($conn, 'grade_boundaries', $gradeBoundaries);

            setSetting($conn, 'max_students_per_counsellor', $_POST['max_students_per_counsellor'] ?? 50);
            setSetting($conn, 'auto_assign_students', isset($_POST['auto_assign_students']) ? '1' : '0');
            setSetting($conn, 'result_display_format', $_POST['result_display_format'] ?? 'detailed');
            $success = true;
            $message = "Academic settings updated successfully";

        } elseif ($category === 'notification') {
            setSetting($conn, 'enable_email_notifications', isset($_POST['enable_email_notifications']) ? '1' : '0');
            setSetting($conn, 'enable_sms_notifications', isset($_POST['enable_sms_notifications']) ? '1' : '0');
            setSetting($conn, 'enable_whatsapp_notifications', isset($_POST['enable_whatsapp_notifications']) ? '1' : '0');
            setSetting($conn, 'notification_from_email', $_POST['notification_from_email'] ?? 'noreply@gyanmanjari.edu');
            setSetting($conn, 'notification_from_name', $_POST['notification_from_name'] ?? 'Gyan Manjari Portal');
            $success = true;
            $message = "Notification settings updated successfully";

        } elseif ($category === 'payment') {
            setSetting($conn, 'enable_online_payment', isset($_POST['enable_online_payment']) ? '1' : '0');
            setSetting($conn, 'payment_gateway', $_POST['payment_gateway'] ?? 'easebuzz');
            setSetting($conn, 'enable_partial_payment', isset($_POST['enable_partial_payment']) ? '1' : '0');
            setSetting($conn, 'late_fee_percentage', $_POST['late_fee_percentage'] ?? 2);
            setSetting($conn, 'grace_period_days', $_POST['grace_period_days'] ?? 7);
            setSetting($conn, 'gst_percentage', $_POST['gst_percentage'] ?? 18);
            $success = true;
            $message = "Payment settings updated successfully";
        }

        if ($success) {
            logAudit($conn, "Updated $category settings", "Settings", $message);
            $_SESSION['success_message'] = $message;
        } else {
            $_SESSION['error_message'] = $message;
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }

    header('Location: settings.php');
    exit;
} else {
    header('Location: settings.php');
    exit;
}
