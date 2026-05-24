<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';

$log_dir = dirname(__DIR__) . '/scratch';
if (!is_dir($log_dir)) { mkdir($log_dir, 0777, true); }

$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    file_put_contents($log_dir . '/edit_save_log.txt', "[" . date('Y-m-d H:i:s') . "] JS EVENT: " . $input['event'] . "\nData: " . print_r($input['data'], true) . "\n\n", FILE_APPEND);
}
echo json_encode(['status' => 'ok']);
