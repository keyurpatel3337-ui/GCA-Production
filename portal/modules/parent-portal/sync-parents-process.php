<?php
/**
 * Sync Parent Accounts
 * Loops through all students and ensures a parent account exists for each unique mobile number.
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

try {
    // Get all unique parent mobile numbers from students
    $stmt = $conn->query("SELECT DISTINCT parent_mob FROM tbl_gm_std_registration WHERE parent_mob IS NOT NULL AND parent_mob != ''");
    $mobiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $success_count = 0;
    $already_existed = 0;

    foreach ($mobiles as $mob) {
        // Check if already exists in tbl_parent_login
        $check = $conn->prepare("SELECT id FROM tbl_parent_login WHERE mobile_number = ?");
        $check->execute([$mob]);
        if ($check->fetch()) {
            $already_existed++;
            continue;
        }

        // Create account
        if (createParentAccount($mob, $conn)) {
            $success_count++;
        }
    }

    set_flash_message('success', "Sync completed: $success_count new parent accounts created. $already_existed already existed.");
} catch (PDOException $e) {
    set_flash_message('error', "Sync failed: " . $e->getMessage());
}

header('Location: manage-parents.php');
exit;
