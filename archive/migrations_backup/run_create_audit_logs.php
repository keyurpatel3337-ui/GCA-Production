<?php
/**
 * Execute the audit logs table creation
 */

try {
    // Connect to database
    $conn = new PDO('mysql:host=localhost;dbname=counselling;charset=utf8mb4', 'root', 'Counselling@2025');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/create_audit_logs_table.sql');

    // Execute SQL
    $conn->exec($sql);

    echo "✓ Table tbl_audit_logs created successfully!\n";

    // Verify table structure
    $stmt = $conn->query("DESCRIBE tbl_audit_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nTable structure (" . count($columns) . " columns):\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }

    echo "\n✓ Audit log system ready for use!\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
