<?php
require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once DB_CONNECT_FILE;

try {
    $stmt = $conn->query("DESCRIBE tbl_oes_exams");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
