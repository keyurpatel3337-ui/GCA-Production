<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

$stmt = $conn->query("SHOW COLUMNS FROM tbl_oes_questions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
