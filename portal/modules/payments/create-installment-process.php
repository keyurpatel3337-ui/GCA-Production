<?php
/**
 * Create Installment Process - Backend Handler
 * Handles direct installment creation by Principal/Accountant
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

header('Content-Type: application/json');

// Check if user has permission
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$student_id = intval($_POST['student_id'] ?? 0);
$fee_component = trim($_POST['fee_component'] ?? '');
$total_amount = floatval($_POST['total_amount'] ?? 0);
$num_installments = intval($_POST['num_installments'] ?? 0);
$first_due_date = $_POST['first_due_date'] ?? '';
$interval_days = intval($_POST['interval_days'] ?? 30);
$remarks = trim($_POST['remarks'] ?? '');
$user_id = $_SESSION['user_id'];

// Validation
if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a student']);
    exit;
}

if (empty($fee_component)) {
    echo json_encode(['success' => false, 'message' => 'Please select a fee component']);
    exit;
}

if ($total_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid amount']);
    exit;
}

if ($num_installments < 2 || $num_installments > 12) {
    echo json_encode(['success' => false, 'message' => 'Number of installments must be between 2 and 12']);
    exit;
}

if (empty($first_due_date)) {
    echo json_encode(['success' => false, 'message' => 'Please select the first installment date']);
    exit;
}

try {
    $conn->beginTransaction();

    // Verify student exists
    $stmt = $conn->prepare("SELECT id, enrollment_id, is_enrolled FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found");
    }

    // Get or create fee allocation record - check by both registration id and enrollment id
    $alloc_ids = [$student_id];
    if (!empty($student['enrollment_id'])) {
        $alloc_ids[] = $student['enrollment_id'];
    }
    $alloc_placeholders = implode(',', array_fill(0, count($alloc_ids), '?'));

    $stmt = $conn->prepare("SELECT id, fee_config_id FROM tbl_student_fee_allocation WHERE student_id IN ($alloc_placeholders) ORDER BY academic_year DESC LIMIT 1");
    $stmt->execute($alloc_ids);
    $allocation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($allocation) {
        $allocation_id = $allocation['id'];
        $fee_config_id = $allocation['fee_config_id'];
    } else {
        // Get active fee config
        $stmt = $conn->prepare("SELECT id FROM tbl_fee_config WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $fee_config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fee_config) {
            throw new Exception("No active fee configuration found");
        }

        $fee_config_id = $fee_config['id'];
        $current_year = date('Y');

        // Create allocation
        $stmt = $conn->prepare("INSERT INTO tbl_student_fee_allocation 
            (student_id, fee_config_id, allocated_amount, paid_amount, pending_amount, status, academic_year, allocated_by, created_by, allocated_at, updated_at) 
            VALUES (?, ?, ?, 0, ?, 'pending', ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$student_id, $fee_config_id, $total_amount, $total_amount, $current_year, $user_id, $user_id]);
        $allocation_id = $conn->lastInsertId();
    }

    // Calculate installment amounts
    $installment_amount = $total_amount / $num_installments;

    // Generate request number for tracking
    $request_no = 'INS-DIR-' . date('Ymd') . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT);

    // Create a record in installment_requests for tracking (with approved status)
    $stmt = $conn->prepare("INSERT INTO tbl_installment_requests 
                           (student_id, enrollment_id, request_no, fee_component, total_amount, 
                            requested_installments, reason, status, reviewed_by, reviewed_at, 
                            review_remarks, created_by, request_type, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?, ?, 'direct', NOW(), NOW())");
    $stmt->execute([
        $student_id,
        $student['enrollment_id'] ?? null,
        $request_no,
        $fee_component,
        $total_amount,
        $num_installments,
        'Direct creation by ' . ($_SESSION['user_name'] ?? 'Admin'),
        $user_id,
        $remarks ?: 'Direct installment creation',
        $user_id
    ]);

    // Create installments
    $current_date = new DateTime($first_due_date);
    $stmt_inst = $conn->prepare("INSERT INTO tbl_fee_installments 
        (allocation_id, student_id, fee_config_id, installment_number, due_amount, due_date, paid_amount, payment_status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 0, 'pending', ?)");

    for ($i = 1; $i <= $num_installments; $i++) {
        $due_date = $current_date->format('Y-m-d');
        $stmt_inst->execute([$allocation_id, $student_id, $fee_config_id, $i, $installment_amount, $due_date, $user_id]);

        // Add interval days for next installment
        $current_date->modify("+{$interval_days} days");
    }

    $conn->commit();

    // Log the action
    logError("Direct installment created: Student ID {$student_id}, Amount: {$total_amount}, Installments: {$num_installments}, By User: {$user_id}", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => "Successfully created {$num_installments} installments of ₹" . formatIndianCurrency($installment_amount) . " each for the student.",
        'request_no' => $request_no
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Direct installment creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create installments: ' . $e->getMessage()]);
}



