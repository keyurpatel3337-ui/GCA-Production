<?php

/**
 * Counsellor Assignment Process Handler
 * Handles assigning/removing counsellor for a student
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !isset($input['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$student_id = intval($input['student_id']);
$counsellor_id = isset($input['counsellor_id']) && $input['counsellor_id'] !== '' ? intval($input['counsellor_id']) : null;
$action = $input['action'] ?? 'assign';

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

try {
    $op = new Operation();

    // Verify student exists
    $student = $op->selectOne('tbl_gm_std_registration', ['*'], ['id' => $student_id]);
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // If assigning, verify counsellor exists
    if ($action === 'assign' && $counsellor_id) {
        $counsellor = $op->selectOne('tbl_users', ['*'], ['id' => $counsellor_id, 'role_id' => ROLE_COUNSELLOR]);
        if (!$counsellor) {
            echo json_encode(['success' => false, 'message' => 'Invalid counsellor']);
            exit;
        }
    }

    // Update student's counsellor assignment
    if ($action === 'remove') {
        $op->update('tbl_gm_std_registration', ['counsellor_id' => null], ['id' => $student_id]);
        $message = 'Counsellor removed successfully';
    } else {
        $op->update('tbl_gm_std_registration', ['counsellor_id' => $counsellor_id], ['id' => $student_id]);
        $message = 'Counsellor assigned successfully';
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    error_log("Counsellor assignment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
