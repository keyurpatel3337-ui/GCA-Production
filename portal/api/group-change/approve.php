<?php

/**
 * API Endpoint: Approve Group Change Request
 * Processes approval and updates student group + fee allocation
 */

header('Content-Type: application/json');
session_start();

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Principal or Admin
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden - Insufficient permissions']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

// Validate required fields
$request_id = intval($input['request_id'] ?? 0);
$remarks = trim($input['remarks'] ?? '');

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    // Get request details
    $stmt = $conn->prepare("SELECT * FROM tbl_group_change_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    if ($request['status'] === 'approved') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Request already approved']);
        exit;
    }

    if ($request['status'] === 'rejected') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cannot approve rejected request']);
        exit;
    }

    // Begin transaction
    $conn->beginTransaction();

    // Update request status
    $stmt = $conn->prepare("UPDATE tbl_group_change_requests 
                            SET status = 'approved',
                                reviewed_by = ?,
                                review_date = NOW(),
                                review_comments = ?
                            WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $remarks, $request_id]);

    // Update student's group in registration table
    $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                            SET group_id = ? WHERE id = ?");
    $stmt->execute([$request['requested_group_id'], $request['student_id']]);

    // Update enrolled student record
    if ($request['enrollment_id']) {
        $stmt = $conn->prepare("UPDATE tbl_enrolled_students 
                                SET group_id = ? WHERE enrollment_id = ?");
        $stmt->execute([$request['requested_group_id'], $request['enrollment_id']]);
    }

    // Update fee allocation if fee config changed
    if ($request['new_fee_allocation_id'] && $request['old_fee_allocation_id'] !== $request['new_fee_allocation_id']) {
        $stmt = $conn->prepare("UPDATE tbl_student_fee_allocation 
                                SET fee_config_id = ? WHERE student_id = ?");
        $stmt->execute([$request['new_fee_allocation_id'], $request['enrollment_id']]);
    }

    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Request approved successfully',
        'data' => [
            'request_id' => $request_id,
            'new_status' => 'approved'
        ]
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("API Approve Request Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("API Approve Request Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
