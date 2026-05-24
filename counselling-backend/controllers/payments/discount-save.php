<?php
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

// Initialize database operations
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_allocation_helper.php';

// Check auth
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['accountant', 'super_admin', 'principle'])) {
    sendErrorResponse('Unauthorized access', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? null;
$amount = floatval($input['amount'] ?? 0);
$reason = trim($input['reason'] ?? '');
$type = $input['type'] ?? 'flat'; // flat, percentage, smart

if (!$student_id || $amount <= 0) {
    sendErrorResponse('Invalid student ID or amount', 400);
}

try {
    $dbOps = new DatabaseOperations();

    // 1. Get Enrollment ID
    $enrollment = $dbOps->selectOne('tbl_gm_std_registration', ['enrollment_id'], ['id' => $student_id]);

    if (!$enrollment || empty($enrollment['enrollment_id'])) {
        sendErrorResponse('Student is not enrolled yet. Cannot apply discount.', 400);
    }

    // 2. Update Discount in tbl_enrolled_students
    // We append to the existing discount amount
    $updateSql = "UPDATE tbl_enrolled_students 
                  SET post_admission_discount_amount = post_admission_discount_amount + :amount,
                      post_admission_discount_remarks = CONCAT(IFNULL(post_admission_discount_remarks, ''), :remarks),
                      updated_at = NOW()
                  WHERE registration_id = :student_id AND is_active = 1";

    $discount_remark = " | Add: $amount ($type) - " . $reason . " [" . date('d-M-Y') . "]";

    // Clean up first pipe if it's the first remark
    // (Logic handled by CONCAT usually, but good to be safe if column is NULL)

    $params = [
        'amount' => $amount,
        'remarks' => $discount_remark,
        'student_id' => $student_id
    ];

    $stmt = $conn->prepare($updateSql);
    $success = $stmt->execute($params);

    if ($success) {
        // 3. Sync Fee Allocation
        // This recalculates pending amounts based on the new total discount
        $syncResult = syncStudentFeeAllocation($conn, $student_id);

        if ($syncResult['success']) {
            sendSuccessResponse(['message' => 'Discount applied successfully', 'new_total_discount' => $amount]);
        } else {
            // Discount saved but sync failed
            sendSuccessResponse(['message' => 'Discount applied but fee sync failed. Please refresh.'], 'Discount applied with warning');
        }
    } else {
        sendErrorResponse('Failed to update discount record', 500);
    }

} catch (Exception $e) {
    error_log("Discount Save Error: " . $e->getMessage());
    sendErrorResponse('Server error: ' . $e->getMessage(), 500);
}
