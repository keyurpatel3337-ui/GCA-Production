<?php
require_once __DIR__ . '/../../../common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../../../common/helpers/report_functions.php';

header('Content-Type: application/json');

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_POST['type'] ?? 'simple';
$date = $_POST['date'] ?? date('Y-m-d');
$mobile = $_POST['mobile'] ?? '9998994020'; // Default target number (can be customized)

if ($type === 'detailed') {
    $result = sendDetailedCollectionSummary($conn, $mobile, $date);
} else {
    $result = sendDailyCollectionSummary($conn, $mobile, $date);
}

echo json_encode($result);
