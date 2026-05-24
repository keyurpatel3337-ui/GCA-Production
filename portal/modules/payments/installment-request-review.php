<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Accountant, Principal or Super Admin
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$request_id = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$remarks = trim($_POST['remarks'] ?? '');
$user_id = $_SESSION['user_id'];

// Validation
if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $conn->beginTransaction();
    $op = new Operation();

    // Get request details
    $request = $op->readWithJoin(
        'tbl_installment_requests ir',
        [
            'ir.*',
            's.id as student_id',
            "CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as student_name",
            's.mob'
        ],
        [
            ['type' => 'JOIN', 'table' => 'tbl_gm_std_registration s', 'on' => 'ir.student_id = s.id']
        ],
        ['ir.id' => $request_id, 'ir.status' => 'pending']
    );

    if (!$request) {
        throw new Exception("Request not found or already processed");
    }

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    // Update request status
    $op->update('tbl_installment_requests', [
        'status' => $new_status,
        'reviewed_by' => $user_id,
        'reviewed_at' => date('Y-m-d H:i:s'),
        'review_remarks' => $remarks,
        'updated_at' => date('Y-m-d H:i:s')
    ], ['id' => $request_id]);

    // If approved, create installment schedule
    if ($action === 'approve') {
        $total_amount = floatval($request['total_amount']);
        $num_installments = intval($request['requested_installments']);
        $installment_amount = $total_amount / $num_installments;

        error_log("Installment Request Approval - Request ID: $request_id, Student ID: {$request['student_id']}, Total: $total_amount, Num: $num_installments");

        // Get or create fee allocation record
        $allocation = $op->selectOne(
            'tbl_student_fee_allocation',
            ['id', 'fee_config_id'],
            ['student_id' => $request['student_id']]
        );

        error_log("Installment Request Approval - Allocation found: " . ($allocation ? json_encode($allocation) : 'null'));

        if ($allocation) {
            $allocation_id = $allocation['id'];
            $fee_config_id = $allocation['fee_config_id'];
        } else {
            // Create allocation if doesn't exist
            // First get active fee config
            $fee_config = $op->selectOne('tbl_fee_config', ['id'], ['is_active' => 1]);

            if (!$fee_config) {
                throw new Exception("No active fee configuration found");
            }

            $fee_config_id = $fee_config['id'];
            $current_year = date('Y');

            $allocation_id = $op->insert('tbl_student_fee_allocation', [
                'student_id' => $request['student_id'],
                'fee_config_id' => $fee_config_id,
                'allocated_amount' => $total_amount,
                'paid_amount' => 0,
                'pending_amount' => $total_amount,
                'status' => 'pending',
                'academic_year' => $current_year,
                'allocated_by' => $user_id,
                'allocated_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        error_log("Installment Request Approval - Creating installments: Allocation ID: $allocation_id, Fee Config ID: $fee_config_id");

        // Create installments
        for ($i = 1; $i <= $num_installments; $i++) {
            $installment_id = $op->insert('tbl_fee_installments', [
                'allocation_id' => $allocation_id,
                'student_id' => $request['student_id'],
                'fee_config_id' => $fee_config_id,
                'installment_number' => $i,
                'due_amount' => $installment_amount,
                'paid_amount' => 0,
                'payment_status' => 'pending'
            ]);
            error_log("Installment Request Approval - Created installment #$i, ID: $installment_id, Amount: $installment_amount");
        }

        error_log("Installment Request Approval - All $num_installments installments created successfully");
    }

    $conn->commit();

    // Send notification to student (optional)
    // You can implement email/SMS notification here

    $message = $action === 'approve'
        ? 'Installment request approved successfully. Installment schedule has been created.'
        : 'Installment request rejected.';

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Installment request review error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process request. Please try again.']);
}
