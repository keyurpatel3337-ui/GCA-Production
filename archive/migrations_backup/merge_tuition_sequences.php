<?php
/**
 * Merge Tuition Fee Part 1 and Part 2 Sequences
 * Both parts will now share the same sequence
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "=== Merging Tuition Fee Sequences ===\n\n";

try {
    $conn->beginTransaction();

    // Get current sequences for both parts
    $stmt = $conn->query("SELECT fee_type, last_sequence FROM tbl_receipt_sequences 
                         WHERE fee_type IN ('tuition_fee_part1', 'tuition_fee_part2')");
    $current_sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Current Sequences:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($current_sequences as $seq) {
        echo "{$seq['fee_type']}: {$seq['last_sequence']}\n";
    }
    echo str_repeat('-', 60) . "\n\n";

    // Get the maximum sequence number from both
    $stmt = $conn->query("SELECT MAX(last_sequence) as max_seq FROM tbl_receipt_sequences 
                         WHERE fee_type IN ('tuition_fee_part1', 'tuition_fee_part2')");
    $max_seq = $stmt->fetchColumn() ?? 0;

    echo "Maximum sequence found: $max_seq\n\n";

    // Check if tuition_fee entry already exists
    $stmt = $conn->query("SELECT id, last_sequence FROM tbl_receipt_sequences 
                         WHERE fee_type = 'tuition_fee' AND school_id IS NULL");
    $tuition_fee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tuition_fee) {
        echo "tuition_fee entry already exists with sequence: {$tuition_fee['last_sequence']}\n";
        echo "Updating to max sequence: $max_seq\n";

        $stmt = $conn->prepare("UPDATE tbl_receipt_sequences 
                               SET last_sequence = ?, updated_at = NOW() 
                               WHERE fee_type = 'tuition_fee' AND school_id IS NULL");
        $stmt->execute([$max_seq]);
    } else {
        echo "Creating new tuition_fee entry with sequence: $max_seq\n";

        $stmt = $conn->prepare("INSERT INTO tbl_receipt_sequences 
                               (fee_type, school_id, last_sequence, academic_year, created_at, updated_at) 
                               VALUES ('tuition_fee', NULL, ?, '2026-2027', NOW(), NOW())");
        $stmt->execute([$max_seq]);
    }

    // Delete the old separate entries
    echo "Removing old tuition_fee_part1 and tuition_fee_part2 entries...\n";
    $stmt = $conn->prepare("DELETE FROM tbl_receipt_sequences 
                           WHERE fee_type IN ('tuition_fee_part1', 'tuition_fee_part2')");
    $stmt->execute();

    $conn->commit();

    echo "\n✅ Sequences merged successfully!\n\n";

    // Show updated sequences
    echo "Updated Receipt Sequences:\n";
    echo str_repeat('=', 80) . "\n";
    printf("%-30s %-15s %-15s\n", 'Fee Type', 'School ID', 'Last Sequence');
    echo str_repeat('=', 80) . "\n";

    $stmt = $conn->query("SELECT fee_type, school_id, last_sequence FROM tbl_receipt_sequences ORDER BY fee_type, school_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf(
            "%-30s %-15s %-15s\n",
            $row['fee_type'],
            $row['school_id'] ?? 'NULL',
            $row['last_sequence']
        );
    }
    echo str_repeat('=', 80) . "\n\n";

    echo "📝 NOTE: Tuition fee part 1 and part 2 now share the same sequence.\n";
    echo "When payment is made for either part, they will get consecutive numbers from the same sequence.\n";

} catch (Exception $e) {
    $conn->rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
