<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

$error = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aadhaar = trim($_POST['aadhaar'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    // Validate all fields are present
    if (empty($aadhaar) || empty($password) || empty($captcha)) {
        logError("Student login failed - Missing fields | Aadhaar: " . ($aadhaar ?: 'empty') . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        set_flash_message('error', 'Please fill in all fields.');
        header('Location: student-login.php');
        exit;
    }

    // Validate captcha
    if (!isset($_SESSION['captcha']) || strtoupper($captcha) !== strtoupper($_SESSION['captcha'])) {
        logError("Student login failed - Invalid captcha | Aadhaar: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        set_flash_message('error', 'Invalid captcha code. Please try again.');
        header('Location: student-login.php');
        exit;
    }

    // Clear captcha after validation
    unset($_SESSION['captcha']);

    // Validate Aadhaar/Mobile format (10 or 12 digits)
    if (!preg_match('/^[0-9]{10,12}$/', $aadhaar)) {
        logError("Student login failed - Invalid format | Identifier: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        set_flash_message('error', 'Invalid format. Please enter 10 digits (Mobile) or 12 digits (Aadhaar).');
        header('Location: student-login.php');
        exit;
    }

    try {
        // Query to fetch student by Aadhaar or Mobile
        $stmt = $conn->prepare("
            SELECT r.id, r.student_name, r.surname, r.fathers_name, r.mob, r.aadhaar, r.password, 
                   r.admission_confirmed, r.standard, r.course_id, 
                   CASE WHEN p_paid.id IS NOT NULL OR r.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid, 
                   COALESCE(p_paid.payment_mode, p_pend.payment_mode, 'online') as token_payment_mode, 
                   COALESCE(p_paid.amount, p_pend.amount, 0) as token_amount
            FROM tbl_gm_std_registration r
            LEFT JOIN tbl_payments p_paid ON r.id = p_paid.student_id 
                AND (p_paid.payment_type = 'token_fee' OR p_paid.fee_component = 'tuition_fee_part1') 
                AND p_paid.status = 'paid'
            LEFT JOIN tbl_payments p_pend ON r.id = p_pend.student_id 
                AND (p_pend.payment_type = 'token_fee' OR p_pend.fee_component = 'tuition_fee_part1') 
                AND p_pend.status = 'pending'
            WHERE r.aadhaar = :aadhaar OR r.mob = :mob
        ");
        $stmt->execute([
            'aadhaar' => $aadhaar,
            'mob' => $aadhaar
        ]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            // Log failed login attempt
            logError("Student login failed - Invalid credentials | Identifier: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            set_flash_message('error', 'Invalid Aadhaar/Mobile number or password.');
            header('Location: student-login.php');
            exit;
        }

        // Check if admission is confirmed
        if (!$student['admission_confirmed']) {
            logError("Student login failed - Admission not confirmed | Student ID: {$student['id']} | Aadhaar: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            set_flash_message('error', 'Your admission is not confirmed yet. Please contact the counselling office.');
            header('Location: student-login.php');
            exit;
        }

        // Verify password - check mobile number first (for backward compatibility), then hashed password
        $passwordValid = false;

        // Try mobile number match (backward compatibility)
        if ($password === $student['mob']) {
            $passwordValid = true;
        }
        // Try hashed password if exists
        elseif (!empty($student['password']) && password_verify($password, $student['password'])) {
            $passwordValid = true;
        }

        if (!$passwordValid) {
            logError("Student login failed - Invalid password | Aadhaar: $aadhaar | Student ID: {$student['id']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            set_flash_message('error', 'Invalid Aadhaar number or password.');
            header('Location: student-login.php');
            exit;
        }

        // Restriction: Do not allow login for standard 11 or course id 1, 2
        if ($student['standard'] == 11 || $student['course_id'] == 1 || $student['course_id'] == 2) {
            logError("Student login restricted - Disallowed standard/course | Student ID: {$student['id']} | Aadhaar: $aadhaar | Standard: {$student['standard']} | Course ID: {$student['course_id']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            set_flash_message('error', 'Login is currently restricted for your standard or course. Please contact the administration.');
            header('Location: student-login.php');
            exit;
        }

        // Login successful - Set session variables
        $_SESSION['student_id'] = $student['id'];
        $_SESSION['student_name'] = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name']);
        $_SESSION['full_name'] = $_SESSION['student_name']; // For sidebar compatibility
        $_SESSION['student_mobile'] = $student['mob'];
        $_SESSION['student_aadhaar'] = $student['aadhaar'];
        $_SESSION['user_role'] = 'student';
        $_SESSION['is_student_login'] = true;
        $_SESSION['login_time'] = time();

        // Log successful login
        $logMessage = "Student login successful | Student ID: {$student['id']} | Name: {$_SESSION['student_name']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        logError($logMessage);

        // Additional logging to student-login.log as requested
        $studentLogFile = LOGS_PATH . 'student-login.log';
        $studentLogEntry = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Student ID: {$student['id']} | Name: {$_SESSION['student_name']} | Aadhaar: {$student['aadhaar']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        @file_put_contents($studentLogFile, $studentLogEntry, FILE_APPEND);

        // Update last login time (if column exists)
        try {
            $updateLoginStmt = $conn->prepare("UPDATE tbl_gm_std_registration SET last_login = NOW() WHERE id = :id");
            $updateLoginStmt->execute(['id' => $student['id']]);
        } catch (PDOException $e) {
            // Column may not exist, ignore error
        }

        // Check token payment status - CRITICAL WORKFLOW
        // Bypass token fee check for Re-NEET students (course_id = 6)
        if (!$student['token_fees_paid'] && $student['course_id'] != 6) {
            if ($student['token_payment_mode'] === 'online') {
                // Online payment - redirect to payment page
                $_SESSION['info_msg'] = 'Please complete your token fee payment to activate your account.';
                $_SESSION['token_payment_pending'] = true;
                header('Location: token-fee-payment.php');
                exit;
            } elseif ($student['token_payment_mode'] === 'offline') {
                // Offline payment pending verification
                set_flash_message('warning', 'Your offline token fee payment is being verified by the accounts department. You have limited access until verification is complete.');
                $_SESSION['token_payment_pending'] = true;
                header('Location: ../dashboard/student_dashboard.php');
                exit;
            } else {
                // No payment mode set or 'pending' - Allow access with warning
                set_flash_message('warning', 'Token fee payment is pending. Please visit the accounts department or proceed with online payment.');
                $_SESSION['token_payment_pending'] = true;
                header('Location: ../dashboard/student_dashboard.php');
                exit;
            }
        }

        // Grant access for Re-NEET or students who paid token fees
        $_SESSION['token_payment_verified'] = ($student['course_id'] == 6) ? true : $student['token_fees_paid'];
        header('Location: ../dashboard/student_dashboard.php');
        exit;
    } catch (PDOException $e) {
        logError("Student login error - Database exception | Error: " . $e->getMessage() . " | Aadhaar: $aadhaar");

        set_flash_message('error', 'An error occurred. Please try again later.');
        header('Location: student-login.php');
        exit;
    }
} else {
    // Direct access not allowed
    header('Location: student-login.php');
    exit;
}
