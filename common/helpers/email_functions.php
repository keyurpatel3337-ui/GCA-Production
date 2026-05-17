<?php

/**
 * Email Functions
 * Helper functions for sending emails using SMTP
 */

// Try to load Composer autoloader
if (defined('AUTOLOADER_FILE')) {
    if (file_exists(AUTOLOADER_FILE)) {
        require_once AUTOLOADER_FILE;
    }
} else {
    // Fallback if constants.php not loaded (check relative to portal/vendor)
    $fallbackPath = dirname(dirname(__DIR__)) . '/portal/vendor/autoload.php';
    if (file_exists($fallbackPath)) {
        require_once $fallbackPath;
    }
}

/**
 * Get active SMTP configuration from static config
 * @param string $type Configuration type ('support' or 'security')
 */
function getSmtpConfigFromDb($conn = null, $type = 'support')
{
    try {
        // First priority: Check if constants are defined in env.config.php
        if ($type === 'security' || $type === 'otp' || $type === 'system') {
            // Use Security SMTP (SMTP_HOST in env.config.php)
            if (defined('SMTP_HOST')) {
                return [
                    'smtp_host' => SMTP_HOST,
                    'smtp_port' => SMTP_PORT,
                    'smtp_username' => SMTP_USERNAME,
                    'smtp_password' => SMTP_PASSWORD,
                    'smtp_encryption' => SMTP_ENCRYPTION,
                    'smtp_from_email' => SMTP_FROM_EMAIL,
                    'smtp_from_name' => SMTP_FROM_NAME,
                    'smtp_timeout' => 30,
                    'is_active' => 1
                ];
            }
        }

        // Use Regular/Support SMTP (SMTP_REGULAR_HOST in env.config.php)
        if (defined('SMTP_REGULAR_HOST')) {
            return [
                'smtp_host' => SMTP_REGULAR_HOST,
                'smtp_port' => SMTP_REGULAR_PORT,
                'smtp_username' => SMTP_REGULAR_USERNAME,
                'smtp_password' => SMTP_REGULAR_PASSWORD,
                'smtp_encryption' => SMTP_REGULAR_ENCRYPTION,
                'smtp_from_email' => SMTP_REGULAR_FROM_EMAIL,
                'smtp_from_name' => SMTP_REGULAR_FROM_NAME,
                'smtp_timeout' => 30,
                'is_active' => 1
            ];
        }

        // Fallback to app-config.php if not using constants
        if (!function_exists('getSmtpConfig')) {
            require_once __DIR__ . '/../config/app-config.php';
        }

        return getSmtpConfig($conn);
    } catch (\Exception $e) {
        return null;
    }
}

if (!function_exists('getEmailTemplate')) {
    /**
     * Get email template by code
     */
    function getEmailTemplate($conn, $template_code)
    {
        try {
            $stmt = $conn->prepare("SELECT * FROM tbl_email_templates WHERE template_code = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$template_code]);
            return $stmt->fetch();
        } catch (\Exception $e) {
            return null;
        }
    }
}

/**
 * Replace variables in email template
 */
if (!function_exists('replaceTemplateVariables')) {
    function replaceTemplateVariables($content, $variables)
    {
        if (empty($variables) || !is_array($variables)) {
            return $content;
        }

        // Auto-alias common variables to ensure template compatibility across different modules
        if (isset($variables['student_name']) && !isset($variables['name'])) $variables['name'] = $variables['student_name'];
        if (isset($variables['name']) && !isset($variables['student_name'])) $variables['student_name'] = $variables['name'];
        if (isset($variables['admission_letter_number']) && !isset($variables['admission_no'])) $variables['admission_no'] = $variables['admission_letter_number'];
        if (isset($variables['receipt_numbers']) && !isset($variables['receipt_no'])) $variables['receipt_no'] = $variables['receipt_numbers'];
        if (isset($variables['payment_id']) && !isset($variables['transaction_id'])) $variables['transaction_id'] = $variables['payment_id'];
        if (isset($variables['payment_id']) && !isset($variables['receipt_no']) && !isset($variables['receipt_numbers'])) $variables['receipt_no'] = $variables['payment_id'];
        if (isset($variables['token_amount']) && !isset($variables['amount'])) $variables['amount'] = $variables['token_amount'];
        if (isset($variables['amount']) && !isset($variables['token_amount'])) $variables['token_amount'] = $variables['amount'];
        
        // Add default values for common placeholders if not provided
        if (!isset($variables['payment_datetime'])) $variables['payment_datetime'] = date('Y-m-d H:i:s');
        if (!isset($variables['payment_date'])) $variables['payment_date'] = date('Y-m-d');
        if (!isset($variables['enrollment_no'])) $variables['enrollment_no'] = 'N/A';
        if (!isset($variables['payment_mode'])) $variables['payment_mode'] = 'Online';
        if (!isset($variables['collected_by'])) $variables['collected_by'] = 'System';

        foreach ($variables as $key => $value) {
            $value = $value ?? '';
            
            // Handle numeric keys (WhatsApp style: index 0 -> {{1}})
            if (is_numeric($key)) {
                $placeholder = '{{' . ($key + 1) . '}}';
                $content = str_replace($placeholder, $value, $content);
            }
            
            // Handle named keys (Email style: {{variable_name}})
            if (is_string($key)) {
                // Case-insensitive replacement
                $pattern = '/\{\{' . preg_quote($key, '/') . '\}\}/i';
                $content = preg_replace($pattern, $value, $content);
            }
        }
        return $content;
    }
}

/**
 * Send email using configured SMTP
 * @param string $config_type Configuration type ('support' or 'security')
 */
function sendEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code = null, $config_type = 'support')
{
    $config = getSmtpConfigFromDb($conn, $config_type);

    if (!$config) {
        $log_file = defined('LOGS_PATH') ? LOGS_PATH . 'mail/mail.log' : dirname(__DIR__) . '/logs/mail/mail.log';
        $error_msg = "[" . date('Y-m-d H:i:s') . "] ERROR: No active SMTP configuration found for type: {$config_type}. Failed to send to: {$recipient_email}\n";
        @error_log($error_msg, 3, $log_file);

        logEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, 'failed', 'No active SMTP configuration found');
        return ['success' => false, 'error' => 'SMTP not configured'];
    }

    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Fallback to native PHP mail() function
        return sendEmailNative($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, $config);
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];


        $mail->Password = $config['smtp_password'];
        $mail->Port = $config['smtp_port'];
        $mail->Timeout = $config['smtp_timeout'] ?? 30;

        $encryption = strtolower($config['smtp_encryption'] ?? '');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Recipients
        $mail->setFrom($config['smtp_from_email'], $config['smtp_from_name']);
        $mail->addReplyTo($config['smtp_from_email'], $config['smtp_from_name']);
        $mail->addAddress($recipient_email, $recipient_name);

        // Deliverability Headers & Metadata
        $mail->Hostname = 'gyanmanjari.co.in'; // Ensure Message-ID domain matches
        $mail->XMailer = 'GCA Portal Mailer';
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        
        // DKIM Signing (if key exists)
        $dkim_file = dirname(__DIR__) . '/dkim_private.key';
        if (file_exists($dkim_file)) {
            $mail->DKIM_domain = 'gyanmanjari.co.in';
            $mail->DKIM_private = $dkim_file;
            $mail->DKIM_selector = 'default';
            $mail->DKIM_passphrase = '';
            $mail->DKIM_identity = $mail->From;
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Professional AltBody generation
        $plain_body = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], ["\n", "\n", "\n", "\n\n", "\n"], $body);
        $mail->AltBody = trim(strip_tags($plain_body));

        $mail->send();

        logEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, 'sent', null);

        $otp_log = "";
        // Look for 4-6 digit OTP if subject/body contains OTP or it's a security email
        if (stripos($subject, 'OTP') !== false || stripos($body, 'OTP') !== false || $config_type === 'security' || $config_type === 'otp') {
            if (preg_match('/\b(\d{4,6})\b/', $subject . ' ' . strip_tags($body), $matches)) {
                $otp_log = " OTP: {$matches[1]}.";
            }
        }
        $template_log = $template_code ? " Template: {$template_code}." : "";

        $log_file = defined('LOGS_PATH') ? LOGS_PATH . 'mail/mail.log' : dirname(__DIR__) . '/logs/mail/mail.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] INFO: Email sent successfully to {$recipient_email}. Subject: {$subject}.{$template_log}{$otp_log}\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);

        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (\Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        logEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, 'failed', $error);

        $otp_log = "";
        if (stripos($subject, 'OTP') !== false || stripos($body, 'OTP') !== false || $config_type === 'security' || $config_type === 'otp') {
            if (preg_match('/\b(\d{4,6})\b/', $subject . ' ' . strip_tags($body), $matches)) {
                $otp_log = " OTP: {$matches[1]}.";
            }
        }
        $template_log = $template_code ? " Template: {$template_code}." : "";

        $log_file = defined('LOGS_PATH') ? LOGS_PATH . 'mail/mail.log' : dirname(__DIR__) . '/logs/mail/mail.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to send email to {$recipient_email}. Subject: {$subject}.{$template_log}{$otp_log} Error: {$error}\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Fallback: Send email using native PHP mail() function
 */
function sendEmailNative($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, $config)
{
    try {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=UTF-8";
        $headers[] = "From: {$config['smtp_from_name']} <{$config['smtp_from_email']}>";
        $headers[] = "Reply-To: {$config['smtp_from_email']}";
        $headers[] = "X-Mailer: PHP/" . phpversion();

        $success = mail($recipient_email, $subject, $body, implode("\r\n", $headers));

        $otp_log = "";
        if (stripos($subject, 'OTP') !== false || stripos($body, 'OTP') !== false) {
            if (preg_match('/\b(\d{4,6})\b/', $subject . ' ' . strip_tags($body), $matches)) {
                $otp_log = " OTP: {$matches[1]}.";
            }
        }
        $template_log = $template_code ? " Template: {$template_code}." : "";

        $log_file = defined('LOGS_PATH') ? LOGS_PATH . 'mail/mail.log' : dirname(__DIR__) . '/logs/mail/mail.log';
        if ($success) {
            logEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, 'sent', null);
            $log_entry = "[" . date('Y-m-d H:i:s') . "] INFO: Email sent successfully (native mail) to {$recipient_email}. Subject: {$subject}.{$template_log}{$otp_log}\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND);
            return ['success' => true, 'message' => 'Email sent successfully (native mail)'];
        } else {
            logEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, 'failed', 'Native mail() function failed');
            $log_entry = "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to send email (native mail) to {$recipient_email}. Subject: {$subject}.{$template_log}{$otp_log}\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND);
            return ['success' => false, 'error' => 'Failed to send email'];
        }
    } catch (\Exception $e) {
        logEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, 'failed', $e->getMessage());
        
        $otp_log = "";
        if (stripos($subject, 'OTP') !== false || stripos($body, 'OTP') !== false) {
            if (preg_match('/\b(\d{4,6})\b/', $subject . ' ' . strip_tags($body), $matches)) {
                $otp_log = " OTP: {$matches[1]}.";
            }
        }
        $template_log = $template_code ? " Template: {$template_code}." : "";

        $log_file = defined('LOGS_PATH') ? LOGS_PATH . 'mail/mail.log' : dirname(__DIR__) . '/logs/mail/mail.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ERROR: Exception while sending native email to {$recipient_email}. Subject: {$subject}.{$template_log}{$otp_log} Error: " . $e->getMessage() . "\n", 3, $log_file);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send email using template
 * @param string $config_type Configuration type ('support' or 'security')
 */
function sendTemplateEmail($conn, $recipient_email, $recipient_name, $template_code, $variables = [], $config_type = 'support')
{
    $template = getEmailTemplate($conn, $template_code);

    if (!$template) {
        return ['success' => false, 'error' => 'Template not found: ' . $template_code];
    }

    $subject = replaceTemplateVariables($template['subject'], $variables);
    $body = replaceTemplateVariables($template['html_content'], $variables);

    return sendEmail($conn, $recipient_email, $recipient_name, $subject, $body, $template_code, $config_type);
}

if (!function_exists('logEmail')) {
    /**
     * Log email activity
     */
    function logEmail($conn, $recipient_email, $recipient_name = null, $subject = null, $body = null, $template_code = null, $status = null, $error_message = null)
    {
        try {
            // Check if it's the 2-argument array call (from notification_functions)
            if (is_array($recipient_email) && $recipient_name === null) {
                $defaults = [
                    'template_id' => null, 'template_code' => null, 'config_id' => null,
                    'recipient_email' => null, 'recipient_name' => null, 'recipient_type' => null,
                    'reference_type' => null, 'reference_id' => null, 'subject' => null,
                    'html_body' => null, 'text_body' => null, 'variables_used' => null,
                    'cc_emails' => null, 'bcc_emails' => null, 'attachments' => null,
                    'status' => 'pending', 'error_message' => null, 'smtp_response' => null,
                    'sent_at' => null
                ];
                $data = array_merge($defaults, $recipient_email);

                $sql = "INSERT INTO tbl_email_logs (
                    template_id, template_code, config_id, recipient_email, recipient_name,
                    recipient_type, reference_type, reference_id, subject, html_body, text_body,
                    variables_used, cc_emails, bcc_emails, attachments, status, error_message,
                    smtp_response, sent_at, created_at
                ) VALUES (
                    :template_id, :template_code, :config_id, :recipient_email, :recipient_name,
                    :recipient_type, :reference_type, :reference_id, :subject, :html_body, :text_body,
                    :variables_used, :cc_emails, :bcc_emails, :attachments, :status, :error_message,
                    :smtp_response, :sent_at, NOW()
                )";
                $stmt = $conn->prepare($sql);
                $stmt->execute($data);
                return $conn->lastInsertId();
            } else {
                // Standard 8-argument call
                $stmt = $conn->prepare("INSERT INTO tbl_email_logs 
                    (recipient_email, recipient_name, subject, html_body, template_code, status, error_message, sent_at, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $recipient_email,
                    $recipient_name,
                    $subject,
                    $body,
                    $template_code,
                    $status,
                    $error_message
                ]);
            }
        } catch (\Exception $e) {
            // Silent fail - don't break email sending if logging fails
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
}

/**
 * Send test email
 */
function sendTestEmail($test_email, $config = null)
{
    global $conn;

    if (!$config) {
        $config = getSmtpConfigFromDb($conn);
    }

    if (!$config) {
        return ['success' => false, 'error' => 'No SMTP configuration found'];
    }

    $subject = "Test Email from GM Edu Portal";
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #3b82f6;'>Test Email</h2>
            <p>This is a test email from GM Edu Portal.</p>
            <p>If you received this email, your SMTP configuration is working correctly!</p>
            <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>SMTP Server:</strong> {$config['smtp_host']}</p>
                <p><strong>Port:</strong> {$config['smtp_port']}</p>
                <p><strong>Encryption:</strong> " . strtoupper($config['smtp_encryption']) . "</p>
                <p><strong>Sent At:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            <p style='color: #10b981;'><strong>âœ“ Configuration Test Successful</strong></p>
        </div>
    </body>
    </html>
    ";

    // Set recipient for test email
    $recipient = $test_email;
    
    return sendEmail($conn, $recipient, 'Test Recipient', $subject, $body, 'test_email');
}

/**
 * Send bulk emails (for notifications, etc.)
 */
function sendBulkEmails($conn, $recipients, $template_slug, $variables_array)
{
    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($recipients as $index => $recipient) {
        $variables = $variables_array[$index] ?? [];
        $result = sendTemplateEmail(
            $conn,
            $recipient['email'],
            $recipient['name'] ?? '',
            $template_slug,
            $variables
        );

        if ($result['success']) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'email' => $recipient['email'],
                'error' => $result['error']
            ];
        }

        // Small delay to prevent being flagged as spam
        usleep(100000); // 0.1 second
    }

    return $results;
}

/**
 * Validate email address
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get email statistics
 */
function getEmailStats($conn, $days = 30)
{
    try {
        $stmt = $conn->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                DATE(sent_at) as date
            FROM tbl_email_logs 
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY status, DATE(sent_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    } catch (\Exception $e) {
        return [];
    }
}


