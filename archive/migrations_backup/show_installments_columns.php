<?php
require_once __DIR__ . '/../../common/db_connect.php';

$stmt = $conn->query('SHOW COLUMNS FROM tbl_fee_installments');
echo "Columns in tbl_fee_installments:\n";
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $col['Field'] . "\n";
}
