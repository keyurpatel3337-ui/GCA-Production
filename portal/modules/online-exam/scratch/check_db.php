<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

$q = $conn->query('SELECT id, question_type_id, option_a, option_b, question_text FROM tbl_oes_questions ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($q);
