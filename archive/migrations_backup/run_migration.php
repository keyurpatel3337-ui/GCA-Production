<?php
/**
 * Run Receipt Sequences Migration
 * This script migrates existing receipt counts to the new tbl_receipt_sequences table
 */

// Include database connection
require_once __DIR__ . '/../../common/db_connect.php';

echo "=== Receipt Sequences Migration ===\n\n";

try {
    // Read the migration SQL file
    $sql = file_get_contents(__DIR__ . '/migrate_existing_receipt_counts.sql');

    if ($sql === false) {
        die("ERROR: Could not read migration file\n");
    }

    echo "Starting migration...\n\n";

    // Begin transaction
    $conn->beginTransaction();

    // Split SQL by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($stmt) {
            return !empty($stmt) &&
                !preg_match('/^--/', $stmt) &&
                !preg_match('/^\/\*/', $stmt);
        }
    );

    $count = 0;
    foreach ($statements as $statement) {
        if (empty(trim($statement)))
            continue;

        echo "Executing statement " . (++$count) . "...\n";
        $conn->exec($statement);
        echo "✓ Success\n\n";
    }

    // Commit transaction
    $conn->commit();

    echo "\n=== Migration Completed Successfully ===\n\n";

    // Display current sequences
    echo "Current Receipt Sequences:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-25s %-15s %-15s %-15s\n", "Fee Type", "School ID", "Last Sequence", "Academic Year");
    echo str_repeat("-", 80) . "\n";

    $stmt = $conn->query("SELECT fee_type, school_id, last_sequence, academic_year FROM tbl_receipt_sequences ORDER BY fee_type, school_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf(
            "%-25s %-15s %-15s %-15s\n",
            $row['fee_type'],
            $row['school_id'] ?? 'NULL',
            $row['last_sequence'],
            $row['academic_year'] ?? 'NULL'
        );
    }
    echo str_repeat("-", 80) . "\n";

} catch (Exception $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Migration failed and rolled back.\n";
    exit(1);
}
