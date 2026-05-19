<?php
require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once DB_CONNECT_FILE;

try {
    $stmt = $conn->prepare("SELECT id, option_a, option_b, option_c, option_d FROM tbl_oes_questions WHERE id = 20");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($row, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
