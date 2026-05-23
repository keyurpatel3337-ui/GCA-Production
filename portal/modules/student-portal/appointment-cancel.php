<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student or Parent
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent_login = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if (!$is_student_login && !$is_parent_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = $is_parent_login ? ($_SESSION['active_student_id'] ?? null) : ($is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null));

$appointment_id = intval($_POST['id'] ?? 0);

if ($appointment_id <= 0) {
    $_SESSION['error_message'] = 'Invalid appointment ID.';
    header('Location: my-appointments.php');
    exit;
}

try {
    // Verify the appointment belongs to this student
    $stmt = $conn->prepare("SELECT * FROM tbl_appointments WHERE id = ? AND student_id = ?");
    $stmt->execute([$appointment_id, $student_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $_SESSION['error_message'] = 'Appointment not found or you do not have permission to cancel it.';
        header('Location: my-appointments.php');
        exit;
    }

    // Check if appointment can be cancelled (not already cancelled or completed)
    if ($appointment['status'] == 'cancelled' || $appointment['status'] == 'completed') {
        $_SESSION['error_message'] = 'This appointment cannot be cancelled.';
        header('Location: my-appointments.php');
        exit;
    }

    // Update appointment status to cancelled
    $stmt = $conn->prepare("UPDATE tbl_appointments SET status = 'cancelled' WHERE id = ?");
    $result = $stmt->execute([$appointment_id]);

    if ($result) {
        $_SESSION['success_message'] = 'Appointment cancelled successfully.';
    } else {
        $_SESSION['error_message'] = 'Failed to cancel appointment. Please try again.';
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Cancel Appointment");
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
}

header('Location: my-appointments.php');
exit;
