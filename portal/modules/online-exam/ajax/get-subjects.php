<?php
$root = dirname(__DIR__, 4);
require_once $root . '/common/constants.php';
require_once DB_CONNECT_FILE;

$standard_id = isset($_GET['standard_id']) ? (int)$_GET['standard_id'] : 0;
$subjects = [];

if($standard_id > 0) {
    if (in_array($standard_id, [11, 12, 13])) {
        $stmt = $conn->prepare("SELECT MIN(id) as id, subject_name 
                                FROM tbl_subjects 
                                WHERE standard_id IN (SELECT id FROM tbl_courses WHERE standard = ?) 
                                  AND activated = 1 
                                  AND is_deleted = 0 
                                GROUP BY subject_name 
                                ORDER BY subject_name ASC");
        $stmt->execute([$standard_id]);
    } else {
        $stmt = $conn->prepare("SELECT id, subject_name FROM tbl_subjects WHERE standard_id = ? AND activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC");
        $stmt->execute([$standard_id]);
    }
} else {
    $stmt = $conn->prepare("SELECT MIN(id) as id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 GROUP BY subject_name ORDER BY subject_name ASC");
    $stmt->execute();
}
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($subjects);
