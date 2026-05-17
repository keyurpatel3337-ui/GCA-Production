<?php

/**
 * Download WhatsApp Template CSV Template
 * Path: counselling-backend/controllers/settings/download-whatsapp-template-csv.php
 * Note: This file is included in whatsapp_controller.php which already has bootstrap and auth
 */

try {
    error_log("WhatsApp CSV Template Download - User: " . ($_SESSION['user_id'] ?? 'unknown'));

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="whatsapp_templates_' . date('Y-m-d') . '.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    if (!$output) {
        throw new Exception('Failed to open output stream');
    }

    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // CSV Headers
    $headers = [
        'template_name',
        'template_category',
        'body_text',
        'variable_names',
        'header_type',
        'approval_status',
        'is_active'
    ];

    fputcsv($output, $headers);

    // Add sample data rows
    $sample1 = [
        'welcome_message',
        'utility',
        'Hello {{1}}, Welcome to our counselling system! Your enrollment number is {{2}}.',
        'name,enrollment_no',
        'none',
        'draft',
        '1'
    ];

    $sample2 = [
        'appointment_reminder',
        'utility',
        'Dear {{1}}, Your appointment is scheduled for {{2}} at {{3}}. Please arrive 10 minutes early.',
        'name,date,time',
        'none',
        'approved',
        '1'
    ];

    $sample3 = [
        'fee_receipt_notification',
        'utility',
        'Hi {{1}}, Your fee payment of Rs.{{2}} has been received. Receipt No: {{3}}',
        'name,amount,receipt_no',
        'none',
        'approved',
        '1'
    ];

    fputcsv($output, $sample1);
    fputcsv($output, $sample2);
    fputcsv($output, $sample3);

    // Add blank row for user to fill
    $blank = array_fill(0, count($headers), '');
    fputcsv($output, $blank);

    fclose($output);
    error_log("WhatsApp CSV Template Download - Success");
    exit;

} catch (Exception $e) {
    error_log("WhatsApp CSV Template Download Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to generate CSV template']);
    exit;
}
