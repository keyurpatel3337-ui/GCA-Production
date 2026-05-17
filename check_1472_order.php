<?php
define('APP_INIT', true);
require 'common/db_connect.php';
$stmt = $conn->query("SELECT * FROM tbl_payment_orders WHERE transaction_id = 'GM20260515181900C318091517'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
