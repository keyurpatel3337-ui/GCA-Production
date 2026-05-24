<?php

/**
 * API Endpoint: Submit Group Change Request
 * Handles submission of new group change requests
 */

header('Content-Type: application/json');
session_start();

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if student is authenticated
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    // Fallback to POST data
    $input = $_POST;
}

// Validate required fields
$required_fields = ['student_id', 'current_group_id', 'requested_group_id', 'reason'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$student_id = intval($input['student_id']);
$enrollment_id = intval($input['enrollment_id'] ?? 0);
$current_group_id = intval($input['current_group_id']);
$requested_group_id = intval($input['requested_group_id']);
$reason = trim($input['reason']);

// Validate student ID matches session
if ($student_id !== $_SESSION['student_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Validate reason length
if (strlen($reason) < 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reason must be at least 50 characters']);
    exit;
}

try {
    // Check for existing pending request
    $stmt = $conn->prepare("SELECT id FROM tbl_group_change_requests 
                            WHERE student_id = ? AND status IN ('pending', 'under_review')");
    $stmt->execute([$student_id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have a pending request'
        ]);
        exit;
    }

    // Get student and fee details
    $stmt = $conn->prepare("SELECT board_id, medium_id, standard, enrollment_id
                            FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Get current and new fee configs
    $stmt = $conn->prepare("SELECT id, total_fees FROM tbl_fee_config 
                            WHERE board_id = ? AND medium_id = ? AND group_id = ? 
                            AND standard = ? AND is_active = 1 LIMIT 1");

    $stmt->execute([$student['board_id'], $student['medium_id'], $current_group_id, $student['standard']]);
    $current_fee_config = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_total = $current_fee_config['total_fees'] ?? 0;
    $current_fee_config_id = $current_fee_config['id'] ?? null;

    $stmt->execute([$student['board_id'], $student['medium_id'], $requested_group_id, $student['standard']]);
    $new_fee_config = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_total = $new_fee_config['total_fees'] ?? 0;
    $new_fee_config_id = $new_fee_config['id'] ?? null;

    // Get fees already paid
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                            FROM tbl_payments WHERE student_id = ? AND payment_type != 'token_fee'");
    $stmt->execute([$student_id]);
    $fees_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'];

    // Calculate fee difference
    $fee_difference = $new_total - $current_total;
    $adjusted_pending = $new_total - $fees_paid;

    // Begin transaction
    $conn->beginTransaction();

    // Insert request
    $stmt = $conn->prepare("INSERT INTO tbl_group_change_requests 
                            (student_id, enrollment_id, current_group_id, requested_group_id, 
                             reason, status, request_date, current_total_fees, new_total_fees, 
                             fee_difference, fees_already_paid, adjusted_pending_amount,
                             old_fee_allocation_id, new_fee_allocation_id, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $student_id,
        $student['enrollment_id'],
        $current_group_id,
        $requested_group_id,
        $reason,
        $current_total,
        $new_total,
        $fee_difference,
        $fees_paid,
        $adjusted_pending,
        $current_fee_config_id,
        $new_fee_config_id,
        $student_id  // created_by
    ]);

    $request_id = $conn->lastInsertId();

    $conn->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Group change request submitted successfully',
        'data' => [
            'request_id' => $request_id,
            'request_number' => 'REQ-' . $request_id
        ]
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("API Submit Request Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("API Submit Request Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
