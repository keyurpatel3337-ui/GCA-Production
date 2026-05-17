<?php
/**
 * Verify Receipt Sequences Setup
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "Current Receipt Sequences:\n";
echo str_repeat('-', 80) . "\n";
printf("%-25s %-15s %-15s %-15s\n", 'Fee Type', 'School ID', 'Last Sequence', 'Academic Year');
echo str_repeat('-', 80) . "\n";

$stmt = $conn->query('SELECT fee_type, school_id, last_sequence, academic_year FROM tbl_receipt_sequences ORDER BY fee_type, school_id');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-25s %-15s %-15s %-15s\n",
        $row['fee_type'],
        $row['school_id'] ?? 'NULL',
        $row['last_sequence'],
        $row['academic_year'] ?? 'NULL'
    );
}
echo str_repeat('-', 80) . "\n";
