<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
    $type_name = trim($_POST['type_name']);
    $type_code = trim(strtoupper($_POST['type_code']));
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];

    try {
        if ($type_id > 0) {
            // Update existing type
            $stmt = $conn->prepare("UPDATE tbl_scholarship_types 
                                   SET type_name = ?, type_code = ?, description = ?, is_active = ? 
                                   WHERE id = ?");
            $stmt->execute([$type_name, $type_code, $description, $is_active, $type_id]);
            sendSuccessResponse(['id' => $type_id], 'Scholarship type updated successfully!');
        } else {
            // Insert new type
            $stmt = $conn->prepare("INSERT INTO tbl_scholarship_types 
                                   (type_name, type_code, description, is_active, created_by) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$type_name, $type_code, $description, $is_active, $user_id]);
            $new_id = $conn->lastInsertId();
            sendSuccessResponse(['id' => $new_id], 'Scholarship type added successfully!');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Scholarship Type");
        if ($e->getCode() == 23000) {
            sendErrorResponse('Scholarship type name or code already exists!', 400);
        } else {
            sendErrorResponse('Error saving scholarship type. Please try again.', 500);
        }
    }
}

sendErrorResponse('Invalid request method', 405);
