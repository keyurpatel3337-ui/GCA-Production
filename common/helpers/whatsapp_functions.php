<?php

/**
 * WhatsApp API Helper Functions
 * Supports BhashSMS and Wati providers
 */

require_once __DIR__ . '/../db_connect.php';
require_once OPERATION_FILE;

/**
 * WhatsApp Service Status - Set to TRUE to enable messages
 */
define('WHATSAPP_SERVICE_ACTIVE', true);

/**
 * Get active WhatsApp provider - Using Static Config
 */
function getActiveWhatsAppProvider($conn = null, $provider_name = null)
{
    try {
        // Load static config from env.config.php via app-config.php
        require_once __DIR__ . '/../config/app-config.php';

        $config = getWhatsAppConfig($provider_name);

        if (!$config) {
            return null;
        }

        return $config;
    } catch (Exception $e) {
        error_log("Error getting WhatsApp provider: " . $e->getMessage());
        return null;
    }
}

/**
 * Send WhatsApp Template Message - BhashSMS
 * API Format: http://bhashsms.com/api/sendmsgutil.php?user=USERNAME&pass=PASSWORD&sender=SENDER&phone=NUMBER&text=TEMPLATE&priority=wa&stype=normal&Params=p1,p2
 */
function sendWhatsAppBhashSMS($config, $recipient, $template_name, $variables = [], $media_url = null, $media_type = null)
{
    if (!WHATSAPP_SERVICE_ACTIVE) {
        return ['success' => false, 'error' => 'WhatsApp service is currently disabled.'];
    }
    // BhashSMS expects phone without country code (10 digits only)
    $recipient = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $recipient));

    // Check for Test Mode
    if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
        $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
        if (!in_array($recipient, $allowed)) {
            return ['success' => false, 'error' => 'Blocked: Recipient not in WhatsApp test whitelist.'];
        }
    }

    // Build API URL with query parameters - Official BhashSMS API format
    $params = [
        'user' => $config['api_secret'] ?? '', // Username (GYANMANJARI_CAREER)
        'pass' => $config['api_key'], // Password
        'sender' => $config['sender_number'] ?? 'BUZWAP', // Sender ID
        'phone' => $recipient,
        'text' => $template_name,
        'priority' => 'wa',
        'stype' => 'normal'
        // Note: Do NOT add htype for normal template messages
        // htype is only for: media (image/video/document) OR non-template text (htype=normal)
    ];

    // Add parameters if provided (Strict Cleaning for Meta & BhashSMS)
    if (!empty($variables)) {
        $variables = array_map(function ($val) use ($template_name) {
            $val = (string) $val;
            
            // Allow newlines only for summary reports to ensure proper rendering
            if (strpos($template_name, 'daily_summary') === false) {
                // 1. Remove newlines and tabs (Strict Meta Guideline for standard templates)
                $val = str_replace(["\n", "\r", "\t"], " ", $val);
            }
            
            $val = trim($val);

            // If the value is empty after cleanup, send a dash instead of a blank.
            return $val === '' ? '-' : $val;
        }, $variables);
        $params['Params'] = implode(',', $variables);
    }

    // Add media if provided (image/video/document)
    // API: &htype=image&url=URL or &htype=video&url=URL or &htype=document&url=URL
    if ($media_url && $media_type) {
        $params['htype'] = $media_type; // image/video/document
        $params['url'] = $media_url;
    }

    // Use RFC3986 encoding (spaces as %20, not +) to match BhashSMS API expectations
    $baseUrl = ($template_name === 'dy_msg_001' && defined('WHATSAPP_DYNAMIC_API_URL'))
        ? WHATSAPP_DYNAMIC_API_URL
        : (defined('WHATSAPP_API_URL') ? WHATSAPP_API_URL : 'http://bhashsms.com/api/sendmsgutil.php');

    $api_url = $baseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    
    // No special newline handling here as it's causing Meta to block messages

    // Make GET request
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // BhashSMS returns plain text response (Success: 1234567890 | Error: Error Message)
    // Common errors: "Invalid Type", "Marketing Templates Not Allowed", "Insufficient Balance"
    // BhashSMS returns plain text response (Success: 1234567890 | Error: Error Message)
    // NOTE: Sometimes returns empty string on success with HTTP 200
    $success = ($http_code == 200 &&
        stripos($response, 'error') === false &&
        stripos($response, 'not allowed') === false &&
        stripos($response, 'invalid') === false);

    $message_id = null;
    if ($success) {
        // BhashSMS response format: "Success: 1234567890"
        if (preg_match('/(?:Success|success):\s*([A-Z0-9\.\-_]+)/i', $response, $matches)) {
            $message_id = $matches[1];
        } else {
            $message_id = trim(strip_tags($response));
        }
        $otp_log = "";
        $var_log = "";
        if (!empty($variables)) {
            $var_log = "\nVariables Sent:\n";
            foreach ($variables as $idx => $v) {
                $var_log .= "  Var " . ($idx + 1) . ": " . $v . "\n";
            }
        }
        $url_log = "\nAPI URL: " . preg_replace('/pass=[^&]*/', 'pass=********', $api_url);

        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] INFO: WhatsApp BhashSMS sent successfully to {$recipient}. Template: {$template_name}.{$otp_log}{$var_log} Message ID: {$message_id}{$url_log}\n" . str_repeat('-', 50) . "\n\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    } else {
        $var_log = "";
        if (!empty($variables)) {
            $var_log = "\nVariables attempted:\n";
            foreach ($variables as $idx => $v) {
                $var_log .= "  Var " . ($idx + 1) . ": " . $v . "\n";
            }
        }
        $url_log = "\nAPI URL: " . preg_replace('/pass=[^&]*/', 'pass=********', $api_url);

        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] ERROR: WhatsApp BhashSMS failed to {$recipient}. Template: {$template_name}.{$var_log} Response: {$response}. HTTP Code: {$http_code}. Curl Error: {$error}{$url_log}\n" . str_repeat('-', 50) . "\n\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    return [
        'success' => $success,
        'message_id' => $message_id,
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error
    ];
}

/**
 * Send WhatsApp OTP/Authentication Message - BhashSMS
 * API: user=X&pass=Y&sender=Z&phone=NUM&text=TEMPLATENAME&priority=wa&stype=auth&Params=OTP
 * 
 * @param array $config WhatsApp provider configuration
 * @param string $recipient Phone number without country code 91
 * @param string $template_name Template name for OTP
 * @param string $otp_code OTP code to send
 * @return array Result with success status and response
 */
function sendWhatsAppOTP_BhashSMS($config, $recipient, $template_name, $otp_code)
{
    if (!WHATSAPP_SERVICE_ACTIVE) {
        return ['success' => false, 'error' => 'WhatsApp service is currently disabled.'];
    }
    // BhashSMS expects phone without country code (91)
    $recipient = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $recipient));

    // Check for Test Mode
    if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
        $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
        if (!in_array($recipient, $allowed) && !in_array(preg_replace('/^91/', '', $recipient), $allowed)) {
            return ['success' => false, 'error' => 'Blocked: Recipient not in WhatsApp test whitelist.'];
        }
    }

    $params = [
        'user' => $config['api_secret'],
        'pass' => $config['api_key'],
        'sender' => $config['sender_number'],
        'phone' => $recipient,
        'text' => $template_name,
        'priority' => 'wa',
        'stype' => 'auth',  // Authentication type
        'Params' => $otp_code
    ];

    $api_url = (defined('WHATSAPP_API_URL') ? WHATSAPP_API_URL : 'http://bhashsms.com/api/sendmsg.php') . '?' . http_build_query($params);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    $success = ($http_code == 200 && !empty($response) && stripos($response, 'error') === false);

    $message_id = null;
    if ($success) {
        if (preg_match('/(?:Success|success):\s*(\d+)/i', $response, $matches)) {
            $message_id = $matches[1];
        } else {
            $message_id = trim(strip_tags($response));
        }
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        error_log("[" . date('Y-m-d H:i:s') . "] INFO: WhatsApp OTP sent successfully via BhashSMS to {$recipient}. OTP: {$otp_code}. Message ID: {$message_id}\n" . str_repeat('-', 50) . "\n\n", 3, $log_file);
    } else {
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ERROR: WhatsApp OTP failed via BhashSMS to {$recipient}. Response: {$response}. HTTP Code: {$http_code}\n" . str_repeat('-', 50) . "\n\n", 3, $log_file);
    }

    return [
        'success' => $success,
        'message_id' => $message_id,
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error
    ];
}

/**
 * Send WhatsApp Non-Template Text (After customer replies) - BhashSMS
 * API: user=X&pass=Y&sender=Z&phone=NUM&text=TEXT&priority=wa&stype=normal&htype=normal
 * 
 * @param array $config WhatsApp provider configuration
 * @param string $recipient Phone number without country code 91
 * @param string $text Plain text message to send
 * @return array Result with success status and response
 */
function sendWhatsAppText_BhashSMS($config, $recipient, $text)
{
    if (!WHATSAPP_SERVICE_ACTIVE) {
        return ['success' => false, 'error' => 'WhatsApp service is currently disabled.'];
    }
    // BhashSMS expects phone without country code (91)
    $recipient = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $recipient));

    // Check for Test Mode
    if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
        $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
        if (!in_array($recipient, $allowed) && !in_array(preg_replace('/^91/', '', $recipient), $allowed)) {
            return ['success' => false, 'error' => 'Blocked: Recipient not in WhatsApp test whitelist.'];
        }
    }

    $params = [
        'user' => $config['api_secret'],
        'pass' => $config['api_key'],
        'sender' => $config['sender_number'],
        'phone' => $recipient,
        'text' => $text,
        'priority' => 'wa',
        'stype' => 'normal',
        'htype' => 'normal'  // Required for non-template messages
    ];

    $api_url = (defined('WHATSAPP_API_URL') ? WHATSAPP_API_URL : 'http://bhashsms.com/api/sendmsg.php') . '?' . http_build_query($params);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    $success = ($http_code == 200 && !empty($response) && stripos($response, 'error') === false);

    $message_id = null;
    if ($success) {
        if (preg_match('/(?:Success|success):\s*(\d+)/i', $response, $matches)) {
            $message_id = $matches[1];
        } else {
            $message_id = trim(strip_tags($response));
        }
        $otp_log = "";
        if (preg_match('/\b(\d{4,6})\b/', $text, $matches)) {
            $otp_log = " OTP: {$matches[1]}.";
        }
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] INFO: WhatsApp Text sent successfully via BhashSMS to {$recipient}.{$otp_log} Message ID: {$message_id}\n" . str_repeat('-', 50) . "\n\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    } else {
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] ERROR: WhatsApp Text failed via BhashSMS to {$recipient}. Response: {$response}. HTTP Code: {$http_code}\n" . str_repeat('-', 50) . "\n\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    return [
        'success' => $success,
        'message_id' => $message_id,
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error
    ];
}

/**
 * Send WhatsApp Template Message - Wati
 */
function sendWhatsAppWati($config, $recipient, $template_name, $variables = [])
{
    if (!WHATSAPP_SERVICE_ACTIVE) {
        return ['success' => false, 'error' => 'WhatsApp service is currently disabled.'];
    }
    // Wati expects recipient in international format without +
    $recipient = preg_replace('/[^0-9]/', '', $recipient);

    $api_url = rtrim($config['api_url'], '/') . '/sendTemplateMessage';

    // Wati format: parameters as array of objects with type and text
    $parameters = [];
    foreach ($variables as $value) {
        $parameters[] = [
            'type' => 'text',
            'text' => $value
        ];
    }

    $data = [
        'whatsappNumber' => $recipient,
        'template_name' => $template_name,
        'broadcast_name' => 'Template Message',
        'parameters' => $parameters
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['api_key'],
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $result = json_decode($response, true);
    $success = $http_code == 200 && (!isset($result['error']) || !$result['error']);

    $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
    if ($success) {
        $otp_log = "";
        if (!empty($variables)) {
            foreach ($variables as $v) {
                if (preg_match('/^\d{4,6}$/', trim((string) $v))) {
                    $otp_log = " OTP: {$v}.";
                    break;
                }
            }
        }
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        @file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] INFO: WhatsApp Wati sent successfully to {$recipient}. Template: {$template_name}.{$otp_log}\n" . str_repeat('-', 50) . "\n\n", FILE_APPEND);
    } else {
        $error_detail = isset($result['error']) ? (is_array($result['error']) ? json_encode($result['error']) : $result['error']) : 'Unknown Wati error';
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        @file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: WhatsApp Wati failed to {$recipient}. Template: {$template_name}. Error: {$error_detail}. HTTP Code: {$http_code}\n" . str_repeat('-', 50) . "\n\n", FILE_APPEND);
    }

    return [
        'success' => $success,
        'message_id' => $result['messageId'] ?? $result['result']['messageId'] ?? null,
        'response' => $result,
        'http_code' => $http_code
    ];
}

/**
 * Send WhatsApp Template Message (Auto-detect provider)
 * 
 * @param PDO $conn Database connection
 * @param string $recipient Phone number (with or without country code)
 * @param int|string $template_id Template ID (integer) or template name (string)
 * @param array $variables Template variables in order
 * @param string|null $provider_name Optional provider name
 * @param string|null $related_entity Optional entity type (e.g., 'payment', 'enrollment')
 * @param int|null $related_id Optional entity ID
 * @param int|null $sent_by Optional user ID who sent the message
 * @return array Response with success status and details
 */
function sendWhatsAppTemplate($conn, $recipient, $template_id, $variables = [], $provider_name = null, $related_entity = null, $related_id = null, $sent_by = null)
{
    // Auto-fetch mobile from tbl_gm_std_registration if student ID is provided
    if (!empty($related_id) && (empty($recipient) || $recipient === 'STUDENT_MOBILE')) {
        try {
            $stmt_mob = $conn->prepare("SELECT mob FROM tbl_gm_std_registration WHERE id = ?");
            $stmt_mob->execute([$related_id]);
            $db_mob = $stmt_mob->fetchColumn();
            if ($db_mob) {
                $recipient = $db_mob;
            }
        } catch (Exception $e) {
            // Continue with original recipient if DB lookup fails
        }
    }

    if (!WHATSAPP_SERVICE_ACTIVE) {
        return ['success' => false, 'error' => 'WhatsApp service is currently disabled.'];
    }

    try {
        // Get template details - support both ID (integer) and name (string)
        if (is_numeric($template_id)) {
            $stmt = $conn->prepare("SELECT 
                               t.id as template_id, t.template_name, t.template_category, t.body_text,
                               t.header_type, t.header_content, t.approval_status, t.variables, t.is_active as template_active
                               FROM tbl_whatsapp_templates t
                               WHERE t.id = ? AND t.is_active = 1");
            $stmt->execute([$template_id]);
        } else {
            // Template name passed - look up by name
            $stmt = $conn->prepare("SELECT 
                               t.id as template_id, t.template_name, t.template_category, t.body_text,
                               t.header_type, t.header_content, t.approval_status, t.variables, t.is_active as template_active
                               FROM tbl_whatsapp_templates t
                               WHERE t.template_name = ? AND t.is_active = 1");
            $stmt->execute([$template_id]);
        }
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found or inactive: ' . $template_id];
        }

        // Check template approval status
        if ($template['approval_status'] !== 'approved') {
            return ['success' => false, 'error' => 'Template not approved. Status: ' . $template['approval_status']];
        }

        // Process variables - Filter and order based on template definition
        $wa_variables = $variables;
        if (!empty($template['variables'])) {
            $template_vars = json_decode($template['variables'], true);
            if (is_array($template_vars) && !empty($template_vars)) {
                $wa_variables = [];
                foreach ($template_vars as $index => $var_name) {
                    // Check if variable exists in input
                    if (isset($variables[$var_name])) {
                        $wa_variables[] = $variables[$var_name];
                    } else if (preg_match('/^var(\d+)$/i', $var_name, $m)) {
                        // If it's var1, var2, etc., and not in input, take from indexed values
                        $vals = array_values($variables);
                        $v_idx = intval($m[1]) - 1;
                        $wa_variables[] = $vals[$v_idx] ?? '';
                    } else {
                        // Support common aliases if not already in $variables
                        switch ($var_name) {
                            case 'name':
                                $wa_variables[] = $variables['student_name'] ?? '';
                                break;
                            case 'student_name':
                                $wa_variables[] = $variables['name'] ?? '';
                                break;
                            case 'receipt_no':
                                $wa_variables[] = $variables['receipt_numbers'] ?? '';
                                break;
                            case 'transaction_id':
                                $wa_variables[] = $variables['payment_id'] ?? '';
                                break;
                            case 'amount':
                                $wa_variables[] = $variables['token_amount'] ?? '';
                                break;
                            default:
                                $wa_variables[] = '';
                        }
                    }
                }
            } else if (is_array($variables) && !empty($variables) && array_keys($variables) !== range(0, count($variables) - 1)) {
                $wa_variables = array_values($variables);
            }
        } else {
            // Fallback: Parse body_text to get required variable count
            $parsed_vars = parseTemplateVariables($template['body_text']);
            if (!empty($parsed_vars)) {
                $max_var = max($parsed_vars);
                $wa_variables = [];
                // If associative, try to pick common ones first or just take values
                if (is_array($variables) && !empty($variables) && array_keys($variables) !== range(0, count($variables) - 1)) {
                    // For associative, we pick the first $max_var values in order
                    $vals = array_values($variables);
                    for ($i = 0; $i < $max_var; $i++) {
                        $wa_variables[] = $vals[$i] ?? '';
                    }
                } else if (is_array($variables)) {
                    // Already indexed, just trim to $max_var
                    $wa_variables = array_slice($variables, 0, $max_var);
                }
            } else if (is_array($variables) && !empty($variables) && array_keys($variables) !== range(0, count($variables) - 1)) {
                $wa_variables = array_values($variables);
            }
        }

        // Get static WhatsApp provider configuration
        $config = getActiveWhatsAppProvider($conn, $provider_name);
        if (!$config) {
            return ['success' => false, 'error' => 'WhatsApp provider not configured'];
        }

        // Merge config with template
        $template = array_merge($template, $config);

        // Send based on provider
        if ($config['provider_name'] === 'bhashsms') {
            $result = sendWhatsAppBhashSMS($config, $recipient, $template['template_name'], $wa_variables);
        } elseif ($config['provider_name'] === 'wati') {
            $result = sendWhatsAppWati($config, $recipient, $template['template_name'], $wa_variables);
        } else {
            return ['success' => false, 'error' => 'Unsupported provider'];
        }

        // Generate message content for logging
        $message_content = replaceTemplateVariables($template['body_text'], $variables);

        // Log the message with related entity tracking
        logWhatsAppMessage($conn, [
            'template_id' => $template['template_id'],
            'recipient_number' => $recipient,
            'template_name' => $template['template_name'],
            'message_content' => $message_content,
            'variables' => $wa_variables, // Log processed/filtered variables
            'status' => $result['success'] ? 'sent' : 'failed',
            'message_id' => $result['message_id'] ?? null,
            'error_message' => $result['success'] ? null : (is_array($result['response']) ? ($result['response']['error'] ?? json_encode($result['response'])) : $result['response']),
            'api_response' => $result,
            'related_entity' => $related_entity,
            'related_id' => $related_id,
            'created_by' => $sent_by
        ]);

        // Implement CC Logic: Send a duplicate to the monitoring numbers ONLY for Fees/Payments
        // Skip CC for reports/parent_update_not to avoid cluttering monitoring numbers
        $cc_numbers = ($template['template_name'] === 'parent_update_not') ? [] : ['9998994020'];
        $fee_templates = ['parent_update_not', 'fee_reminder', 'token_fee', 'feepaymentsuccess', 'pending_fee', 'payment_success'];
        $is_fee_notif = false;

        foreach ($fee_templates as $ft) {
            if (strpos($template['template_name'], $ft) !== false) {
                $is_fee_notif = true;
                break;
            }
        }

        // Clean recipient for comparison (10 digits)
        $clean_recipient = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $recipient));

        if ($is_fee_notif && $result['success']) {
            foreach ($cc_numbers as $cc_number) {
                $clean_cc = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $cc_number));
                if ($clean_recipient !== $clean_cc) {
                    if ($config['provider_name'] === 'bhashsms') {
                        sendWhatsAppBhashSMS($config, $cc_number, $template['template_name'], $wa_variables);
                    } elseif ($config['provider_name'] === 'wati') {
                        sendWhatsAppWati($config, $cc_number, $template['template_name'], $wa_variables);
                    }
                }
            }
        }

        if ($result['success']) {
            $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
            error_log("[" . date('Y-m-d H:i:s') . "] INFO: WhatsApp Template message sent successfully to {$recipient}. Provider: {$config['provider_name']}\n", 3, $log_file);
        } else {
            $err = $result['error'] ?? 'Unknown error';
            $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
            error_log("[" . date('Y-m-d H:i:s') . "] ERROR: WhatsApp Template message failed to {$recipient}. Provider: {$config['provider_name']}. Error: {$err}\n", 3, $log_file);
        }

        return $result;
    } catch (Exception $e) {
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ERROR: Exception while sending WhatsApp template to {$recipient}. Error: " . $e->getMessage() . "\n", 3, $log_file);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Log WhatsApp message
 */
function logWhatsAppMessage($conn, $data)
{
    try {
        $stmt = $conn->prepare("INSERT INTO tbl_whatsapp_logs 
                               (template_id, recipient_number, recipient_name, student_id,
                                related_entity, related_id, message_type, template_name, message_content, 
                                variables, media_url, status, message_id, error_message, sent_at, 
                                api_response, created_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

        $stmt->execute([
            $data['template_id'] ?? null,
            $data['recipient_number'],
            $data['recipient_name'] ?? null,
            $data['student_id'] ?? null,
            $data['related_entity'] ?? null,
            $data['related_id'] ?? null,
            $data['message_type'] ?? 'template',
            $data['template_name'] ?? null,
            $data['message_content'] ?? null,
            json_encode($data['variables'] ?? []),
            $data['media_url'] ?? null,
            $data['status'],
            $data['message_id'] ?? null,
            $data['error_message'] ?? null,
            json_encode($data['api_response'] ?? []),
            $data['created_by'] ?? $_SESSION['user_id'] ?? null
        ]);

        return $conn->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error logging WhatsApp message: " . $e->getMessage());
        return false;
    }
}

/**
 * Get template by ID
 */
function getWhatsAppTemplate($conn, $template_id)
{
    try {
        $stmt = $conn->prepare("SELECT t.*, 'Static Config' as provider_name, 'Static Config' as provider_display_name
                               FROM tbl_whatsapp_templates t
                               WHERE t.id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            // Convert variables JSON array to comma-separated string for display
            if (!empty($template['variables'])) {
                $vars = json_decode($template['variables'], true);
                $template['variable_names'] = is_array($vars) ? implode(', ', $vars) : '';
            } else {
                $template['variable_names'] = '';
            }
        }

        return $template;
    } catch (PDOException $e) {
        error_log("Error getting WhatsApp template: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all templates
 */
function getAllWhatsAppTemplates($conn, $filters = [])
{
    try {
        // Static WhatsApp configuration - no provider table join needed
        $query = "SELECT t.*, 
                  'Static Config' as provider_name, 
                  'Static Config' as provider_display_name
                  FROM tbl_whatsapp_templates t
                  WHERE 1=1";
        $params = [];

        if (isset($filters['category']) && !empty($filters['category'])) {
            $query .= " AND t.template_category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['status']) && !empty($filters['status'])) {
            $query .= " AND t.approval_status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['active']) && !empty($filters['active'])) {
            $query .= " AND t.is_active = ?";
            $params[] = $filters['active'];
        }

        $query .= " ORDER BY t.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert variables JSON array to comma-separated string for display
        foreach ($results as &$template) {
            if (!empty($template['variables'])) {
                $vars = json_decode($template['variables'], true);
                $template['variable_names'] = is_array($vars) ? implode(', ', $vars) : '';
            } else {
                $template['variable_names'] = '';
            }
        }

        return $results;
    } catch (PDOException $e) {
        error_log("Error getting WhatsApp templates: " . $e->getMessage());
        return [];
    }
}

/**
 * Test WhatsApp template
 */
function testWhatsAppTemplate($conn, $template_id, $test_number, $test_variables)
{
    try {
        $result = sendWhatsAppTemplate($conn, $test_number, $template_id, $test_variables);

        // Test is already logged by sendWhatsAppTemplate -> logWhatsAppMessage into tbl_whatsapp_logs
        // No separate test table needed

        return $result;
    } catch (Exception $e) {
        error_log("Error testing WhatsApp template: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Submit template for approval (provider-specific implementation)
 */
function submitTemplateForApproval($conn, $template_id)
{
    try {
        $template = getWhatsAppTemplate($conn, $template_id);

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        // Note: Actual submission depends on provider's API
        // For now, mark as pending - admin will manually submit via provider dashboard
        $stmt = $conn->prepare("UPDATE tbl_whatsapp_templates 
                               SET approval_status = 'pending', submitted_at = NOW()
                               WHERE id = ?");
        $stmt->execute([$template_id]);

        return [
            'success' => true,
            'message' => 'Template marked as pending. Please submit via ' . $template['provider_display_name'] . ' dashboard.',
            'note' => 'You need to manually submit this template in the WhatsApp Business API provider dashboard for approval.'
        ];
    } catch (Exception $e) {
        error_log("Error submitting template: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get template categories
 */
function getTemplateCategories()
{
    return [
        'utility' => 'Utility',
        'authentication' => 'Authentication',
        'marketing' => 'Marketing'
    ];
}

/**
 * Get approval statuses
 */
function getApprovalStatuses()
{
    return [
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];
}

/**
 * Parse template body and extract variables
 */
function parseTemplateVariables($body_text)
{
    if (empty($body_text)) {
        return [];
    }
    preg_match_all('/\{\{(\d+)\}\}/', $body_text, $matches);
    return array_unique($matches[1]);
}

/**
 * Replace template variables with actual values
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
 * Check delivery status for BhashSMS message
 * 
 * @param array $config Configuration
 * @param string $message_id BhashSMS message ID
 * @return array Status results
 */
function checkWhatsAppBhashSMSStatus($config, $message_id)
{
    $params = [
        'user' => $config['api_secret'],
        'pass' => $config['api_key'],
        'msgid' => $message_id
    ];

    $api_url = 'http://bhashsms.com/api/getdlr.php?' . http_build_query($params);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Response is typically plain text: "Delivered", "Submitted", "Read", etc.
    $status = trim(strtolower($response));

    return [
        'success' => $http_code == 200 && !empty($response),
        'status' => $status,
        'raw_response' => $response
    ];
}

/**
 * Sync WhatsApp delivery statuses from providers
 * Typically called via cron job or manual refresh
 * 
 * @param PDO $conn Database connection
 * @param int $limit Max messages to check
 * @return array Stats of synced messages
 */
function syncWhatsAppStatus($conn, $limit = 50)
{
    $stats = ['checked' => 0, 'updated' => 0];

    try {
        // Fetch 'sent' messages that haven't been updated to final status yet
        // Check messages that were sent at least 1 minute ago
        // and limit to last 7 days to avoid searching too far back.
        $stmt = $conn->prepare("SELECT id, message_id, status FROM tbl_whatsapp_logs 
                               WHERE status = 'sent' AND message_id IS NOT NULL 
                               AND created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                               ORDER BY created_at ASC LIMIT ?");
        $stmt->execute([$limit]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($logs))
            return $stats;

        $config = getActiveWhatsAppProvider($conn);
        if (!$config || $config['provider_name'] !== 'bhashsms')
            return $stats;

        foreach ($logs as $log) {
            $stats['checked']++;
            $result = checkWhatsAppBhashSMSStatus($config, $log['message_id']);

            if ($result['success']) {
                $new_status = null;
                // Map BhashSMS statuses to our system statuses
                if (stripos($result['status'], 'delivered') !== false || stripos($result['status'], 'delivrd') !== false) {
                    $new_status = 'delivered';
                } elseif (stripos($result['status'], 'read') !== false) {
                    $new_status = 'read';
                } elseif (stripos($result['status'], 'failed') !== false || stripos($result['status'], 'undelivrd') !== false || stripos($result['status'], 'error') !== false || stripos($result['status'], 'expired') !== false) {
                    $new_status = 'failed';
                }

                if ($new_status && $new_status !== $log['status']) {
                    $timestamp_sql = "";
                    if ($new_status === 'delivered') {
                        $timestamp_sql = ", delivered_at = NOW()";
                    } elseif ($new_status === 'read') {
                        $timestamp_sql = ", read_at = NOW(), delivered_at = IFNULL(delivered_at, NOW())";
                    }

                    $upd = $conn->prepare("UPDATE tbl_whatsapp_logs SET status = ?, updated_at = NOW() $timestamp_sql WHERE id = ?");
                    $upd->execute([$new_status, $log['id']]);
                    $stats['updated']++;

                    $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
                    error_log("[" . date('Y-m-d H:i:s') . "] INFO: WhatsApp status updated for Log ID {$log['id']} to {$new_status}\n", 3, $log_file);
                }
            }
        }
    } catch (Exception $e) {
        $log_file = (defined('LOGS_PATH') ? LOGS_PATH : dirname(__DIR__) . '/logs/') . 'whatsapp/whatsapp-' . date('Y-m-d') . '.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ERROR: WhatsApp status sync failed: " . $e->getMessage() . "\n", 3, $log_file);
    }

    return $stats;
}



/**
 * Send Free-form Dynamic WhatsApp Message using a wrapper template
 * 
 * @param PDO $conn Database connection
 * @param string $recipient Phone number
 * @param string $message Full message text to be placed in {{1}}
 * @param int|null $related_id Optional student/entity ID
 * @param int|null $sent_by Optional user ID
 * @return array Response
 */
function sendWhatsAppDynamic($conn, $recipient, $message, $related_id = null, $sent_by = null)
{
    return sendWhatsAppTemplate($conn, $recipient, 'dy_msg_001', [$message], 'bhashsms', 'dynamic', $related_id, $sent_by);
}
