<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

echo "--- Standards ---\n";
$res = $conn->query("SELECT stdid, stdtext FROM standard");
print_r($res->fetchAll(PDO::FETCH_ASSOC));

echo "--- Subjects (Physics) ---\n";
$res = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects WHERE subject_name LIKE '%Physics%'");
print_r($res->fetchAll(PDO::FETCH_ASSOC));

echo "--- Chapters Table Structure ---\n";
$res = $conn->query("DESC chapters");
print_r($res->fetchAll(PDO::FETCH_ASSOC));

echo "--- Topics Table Structure ---\n";
$res = $conn->query("DESC tbl_topics");
print_r($res->fetchAll(PDO::FETCH_ASSOC));
