<?php
define('APP_INIT', true);
require_once 'c:\xampp\htdocs\GCA-Production\env.config.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\db_connect.php';

try {
    $conn->beginTransaction();

    // 1. Get all hostel records ordered by creation time
    $sql = "SELECT id, receipt_no, created_at 
            FROM tbl_payments 
            WHERE fee_component IN ('hostel_security', 'hostel_fee') 
              AND status = 'paid' 
            ORDER BY created_at ASC, id ASC";
    
    $stmt = $conn->query($sql);
    $records = $stmt->fetchAll();

    echo "Found " . count($records) . " records to process.\n";

    // 2. Update each record with new sequence
    $updateStmt = $conn->prepare("UPDATE tbl_payments SET receipt_no = ? WHERE id = ?");
    
    $sequence = 1;
    foreach ($records as $row) {
        $updateStmt->execute([(string)$sequence, $row['id']]);
        $sequence++;
    }

    $final_sequence = $sequence - 1;

    // 3. Update the global sequence counter
    $seqUpdateStmt = $conn->prepare("UPDATE tbl_receipt_sequences SET last_sequence = ?, updated_at = NOW() WHERE fee_type = 'hostel_fee'");
    $seqUpdateStmt->execute([$final_sequence]);

    $conn->commit();
    echo "Successfully re-sequenced $final_sequence records.\n";
    echo "Global counter updated to $final_sequence.\n";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}
