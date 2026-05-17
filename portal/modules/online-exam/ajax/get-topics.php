<?php
$root = dirname(__DIR__, 4);
require_once $root . '/common/constants.php';
require_once DB_CONNECT_FILE;

$chapter_id = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : 0;
$topics = [];

if($chapter_id > 0) {
    $stmt = $conn->prepare("SELECT id, topic_name_english as topic_name FROM tbl_topics WHERE chapter_id = ? AND activated = 1 AND is_deleted = 0 ORDER BY topic_name_english ASC");
    $stmt->execute([$chapter_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($topics);
