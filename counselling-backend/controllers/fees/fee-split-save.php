<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
session_start();
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

$dbOps = new DatabaseOperations();

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Unauthorized access!");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $split_name = trim($_POST['split_name'] ?? '');
    $split_percentage = $_POST['split_percentage'] ?? null;
    $split_order = $_POST['split_order'] ?? null;
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $created_by = $_SESSION['user_id'];

    // Validation
    $errors = [];

    if (empty($split_name)) {
        $errors[] = "Split name is required.";
    }

    if ($split_percentage === null || $split_percentage < 0 || $split_percentage > 100) {
        $errors[] = "Valid percentage (0-100) is required.";
    }

    if (empty($split_order) || $split_order < 1) {
        $errors[] = "Valid order number is required.";
    }

    // Check if order already exists
    try {
        $count = $dbOps->count('tbl_fee_split_configuration', ['split_order' => $split_order, 'is_active' => 1]);
        if ($count > 0) {
            $errors[] = "An active split with this order already exists.";
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Check Duplicate Split Order");
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_fee_split_configuration 
                (split_name, split_percentage, split_order, description, is_active, created_by) 
                VALUES (:split_name, :split_percentage, :split_order, :description, :is_active, :created_by)");

            $stmt->execute([
                'split_name' => $split_name,
                'split_percentage' => $split_percentage,
                'split_order' => $split_order,
                'description' => $description,
                'is_active' => $is_active,
                'created_by' => $created_by
            ]);

            set_flash_message('success', "Fee split added successfully!");
        } catch (PDOException $e) {
            logDatabaseError($e, "Add Fee Split");
            set_flash_message('error', "Failed to add fee split. Please try again.");
        }
    } else {
        set_flash_message('error', implode('<br>', $errors));
    }
}

header('Location: fee-splits.php');
exit;
