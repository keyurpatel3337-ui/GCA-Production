<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Counsellor, Receptionist or Super Admin
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_RECEPTION)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
$appointment_id = intval($_POST['appointment_id'] ?? 0);
$status = $_POST['status'] ?? null;
$counsellor_id = $_SESSION['user_id'];

// Validation
if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit;
}

$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Verify appointment belongs to this counsellor (unless admin/reception)
    if (!hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
        $stmt = $conn->prepare("SELECT id FROM tbl_appointments WHERE id = ? AND counsellor_id = ?");
        $stmt->execute([$appointment_id, $counsellor_id]);

        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found or unauthorized']);
            exit;
        }

        // Update appointment status for specific counsellor
        $stmt = $conn->prepare("UPDATE tbl_appointments 
                               SET status = ?, updated_at = NOW() 
                               WHERE id = ? AND counsellor_id = ?");
        $stmt->execute([$status, $appointment_id, $counsellor_id]);
    } else {
        // Reception/Admin can update any appointment status
        $stmt = $conn->prepare("UPDATE tbl_appointments 
                               SET status = ?, updated_at = NOW() 
                               WHERE id = ?");
        $stmt->execute([$status, $appointment_id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment status updated to ' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
    ]);
} catch (PDOException $e) {
    logDatabaseError($e, "Update Appointment Status");
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
