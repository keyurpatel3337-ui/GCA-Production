<?php
require 'common/constants.php';
require DB_CONNECT_FILE;
$stmt = $conn->query('SELECT id, role_name FROM tbl_roles');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ': ' . $row['role_name'] . PHP_EOL;
}
?>
