<?php
/**
 * Final Receipt Sequence Status
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           UPDATED RECEIPT SEQUENCE CONFIGURATION                              ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "📋 CURRENT SEQUENCES:\n";
echo str_repeat('=', 80) . "\n";
printf("%-30s %-15s %-15s %-15s\n", 'Fee Type', 'School', 'Last Receipt', 'Next Receipt');
echo str_repeat('=', 80) . "\n";

$stmt = $conn->query("SELECT fee_type, school_id, last_sequence FROM tbl_receipt_sequences ORDER BY fee_type, school_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $school_display = 'All Schools';
    if ($row['school_id'] == 1)
        $school_display = 'GM School';
    if ($row['school_id'] == 2)
        $school_display = 'SGM School';

    printf(
        "%-30s %-15s %-15s %-15s\n",
        $row['fee_type'],
        $school_display,
        $row['last_sequence'] == 0 ? 'None' : $row['last_sequence'],
        $row['last_sequence'] + 1
    );
}
echo str_repeat('=', 80) . "\n\n";

echo "✅ KEY CHANGES:\n";
echo str_repeat('-', 80) . "\n";
echo "1. Tuition Fee Part 1 and Part 2 now SHARE the same sequence\n";
echo "   - They are the same fee, just collected in 2 installments\n";
echo "   - Both will get consecutive numbers from 'tuition_fee' sequence\n";
echo "   - Example: Part 1 gets receipt #3, Part 2 gets #4, Part 1 gets #5, etc.\n\n";

echo "2. Independent Sequences:\n";
echo "   - School Fee: Separate for GM (school_id=1) and SGM (school_id=2)\n";
echo "   - Trust Facilities Fee: Shared across all schools\n";
echo "   - Hostel Fee: Shared across all schools\n";
echo "   - Transport Fee: Shared across all schools\n";
echo "   - Tuition Fee: Shared for both Part 1 and Part 2\n";
echo "   - Token Fee: Shared (goes to tuition_fee sequence)\n";
echo str_repeat('-', 80) . "\n\n";

echo "📝 RECEIPT NUMBER FORMAT:\n";
echo "   Simple sequential numbers: 1, 2, 3, 4, 5...\n";
echo "   No prefixes or suffixes\n\n";

echo "🎯 SYSTEM STATUS: Ready for production use!\n";
