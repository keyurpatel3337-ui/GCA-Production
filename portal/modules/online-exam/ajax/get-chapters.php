<?php
$root = dirname(__DIR__, 4);
require_once $root . '/common/constants.php';
require_once DB_CONNECT_FILE;

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$chapters = [];

if($subject_id > 0) {
    $stmt = $conn->prepare("SELECT chpid, chapter FROM tbl_chapters WHERE subid = ? AND activated = 1 AND is_deleted = 0 ORDER BY chapter_number ASC, chpid ASC");
    $stmt->execute([$subject_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($chapters);
