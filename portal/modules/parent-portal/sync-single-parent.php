<?php
/**
 * Sync Single Parent Account
 * Creates a parent account for a specific mobile number and redirects back.
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'parent_functions.php';
require_once HELPERS_PATH . 'flash_message.php';

// Check access - Only Super Admin and Principal
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$mobile = $_GET['mobile'] ?? '';

if (empty($mobile)) {
    set_flash_message('error', "Mobile number is missing.");
    header('Location: manage-parents.php?tab=pending');
    exit;
}

try {
    if (createParentAccount($mobile, $conn)) {
        set_flash_message('success', "Parent account created/linked successfully for mobile: $mobile");
    } else {
        set_flash_message('error', "Failed to create parent account for mobile: $mobile");
    }
} catch (PDOException $e) {
    set_flash_message('error', "Database error: " . $e->getMessage());
}

header('Location: manage-parents.php?tab=pending');
exit;
