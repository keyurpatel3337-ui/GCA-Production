<?php

/**
 * Notification Variable Helper
 * Prepares variables for different notification types
 */

require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

/**
 * Get student notification variables
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID (enrollment_id for enrolled students, id for registration)
 * @param string $notification_type Type of notification
 * @return array Variables array
 */
function getStudentNotificationVariables($conn, $student_id, $notification_type, $additional_data = [])
{
    // Determine which table to use based on notification type
    // Pre-admission: tbl_gm_std_registration
    // Post-admission: tbl_enrolled_students
    $pre_admission_notifications = ['registration_success', 'admission_confirmed'];
    $use_registration_table = in_array($notification_type, $pre_admission_notifications);

    if ($use_registration_table) {
        // Get registration details (before admission)
        $stmt = $conn->prepare("
            SELECT 
                s.*,
                c.course_name,
                u.name as counsellor_name,
                u.email as counsellor_email
            FROM tbl_gm_std_registration s
            LEFT JOIN tbl_courses c ON s.course_id = c.id
            LEFT JOIN tbl_users u ON s.counsellor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Get enrolled student details (after admission)
        $stmt = $conn->prepare("
            SELECT 
                e.*,
                s.surname,
                s.student_name,
                s.fathers_name,
                s.mob,
                s.email,
                s.aadhaar,
                c.course_name,
                u.name as counsellor_name,
                u.email as counsellor_email
            FROM tbl_enrolled_students e
            INNER JOIN tbl_gm_std_registration s ON e.registration_id = s.id
            LEFT JOIN tbl_courses c ON e.course_id = c.id
            LEFT JOIN tbl_users u ON s.counsellor_id = u.id
            WHERE e.enrollment_id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$student) {
        return [];
    }

    // Base variables available for all notifications
    $variables = [
        'student_name' => trim($student['surname'] . ' ' . $student['student_name']),
        'student_mobile' => $student['mob'],
        'student_email' => $student['email'] ?? '',
        'course_name' => $student['course_name'] ?? '',
        'registration_id' => $use_registration_table ? 'GM' . str_pad($student['id'], 6, '0', STR_PAD_LEFT) : ($student['enrollment_no'] ?? 'N/A'),
        'registration_date' => date('d-M-Y', strtotime($student['created_at'] ?? $student['enrollment_date'])),
        'counsellor_name' => $student['counsellor_name'] ?? 'Support Team',
        'portal_url' => defined('PORTAL_URL') ? PORTAL_URL : (defined('BASE_URL') ? BASE_URL . '/portal' : '')
    ];

    // Add enrollment-specific variables for enrolled students
    if (!$use_registration_table) {
        $variables['enrollment_no'] = $student['enrollment_no'] ?? 'N/A';
        $variables['enrollment_date'] = date('d-M-Y', strtotime($student['enrollment_date']));
        $variables['roll_no'] = $student['roll_no'] ?? 'N/A';
    }

    // Add notification-specific variables
    switch ($notification_type) {
        case 'registration_success':
            // Already have all required variables
            break;

        case 'admission_confirmed':
            $variables['admission_letter_no'] = $additional_data['admission_letter_no'] ?? 'N/A';
            $variables['admission_no'] = $student['admission_no'] ?? 'N/A';
            $variables['session'] = $additional_data['session'] ?? '2025-26';
            $variables['token_amount'] = formatIndianCurrency($additional_data['token_amount'] ?? 0);
            $variables['scholarship'] = $additional_data['scholarship'] ?? 'None';
            $variables['payment_url'] = (defined('PORTAL_URL') ? PORTAL_URL : (defined('BASE_URL') ? BASE_URL . '/portal' : '')) . '/modules/student-portal/payment';
            break;

        case 'login_credentials':
            $variables['username'] = $additional_data['username'] ?? ($student['email'] ?? $student['aadhaar']);
            $variables['password'] = $additional_data['password'] ?? '********';
            break;

        case 'token_fee_success':
        case 'fee_payment_success':
            $variables['receipt_no'] = $additional_data['receipt_no'] ?? 'N/A';
            $variables['amount'] = formatIndianCurrency($additional_data['amount'] ?? 0);
            $variables['payment_date'] = date('d-M-Y', strtotime($additional_data['payment_date'] ?? 'now'));
            $variables['payment_mode'] = $additional_data['payment_mode'] ?? 'Online';
            $variables['transaction_id'] = $additional_data['transaction_id'] ?? 'N/A';
            $variables['receipt_url'] = (defined('PORTAL_URL') ? PORTAL_URL : (defined('BASE_URL') ? BASE_URL . '/portal' : '')) . '/modules/student-portal/receipts/' . ($additional_data['receipt_id'] ?? '');
            break;

        case 'fee_reminder':
            $variables['due_amount'] = formatIndianCurrency($additional_data['due_amount'] ?? 0);
            $variables['due_date'] = date('d-M-Y', strtotime($additional_data['due_date'] ?? '+7 days'));
            $variables['payment_url'] = (defined('PORTAL_URL') ? PORTAL_URL : (defined('BASE_URL') ? BASE_URL . '/portal' : '')) . '/modules/student-portal/payment';
            break;
    }

    return $variables;
}

/**
 * Get staff notification variables
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Related student ID (enrollment_id for enrolled, id for registration)
 * @param string $notification_type Type of notification
 * @param array $additional_data Additional data
 * @return array Variables array
 */
function getStaffNotificationVariables($conn, $student_id, $notification_type, $additional_data = [])
{
    // Staff notifications about registration use registration table
    // Staff notifications about payments/fees use enrollment table
    $use_registration_table = ($notification_type === 'new_registration');

    if ($use_registration_table) {
        // Get registration details
        $stmt = $conn->prepare("
            SELECT 
                s.*,
                c.course_name
            FROM tbl_gm_std_registration s
            LEFT JOIN tbl_courses c ON s.course_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Get enrolled student details
        $stmt = $conn->prepare("
            SELECT 
                e.*,
                s.surname,
                s.student_name,
                s.mob,
                s.email,
                c.course_name
            FROM tbl_enrolled_students e
            INNER JOIN tbl_gm_std_registration s ON e.registration_id = s.id
            LEFT JOIN tbl_courses c ON e.course_id = c.id
            WHERE e.enrollment_id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$student) {
        return [];
    }

    // Base variables
    $variables = [
        'student_name' => trim($student['surname'] . ' ' . $student['student_name']),
        'student_mobile' => $student['mob'],
        'student_email' => $student['email'] ?? '',
        'course_name' => $student['course_name'] ?? '',
        'registration_id' => $use_registration_table ? 'GM' . str_pad($student['id'], 6, '0', STR_PAD_LEFT) : ($student['enrollment_no'] ?? 'N/A'),
        'registration_date' => date('d-M-Y', strtotime($student['created_at'] ?? $student['enrollment_date'])),
        'view_url' => (defined('PORTAL_URL') ? PORTAL_URL : (defined('BASE_URL') ? BASE_URL . '/portal' : '')) . '/modules/students/view.php?id=' . $student_id
    ];

    // Add enrollment number for enrolled students
    if (!$use_registration_table) {
        $variables['enrollment_no'] = $student['enrollment_no'] ?? 'N/A';
        $variables['enrollment_id'] = $student['enrollment_id'];
    }

    // Add notification-specific variables
    switch ($notification_type) {
        case 'new_registration':
            $variables['counsellor_name'] = $additional_data['counsellor_name'] ?? 'Counsellor';
            break;

        case 'token_payment':
            $variables['admission_no'] = $student['admission_no'] ?? 'N/A';
            $variables['receipt_no'] = $additional_data['receipt_no'] ?? 'N/A';
            $variables['amount'] = formatIndianCurrency($additional_data['amount'] ?? 0);
            $variables['payment_mode'] = $additional_data['payment_mode'] ?? 'Online';
            $variables['transaction_id'] = $additional_data['transaction_id'] ?? 'N/A';
            $variables['payment_date'] = date('d-M-Y', strtotime($additional_data['payment_date'] ?? 'now'));
            break;
    }

    return $variables;
}

/**
 * Send notification with auto-populated variables
 * 
 * @param PDO $conn Database connection
 * @param string $notification_type Notification type
 * @param int $student_id Student ID (enrollment_id for enrolled, id for registration)
 * @param array $additional_data Additional data for variables
 * @param array $options Additional options
 * @return array Results
 */
function sendStudentNotification($conn, $notification_type, $student_id, $additional_data = [], $options = [])
{
    // Determine which table to query
    $pre_admission_notifications = ['registration_success', 'admission_confirmed'];
    $use_registration_table = in_array($notification_type, $pre_admission_notifications);

    if ($use_registration_table) {
        // Get registration student contact info
        $stmt = $conn->prepare("SELECT surname, student_name, mob, email FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Get enrolled student contact info
        $stmt = $conn->prepare("
            SELECT s.surname, s.student_name, s.mob, s.email 
            FROM tbl_enrolled_students e
            INNER JOIN tbl_gm_std_registration s ON e.registration_id = s.id
            WHERE e.enrollment_id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$student) {
        return ['success' => false, 'error' => 'Student not found'];
    }

    // Get notification variables
    $variables = getStudentNotificationVariables($conn, $student_id, $notification_type, $additional_data);

    // Prepare recipient info
    $recipient = [
        'name' => trim($student['surname'] . ' ' . $student['student_name']),
        'mobile' => $student['mob'],
        'email' => $student['email'] ?? ''
    ];

    // Add student_id to options
    $options['student_id'] = $student_id;
    $options['reference_type'] = $options['reference_type'] ?? ($use_registration_table ? 'registration' : 'enrollment');
    $options['reference_id'] = $options['reference_id'] ?? $student_id;

    // Send notification
    return sendNotification($conn, $notification_type, $recipient, $variables, $options);
}
