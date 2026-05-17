<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../session_config.php';
require_once dirname(__DIR__, 2) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../../common/helpers/security_helper.php';

if (!isset($_SESSION['pending_auth_user_id'])) {
    echo json_encode(['authorized' => false, 'error' => 'No pending session']);
    exit;
}

$user_id = $_SESSION['pending_auth_user_id'];
$device_uuid = getDeviceUUID();

$authorized = isDeviceAuthorized($conn, $user_id, $device_uuid);

echo json_encode(['authorized' => $authorized]);
