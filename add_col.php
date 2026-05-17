<?php
define('APP_INIT', true);
require 'common/db_connect.php';

try {
    $stmt = $conn->query('ALTER TABLE tbl_payment_orders ADD COLUMN component_breakdown TEXT NULL AFTER split_amounts');
    echo 'Column added.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
