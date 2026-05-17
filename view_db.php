<?php
require_once 'common/constants.php';
require_once 'env.config.php';
require_once 'common/db_connect.php';

$res = $conn->query("SELECT id, question_text, option_a, option_b, option_c, option_d FROM tbl_oes_questions ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    echo "ID: {$r['id']}\n";
    echo "Q: " . substr(strip_tags($r['question_text']), 0, 50) . "...\n";
    echo "A: " . $r['option_a'] . "\n";
    echo "B: " . $r['option_b'] . "\n";
    echo "-------------------\n";
}
