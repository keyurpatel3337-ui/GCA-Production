<?php
require 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require DB_CONNECT_FILE;

try {
    $conn->query('SET FOREIGN_KEY_CHECKS = 0');
    $conn->query('TRUNCATE TABLE tbl_oes_questions');
    $conn->query('TRUNCATE TABLE tbl_oes_exams');
    $conn->query('TRUNCATE TABLE tbl_oes_exam_questions');
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    echo "Tables truncated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
