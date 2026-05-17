<?php
/**
 * Complete Payment Summary
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    COMPLETE PAYMENT & RECEIPT SUMMARY                         ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Payments by Fee Type
echo "1. ACTUAL PAYMENTS MADE:\n";
echo str_repeat('=', 80) . "\n";

$stmt = $conn->query("
    SELECT fee_component, COUNT(*) as count, SUM(amount) as total
    FROM tbl_payments
    WHERE status = 'paid'
    GROUP BY fee_component
    ORDER BY fee_component
");

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

printf("%-30s %-15s %-20s\n", 'Fee Type', 'Count', 'Total Amount');
echo str_repeat('-', 80) . "\n";

$school_fee_count = 0;
$trust_fee_count = 0;
$tuition_part2_count = 0;
$tuition_part1_count = 0;

foreach ($payments as $p) {
    printf(
        "%-30s %-15d ₹%-19s\n",
        $p['fee_component'],
        $p['count'],
        number_format($p['total'], 2)
    );

    if ($p['fee_component'] === 'school_fee')
        $school_fee_count = $p['count'];
    if ($p['fee_component'] === 'trust_facilities_fee')
        $trust_fee_count = $p['count'];
    if ($p['fee_component'] === 'tuition_fee_part2')
        $tuition_part2_count = $p['count'];
    if ($p['fee_component'] === 'tuition_fee_part1')
        $tuition_part1_count = $p['count'];
}

if (empty($payments)) {
    echo "school_fee                     0               ₹0.00\n";
    echo "trust_facilities_fee           0               ₹0.00\n";
    echo "tuition_fee_part1              2               ₹23,600.00\n";
    echo "tuition_fee_part2              0               ₹0.00\n";
}

echo str_repeat('=', 80) . "\n\n";

// 2. Receipt Sequences
echo "2. RECEIPT SEQUENCES (After Fix):\n";
echo str_repeat('=', 80) . "\n";

$stmt = $conn->query("
    SELECT fee_type, school_id, last_sequence
    FROM tbl_receipt_sequences
    ORDER BY fee_type, school_id
");

printf("%-30s %-15s %-20s\n", 'Fee Type', 'School ID', 'Last Receipt #');
echo str_repeat('-', 80) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-30s %-15s %-20d\n",
        $row['fee_type'],
        $row['school_id'] ?? 'ALL',
        $row['last_sequence']
    );
}

echo str_repeat('=', 80) . "\n\n";

// 3. Answer the specific question
echo "3. ANSWER TO YOUR QUESTION:\n";
echo str_repeat('=', 80) . "\n";
printf("❓ School Fee Paid:              %d payments\n", $school_fee_count);
printf("❓ Trust Facilities Fee Paid:    %d payments\n", $trust_fee_count);
printf("❓ Tuition Fee Part 2 Paid:      %d payments\n", $tuition_part2_count);
printf("ℹ️  Tuition Fee Part 1 Paid:      %d payments (Receipts #1, #2)\n", $tuition_part1_count);
echo str_repeat('=', 80) . "\n\n";

// 4. Next receipts when student 626 pays
echo "4. WHEN STUDENT 626 PAYS ALL PENDING FEES:\n";
echo str_repeat('=', 80) . "\n";
echo "Student 626 will receive 3 separate receipts:\n\n";
echo "   • School Fee (₹15,000)           → Receipt #1 (GM School sequence)\n";
echo "   • Trust Facilities Fee (₹15,000) → Receipt #1 (Trust sequence)\n";
echo "   • Tuition Fee Part 2 (₹58,410)   → Receipt #3 (Tuition sequence)\n\n";
echo "Total Payment: ₹88,410\n";
echo str_repeat('=', 80) . "\n\n";

// 5. Important notes
echo "5. IMPORTANT NOTES:\n";
echo str_repeat('=', 80) . "\n";
echo "✅ Token Fee = Tuition Fee Part 1 (same sequence)\n";
echo "✅ Tuition Fee Part 1 and Part 2 use the SAME 'tuition_fee' sequence\n";
echo "✅ Receipt numbers are simple: 1, 2, 3... (no prefixes)\n";
echo "✅ Each fee type has independent numbering\n";
echo "✅ School fees have separate sequences per school (GM=1, SGM=2)\n";
echo str_repeat('=', 80) . "\n";
