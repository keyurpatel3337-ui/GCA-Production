<?php
define('APP_INIT', true);
require_once 'c:\xampp\htdocs\GCA-Production\env.config.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\constants.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\db_connect.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\lib\Operation.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\helpers\receipt_sequence_helper.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\helpers\receipt_mapping_functions.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\helpers\format_helper.php';

$dbOps = new Operation($conn);

// Transaction Details
$student_id = 2361;
$transaction_id = 'GM2026050611334369FAD9BF221BF8668';
$easepayid = 'E2605060Y84MXZ';
$amount = 10000.00;
$fee_component = 'hostel_security';
$payment_type_label = formatFeeKey($fee_component);

try {
    $conn->beginTransaction();

    // 1. Check if already processed
    $stmt = $conn->prepare("SELECT id, status FROM tbl_payment_orders WHERE transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    if ($order['status'] === 'completed') {
        echo "Order already marked as completed.\n";
    } else {
        // 2. Update Payment Order
        $stmt = $conn->prepare("UPDATE tbl_payment_orders SET status = 'completed', payment_id = ?, completed_at = NOW() WHERE transaction_id = ?");
        $stmt->execute([$easepayid, $transaction_id]);
        echo "Updated tbl_payment_orders status to completed.\n";
    }

    // 3. Check if payment already exists in tbl_payments
    $stmt = $conn->prepare("SELECT id FROM tbl_payments WHERE transaction_id = ? OR payment_id = ?");
    $stmt->execute([$transaction_id, $easepayid]);
    if ($stmt->fetch()) {
        echo "Payment already exists in tbl_payments.\n";
    } else {
        // 4. Get Student metadata (school_id)
        $stmt = $conn->prepare("SELECT school_id FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        $school_id = $student['school_id'] ?? 1;

        // 5. Generate Receipt Number
        $seq_result = getNextReceiptNumber($conn, $fee_component, $school_id, null, $student_id);
        if ($seq_result['success']) {
            $r_no = $seq_result['receipt_no'];
        } else {
            $r_no = 'EZB-MANUAL-' . date('Ymd') . '-' . substr($easepayid, -4);
        }
        echo "Generated Receipt Number: $r_no\n";

        // 6. Get Receipt Config ID
        $r_conf = getReceiptConfigForFee($conn, $fee_component, $school_id) ?: 1;

        // 7. Insert into tbl_payments
        $last_payment_id = $dbOps->insert('tbl_payments', [
            'student_id' => $student_id,
            'receipt_no' => $r_no,
            'amount' => $amount,
            'payment_date' => '2026-05-06',
            'payment_mode' => 'online',
            'transaction_id' => $transaction_id,
            'payment_id' => $easepayid,
            'gateway_name' => 'easebuzz',
            'payment_type' => $payment_type_label,
            'fee_component' => $fee_component,
            'receipt_config_id' => $r_conf,
            'remarks' => "Manual update after EaseBuzz verification (Transaction: $transaction_id)",
            'status' => 'paid',
            'created_by' => $student_id,
            'created_at' => '2026-05-06 06:03:43',
            'term_id' => 1
        ]);
        echo "Inserted into tbl_payments. ID: $last_payment_id\n";

        // 8. Log transaction
        $stmt_log = $conn->prepare("INSERT INTO tbl_payment_transactions 
            (payment_id, student_id, transaction_type, amount, transaction_date, description, processed_by) 
            VALUES (?, ?, 'payment', ?, '2026-05-06 06:03:43', ?, ?)");
        $stmt_log->execute([
            $last_payment_id,
            $student_id,
            $amount,
            "Online payment - Component: $fee_component, Receipt: $r_no (Verified via EaseBuzz API)",
            $student_id
        ]);
        echo "Logged transaction in tbl_payment_transactions.\n";

        // 9. Update/Delete pending payments if exists
        $stmt = $conn->prepare("DELETE FROM tbl_pending_payments WHERE student_id = ? AND payment_type = ?");
        $stmt->execute([$student_id, $fee_component]);
        echo "Cleaned up tbl_pending_payments.\n";
    }

    $conn->commit();
    echo "Manual update completed successfully!\n";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
