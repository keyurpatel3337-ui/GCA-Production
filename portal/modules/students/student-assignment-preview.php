<?php

/**
 * Student Assignment Preview API
 * Looks up students by mobile numbers for bulk assignment preview
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$mobile_numbers = $_POST['mobile_numbers'] ?? '';

if (empty($mobile_numbers)) {
    echo json_encode(['success' => false, 'message' => 'No mobile numbers provided']);
    exit;
}

// Parse comma-separated mobile numbers
$mobiles = array_map('trim', explode(',', $mobile_numbers));
$mobiles = array_filter($mobiles, function ($m) {
    return !empty($m);
});

if (empty($mobiles)) {
    echo json_encode(['success' => false, 'message' => 'No valid mobile numbers found']);
    exit;
}

try {
    // Create placeholders for query
    $placeholders = str_repeat('?,', count($mobiles) - 1) . '?';

    // Query students by mobile numbers
    $query = "SELECT s.id, s.surname, s.student_name, s.fathers_name,
              CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as full_name,
              s.mob, s.counsellor_id, u.name as counsellor_name
              FROM tbl_gm_std_registration s
              LEFT JOIN tbl_users u ON s.counsellor_id = u.id
              WHERE s.mob IN ($placeholders)
              ORDER BY s.id ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute($mobiles);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Find which mobile numbers were not found
    $foundMobiles = array_column($students, 'mob');
    $notFound = array_diff($mobiles, $foundMobiles);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'not_found' => array_values($notFound),
        'total_searched' => count($mobiles),
        'total_found' => count($students)
    ]);
} catch (PDOException $e) {
    logDatabaseError($e, "Student Assignment Preview");
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

