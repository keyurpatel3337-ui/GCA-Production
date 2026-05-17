<?php
require 'common/constants.php';
require DB_CONNECT_FILE;
$stmt = $conn->query('DESCRIBE standard');
print_r($stmt->fetchAll());
?>
