<?php
define('APP_INIT', true);
require 'common/db_connect.php';

echo "PAYMENTS:\n";
$stmt = $conn->query('SELECT * FROM tbl_payments WHERE student_id = 1472 ORDER BY id DESC LIMIT 5');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "ALLOCATION:\n";
$stmt = $conn->query('SELECT * FROM tbl_student_fee_allocation WHERE student_id = 1472 ORDER BY id DESC LIMIT 10');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
