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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $split_id = $_POST['split_id'] ?? null;
    $split_name = trim($_POST['split_name'] ?? '');
    $split_percentage = $_POST['split_percentage'] ?? null;
    $split_order = $_POST['split_order'] ?? null;
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    $errors = [];

    if (empty($split_id)) {
        $errors[] = "Invalid split ID.";
    }

    if (empty($split_name)) {
        $errors[] = "Split name is required.";
    }

    if ($split_percentage === null || $split_percentage < 0 || $split_percentage > 100) {
        $errors[] = "Valid percentage (0-100) is required.";
    }

    if (empty($split_order) || $split_order < 1) {
        $errors[] = "Valid order number is required.";
    }

    // Check if order already exists for another record
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_fee_split_configuration 
                                WHERE split_order = :order AND is_active = 1 AND id != :id");
        $stmt->execute(['order' => $split_order, 'id' => $split_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Another active split with this order already exists.";
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Check Duplicate Split Order");
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE tbl_fee_split_configuration 
                SET split_name = :split_name, 
                    split_percentage = :split_percentage, 
                    split_order = :split_order, 
                    description = :description, 
                    is_active = :is_active 
                WHERE id = :id");

            $stmt->execute([
                'split_name' => $split_name,
                'split_percentage' => $split_percentage,
                'split_order' => $split_order,
                'description' => $description,
                'is_active' => $is_active,
                'id' => $split_id
            ]);

            set_flash_message('success', "Fee split updated successfully!");
        } catch (PDOException $e) {
            logDatabaseError($e, "Update Fee Split");
            set_flash_message('error', "Failed to update fee split. Please try again.");
        }
    } else {
        set_flash_message('error', implode('<br>', $errors));
    }
}

header('Location: fee-splits.php');
exit;
