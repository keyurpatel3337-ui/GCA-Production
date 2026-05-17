<?php
define('APP_INIT', true);
require 'common/db_connect.php';

try {
    $conn->beginTransaction();

    // 1. Update the existing Trust Facilities Fee record
    $stmt = $conn->prepare("UPDATE tbl_payments SET amount = 12000.00 WHERE id = 5386 AND student_id = 1472");
    $stmt->execute();

    // 2. Insert a new Transport Fee record
    $stmt = $conn->prepare("SELECT * FROM tbl_payments WHERE id = 5386");
    $stmt->execute();
    $orig = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get a receipt number for transport fee
    require_once 'common/helpers/receipt_mapping_functions.php';
    require_once 'common/helpers/receipt_sequence_helper.php';

    $school_id = getStudentSchoolId($conn, 1472);
    $seq_result = getNextReceiptNumber($conn, 'transport_fee', $school_id, null, 1472);
    $receipt_no = $seq_result['success'] ? $seq_result['receipt_no'] : 'EZB-TRANS-1472';
    $receipt_config_id = getReceiptConfigForFee($conn, 'transport_fee', $school_id);

    $stmt_ins = $conn->prepare("INSERT INTO tbl_payments 
                           (student_id, receipt_no, amount, payment_date, payment_mode, 
                            transaction_id, payment_id, payment_type, fee_component, receipt_config_id, remarks, 
                            status, created_by, created_at) 
                           VALUES 
                           (?, ?, ?, ?, ?, ?, ?, 'Transport Fee', 'transport_fee', ?, 'Transport Fee (Online via EaseBuzz)', 'paid', 31, ?)");
    $stmt_ins->execute([
        1472, 
        $receipt_no, 
        21600.00, 
        $orig['payment_date'], 
        $orig['payment_mode'], 
        $orig['transaction_id'], 
        $orig['payment_id'], 
        $receipt_config_id,
        $orig['created_at']
    ]);

    $conn->commit();
    echo "Fixed records for student 1472.\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Error: " . $e->getMessage();
}
