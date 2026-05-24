<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php'; 
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../common/helpers/report_functions.php';

// Path Trace:
// File: portal/api/whatsapp/send-daily-report.php
// session_config: portal/session_config.php -> ../../session_config.php (Correct)
// helpers: root/common/helpers/... -> ../../../common/helpers/... (Correct)

error_log("WhatsApp Report API Hit: Type=" . ($_POST['type'] ?? 'N/A') . ", Mobile=" . ($_POST['mobile'] ?? 'N/A'));

header('Content-Type: application/json');

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

$type = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['type'] ?? '');
$mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
$date = preg_replace('/[^0-9\-]/', '', $_POST['date'] ?? date('Y-m-d'));

if (empty($mobile) || strlen($mobile) < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid mobile number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

try {
    $result = ['success' => false, 'message' => 'Invalid report type'];

    if ($type === 'simple') {
        $result = sendDailyCollectionSummary($conn, $mobile, $date);
    } elseif ($type === 'detailed') {
        $result = sendDetailedCollectionSummary($conn, $mobile, $date);
    } elseif ($type === 'coursewise') {
        $result = sendCourseWiseCollectionSummary($conn, $mobile, $date);
    }

    if ($result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Report sent successfully to ' . htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8')
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send report: ' . htmlspecialchars($result['error'] ?? 'Unknown error', ENT_QUOTES, 'UTF-8')
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'System error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
