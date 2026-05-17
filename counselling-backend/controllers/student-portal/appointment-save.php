<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$student_id = $is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null);

if (!$is_student_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $counsellor_id = intval($_POST['counsellor_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($counsellor_id) || empty($appointment_date) || empty($appointment_time) || empty($purpose)) {
        $_SESSION['error_message'] = 'All required fields must be filled.';
        header('Location: ' . BASE_URL . '/modules/student-portal/appointments.php');
        exit;
    }

    // Check if date is not in the past
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $_SESSION['error_message'] = 'Appointment date cannot be in the past.';
        header('Location: ' . BASE_URL . '/modules/student-portal/appointments.php');
        exit;
    }

    try {
        // Create appointments table if it doesn't exist (for compatibility)
        $conn->exec("CREATE TABLE IF NOT EXISTS tbl_appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            counsellor_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            purpose VARCHAR(255),
            notes TEXT,
            status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Insert appointment
        $stmt = $conn->prepare("
            INSERT INTO tbl_appointments 
            (student_id, counsellor_id, appointment_date, appointment_time, purpose, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");

        $result = $stmt->execute([
            $student_id,
            $counsellor_id,
            $appointment_date,
            $appointment_time,
            $purpose,
            $notes
        ]);

        if ($result) {
            $_SESSION['success_message'] = 'Appointment request submitted successfully. You will be notified once confirmed.';
            header('Location: ' . BASE_URL . '/modules/student-portal/my-appointments.php');
        } else {
            $_SESSION['error_message'] = 'Failed to book appointment. Please try again.';
            header('Location: ' . BASE_URL . '/modules/student-portal/appointments.php');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Book Appointment");
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        header('Location: ' . BASE_URL . '/modules/student-portal/appointments.php');
    }
    exit;
} else {
    header('Location: ' . BASE_URL . '/modules/student-portal/appointments.php');
    exit;
}
