<?php

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
$base_path = dirname(dirname(__DIR__));
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $config_id = $_POST['id'] ?? 0;

    if (!$config_id) {
        throw new Exception('Configuration ID is required');
    }

    // Check if configuration is assigned to any students
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_student_fee_allocation WHERE fee_config_id = ?");
    $stmt->execute([$config_id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: Configuration is assigned to ' . $count . ' student(s)']);
        exit;
    }

    // Delete configuration (cascades to installments)
    $stmt = $conn->prepare("DELETE FROM tbl_fee_config WHERE id = ?");
    $result = $stmt->execute([$config_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Fee configuration deleted successfully']);
    } else {
        throw new Exception('Failed to delete configuration');
    }
} catch (PDOException $e) {
    error_log("Fee Config Delete Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Fee Config Delete Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
