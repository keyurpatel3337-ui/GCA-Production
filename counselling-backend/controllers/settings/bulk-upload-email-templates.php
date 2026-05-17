<?php

/**
 * Bulk Upload Email Templates
 * Path: counselling-backend/controllers/settings/bulk-upload-email-templates.php
 * Note: This file is included in email_controller.php which already has bootstrap and auth
 */

header('Content-Type: application/json');

try {
    error_log("Email Bulk Upload - Started by user: " . ($_SESSION['user_id'] ?? 'unknown'));

    // Validate file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['csv_file']['error'] ?? 'unknown';
        error_log("Email Bulk Upload - File upload error: " . $error_code);
        sendErrorResponse('No file uploaded or upload error occurred');
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $filename = $_FILES['csv_file']['name'] ?? 'unknown';
    error_log("Email Bulk Upload - Processing file: " . $filename);

    if (!file_exists($file)) {
        error_log("Email Bulk Upload - File not found: " . $file);
        sendErrorResponse('Uploaded file not found');
    }

    // Open and read CSV
    $handle = fopen($file, 'r');
    if (!$handle) {
        error_log("Email Bulk Upload - Could not open file: " . $file);
        sendErrorResponse('Could not open CSV file');
    }

    // Read and validate headers
    $headers = fgetcsv($handle);

    // Remove BOM if present
    if (!empty($headers[0])) {
        $headers[0] = str_replace("\xEF\xBB\xBF", '', $headers[0]);
    }

    $required_headers = [
        'template_code',
        'template_name',
        'subject',
        'body_text',
        'recipient_type',
        'category',
        'variable_names',
        'is_active'
    ];

    $missing = array_diff($required_headers, $headers);
    if (!empty($missing)) {
        fclose($handle);
        error_log("Email Bulk Upload - Missing columns: " . implode(', ', $missing));
        sendErrorResponse('Missing required columns: ' . implode(', ', $missing));
    }

    error_log("Email Bulk Upload - CSV headers validated successfully");

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
            if (empty($data['template_code'])) {
                throw new Exception('Template code is required');
            }

            if (empty($data['template_name'])) {
                throw new Exception('Template name is required');
            }

            if (empty($data['subject'])) {
                throw new Exception('Subject is required');
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

            // Validate recipient type
            $valid_recipients = ['student', 'parent', 'staff', 'admin', 'all'];
            $recipient_type = $data['recipient_type'] ?? 'student';
            if (!in_array($recipient_type, $valid_recipients)) {
                throw new Exception('Invalid recipient type. Must be: student, parent, staff, admin, or all');
            }

            // Validate category
            $valid_categories = [
                'ADMISSION',
                'FEE_NOTIFICATION',
                'APPOINTMENT',
                'STUDENT_NOTIFICATION',
                'STAFF_NOTIFICATION',
                'SYSTEM_ALERT',
                'REPORT',
                'GENERAL'
            ];
            $category = strtoupper($data['category']);
            if (!in_array($category, $valid_categories)) {
                throw new Exception('Invalid category');
            }

            // Parse is_active
            $is_active = in_array(strtolower($data['is_active']), ['1', 'yes', 'true', 'active']) ? 1 : 0;

            // Check for duplicate template code
            $stmt = $conn->prepare("SELECT id FROM tbl_email_templates WHERE template_code = ?");
            $stmt->execute([$data['template_code']]);
            if ($stmt->fetch()) {
                throw new Exception('Template code already exists');
            }

            // Insert template
            $sql = "INSERT INTO tbl_email_templates 
                    (template_code, template_name, subject, body_text, 
                     recipient_type, category, variables, is_active, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                strtoupper(trim($data['template_code'])),
                trim($data['template_name']),
                trim($data['subject']),
                $data['body_text'],
                $recipient_type,
                $category,
                $variables_json,
                $is_active,
                $_SESSION['user_id'] ?? 1
            ]);

            $success_count++;

        } catch (Exception $e) {
            $error_count++;
            $errors[] = [
                'row' => $row_number,
                'message' => $e->getMessage()
            ];
        }
    }

    fclose($handle);

    if ($success_count > 0) {
        $conn->commit();
        error_log("Email Bulk Upload - Success: {$success_count} created, {$error_count} errors");
        sendSuccessResponse([
            'total_processed' => $success_count + $error_count,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ], 'Bulk upload completed');
    } else {
        $conn->rollBack();
        error_log("Email Bulk Upload - No valid templates found");
        sendErrorResponse('No valid templates found in CSV file', 400);
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Email Bulk Upload - Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendErrorResponse('Upload failed: ' . $e->getMessage());
}
