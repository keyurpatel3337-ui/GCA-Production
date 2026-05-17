<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

$sql = file_get_contents('schema.sql');
try {
    $conn->exec($sql);
    echo "Schema updated successfully!";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
