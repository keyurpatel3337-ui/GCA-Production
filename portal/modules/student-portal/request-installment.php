<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Tighten access: Only parents are allowed to request installments
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if ($is_student) {
    echo json_encode(['success' => false, 'message' => 'Access Denied: Fees and Wallet are managed exclusively by Parents.']);
    exit;
}

if (!$is_parent) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_SESSION['active_student_id'] ?? $_SESSION['student_id'];
$fee_component = $_POST['fee_component'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$requested_installments = intval($_POST['requested_installments'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

// Validation
if (empty($fee_component)) {
    echo json_encode(['success' => false, 'message' => 'Fee component is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    exit;
}

if ($requested_installments < 2 || $requested_installments > 12) {
    echo json_encode(['success' => false, 'message' => 'Number of installments must be between 2 and 12']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason for installment request is required']);
    exit;
}

try {
    $conn->beginTransaction();
    $op = new Operation();

    // Check if there's already a pending request for this fee component
    $existing = $op->selectOne(
        'tbl_installment_requests',
        ['id', 'status'],
        ['student_id' => $student_id, 'fee_component' => $fee_component]
    );

    if ($existing && $existing['status'] === 'pending') {
        echo json_encode(['success' => false, 'message' => 'You already have a pending installment request for this fee component']);
        exit;
    }

    // Get student and enrollment details
    $stmt = $conn->prepare("SELECT r.*, 
                           CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) as full_name,
                           r.enrollment_id,
                           c.course_name
                           FROM tbl_gm_std_registration r
                           LEFT JOIN tbl_courses c ON r.course_id = c.id
                           WHERE r.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception("Student not found");
    }

    // Generate unique request number with microtime to prevent duplicates
    $request_no = 'INS-REQ-' . date('Ymd') . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT) . '-' . substr(microtime(true) * 10000, -4);

    // Insert installment request
    $stmt = $conn->prepare("INSERT INTO tbl_installment_requests 
                           (student_id, enrollment_id, request_no, fee_component, total_amount, 
                            requested_installments, reason, status, created_by, request_type, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'student', NOW(), NOW())");
    $stmt->execute([
        $student_id,
        $student['enrollment_id'],
        $request_no,
        $fee_component,
        $amount,
        $requested_installments,
        $reason,
        $student_id  // created_by: student's own ID for self-initiated requests
    ]);

    $request_id = $conn->lastInsertId();

    $conn->commit();

    // Send notification to accountant (optional - implement if needed)
    try {
        // You can implement notification here
    } catch (Exception $e) {
        logError("Installment request notification error: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Installment request submitted successfully. You will be notified once it is reviewed.',
        'request_no' => $request_no
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Installment request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit request. Please try again.']);
}
