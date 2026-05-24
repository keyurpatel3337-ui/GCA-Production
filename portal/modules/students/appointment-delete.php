<?php

/**
 * Appointment Delete Handler
 * Handles deleting appointments for counsellors
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Counsellor, Receptionist or Super Admin
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_RECEPTION)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: appointments.php');
    exit;
}

$appointment_id = $_POST['id'] ?? 0;

if (!$appointment_id) {
    set_flash_message('error', 'Invalid appointment ID');
    header('Location: appointments.php');
    exit;
}

$counsellor_id = $_SESSION['user_id'];

try {
    // Verify the appointment belongs to this counsellor before deleting (unless admin/reception)
    if (!hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
        $checkStmt = $conn->prepare("SELECT id FROM tbl_appointments WHERE id = ? AND counsellor_id = ?");
        $checkStmt->execute([$appointment_id, $counsellor_id]);

        if (!$checkStmt->fetch()) {
            set_flash_message('error', 'Appointment not found or unauthorized');
            header('Location: appointments.php');
            exit;
        }

        // Delete the appointment for specific counsellor
        $stmt = $conn->prepare("DELETE FROM tbl_appointments WHERE id = ? AND counsellor_id = ?");
        $stmt->execute([$appointment_id, $counsellor_id]);
    } else {
        // Reception/Admin can delete any appointment
        $stmt = $conn->prepare("DELETE FROM tbl_appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
    }

    set_flash_message('success', 'Appointment deleted successfully');
} catch (PDOException $e) {
    logError("Appointment Delete Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', 'An error occurred while deleting the appointment');
}

header('Location: appointments.php');
exit;
