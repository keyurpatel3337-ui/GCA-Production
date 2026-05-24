<?php
/**
 * Reset sequences for fees with no payments
 */

require_once __DIR__ . '/../../common/db_connect.php';

$fees_to_reset = ['trust_facilities_fee', 'hostel_fee', 'transport_fee', 'tuition_fee_part2'];

foreach ($fees_to_reset as $fee) {
    $stmt = $conn->prepare('UPDATE tbl_receipt_sequences SET last_sequence = 0, updated_at = NOW() WHERE fee_type = ? AND school_id IS NULL');
    $stmt->execute([$fee]);
    echo "Reset $fee to 0\n";
}

echo "\n✅ All sequences reset!\n\n";

// Show final status
echo "Final Status:\n";
echo str_repeat('=', 100) . "\n";
printf("%-30s %-15s %-15s %-15s\n", 'Fee Type', 'School ID', 'Last Sequence', 'Next Receipt');
echo str_repeat('=', 100) . "\n";

$stmt = $conn->query("SELECT fee_type, school_id, last_sequence FROM tbl_receipt_sequences ORDER BY fee_type, school_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-30s %-15s %-15s %-15s\n",
        $row['fee_type'],
        $row['school_id'] ?? 'NULL',
        $row['last_sequence'],
        $row['last_sequence'] + 1
    );
}
echo str_repeat('=', 100) . "\n";
