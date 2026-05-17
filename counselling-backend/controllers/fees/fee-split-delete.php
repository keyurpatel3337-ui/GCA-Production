<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
session_start();
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Unauthorized access!");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$split_id = $_GET['id'] ?? null;

if ($split_id) {
    try {
        $stmt = $conn->prepare("DELETE FROM tbl_fee_split_configuration WHERE id = :id");
        $stmt->execute(['id' => $split_id]);

        set_flash_message('success', "Fee split deleted successfully!");
    } catch (PDOException $e) {
        logDatabaseError($e, "Delete Fee Split");
        set_flash_message('error', "Failed to delete fee split. Please try again.");
    }
} else {
    set_flash_message('error', "Invalid split ID.");
}

header('Location: fee-splits.php');
exit;
