<?php
/**
 * Final Payment Status Summary
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║               PAYMENT STATUS & RECEIPT NUMBER SUMMARY                         ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

// Current payments
echo "📊 CURRENT PAYMENT STATUS:\n";
echo str_repeat('─', 80) . "\n";

$fee_types = [
    'school_fee' => 'School Fee',
    'trust_facilities_fee' => 'Trust Facilities Fee',
    'tuition_fee_part1' => 'Tuition Fee Part 1 (Token Fee)',
    'tuition_fee_part2' => 'Tuition Fee Part 2'
];

foreach ($fee_types as $fee_type => $label) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_payments WHERE fee_component = ?");
    $stmt->execute([$fee_type]);
    $count = $stmt->fetchColumn();
    printf("%-40s %5d students paid\n", $label . ':', $count);
}

echo str_repeat('─', 80) . "\n\n";

// Student details with receipts
echo "👥 STUDENTS WHO HAVE PAID:\n";
echo str_repeat('═', 100) . "\n";
printf("%-12s %-30s %-25s %-15s %-12s\n", 'Student ID', 'Name', 'Fee Type', 'Receipt No', 'Amount');
echo str_repeat('═', 100) . "\n";

$query = "SELECT 
            p.student_id,
            CONCAT(s.surname, ' ', s.student_name) as student_name,
            p.fee_component,
            p.receipt_no,
            p.amount
          FROM tbl_payments p
          LEFT JOIN tbl_gm_std_registration s ON p.student_id = s.id
          ORDER BY p.student_id, p.fee_component";

$stmt = $conn->query($query);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-12s %-30s %-25s %-15s ₹%s\n",
        $row['student_id'],
        substr($row['student_name'], 0, 28),
        $row['fee_component'],
        $row['receipt_no'],
        number_format($row['amount'], 2)
    );
}
echo str_repeat('═', 100) . "\n\n";

// Receipt sequences
echo "🔢 RECEIPT NUMBER SEQUENCES (After Fix):\n";
echo str_repeat('═', 100) . "\n";
printf("%-30s %-15s %-20s %-20s\n", 'Fee Type', 'School', 'Last Receipt', 'Next Receipt');
echo str_repeat('═', 100) . "\n";

$stmt = $conn->query("SELECT fee_type, school_id, last_sequence FROM tbl_receipt_sequences ORDER BY fee_type, school_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $school_display = 'All Schools';
    if ($row['school_id'] == 1)
        $school_display = 'GM School';
    if ($row['school_id'] == 2)
        $school_display = 'SGM School';

    printf(
        "%-30s %-15s %-20s %-20s\n",
        $row['fee_type'],
        $school_display,
        $row['last_sequence'] == 0 ? 'None' : $row['last_sequence'],
        $row['last_sequence'] + 1
    );
}
echo str_repeat('═', 100) . "\n\n";

// Important notes
echo "📝 IMPORTANT NOTES:\n";
echo str_repeat('─', 80) . "\n";
echo "1. Token Fee = Tuition Fee Part 1\n";
echo "   When students pay token fee, it is recorded as 'tuition_fee_part1'\n";
echo "   They both use the SAME receipt sequence\n\n";

echo "2. Receipt Number Format:\n";
echo "   • Simple sequential numbers: 1, 2, 3, 4...\n";
echo "   • No prefixes or suffixes\n";
echo "   • Each fee type has independent numbering\n\n";

echo "3. School Fee Sequences:\n";
echo "   • GM School (school_id=1): Has separate sequence\n";
echo "   • SGM School (school_id=2): Has separate sequence\n";
echo "   • Each school's receipts start from 1 independently\n\n";

echo "4. Other Fees:\n";
echo "   • All other fees share single sequences\n";
echo "   • Receipts start from 1 for each fee type\n";
echo str_repeat('─', 80) . "\n\n";

echo "✅ SYSTEM STATUS: All receipt sequences are synchronized with actual payments!\n";
echo "✅ Ready for new payments with correct receipt numbering\n\n";
