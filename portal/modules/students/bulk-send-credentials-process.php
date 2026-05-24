<?php
/**
 * Bulk Send Credentials Processing Script
 */
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;
require_once __DIR__ . '/../../../common/helpers/email_functions.php'; // Corrected path to email functions

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbOps = new DatabaseOperations();
$student_ids = $_POST['student_ids'] ?? [];

if (empty($student_ids)) {
    set_flash_message('error', 'No students selected.');
    header('Location: students.php');
    exit;
}

$success_count = 0;
$fail_count = 0;
$total_count = count($student_ids);

// Portal URL for credentials
$portal_url = PORTAL_URL . '/modules/student-portal/student-login.php';

foreach ($student_ids as $id) {
    try {
        // Fetch student details
        $student = $dbOps->customSelect("SELECT id, student_name, surname, fathers_name, aadhaar, mob, email FROM tbl_gm_std_registration WHERE id = ?", [$id]);
        
        if (empty($student)) continue;
        $s = $student[0];
        
        if (empty($s['email'])) {
            $fail_count++;
            continue;
        }

        $full_name = trim(($s['surname'] ?? '') . ' ' . ($s['student_name'] ?? '') . ' ' . ($s['fathers_name'] ?? ''));
        
        // Prepare variables for email
        $variables = [
            'student_name' => $full_name,
            'aadhaar' => $s['aadhaar'],
            'password' => $s['mob'], // Default password is mobile number
            'portal_url' => $portal_url
        ];

        // Send email using the template
        $result = sendTemplateEmail($conn, $s['email'], $full_name, 'STUDENT_CREDENTIALS', $variables);

        if ($result['success']) {
            $success_count++;
        } else {
            $fail_count++;
        }
    } catch (Exception $e) {
        $fail_count++;
        logError("Error bulk sending credentials for ID $id: " . $e->getMessage());
    }
}

set_flash_message('success', "Bulk sending completed. Success: $success_count, Failed: $fail_count");
header('Location: students.php');
exit;
