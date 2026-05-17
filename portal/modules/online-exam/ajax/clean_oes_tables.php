<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    echo "Cleaning tables...\n";
    
    $tables = ['tbl_oes_questions', 'tbl_oes_exams', 'tbl_oes_exam_questions'];
    
    foreach ($tables as $table) {
        $conn->exec("TRUNCATE TABLE $table");
        echo "Truncated $table\n";
    }
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Done.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
