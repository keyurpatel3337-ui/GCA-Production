<?php
require_once __DIR__ . '/../../../common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../../../common/helpers/report_functions.php';

header('Content-Type: application/json');

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

$type = htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['type'] ?? 'simple'), ENT_QUOTES, 'UTF-8');
$date = htmlspecialchars(preg_replace('/[^0-9\-]/', '', $_POST['date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
$mobile = htmlspecialchars(preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '9998994020'), ENT_QUOTES, 'UTF-8'); // Default target number (can be customized)

if ($type === 'detailed') {
    $result = sendDetailedCollectionSummary($conn, $mobile, $date);
} else {
    $result = sendDailyCollectionSummary($conn, $mobile, $date);
}

echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
