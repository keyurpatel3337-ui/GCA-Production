<?php
/**
 * Hard Delete Without GST Payment
 * 
 * Permanently deletes a record from tbl_payments and updates student allocations.
 * Restricted to Super Admins.
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_allocation_helper.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

try {
    // Input is already parsed into $_POST and $_REQUEST by common/bootstrap.php
    $payment_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

    if (!$payment_id) {
        sendErrorResponse('Payment ID is required');
    }

    // Role check using standard portal roles
    // We use session role_id or user_role depending on system configuration
    $user_role_id = $_SESSION['role_id'] ?? 0;
    
    // Debug logging
    error_log("Hard Delete - Payment ID: $payment_id, User Role ID: $user_role_id");

    if (!in_array($user_role_id, [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ACCOUNTANT])) {
        sendErrorResponse('You do not have permission to delete payments. Role ID: ' . $user_role_id, 403);
    }

    // 1. Fetch payment info before deletion to know which student to sync
    $payment = $dbOps->select('tbl_payments', ['student_id', 'amount', 'transaction_id', 'receipt_no'], ['id' => $payment_id]);

    if (empty($payment)) {
        sendErrorResponse('Payment record not found');
    }

    $student_id = $payment[0]['student_id'];
    $amount = $payment[0]['amount'];
    $tx_id = $payment[0]['transaction_id'];
    $receipt_no = $payment[0]['receipt_no'] ?? '';

    // Extra safety: only delete if it was a TRUST collection record or a Without-GST record (receipt_no = '0')
    if (strpos($receipt_no, 'TRUST-') === false && $receipt_no !== '0') {
        sendErrorResponse('Safety check failed: Only trust collection or without-gst records can be hard-deleted via this endpoint.');
    }

    // 2. Perform Hard Delete (Permanently remove the record)
    $deleted = $dbOps->hardDelete('tbl_payments', ['id' => $payment_id]);

    if (!$deleted) {
        sendErrorResponse('Failed to delete payment record from database');
    }

    // 3. Re-sync Student Fee Allocation
    // This is crucial to ensure the student's pending_amount and status are updated.
    $syncResult = syncStudentFeeAllocation($conn, $student_id);

    if (!$syncResult['success']) {
        // Log error but the deletion was successful
        error_log("Payment Delete Warning: Deletion succeeded but Sync failed for student $student_id: " . $syncResult['message']);
    }

    sendSuccessResponse(null, "Payment ($tx_id) of ₹$amount permanently deleted. Fee allocations re-synchronized.");

} catch (Exception $e) {
    error_log("Hard Delete Without-GST Payment Error: " . $e->getMessage());
    sendErrorResponse('Error: ' . $e->getMessage(), 500);
}
