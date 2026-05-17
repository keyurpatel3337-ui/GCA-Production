<?php
include 'c:/xampp/htdocs/GCA-Production/common/constants.php';
include ENV_CONFIG_FILE;
include DB_CONNECT_FILE;
$q = $conn->query("SELECT * FROM tbl_oes_question_types WHERE status = 1")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($q);
?>
