<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Initialize Operation class
$dbOps = new Operation();

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_POST['student_id'] ?? null;
$counsellor_id = $_POST['counsellor_id'] ?? null;
$action = $_POST['action'] ?? 'assign';

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Verify student exists
    $student = $dbOps->selectOne('tbl_gm_std_registration', ['id', 'surname', 'student_name'], ['id' => $student_id]);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    if ($action === 'remove' || empty($counsellor_id)) {
        // Remove counsellor assignment
        $dbOps->update('tbl_gm_std_registration', [
            'counsellor_id' => null,
            'updated_at' => 'NOW()'
        ], ['id' => $student_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Counsellor assignment removed successfully'
        ]);
    } else {
        // Verify counsellor exists
        $counsellor = $dbOps->selectOne('tbl_users', ['id', 'name'], [
            'id' => $counsellor_id,
            'role_id' => ROLE_COUNSELLOR,
            'status' => 'active'
        ]);

        if (!$counsellor) {
            echo json_encode(['success' => false, 'message' => 'Invalid counsellor selected']);
            exit;
        }

        // Update counsellor assignment
        $dbOps->update('tbl_gm_std_registration', [
            'counsellor_id' => $counsellor_id,
            'updated_at' => 'NOW()'
        ], ['id' => $student_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Counsellor ' . $counsellor['name'] . ' assigned successfully'
        ]);
    }
} catch (PDOException $e) {
    logDatabaseError($e, "AJAX Counsellor Assignment");
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
