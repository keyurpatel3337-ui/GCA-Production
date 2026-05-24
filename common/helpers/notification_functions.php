<?php

/**
 * Email and Notification Helper Functions
 * Handles email sending, template processing, and notification management
 * 
 * Dependencies: PHPMailer, tbl_email_templates, tbl_api_configurations
 */

require_once __DIR__ . '/../db_connect.php';
require_once OPERATION_FILE;
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get active SMTP configuration
 * 
 * @param PDO $conn Database connection
 * @param string|null $config_name Specific configuration name
 * @return array|null SMTP configuration
 */
function getActiveSMTPConfig($conn, $config_name = null)
{
    // Default config name to 'support' if not provided
    $name = strtolower($config_name ?? 'support');

    if ($name === 'security' || $name === 'otp' || $name === 'system') {
        // Use Security SMTP (SMTP_HOST in env.config.php)
        return [
            'id' => 2,
            'api_url' => defined('SMTP_HOST') ? SMTP_HOST : '',
            'port' => defined('SMTP_PORT') ? SMTP_PORT : 465,
            'api_key' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
            'api_secret' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
            'encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'ssl',
            'from_email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '',
            'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Security'
        ];
    } else {
        // Use Regular/Support SMTP (SMTP_REGULAR_HOST in env.config.php)
        return [
            'id' => 1,
            'api_url' => defined('SMTP_REGULAR_HOST') ? SMTP_REGULAR_HOST : '',
            'port' => defined('SMTP_REGULAR_PORT') ? SMTP_REGULAR_PORT : 465,
            'api_key' => defined('SMTP_REGULAR_USERNAME') ? SMTP_REGULAR_USERNAME : '',
            'api_secret' => defined('SMTP_REGULAR_PASSWORD') ? SMTP_REGULAR_PASSWORD : '',
            'encryption' => defined('SMTP_REGULAR_ENCRYPTION') ? SMTP_REGULAR_ENCRYPTION : 'ssl',
            'from_email' => defined('SMTP_REGULAR_FROM_EMAIL') ? SMTP_REGULAR_FROM_EMAIL : '',
            'from_name' => defined('SMTP_REGULAR_FROM_NAME') ? SMTP_REGULAR_FROM_NAME : 'Support'
        ];
    }
}

if (!function_exists('getEmailTemplate')) {
    /**
     * Get email template by code
     * 
     * @param PDO $conn Database connection
     * @param string $template_code Template identifier
     * @return array|null Template data
     */
    function getEmailTemplate($conn, $template_code)
    {
        try {
            $stmt = $conn->prepare("SELECT * FROM tbl_email_templates WHERE template_code = ? AND is_active = 1");
            $stmt->execute([$template_code]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            // Parse JSON fields
            if ($template) {
                $template['variables'] = json_decode($template['variables'], true);
            }

            return $template;
        } catch (PDOException $e) {
            error_log("Error getting email template: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Replace variables in template content
 * 
 * @param string $content Template content with {{variable}} placeholders
 * @param array $variables Key-value pairs to replace
 * @return string Processed content
 */
if (!function_exists('replaceTemplateVariables')) {
    function replaceTemplateVariables($content, $variables)
    {
        if (empty($variables) || !is_array($variables)) {
            return $content;
        }

        // Auto-alias common variables to ensure template compatibility across different modules
        if (isset($variables['student_name']) && !isset($variables['name']))
            $variables['name'] = $variables['student_name'];
        if (isset($variables['name']) && !isset($variables['student_name']))
            $variables['student_name'] = $variables['name'];
        if (isset($variables['admission_letter_number']) && !isset($variables['admission_no']))
            $variables['admission_no'] = $variables['admission_letter_number'];
        if (isset($variables['receipt_numbers']) && !isset($variables['receipt_no']))
            $variables['receipt_no'] = $variables['receipt_numbers'];
        if (isset($variables['payment_id']) && !isset($variables['transaction_id']))
            $variables['transaction_id'] = $variables['payment_id'];
        if (isset($variables['payment_id']) && !isset($variables['receipt_no']) && !isset($variables['receipt_numbers']))
            $variables['receipt_no'] = $variables['payment_id'];
        if (isset($variables['token_amount']) && !isset($variables['amount']))
            $variables['amount'] = $variables['token_amount'];
        if (isset($variables['amount']) && !isset($variables['token_amount']))
            $variables['token_amount'] = $variables['amount'];

        // Add default values for common placeholders if not provided
        if (!isset($variables['payment_datetime']))
            $variables['payment_datetime'] = date('Y-m-d H:i:s');
        if (!isset($variables['payment_date']))
            $variables['payment_date'] = date('Y-m-d');
        if (!isset($variables['enrollment_no']))
            $variables['enrollment_no'] = 'N/A';
        if (!isset($variables['payment_mode']))
            $variables['payment_mode'] = 'Online';
        if (!isset($variables['collected_by']))
            $variables['collected_by'] = 'System';

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
 * Send email using PHPMailer with template
 * 
 * @param PDO $conn Database connection
 * @param string $template_code Template identifier
 * @param string $recipient_email Recipient email address
 * @param string $recipient_name Recipient name
 * @param array $variables Variables to replace in template
 * @param array $options Additional options (cc, bcc, attachments, etc.)
 * @return array Result with success status and message
 */
function sendEmailTemplate($conn, $template_code, $recipient_email, $recipient_name, $variables = [], $options = [])
{
    try {
        // Get template
        $template = getEmailTemplate($conn, $template_code);
        if (!$template) {
            return [
                'success' => false,
                'error' => "Template not found: $template_code"
            ];
        }

        // Get SMTP config
        $smtp_config = getActiveSMTPConfig($conn);
        if (!$smtp_config) {
            return [
                'success' => false,
                'error' => "No active SMTP configuration found"
            ];
        }

        // Process template variables
        $subject = replaceTemplateVariables($template['subject'], $variables);
        $html_body = replaceTemplateVariables($template['html_content'], $variables);
        $text_body = $template['text_content'] ? replaceTemplateVariables($template['text_content'], $variables) : strip_tags($html_body);

        // Create PHPMailer instance
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_config['api_url'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['api_key'] ?? $smtp_config['from_email'];
        $mail->Password = $smtp_config['api_secret'];
        $mail->SMTPSecure = $smtp_config['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'] ?? 587;
        $mail->CharSet = 'UTF-8';

        // Sender
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);

        // Recipient
        $mail->addAddress($recipient_email, $recipient_name);

        // CC and BCC
        if (!empty($options['cc'])) {
            $cc_list = is_array($options['cc']) ? $options['cc'] : explode(',', $options['cc']);
            foreach ($cc_list as $cc_email) {
                $mail->addCC(trim($cc_email));
            }
        }

        if (!empty($options['bcc'])) {
            $bcc_list = is_array($options['bcc']) ? $options['bcc'] : explode(',', $options['bcc']);
            foreach ($bcc_list as $bcc_email) {
                $mail->addBCC(trim($bcc_email));
            }
        }

        // Attachments
        if (!empty($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                // Support both full path string and associative array with 'path' and 'name'
                $at_path = is_array($attachment) ? $attachment['path'] : $attachment;
                $at_name = is_array($attachment) ? ($attachment['name'] ?? '') : '';

                if (file_exists($at_path)) {
                    $mail->addAttachment($at_path, $at_name);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body;

        // Priority
        if (!empty($options['priority'])) {
            if ($options['priority'] === 'high') {
                $mail->Priority = 1;
            } elseif ($options['priority'] === 'low') {
                $mail->Priority = 5;
            }
        }

        // Send email
        $sent = $mail->send();

        // Log email
        $log_id = logEmail($conn, [
            'template_id' => $template['id'],
            'template_code' => $template_code,
            'config_id' => $smtp_config['id'],
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'recipient_type' => $options['recipient_type'] ?? null,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'subject' => $subject,
            'html_body' => $html_body,
            'text_body' => $text_body,
            'variables_used' => json_encode($variables),
            'cc_emails' => !empty($options['cc']) ? (is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc']) : null,
            'bcc_emails' => !empty($options['bcc']) ? (is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc']) : null,
            'attachments' => !empty($options['attachments']) ? json_encode($options['attachments']) : null,
            'status' => $sent ? 'sent' : 'failed',
            'smtp_response' => $sent ? 'Email sent successfully' : $mail->ErrorInfo,
            'sent_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'success' => $sent,
            'log_id' => $log_id,
            'message' => $sent ? 'Email sent successfully' : $mail->ErrorInfo
        ];
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());

        if (isset($template) && isset($smtp_config)) {
            logEmail($conn, [
                'template_id' => $template['id'] ?? null,
                'template_code' => $template_code,
                'config_id' => $smtp_config['id'] ?? null,
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'subject' => $subject ?? 'Error',
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

if (!function_exists('logEmail')) {
    /**
     * Log email activity
     */
    function logEmail($conn, $recipient_email, $recipient_name = null, $subject = null, $body = null, $template_code = null, $status = null, $error_message = null)
    {
        try {
            // Check if it's the 8-argument call or the 2-argument array call
            if (is_array($recipient_email) && $recipient_name === null) {
                $defaults = [
                    'template_id' => null,
                    'template_code' => null,
                    'config_id' => null,
                    'recipient_email' => null,
                    'recipient_name' => null,
                    'recipient_type' => null,
                    'reference_type' => null,
                    'reference_id' => null,
                    'subject' => null,
                    'html_body' => null,
                    'text_body' => null,
                    'variables_used' => null,
                    'cc_emails' => null,
                    'bcc_emails' => null,
                    'attachments' => null,
                    'status' => 'pending',
                    'error_message' => null,
                    'smtp_response' => null,
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
                $log_id = $conn->lastInsertId();
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
                $log_id = $conn->lastInsertId();
            }

            // ALSO WRITE TO mail.log FILE
            $log_file = defined('LOGS_PATH') ? LOGS_PATH . 'mail/mail.log' : dirname(__DIR__) . '/logs/mail/mail.log';
            $log_dir = dirname($log_file);
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }

            $log_email = is_array($recipient_email) ? ($recipient_email['recipient_email'] ?? 'Unknown') : $recipient_email;
            $log_subject = is_array($recipient_email) ? ($recipient_email['subject'] ?? 'No Subject') : $subject;
            $log_status = is_array($recipient_email) ? ($recipient_email['status'] ?? 'pending') : ($status ?? 'pending');
            $log_template = is_array($recipient_email) ? ($recipient_email['template_code'] ?? 'None') : ($template_code ?? 'None');

            $log_entry = "[" . date('Y-m-d H:i:s') . "] [" . strtoupper($log_status) . "] Email to: {$log_email} | Subject: {$log_subject} | Template: {$log_template}\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND);

            return $log_id;

        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Queue email for later sending
 * 
 * @param PDO $conn Database connection
 * @param string $template_code Template identifier
 * @param string $recipient_email Recipient email
 * @param string $recipient_name Recipient name
 * @param array $variables Template variables
 * @param array $options Additional options
 * @return int|null Queue ID
 */
function queueEmail($conn, $template_code, $recipient_email, $recipient_name, $variables = [], $options = [])
{
    try {
        // Get template ID
        $template = getEmailTemplate($conn, $template_code);
        if (!$template) {
            return null;
        }

        $sql = "INSERT INTO tbl_email_logs (
            template_id, recipient_email, recipient_name, recipient_type,
            reference_type, reference_id, variables, cc_emails, bcc_emails,
            attachments, priority, scheduled_at, status
        ) VALUES (
            :template_id, :recipient_email, :recipient_name, :recipient_type,
            :reference_type, :reference_id, :variables, :cc_emails, :bcc_emails,
            :attachments, :priority, :scheduled_at, 'queued'
        )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'template_id' => $template['id'],
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'recipient_type' => $options['recipient_type'] ?? null,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'variables' => json_encode($variables),
            'cc_emails' => !empty($options['cc']) ? (is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc']) : null,
            'bcc_emails' => !empty($options['bcc']) ? (is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc']) : null,
            'attachments' => !empty($options['attachments']) ? json_encode($options['attachments']) : null,
            'priority' => $options['priority'] ?? 'normal',
            'scheduled_at' => $options['scheduled_at'] ?? date('Y-m-d H:i:s')
        ]);

        return $conn->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error queuing email: " . $e->getMessage());
        return null;
    }
}

/**
 * Process email queue (to be called by cron job)
 * 
 * @param PDO $conn Database connection
 * @param int $limit Number of emails to process
 * @return array Processing results
 */
function processEmailQueue($conn, $limit = 50)
{
    try {
        // Get pending emails
        $sql = "SELECT * FROM tbl_email_logs 
                WHERE status = 'pending' 
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                AND retry_count < max_retries
                ORDER BY priority DESC, created_at desc
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$limit]);
        $queue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'total' => count($queue_items),
            'sent' => 0,
            'failed' => 0
        ];

        foreach ($queue_items as $item) {
            // Update status to processing
            $update_stmt = $conn->prepare("UPDATE tbl_email_logs SET status = 'processing' WHERE id = ?");
            $update_stmt->execute([$item['id']]);

            // Decode variables and attachments
            $variables = json_decode($item['variables'], true);
            $attachments = $item['attachments'] ? json_decode($item['attachments'], true) : [];

            // Prepare options
            $options = [
                'recipient_type' => $item['recipient_type'],
                'reference_type' => $item['reference_type'],
                'reference_id' => $item['reference_id'],
                'cc' => $item['cc_emails'],
                'bcc' => $item['bcc_emails'],
                'attachments' => $attachments,
                'priority' => $item['priority']
            ];

            // Send email
            $result = sendEmailTemplate(
                $conn,
                $item['template_code'] ?? null,
                $item['recipient_email'],
                $item['recipient_name'],
                $variables,
                $options
            );

            if ($result['success']) {
                // Update queue status to sent
                $update_stmt = $conn->prepare("UPDATE tbl_email_logs SET status = 'sent', log_id = ?, processed_at = NOW() WHERE id = ?");
                $update_stmt->execute([$result['log_id'], $item['id']]);
                $results['sent']++;
            } else {
                // Increment retry count
                $update_stmt = $conn->prepare("UPDATE tbl_email_logs SET status = 'pending', retry_count = retry_count + 1, error_message = ? WHERE id = ?");
                $update_stmt->execute([$result['error'] ?? 'Unknown error', $item['id']]);
                $results['failed']++;
            }
        }

        return $results;
    } catch (PDOException $e) {
        error_log("Error processing email queue: " . $e->getMessage());
        return ['total' => 0, 'sent' => 0, 'failed' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Send notification (WhatsApp + Email combined)
 * 
 * @param PDO $conn Database connection
 * @param string $notification_type Type of notification (e.g., 'registration_success')
 * @param array $recipient Recipient info ['name', 'mobile', 'email']
 * @param array $variables Template variables
 * @param array $options Additional options
 * @return array Results for both channels
 */
function sendNotification($conn, $notification_type, $recipient, $variables = [], $options = [])
{
    $results = [
        'whatsapp' => null,
        'email' => null
    ];

    // Check notification preferences
    $preferences = getNotificationPreferences($conn, $options['student_id'] ?? null, $options['user_id'] ?? null);


    // Fetch template mappings from database
    try {
        $stmt_mt = $conn->prepare("SELECT whatsapp_template_name, email_template_code FROM tbl_notification_types WHERE type_code = ? AND is_active = 1");
        $stmt_mt->execute([$notification_type]);
        $mapping = $stmt_mt->fetch(PDO::FETCH_ASSOC);
        
        $wa_template = $mapping['whatsapp_template_name'] ?? $notification_type;
        $email_template = $mapping['email_template_code'] ?? null;
    } catch (Exception $e) {
        $wa_template = null;
        $email_template = null;
    }

    // Change 2: Variable Mapping Logic/*  */
    // WhatsApp (BhashSMS) requires variables in a specific indexed order (Array)
    // while Email requires named placeholders.
    $wa_variables = $variables;

    if ($wa_template === 'feepaymentsuccess_002') {
        // Variable Mapping for feepaymentsuccess_002 (5 variables)
        // 1. Name, 2. Amount, 3. Mode, 4. Receipt, 5. Date
        $name = $variables['student_name'] ?? 'Student';
        $amt = formatWhatsAppAmount($variables['amount'] ?? '0');
        $mode = $variables['payment_mode'] ?? 'Online';
        
        $receipt_raw = $variables['receipt_no'] ?? '';
        if (strpos($receipt_raw, ',') !== false) {
            // Remove spaces inside brackets as per BhashSMS tech team advice <1,2,3>
            $receipt = '<' . str_replace(' ', '', trim($receipt_raw, " <>")) . '>';
        } else {
            $receipt = $receipt_raw ?: 'N/A';
        }
        
        // Ensure date is formatted correctly if provided, else fallback to today
        $date_raw = $variables['payment_date'] ?? '';
        if (!empty($date_raw)) {
            $date = date('d-M-Y', strtotime($date_raw));
        } else {
            $date = date('d-M-Y');
        }
        
        $wa_variables = [$name, $amt, $mode, $receipt, $date];
    } elseif ($wa_template === 'gyanman_0112_03') {
        // Variable Mapping for gyanman_0112_03 (3 variables)
        // 1. Name, 2. Amount, 3. Due Date
        $name = $variables['student_full_name'] ?? ($variables['student_name'] ?? 'Student');
        $amt = formatWhatsAppAmount($variables['amount'] ?? ($variables['amount_due'] ?? '0'));
        $date = $variables['due_date'] ?? date('d-M-Y');
        
        $wa_variables = [$name, $amt, $date];
    } elseif ($wa_template === 'parent_update_not' && !empty($variables)) {
        // parent_update_not is now only for custom messages
        if (!isset($variables[0])) {
            $wa_variables = array_values($variables);
        }
    } elseif (is_array($variables) && !empty($variables) && array_keys($variables) !== range(0, count($variables) - 1)) {
        // Fallback: convert associative to values for other WhatsApp templates
        $wa_variables = array_values($variables);
    }

    // Check for course-based restrictions (Exclude Course ID 1 and 2)
    $course_restricted = false;
    if (isset($options['student_id'])) {
        $stmt_c = $conn->prepare("SELECT course_id FROM tbl_gm_std_registration WHERE id = ?");
        $stmt_c->execute([$options['student_id']]);
        $course_info = $stmt_c->fetch(PDO::FETCH_ASSOC);
        if ($course_info && in_array($course_info['course_id'], [1])) {
            $course_restricted = true;
        }
    }

    // Send WhatsApp if enabled or forced, AND not restricted by course
    if (($preferences['whatsapp_enabled'] || ($options['force_whatsapp'] ?? false)) && !$course_restricted) {
        require_once __DIR__ . '/whatsapp_functions.php';

        if (function_exists('sendWhatsAppTemplate')) {
            if ($wa_template && !empty($recipient['mobile'])) {
                // Send to primary recipient
                $results['whatsapp'] = sendWhatsAppTemplate(
                    $conn,
                    $recipient['mobile'],
                    $wa_template,
                    $wa_variables // Pass mapped variables (indexed for BhashSMS)
                );
            }
        }
    }

    // REDIRECT WHATSAPP TO EMAIL IF WHATSAPP IS DISABLED OR FAILED
    // This implements the user request to replace WhatsApp triggers with Email
    $whatsapp_failed = !isset($results['whatsapp']) || (isset($results['whatsapp']['success']) && !$results['whatsapp']['success']);

    // Send Email if enabled OR if it's a fallback for a failed WhatsApp message
    if ((($preferences['email_enabled'] ?? 1) || $whatsapp_failed) && !empty($recipient['email'])) {
        if ($email_template) {
            // Check if we already sent this email via the normal flow
            if (empty($results['email'])) {
                if (($preferences['email_frequency'] ?? 'immediate') === 'immediate') {
                    $results['email'] = sendEmailTemplate(
                        $conn,
                        $email_template,
                        $recipient['email'],
                        $recipient['name'],
                        $variables,
                        $options
                    );
                } else {
                    // Queue for later (daily/weekly digest)
                    $results['email'] = [
                        'success' => true,
                        'queued' => true,
                        'queue_id' => queueEmail(
                            $conn,
                            $email_template,
                            $recipient['email'],
                            $recipient['name'],
                            $variables,
                            $options
                        )
                    ];
                }
            }
        }
    }

    return $results;
}

/**
 * Get notification preferences for user/student
 * 
 * @param PDO $conn Database connection
 * @param int|null $student_id Student ID
 * @param int|null $user_id User ID
 * @return array Preferences
 */
function getNotificationPreferences($conn, $student_id = null, $user_id = null)
{
    try {
        $where = $student_id ? "student_id = ?" : "user_id = ?";
        $id = $student_id ?? $user_id;

        $stmt = $conn->prepare("SELECT * FROM tbl_notification_preferences WHERE $where");
        $stmt->execute([$id]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return defaults if no preferences found
        if (!$prefs) {
            return [
                'whatsapp_enabled' => 1,
                'email_enabled' => 1,
                'sms_enabled' => 0,
                'email_frequency' => 'immediate'
            ];
        }

        return $prefs;
    } catch (PDOException $e) {
        error_log("Error getting notification preferences: " . $e->getMessage());
        return [
            'whatsapp_enabled' => 1,
            'email_enabled' => 1,
            'sms_enabled' => 0,
            'email_frequency' => 'immediate'
        ];
    }
}

/**
 * Send notification to staff (counsellor, accountant, etc.)
 * 
 * @param PDO $conn Database connection
 * @param string $notification_type Type of notification
 * @param string $recipient_role Staff role (counsellor, accountant, principal)
 * @param int $recipient_id Staff user ID
 * @param array $variables Template variables
 * @param array $options Additional options
 * @return array Results
 */
function sendStaffNotification($conn, $notification_type, $recipient_role, $recipient_id, $variables = [], $options = [])
{
    // Get staff details
    $stmt = $conn->prepare("SELECT name, email, mobile FROM tbl_users WHERE id = ?");
    $stmt->execute([$recipient_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        return ['success' => false, 'error' => 'Staff not found'];
    }

    // Map notification types to email templates for staff
    $email_template_map = [
        'new_registration' => 'email_new_registration_counsellor',
        'token_payment' => 'email_token_payment_accountant',
        'admission_confirmed' => 'email_admission_confirmed_counsellor'
    ];

    if (isset($email_template_map[$notification_type])) {
        return sendEmailTemplate(
            $conn,
            $email_template_map[$notification_type],
            $staff['email'],
            $staff['name'],
            $variables,
            array_merge($options, [
                'recipient_type' => $recipient_role,
                'user_id' => $recipient_id
            ])
        );
    }

    return ['success' => false, 'error' => 'Template not found'];
}


