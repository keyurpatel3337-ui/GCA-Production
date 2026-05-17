<?php

/**
 * Enrollment Settings API
 * Get/Update enrollment-related settings
 */

require_once __DIR__ . '/../../common/settings_helper.php';
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

header('Content-Type: application/json');

if (!isset($conn)) {
    sendErrorResponse('Database connection not available', 500);
}

// Get settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $auto_assign_enabled = getSetting($conn, 'auto_assign_division_on_enrollment', false);

        sendSuccessResponse([
            'auto_assign_division_on_enrollment' => $auto_assign_enabled
        ]);
    } catch (Exception $e) {
        sendErrorResponse('Failed to retrieve settings', 500);
    }
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user has permission (Super Admin or Principle only)
    if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
        sendErrorResponse('Access denied. Only Super Admin and Principle can update these settings.', 403);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $auto_assign_enabled = isset($input['auto_assign_division_on_enrollment']) && $input['auto_assign_division_on_enrollment'] ? 1 : 0;

        // Check if setting exists
        $exists = $dbOps->selectOne('tbl_system_settings', ['id'], ['setting_key' => 'auto_assign_division_on_enrollment']);

        if ($exists) {
            // Update existing setting
            $stmt = $conn->prepare("UPDATE tbl_system_settings 
                                   SET setting_value = ?, updated_at = NOW() 
                                   WHERE setting_key = ?");
            $stmt->execute([$auto_assign_enabled, 'auto_assign_division_on_enrollment']);
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO tbl_system_settings 
                                   (setting_key, setting_value, setting_type, category, description, created_at) 
                                   VALUES (?, ?, 'boolean', 'enrollment', ?, NOW())");
            $stmt->execute([
                'auto_assign_division_on_enrollment',
                $auto_assign_enabled,
                'Enable automatic division and roll number assignment during enrollment'
            ]);
        }

        sendSuccessResponse(
            ['auto_assign_division_on_enrollment' => (bool)$auto_assign_enabled],
            'Settings updated successfully'
        );
    } catch (Exception $e) {
        sendErrorResponse('Failed to update settings: ' . $e->getMessage(), 500);
    }
}
