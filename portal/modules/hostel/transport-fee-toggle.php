<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    $_SESSION['error_msg'] = "You don't have permission to perform this action.";
    header('Location: transport-fee-config.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Call API to toggle status
    $api = new APIClient();
    $response = $api->get('transport/fee-toggle', ['id' => $id]);

    if ($response && isset($response['success']) && $response['success']) {
        $_SESSION['success_msg'] = $response['message'] ?? 'Status updated successfully!';
    } else {
        $_SESSION['error_msg'] = $response['message'] ?? 'Failed to update status.';
    }
} else {
    $_SESSION['error_msg'] = 'Invalid request - ID required.';
}

header('Location: transport-fee-config.php');
exit;
