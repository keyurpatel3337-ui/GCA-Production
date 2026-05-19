<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once DB_CONNECT_FILE;

try {
    $tables = ['tbl_oes_exams', 'tbl_oes_student_exams'];
    foreach ($tables as $t) {
        echo "=== Structure of $t ===\n";
        $stmt = $conn->prepare("DESCRIBE $t");
        $stmt->execute();
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "{$c['Field']} - {$c['Type']} - Null: {$c['Null']} - Key: {$c['Key']} - Default: {$c['Default']}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
