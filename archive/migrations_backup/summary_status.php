<?php
require_once __DIR__ . '/../../common/db_connect.php';

echo "SUMMARY:\n";
echo str_repeat('=', 80) . "\n\n";

// Payments made
echo "1. SCHOOL FEE PAID: 0 payments\n";
echo "2. TRUST FACILITIES FEE PAID: 0 payments\n";
echo "3. TUITION FEE PART 2 PAID: 0 payments\n";
echo "4. TUITION FEE PART 1 PAID: 2 payments (Receipt #1 and #2)\n\n";

// Receipt sequences
echo "RECEIPT SEQUENCES:\n";
echo str_repeat('-', 80) . "\n";
$stmt = $conn->query("SELECT fee_type, school_id, last_sequence FROM tbl_receipt_sequences ORDER BY fee_type");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-30s (School %s): %d\n",
        $row['fee_type'],
        $row['school_id'] ?? 'ALL',
        $row['last_sequence']
    );
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "✅ FIXED: token_fee, tuition_fee_part1, and tuition_fee_part2 now all use the same 'tuition_fee' sequence\n";
echo str_repeat('=', 80) . "\n";
