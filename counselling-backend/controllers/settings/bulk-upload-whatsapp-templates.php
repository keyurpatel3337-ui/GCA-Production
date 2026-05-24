<?php

/**
 * Bulk Upload WhatsApp Templates
 * Path: counselling-backend/controllers/settings/bulk-upload-whatsapp-templates.php
 * Note: This file is included in whatsapp_controller.php which already has bootstrap and auth
 */

header('Content-Type: application/json');

try {
    error_log("WhatsApp Bulk Upload - Started by user: " . ($_SESSION['user_id'] ?? 'unknown'));

    // Validate file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['csv_file']['error'] ?? 'unknown';
        error_log("WhatsApp Bulk Upload - File upload error: " . $error_code);
        sendErrorResponse('No file uploaded or upload error occurred');
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $filename = $_FILES['csv_file']['name'] ?? 'unknown';
    error_log("WhatsApp Bulk Upload - Processing file: " . $filename);

    if (!file_exists($file)) {
        error_log("WhatsApp Bulk Upload - File not found: " . $file);
        sendErrorResponse('Uploaded file not found');
    }

    // Open and read CSV
    $handle = fopen($file, 'r');
    if (!$handle) {
        error_log("WhatsApp Bulk Upload - Could not open file: " . $file);
        sendErrorResponse('Could not open CSV file');
    }

    // Read and validate headers
    $headers = fgetcsv($handle);

    // Remove BOM if present
    if (!empty($headers[0])) {
        $headers[0] = str_replace("\xEF\xBB\xBF", '', $headers[0]);
    }

    $required_headers = [
        'template_name',
        'template_category',
        'body_text',
        'variable_names',
        'header_type',
        'approval_status',
        'is_active'
    ];

    $missing = array_diff($required_headers, $headers);
    if (!empty($missing)) {
        fclose($handle);
        error_log("WhatsApp Bulk Upload - Missing columns: " . implode(', ', $missing));
        sendErrorResponse('Missing required columns: ' . implode(', ', $missing));
    }

    error_log("WhatsApp Bulk Upload - CSV headers validated successfully");

    // WhatsApp API configuration is statically set in code
    // Set provider_id to NULL as it's not used
    $provider_id = null;

    error_log("WhatsApp Bulk Upload - Using static WhatsApp configuration");

    // Process rows
    $row_number = 1;
    $success_count = 0;
    $error_count = 0;
    $errors = [];

    $conn->beginTransaction();

    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;

        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        try {
            $data = array_combine($headers, $row);

            // Validate required fields
            if (empty($data['template_name'])) {
                throw new Exception('Template name is required');
            }

            if (empty($data['template_category'])) {
                throw new Exception('Template category is required');
            }

            if (empty($data['body_text'])) {
                throw new Exception('Body text is required');
            }

            // Process variables
            $vars_input = $data['variable_names'] ?? '';
            if (!empty($vars_input)) {
                $vars_array = array_filter(array_map('trim', explode(',', $vars_input)));
                $vars_array = array_values($vars_array);
            } else {
                $vars_array = [];
            }
            $variables_json = json_encode($vars_array);

            // Validate category
            $valid_categories = ['utility', 'authentication', 'marketing'];
            if (!in_array($data['template_category'], $valid_categories)) {
                throw new Exception('Invalid category. Must be: utility, authentication, or marketing');
            }

            // Validate approval status
            $valid_statuses = ['draft', 'pending', 'approved', 'rejected'];
            $approval_status = $data['approval_status'] ?? 'draft';
            if (!in_array($approval_status, $valid_statuses)) {
                throw new Exception('Invalid approval status. Must be: draft, pending, approved, or rejected');
            }

            // Validate header type
            $valid_header_types = ['none', 'text', 'image', 'video', 'document'];
            $header_type = $data['header_type'] ?? 'none';
            if (!in_array($header_type, $valid_header_types)) {
                throw new Exception('Invalid header type');
            }

            // Parse is_active
            $is_active = in_array(strtolower($data['is_active']), ['1', 'yes', 'true', 'active']) ? 1 : 0;

            // Check for duplicate template name (skip provider_id check since it's not used)
            $stmt = $conn->prepare("SELECT id FROM tbl_whatsapp_templates WHERE template_name = ?");
            $stmt->execute([$data['template_name']]);
            if ($stmt->fetch()) {
                throw new Exception('Template name already exists');
            }

            // Insert template
            $sql = "INSERT INTO tbl_whatsapp_templates 
                    (template_name, template_category, body_text, 
                     variables, approval_status, is_active, header_type, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                trim($data['template_name']),
                $data['template_category'],
                $data['body_text'],
                $variables_json,
                $approval_status,
                $is_active,
                $header_type,
                $_SESSION['user_id'] ?? 1
            ]);

            $success_count++;

        } catch (Exception $e) {
            $error_count++;
            $error_msg = $e->getMessage();
            $errors[] = [
                'row' => $row_number,
                'message' => $error_msg
            ];
            error_log("WhatsApp Bulk Upload - Row {$row_number} error: {$error_msg}");
        }
    }

    fclose($handle);

    if ($success_count > 0) {
        $conn->commit();
        error_log("WhatsApp Bulk Upload - Success: {$success_count} created, {$error_count} errors");
        sendSuccessResponse([
            'total_processed' => $success_count + $error_count,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ], 'Bulk upload completed');
    } else {
        $conn->rollBack();
        error_log("WhatsApp Bulk Upload - No valid templates found");
        sendErrorResponse('No valid templates found in CSV file', 400);
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("WhatsApp Bulk Upload - Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendErrorResponse('Upload failed: ' . $e->getMessage());
}
