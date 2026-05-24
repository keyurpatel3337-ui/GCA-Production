<?php
/**
 * Show All Receipt Records Currently Generated
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "=== All Receipt Sequences - Current Status ===\n\n";

// Get all sequences from tbl_receipt_sequences
$stmt = $conn->query("SELECT fee_type, school_id, last_sequence, academic_year, created_at, updated_at 
                      FROM tbl_receipt_sequences 
                      ORDER BY fee_type, school_id");

echo "Receipt Sequence Table:\n";
echo str_repeat('=', 120) . "\n";
printf(
    "%-25s %-12s %-15s %-15s %-20s %-20s\n",
    'Fee Type',
    'School ID',
    'Last Sequence',
    'Academic Year',
    'Created At',
    'Updated At'
);
echo str_repeat('=', 120) . "\n";

$total_receipts = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-25s %-12s %-15s %-15s %-20s %-20s\n",
        $row['fee_type'],
        $row['school_id'] ?? 'NULL',
        $row['last_sequence'],
        $row['academic_year'] ?? 'NULL',
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        date('Y-m-d H:i:s', strtotime($row['updated_at']))
    );
    $total_receipts += $row['last_sequence'];
}
echo str_repeat('=', 120) . "\n";
echo "Total receipts generated across all fee types: $total_receipts\n\n";

// Get breakdown by fee type
echo "Detailed Breakdown:\n";
echo str_repeat('-', 120) . "\n";

$fee_types = [
    'school_fee' => 'School Fee',
    'trust_facilities_fee' => 'Trust Facilities Fee',
    'hostel_fee' => 'Hostel Fee',
    'transport_fee' => 'Transport Fee',
    'tuition_fee_part1' => 'Tuition Fee Part 1',
    'tuition_fee_part2' => 'Tuition Fee Part 2',
    'token_fee' => 'Token Fee'
];

foreach ($fee_types as $fee_type => $label) {
    echo "\n$label ($fee_type):\n";

    if ($fee_type === 'school_fee') {
        // Show separate sequences for each school
        $stmt = $conn->prepare("SELECT school_id, last_sequence FROM tbl_receipt_sequences 
                               WHERE fee_type = ? ORDER BY school_id");
        $stmt->execute([$fee_type]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $school_name = ($row['school_id'] == 1) ? 'GM' : 'SGM';
            echo "  - School $school_name (ID {$row['school_id']}): ";
            echo "Last receipt = {$row['last_sequence']}, Next receipt = " . ($row['last_sequence'] + 1) . "\n";
        }
    } else {
        // Single sequence
        $stmt = $conn->prepare("SELECT last_sequence FROM tbl_receipt_sequences 
                               WHERE fee_type = ? AND school_id IS NULL");
        $stmt->execute([$fee_type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo "  - Last receipt = {$row['last_sequence']}, Next receipt = " . ($row['last_sequence'] + 1) . "\n";
        }
    }
}

echo "\n" . str_repeat('-', 120) . "\n";

// Show which receipts have been generated (1 to last_sequence)
echo "\nReceipts Generated So Far:\n";
echo str_repeat('=', 120) . "\n";

$stmt = $conn->query("SELECT fee_type, school_id, last_sequence FROM tbl_receipt_sequences ORDER BY fee_type, school_id");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fee_display = $row['fee_type'];
    $school_display = '';

    if ($row['school_id'] !== null) {
        $school_name = ($row['school_id'] == 1) ? 'GM' : 'SGM';
        $school_display = " ($school_name)";
    }

    echo "\n$fee_display$school_display:\n";

    if ($row['last_sequence'] > 0) {
        echo "  Generated receipts: ";
        $receipts = [];
        for ($i = 1; $i <= $row['last_sequence']; $i++) {
            $receipts[] = $i;
        }
        echo implode(', ', $receipts) . "\n";
    } else {
        echo "  No receipts generated yet\n";
    }
}

echo "\n" . str_repeat('=', 120) . "\n";
echo "\n✅ Receipt generation system is active and tracking all sequences independently.\n";
