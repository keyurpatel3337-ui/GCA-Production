<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!function_exists('hasAnyRole') || !hasAnyRole([ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT, ROLE_PRINCIPLE, ROLE_WALLET_MANAGER])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$query = $_GET['query'] ?? '';
if (strlen($query) < 2) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

if (!isset($conn)) {
    require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/db_connect.php';
}
try {
    $searchTerm = "%$query%";
    // Searching students in tbl_gm_std_registration
    // Removing admission_confirmed = 1 to broaden search if needed, or keeping it but ensuring it exists
    $stmt = $conn->prepare("
        SELECT id, student_name, surname, fathers_name, mob as phone 
        FROM tbl_gm_std_registration 
        WHERE (student_name LIKE :q1 OR id LIKE :q2 OR mob LIKE :q3 OR surname LIKE :q4) 
        LIMIT 10
    ");
    $stmt->execute([
        'q1' => $searchTerm,
        'q2' => $searchTerm,
        'q3' => $searchTerm,
        'q4' => $searchTerm
    ]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($students as $student) {
        $fullName = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . ($student['fathers_name'] ?? ''));
        $initials = strtoupper(substr($student['surname'] ?? $student['student_name'], 0, 1) . substr($student['student_name'], 0, 1));

        $results[] = [
            'id' => $student['id'],
            'name' => $fullName,
            'class' => 'Student', // Could fetch from enrollment if needed, but 'Student' is fine for now
            'initials' => $initials
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $results]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
