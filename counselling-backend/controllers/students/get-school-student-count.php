<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Initialize Operation class
$dbOps = new Operation();

if (!hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$school_id = intval(htmlspecialchars($_POST['school_id'] ?? '0', ENT_QUOTES, 'UTF-8'));

if ($school_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid school ID']);
    exit;
}

try {
    $result = $dbOps->select('tbl_enrolled_students', ['COUNT(*) as count'], ['school_id' => $school_id]);
    $count = $result[0]['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'count' => $count
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (PDOException $e) {
    logError("Get School Student Count Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
