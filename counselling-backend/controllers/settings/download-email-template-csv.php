<?php

/**
 * Download Email Template CSV Template
 * Path: counselling-backend/controllers/settings/download-email-template-csv.php
 * Note: This file is included in email_controller.php which already has bootstrap and auth
 */

try {
    error_log("Email CSV Template Download - User: " . ($_SESSION['user_id'] ?? 'unknown'));

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="email_templates_' . date('Y-m-d') . '.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    if (!$output) {
        throw new Exception('Failed to open output stream');
    }

    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // CSV Headers
    $headers = [
        'template_code',
        'template_name',
        'subject',
        'body_text',
        'recipient_type',
        'category',
        'variable_names',
        'is_active'
    ];

    fputcsv($output, $headers);

    // Add sample data rows
    $sample1 = [
        'STUDENT_WELCOME',
        'Student Welcome Email',
        'Welcome to {{SCHOOL_NAME}}',
        '<h2>Welcome {{STUDENT_NAME}}</h2><p>Your enrollment number is <strong>{{ENROLLMENT_NO}}</strong></p>',
        'student',
        'STUDENT_NOTIFICATION',
        'SCHOOL_NAME,STUDENT_NAME,ENROLLMENT_NO',
        '1'
    ];

    $sample2 = [
        'FEE_RECEIPT',
        'Fee Receipt',
        'Fee Payment Receipt - {{RECEIPT_NO}}',
        '<h3>Payment Received</h3><p>Dear {{STUDENT_NAME}}, we have received your payment of Rs.{{AMOUNT}}.</p>',
        'student',
        'FEE_NOTIFICATION',
        'RECEIPT_NO,STUDENT_NAME,AMOUNT',
        '1'
    ];

    $sample3 = [
        'APPOINTMENT_REMINDER',
        'Appointment Reminder',
        'Appointment Scheduled - {{DATE}}',
        '<p>Dear {{STUDENT_NAME}}, your appointment is on {{DATE}} at {{TIME}}.</p>',
        'student',
        'APPOINTMENT',
        'STUDENT_NAME,DATE,TIME',
        '1'
    ];

    fputcsv($output, $sample1);
    fputcsv($output, $sample2);
    fputcsv($output, $sample3);

    // Add blank row for user to fill
    $blank = array_fill(0, count($headers), '');
    fputcsv($output, $blank);

    fclose($output);
    error_log("Email CSV Template Download - Success");
    exit;

} catch (Exception $e) {
    error_log("Email CSV Template Download Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to generate CSV template']);
    exit;
}
