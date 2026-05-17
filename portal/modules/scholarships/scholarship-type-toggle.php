<?php

/**
 * Scholarship Type Toggle Handler
 * Handles toggling the active status of scholarship types
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Auth check
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: scholarship-types.php');
    exit;
}

$type_id = $_POST['id'] ?? 0;

if (!$type_id) {
    set_flash_message('error', 'Invalid scholarship type ID');
    header('Location: scholarship-types.php');
    exit;
}

try {
    $api = new APIClient();
    $response = $api->post('scholarships/type-toggle', ['id' => $type_id]);

    if ($response && isset($response['success']) && $response['success']) {
        set_flash_message('success', $response['message'] ?? 'Scholarship type status updated successfully');
    } else {
        set_flash_message('error', $response['error'] ?? $response['message'] ?? 'Failed to update scholarship type status');
    }
} catch (Exception $e) {
    set_flash_message('error', 'An error occurred while updating the scholarship type');
}

header('Location: scholarship-types.php');
exit;