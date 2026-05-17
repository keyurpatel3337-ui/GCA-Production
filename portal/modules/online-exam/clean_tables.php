<?php
require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $conn->exec("TRUNCATE TABLE tbl_oes_questions;");
    $conn->exec("TRUNCATE TABLE tbl_oes_exams;");
    $conn->exec("TRUNCATE TABLE tbl_oes_exam_questions;");
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Tables cleaned successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
