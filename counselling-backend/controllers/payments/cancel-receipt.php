<?php
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json; charset=utf-8');

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Fallback to $_POST
if (empty($data)) {
    $data = $_POST;
}

// Check authorization (Admin or Accountant)
$allowed_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ACCOUNTANT];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
    sendErrorResponse('Unauthorized access! Only accountants or admins can cancel receipts.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $data['payment_id'] ?? null;
    $cancel_reason = trim($data['reason'] ?? '');
    $action_type = trim($data['action_type'] ?? 'cancel'); // 'cancel' or 'cheque_return'
    $cancelled_by = $_SESSION['user_id'];

    if (empty($payment_id)) {
        sendErrorResponse('Payment ID is required.', 400);
    }
    if (empty($cancel_reason)) {
        sendErrorResponse('Cancellation reason is required.', 400);
    }
    if (!in_array($action_type, ['cancel', 'cheque_return'])) {
        $action_type = 'cancel'; // Default to cancel
    }

    $new_status = ($action_type === 'cheque_return') ? 'cheque_return' : 'cancelled';
    $action_label = ($action_type === 'cheque_return') ? 'CHEQUE RETURNED' : 'CANCELLED';

    // Fetch user name for remarks
    $user_name = "User $cancelled_by"; // Fallback
    try {
        $user_stmt = $conn->prepare("SELECT name FROM tbl_users WHERE id = ?");
        $user_stmt->execute([$cancelled_by]);
        $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_row && !empty($user_row['name'])) {
            $user_name = $user_row['name'];
        }
    } catch (Exception $e) {
        logError("Failed to fetch user name for cancellation: " . $e->getMessage());
    }

    try {
        $conn->beginTransaction();

        // 1. Fetch payment details
        $stmt = $conn->prepare("SELECT * FROM tbl_payments WHERE id = ? FOR UPDATE");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("Payment record not found.");
        }

        if ($payment['status'] === 'cancelled' || $payment['status'] === 'cheque_return') {
            throw new Exception("Receipt is already " . str_replace('_', ' ', $payment['status']) . ".");
        }

        $student_id = $payment['student_id'];
        $amount = $payment['amount'];
        $receipt_no = $payment['receipt_no'];
        $fee_component = $payment['fee_component'];
        $payment_type = $payment['payment_type'] ?? '';

        // 2. Mark payment as cancelled/cheque_return
        $new_remarks = $payment['remarks'] . " [$action_label by: $user_name on " . date('Y-m-d H:i') . ": $cancel_reason]";

        $stmt = $conn->prepare("UPDATE tbl_payments 
                               SET status = ?, 
                                   remarks = ? 
                               WHERE id = ?");
        $stmt->execute([$new_status, $new_remarks, $payment_id]);

        // 3. Insert specific cancellation log/transaction correction
        // Create a negative transaction to balance the ledger. 
        // We use 'cancellation' or 'cheque_return' based on the action.
        $stmt = $conn->prepare("INSERT INTO tbl_payment_transactions 
            (payment_id, student_id, transaction_type, amount, transaction_date, 
            description, processed_by) 
            VALUES 
            (:payment_id, :student_id, :transaction_type, :amount, NOW(), 
            :description, :processed_by)");

        $description = "Receipt $receipt_no $action_label. Reason: $cancel_reason";
        // Amount is negative to deduce
        $neg_amount = -1 * abs($amount);
        $transaction_type = ($action_type === 'cheque_return') ? 'cheque_return' : 'cancellation';

        $stmt->execute([
            'payment_id' => $payment_id,
            'student_id' => $student_id,
            'transaction_type' => $transaction_type,
            'amount' => $neg_amount,
            'description' => $description,
            'processed_by' => $cancelled_by
        ]);

        // 4. Revert Fee Flags / Installment Status
        // Logic depends on fee_component

        if ($fee_component === 'tuition_fee_part1' || $fee_component === 'token_fee' || strpos($payment_type, 'Token') !== false) {
            // Revert Token Fee Status
            $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                                   SET token_fees_paid = 0, 
                                       token_amount = 0,
                                       updated_at = NOW() 
                                   WHERE id = ?");
            $stmt->execute([$student_id]);
            logOfflineActivity("Reverted token_fees_paid to 0 for student $student_id due to receipt $receipt_no cancellation.", 'WARNING');
        }

        // 5. Synchronize Fee Allocation table
        require_once __DIR__ . '/../../../common/helpers/fee_allocation_helper.php';
        $sync_res = syncStudentFeeAllocation($conn, $student_id);
        if ($sync_res['success']) {
            logOfflineActivity("Synchronized tbl_student_fee_allocation for student $student_id after cancellation", 'INFO');
        } else {
            logError("Fee allocation sync failed for student $student_id after cancellation: " . $sync_res['message']);
        }

        // Revert Installment if linked
        if (!empty($payment['installment_id'])) {
            $stmt = $conn->prepare("UPDATE tbl_fee_installments 
                                   SET payment_status = 'unpaid', 
                                       paid_amount = 0, 
                                       receipt_no = NULL,
                                       payment_date = NULL,
                                       transaction_id = NULL
                                   WHERE id = ?");
            $stmt->execute([$payment['installment_id']]);
            logOfflineActivity("Reverted installment {$payment['installment_id']} to unpaid.", 'INFO');
        }

        // 6. Save new cancellation reason if requested
        if (!empty($data['is_new_reason']) && $data['is_new_reason'] === true) {
            $reasons_file = __DIR__ . '/../../../portal/config/cancellation_reasons.json';
            if (file_exists($reasons_file)) {
                $reasons = json_decode(file_get_contents($reasons_file), true);
                $found = false;
                foreach ($reasons as &$group) {
                    if ($group['category'] === 'Other') {
                        if (!in_array($cancel_reason, $group['options'])) {
                            $group['options'][] = $cancel_reason;
                            $found = true;
                        }
                        break;
                    }
                }
                if ($found) {
                    file_put_contents($reasons_file, json_encode($reasons, JSON_PRETTY_PRINT));
                    logOfflineActivity("Added new cancellation reason to JSON: $cancel_reason", 'INFO');
                }
            }
        }

        $conn->commit();
        logOfflineActivity("Receipt $receipt_no ($payment_id) cancelled successfully by User $cancelled_by.", 'SUCCESS');

        sendSuccessResponse([], "Receipt cancelled successfully.");

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        logError("Receipt cancellation failed for payment $payment_id: " . $e->getMessage());
        sendErrorResponse('Failed to cancel receipt: ' . $e->getMessage(), 500);
    }
} else {
    sendErrorResponse('Invalid request method.', 405);
}
