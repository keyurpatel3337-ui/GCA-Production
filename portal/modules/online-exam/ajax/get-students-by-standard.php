<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';

header('Content-Type: application/json');

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    echo json_encode([]);
    exit;
}

$standard_id = intval($_GET['standard_id'] ?? 0);

if ($standard_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, CONCAT(COALESCE(student_name, ''), ' ', COALESCE(surname, '')) as name 
        FROM tbl_gm_std_registration 
        WHERE standard = ? AND status = 1 
        ORDER BY student_name ASC
    ");
    $stmt->execute([$standard_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
} catch (Exception $e) {
    echo json_encode([]);
}
