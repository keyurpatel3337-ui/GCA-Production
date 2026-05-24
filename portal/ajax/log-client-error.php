<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/error_logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $message = $data['message'] ?? 'Unknown client error';
    $context = $data['context'] ?? 'General';
    $level = $data['level'] ?? 'error';

    $log_msg = "Client Log [$level] [$context]: $message";
    if (isset($data['details'])) {
        $log_msg .= " | Details: " . json_encode($data['details']);
    }

    error_log($log_msg);

    // Also use the specialized logger if it's an error
    if ($level === 'error' && function_exists('logError')) {
        logError($log_msg);
    }

    echo json_encode(['status' => 'success']);
}
