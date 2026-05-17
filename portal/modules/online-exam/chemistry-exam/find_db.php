<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

$stmt = $conn->query("SHOW DATABASES");
$dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($dbs as $db) {
    try {
        $stmt2 = $conn->query("SHOW TABLES IN `$db` LIKE 'tbl_oes_question'");
        if ($stmt2->rowCount() > 0) {
            echo "Found in DB: $db\n";
        }
    } catch (Exception $e) {}
}
