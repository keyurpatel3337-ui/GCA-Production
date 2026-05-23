<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

$stmt = $conn->query("SELECT id, question_text, option_a, option_b, option_c, option_d FROM tbl_oes_questions");
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "SEARCH RESULTS FOR DEGREE OR HASH:\n";
echo "====================================\n";
foreach ($questions as $q) {
    $found = false;
    foreach ($q as $k => $v) {
        if ($k === 'id') continue;
        if (strpos($v, '##') !== false || strpos($v, '°') !== false || strpos($v, 'deg') !== false || strpos($v, 'C') !== false) {
            $found = true;
        }
    }
    if ($found) {
        echo "ID: " . $q['id'] . "\n";
        echo "Q: " . substr(strip_tags($q['question_text']), 0, 50) . "\n";
        echo "A: " . $q['option_a'] . "\n";
        echo "B: " . $q['option_b'] . "\n";
        echo "C: " . $q['option_c'] . "\n";
        echo "D: " . $q['option_d'] . "\n";
        echo "------------------------------------\n";
    }
}
