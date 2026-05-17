<?php
define('APP_INIT', true);
require 'common/db_connect.php';

echo "FEE CONFIG 32:\n";
$stmt = $conn->query('SELECT * FROM tbl_fee_config WHERE id = 32');
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "TRANSPORT SETTINGS:\n";
$stmt = $conn->query('SELECT * FROM tbl_transport_fee_settings WHERE student_id = 1472');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "ALL PAYMENTS FOR 1472:\n";
$stmt = $conn->query('SELECT * FROM tbl_payments WHERE student_id = 1472 ORDER BY id ASC');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
