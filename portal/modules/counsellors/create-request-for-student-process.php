<?php

/**
 * Create Installment Request for Student - Process Handler
 * Backend handler for counsellor-initiated installment requests
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is a counsellor
if (!hasRole(ROLE_COUNSELLOR)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$counsellor_id = $_SESSION['user_id'];
$student_id = intval($_POST['student_id'] ?? 0);
$fee_component = trim($_POST['fee_component'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$requested_installments = intval($_POST['requested_installments'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

// Validation
if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a student']);
    exit;
}

if (empty($fee_component)) {
    echo json_encode(['success' => false, 'message' => 'Please select a fee component']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid amount']);
    exit;
}

if ($requested_installments < 2 || $requested_installments > 12) {
    echo json_encode(['success' => false, 'message' => 'Number of installments must be between 2 and 12']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for the request']);
    exit;
}

try {
    $conn->beginTransaction();
    $op = new Operation();

    // Verify the student is assigned to this counsellor
    $student = $op->selectOne('tbl_gm_std_registration', ['*'], [
        'id' => $student_id,
        'counsellor_id' => $counsellor_id,
        'is_enrolled' => 1
    ]);

    if (!$student) {
        throw new Exception("Student not found or not assigned to you");
    }

    // Check if there's already a pending request for this fee component
    $existing = $op->selectOne(
        'tbl_installment_requests',
        ['id'],
        ['student_id' => $student_id, 'fee_component' => $fee_component, 'status' => 'pending']
    );

    if ($existing) {
        throw new Exception("A pending installment request already exists for this fee component");
    }

    // Generate unique request number with microtime to prevent duplicates
    $request_no = 'INS-REQ-' . date('Ymd') . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT) . '-' . substr(microtime(true) * 10000, -4);

    // Insert installment request
    $stmt = $conn->prepare("INSERT INTO tbl_installment_requests 
                           (student_id, enrollment_id, request_no, fee_component, total_amount, 
                            requested_installments, reason, status, created_by, request_type, 
                            created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'counsellor', NOW(), NOW())");
    $stmt->execute([
        $student_id,
        $student['enrollment_id'] ?? null,
        $request_no,
        $fee_component,
        $amount,
        $requested_installments,
        $reason,
        $counsellor_id
    ]);

    $conn->commit();

    // Log the action
    logError("Counsellor installment request created: Student ID {$student_id}, Amount: {$amount}, By Counsellor: {$counsellor_id}", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Installment request submitted successfully. It will be reviewed by the Principal.',
        'request_no' => $request_no
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Counsellor installment request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
