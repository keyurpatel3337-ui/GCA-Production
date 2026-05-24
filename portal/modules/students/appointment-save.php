<?php
/**
 * Appointment Save Handler
 * Handles creating and updating appointments for counsellors
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check access (Counsellor, Principal, Reception, Super Admin)
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: appointments.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method');
    header('Location: appointments.php');
    exit;
}

// Determine the counsellor/staff ID
// If Reception/Admin, use staff_id from POST, otherwise use the session user_id
$counsellor_id = $_SESSION['user_id'];
if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN)) {
    if (isset($_POST['staff_id'])) {
        $counsellor_id = (int) $_POST['staff_id'];
    }
}

$appointment_id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : null;
$appointment_date = trim($_POST['appointment_date'] ?? '');
$appointment_time = trim($_POST['appointment_time'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$status = trim($_POST['status'] ?? 'pending');

// Validation
if (empty($appointment_date) || empty($appointment_time)) {
    set_flash_message('error', 'Date and time are required');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'appointments.php'));
    exit;
}

try {
    if ($appointment_id) {
        // Update existing appointment
        // First verify the appointment belongs to this counsellor (unless admin/reception)
        if (!hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
            $checkStmt = $conn->prepare("SELECT id FROM tbl_appointments WHERE id = ? AND counsellor_id = ?");
            $checkStmt->execute([$appointment_id, $_SESSION['user_id']]);

            if (!$checkStmt->fetch()) {
                set_flash_message('error', 'Appointment not found or unauthorized');
                header('Location: appointments.php');
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE tbl_appointments SET 
            appointment_date = ?, 
            appointment_time = ?, 
            notes = ?,
            status = ?,
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$appointment_date, $appointment_time, $notes, $status, $appointment_id]);

        set_flash_message('success', 'Appointment updated successfully');
    } else {
        // Create new appointment
        if (!$student_id) {
            set_flash_message('error', 'Student is required');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'appointments.php'));
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO tbl_appointments 
            (student_id, counsellor_id, appointment_date, appointment_time, notes, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$student_id, $counsellor_id, $appointment_date, $appointment_time, $notes, $status]);

        set_flash_message('success', 'Appointment scheduled successfully');
    }
} catch (PDOException $e) {
    logError("Appointment Save Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', 'An error occurred while saving the appointment: ' . $e->getMessage());
}

header('Location: appointments.php');
exit;

