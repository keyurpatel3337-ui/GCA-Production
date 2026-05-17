<?php
/**
 * Drop and recreate audit logs table with comprehensive schema
 */

try {
    // Connect to database
    $conn = new PDO('mysql:host=localhost;dbname=counselling;charset=utf8mb4', 'root', 'Counselling@2025');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Dropping existing tbl_audit_logs table...\n";
    $conn->exec("DROP TABLE IF EXISTS tbl_audit_logs");
    echo "✓ Table dropped\n\n";

    // Read and execute SQL file
    echo "Creating comprehensive tbl_audit_logs table...\n";
    $sql = file_get_contents(__DIR__ . '/create_audit_logs_table.sql');
    $conn->exec($sql);

    echo "✓ Table tbl_audit_logs created successfully!\n\n";

    // Verify table structure
    $stmt = $conn->query("DESCRIBE tbl_audit_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Table structure (" . count($columns) . " columns):\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }

    // Show indexes
    $stmt = $conn->query("SHOW INDEX FROM tbl_audit_logs");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $uniqueIndexNames = array_unique(array_column($indexes, 'Key_name'));

    echo "\nIndexes (" . count($uniqueIndexNames) . " indexes):\n";
    foreach ($uniqueIndexNames as $indexName) {
        echo "  - {$indexName}\n";
    }

    echo "\n✓ Comprehensive audit log system ready!\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
