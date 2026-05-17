<?php
require_once __DIR__ . '/../../common/db_connect.php';

// Check current column definition
$stmt = $conn->query("SHOW COLUMNS FROM tbl_receipt_sequences WHERE Field = 'fee_type'");
$column = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Current fee_type column: " . $column['Type'] . "\n\n";

// Alter column to be longer
echo "Extending fee_type column to VARCHAR(50)...\n";
$conn->exec("ALTER TABLE tbl_receipt_sequences MODIFY COLUMN fee_type VARCHAR(50) NOT NULL");

echo "✅ Column updated successfully!\n";
