<?php

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

try {
    // Get JSON data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
        sendErrorResponse('No configurations selected for deletion');
    }

    $ids = array_filter($input['ids'], 'is_numeric');

    if (empty($ids)) {
        sendErrorResponse('Invalid configuration IDs provided');
    }

    // Check if user has permission to delete
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
        sendErrorResponse('You do not have permission to delete fee configurations', 403);
    }

    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($ids as $id) {
        try {
            // Check if config exists
            $config = $dbOps->select('tbl_fee_config', ['id', 'course_name'], ['id' => $id]);

            if (empty($config)) {
                $errors[] = "Configuration ID $id not found";
                $errorCount++;
                continue;
            }

            // Delete the configuration
            $deleted = $dbOps->delete('tbl_fee_config', ['id' => $id]);

            if ($deleted) {
                $successCount++;
            } else {
                $errors[] = "Failed to delete configuration ID $id";
                $errorCount++;
            }
        } catch (Exception $e) {
            $errors[] = "Error deleting ID $id: " . $e->getMessage();
            $errorCount++;
        }
    }

    if ($errorCount > 0) {
        sendErrorResponse(
            "Deleted $successCount configuration(s). Failed: $errorCount. Errors: " . implode(', ', $errors)
        );
    } else {
        sendSuccessResponse([
            'message' => "Successfully deleted $successCount fee configuration(s)",
            'deleted_count' => $successCount
        ]);
    }

} catch (Exception $e) {
    sendErrorResponse('Error: ' . $e->getMessage(), 500);
}
