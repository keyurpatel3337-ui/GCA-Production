<?php

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || empty($input['id'])) {
        sendErrorResponse('Scholarship type ID is required');
    }

    $id = (int) $input['id'];

    // Check if user has permission (only Super Admin can hard delete)
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        sendErrorResponse('You do not have permission to permanently delete scholarship types', 403);
    }

    // Check if type exists
    $type = $dbOps->select('tbl_scholarship_types', ['id', 'type_name'], ['id' => $id]);

    if (empty($type)) {
        sendErrorResponse('Scholarship type not found');
    }

    // Check if type is being used in scholarship rules
    $rulesUsingType = $dbOps->select('tbl_scholarship_rules', ['id'], ['scholarship_type_id' => $id]);
    $rulesCount = count($rulesUsingType);

    if ($rulesCount > 0) {
        sendErrorResponse("Cannot delete this scholarship type. It is being used by {$rulesCount} scholarship rule(s). Please delete or update those rules first.");
    }

    // Permanently delete the scholarship type
    $deleted = $dbOps->delete('tbl_scholarship_types', ['id' => $id]);

    if ($deleted) {
        sendSuccessResponse([
            'message' => 'Scholarship type permanently deleted successfully'
        ]);
    } else {
        sendErrorResponse('Failed to delete scholarship type');
    }

} catch (Exception $e) {
    error_log("Scholarship Type Hard Delete Error: " . $e->getMessage());
    sendErrorResponse('Error: ' . $e->getMessage(), 500);
}
