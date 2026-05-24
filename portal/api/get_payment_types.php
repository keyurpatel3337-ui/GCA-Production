<?php
require_once __DIR__ . '/session_config.php';
require_once dirname(__DIR__) . '/common/constants.php';
require_once DB_CONNECT_FILE;

try {
    $stmt = $conn->prepare("SELECT DISTINCT payment_type FROM tbl_payments ORDER BY payment_type");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($types);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>