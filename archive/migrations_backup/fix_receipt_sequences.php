<?php
/**
 * Fix and Synchronize Receipt Sequences
 * This will reset sequences based on actual payment records
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "=== Fixing Receipt Sequences ===\n\n";

$conn->beginTransaction();

try {
    // Get actual counts from tbl_payments for each fee type
    echo "Step 1: Analyzing actual payment records...\n";
    echo str_repeat('-', 100) . "\n";

    // School fees by school_id
    $stmt = $conn->query("
        SELECT s.school_id, COUNT(*) as count
        FROM tbl_payments p
        LEFT JOIN tbl_gm_std_registration s ON p.student_id = s.id
        WHERE p.fee_component = 'school_fee'
        GROUP BY s.school_id
    ");

    $school_fee_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $school_fee_counts[$row['school_id']] = $row['count'];
        echo "School Fee (school_id={$row['school_id']}): {$row['count']} payments\n";
    }

    // Other fees - map tuition parts to tuition_fee
    $other_fees = ['trust_facilities_fee', 'hostel_fee', 'transport_fee'];

    foreach ($other_fees as $fee_type) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_payments WHERE fee_component = ?");
        $stmt->execute([$fee_type]);
        $count = $stmt->fetchColumn();
        echo "$fee_type: $count payments\n";

        if ($count > 0) {
            // Update sequence
            $stmt = $conn->prepare("
                UPDATE tbl_receipt_sequences 
                SET last_sequence = ?, updated_at = NOW()
                WHERE fee_type = ? AND school_id IS NULL
            ");
            $stmt->execute([$count, $fee_type]);
            echo "  → Updated sequence to $count\n";
        }
    }

    // Handle tuition fees (part1, part2, and token_fee all use tuition_fee sequence)
    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM tbl_payments 
        WHERE fee_component IN ('tuition_fee_part1', 'tuition_fee_part2', 'token_fee', 'tuition_fee')
    ");
    $tuition_count = $stmt->fetchColumn();
    echo "tuition_fee (all parts combined): $tuition_count payments\n";

    $stmt = $conn->prepare("
        UPDATE tbl_receipt_sequences 
        SET last_sequence = ?, updated_at = NOW()
        WHERE fee_type = 'tuition_fee' AND school_id IS NULL
    ");
    $stmt->execute([$tuition_count]);
    echo "  → Updated tuition_fee sequence to $tuition_count\n";

    // Reset unused sequences
    $stmt = $conn->query("
        UPDATE tbl_receipt_sequences 
        SET last_sequence = 0, updated_at = NOW()
        WHERE fee_type IN ('token_fee', 'transport_fee') AND school_id IS NULL
    ");

    // Update school fee sequences
    foreach ([1, 2] as $school_id) {
        $count = $school_fee_counts[$school_id] ?? 0;
        $stmt = $conn->prepare("
            UPDATE tbl_receipt_sequences 
            SET last_sequence = ?, updated_at = NOW()
            WHERE fee_type = 'school_fee' AND school_id = ?
        ");
        $stmt->execute([$count, $school_id]);
        echo "School Fee (school_id=$school_id): Updated sequence to $count\n";
    }

    echo str_repeat('-', 100) . "\n\n";

    // Show updated sequences
    echo "Step 2: Updated Sequences:\n";
    echo str_repeat('-', 100) . "\n";
    printf("%-30s %-15s %-15s %-15s\n", 'Fee Type', 'School ID', 'Last Sequence', 'Next Receipt');
    echo str_repeat('-', 100) . "\n";

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
    echo str_repeat('-', 100) . "\n";

    $conn->commit();
    echo "\n✅ Receipt sequences have been synchronized with actual payment records!\n";

    // Important note about token fee
    echo "\n📝 IMPORTANT NOTE:\n";
    echo "Token fee payments are recorded as 'tuition_fee_part1' in the database.\n";
    echo "Token fee and tuition_fee_part1 share the same sequence since they are the same fee.\n";
    echo "The 'token_fee' sequence entry is reserved for any standalone token processing.\n";

} catch (Exception $e) {
    $conn->rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Changes have been rolled back.\n";
}
