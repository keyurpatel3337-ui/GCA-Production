<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aadhaar = trim($_POST['aadhaar'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    // Validate fields
    if (empty($aadhaar) || empty($password) || empty($captcha)) {
        logError("Final exam login failed - Missing fields | Aadhaar: " . ($aadhaar ?: 'empty') . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        set_flash_message('error', 'Please fill in all fields.');
        header('Location: final-exam-login.php');
        exit;
    }

    // Validate captcha
    if (!isset($_SESSION['captcha']) || strtoupper($captcha) !== strtoupper($_SESSION['captcha'])) {
        logError("Final exam login failed - Invalid captcha | Aadhaar: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        set_flash_message('error', 'Invalid security verification code. Please try again.');
        header('Location: final-exam-login.php');
        exit;
    }

    // Clear captcha
    unset($_SESSION['captcha']);

    // Validate Aadhaar/Mobile format
    if (!preg_match('/^[0-9]{10,12}$/', $aadhaar)) {
        logError("Final exam login failed - Invalid format | Identifier: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        set_flash_message('error', 'Invalid format. Please enter 10 digits (Mobile) or 12 digits (Aadhaar).');
        header('Location: final-exam-login.php');
        exit;
    }

    try {
        // Query student registration
        $stmt = $conn->prepare("
            SELECT r.id, r.student_name, r.surname, r.fathers_name, r.mob, r.aadhaar, r.password, 
                   r.admission_confirmed, r.standard, r.course_id
            FROM tbl_gm_std_registration r
            WHERE r.aadhaar = :aadhaar OR r.mob = :mob
        ");
        $stmt->execute([
            'aadhaar' => $aadhaar,
            'mob' => $aadhaar
        ]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            logError("Final exam login failed - Invalid credentials | Identifier: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            set_flash_message('error', 'Invalid Aadhaar/Mobile number or password.');
            header('Location: final-exam-login.php');
            exit;
        }

        // Check admission confirmation
        if (!$student['admission_confirmed']) {
            logError("Final exam login failed - Admission not confirmed | Student ID: {$student['id']} | Aadhaar: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            set_flash_message('error', 'Your admission is not confirmed yet. Please contact the administration.');
            header('Location: final-exam-login.php');
            exit;
        }

        // Password validation
        $passwordValid = false;
        if ($password === $student['mob']) {
            $passwordValid = true;
        } elseif (!empty($student['password']) && password_verify($password, $student['password'])) {
            $passwordValid = true;
        }

        if (!$passwordValid) {
            logError("Final exam login failed - Invalid password | Aadhaar: $aadhaar | Student ID: {$student['id']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            set_flash_message('error', 'Invalid Aadhaar number or password.');
            header('Location: final-exam-login.php');
            exit;
        }

        // Restrict standard 11 or course 1, 2
        if ($student['standard'] == 11 || $student['course_id'] == 1 || $student['course_id'] == 2) {
            logError("Final exam login restricted - Disallowed standard/course | Student ID: {$student['id']} | Aadhaar: $aadhaar | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            set_flash_message('error', 'Login is currently restricted for your standard or course.');
            header('Location: final-exam-login.php');
            exit;
        }

        // Clean out standard student or administrative session variables to prevent authorization leak
        $prev_session = $_SESSION;
        session_unset();

        // Restore baseline flash messages or generic system settings if needed
        if (isset($prev_session['flash_messages'])) {
            $_SESSION['flash_messages'] = $prev_session['flash_messages'];
        }

        // Set secure Final Exam isolated session variables
        $_SESSION['final_student_id'] = $student['id'];
        $_SESSION['student_id'] = $student['id']; // Satisfies globalvariable.php logged-in check without granting standard menu permissions
        $_SESSION['final_student_name'] = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name']);
        $_SESSION['final_student_mobile'] = $student['mob'];
        $_SESSION['final_student_aadhaar'] = $student['aadhaar'];
        $_SESSION['is_final_exam_login'] = true;
        $_SESSION['login_time'] = time();

        logAuthSuccess("Final Exam Terminal Login Success | Student ID: {$student['id']} | Name: {$_SESSION['final_student_name']} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        // Redirect to dedicated distraction-free final exam portal
        header('Location: final-exams.php');
        exit;
    } catch (PDOException $e) {
        logError("Final exam login error - Database exception | Error: " . $e->getMessage() . " | Aadhaar: $aadhaar");
        set_flash_message('error', 'An error occurred. Please try again later.');
        header('Location: final-exam-login.php');
        exit;
    }
} else {
    header('Location: final-exam-login.php');
    exit;
}
