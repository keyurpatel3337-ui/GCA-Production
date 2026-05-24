<?php

/**
 * WhatsApp API Helper Class
 * Handles sending WhatsApp messages via BhashSMS API
 * 
 * Copied from GCA implementation - tested and working
 */

class WhatsAppHelper
{
    private $conn;
    private $config;
    private $lastError;

    /**
     * Constructor - Initialize with database connection
     * @param PDO $conn Database connection
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadDefaultConfig();
    }

    /**
     * Load default WhatsApp configuration from static config
     */
    private function loadDefaultConfig()
    {
        // Ensure constants from env.config.php are available
        if (!defined('APP_INIT'))
            define('APP_INIT', true);
        require_once __DIR__ . '/../../env.config.php';

        $this->config = [
            'api_user' => defined('WHATSAPP_API_SECRET') ? WHATSAPP_API_SECRET : 'GYANMANJARI_CAREER',
            'api_pass' => defined('WHATSAPP_API_KEY') ? WHATSAPP_API_KEY : '098765',
            'api_url' => defined('WHATSAPP_API_URL') ? WHATSAPP_API_URL : 'http://bhashsms.com/api/sendmsgutil.php',
            'sender_id' => defined('WHATSAPP_SENDER') ? WHATSAPP_SENDER : 'BUZWAP',
            'priority' => 'wa'
        ];
    }

    /**
     * Send WhatsApp message (without media)
     * BhashSMS API: http://bhashsms.com/api/sendmsgutil.php
     *
     * @param string $phone Phone number (without country code)
     * @param string $templateCode Template code
     * @param array $params Parameters for the template
     * @param int $sentBy User ID who sent
     * @param string $relatedEntity Related entity type
     * @param int $relatedId Related entity ID
     * @return array Response with success status and message
     */
    public function sendMessage($phone, $templateCode, $params = [], $sentBy = null, $relatedEntity = null, $relatedId = null)
    {
        if (!$this->config) {
            $this->lastError = "No WhatsApp configuration found";
            return ['success' => false, 'message' => $this->lastError];
        }

        // Clean phone number (remove +, spaces, etc. and strip leading 91)
        $phone = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $phone));

        // Check for Test Mode
        if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
            $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
            if (!in_array($phone, $allowed)) {
                $err = "Blocked: Recipient $phone not in WhatsApp test whitelist.";
                $this->logToFile($phone, $templateCode, ['success' => false, 'message' => $err]);
                return ['success' => false, 'message' => $err];
            }
        }

        // Build API URL - use sendmsgutil.php for ALL template types
        $apiUrl = $this->config['api_url'];

        $templateCode = trim($templateCode);
        $queryParams = [
            'user' => $this->config['api_user'],      // GYANMANJARI_CAREER
            'pass' => $this->config['api_pass'],      // 098765
            'sender' => $this->config['sender_id'],   // BUZWAP
            'phone' => $phone,                        // 7990965567
            'text' => $templateCode,                  // Template name
            'priority' => $this->config['priority'] ?? 'wa',  // wa
            'stype' => 'normal'                       // normal for utility/marketing templates
        ];

        // Truncate parameters to 30 characters maximum and trim them
        if (!empty($params)) {
            $params = array_map(function ($val) {
                return mb_substr(trim((string) $val), 0, 30);
            }, $params);
            $queryParams['Params'] = implode(',', $params);
        }

        // Build URL using RFC3986 (encodes space as %20 instead of +)
        $fullUrl = $apiUrl . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        // Log the message attempt
        $logId = $this->logMessage($templateCode, $phone, $params, null, $sentBy, $relatedEntity, $relatedId);

        // Send the request
        $response = $this->makeRequest($fullUrl);

        // Update log with response
        $this->updateLog($logId, $response);

        // Add file logging for visibility
        $this->logToFile($phone, $templateCode, $response);

        return $response;
    }

    /**
     * Send WhatsApp message with media attachment
     *
     * @param string $phone Phone number
     * @param string $templateCode Template code
     * @param array $params Parameters for the template
     * @param string $mediaUrl URL of the media file
     * @param string $mediaType Type of media (image, video, document)
     * @param int $sentBy User ID who sent
     * @param string $relatedEntity Related entity type
     * @param int $relatedId Related entity ID
     * @return array Response with success status and message
     */
    public function sendMessageWithMedia($phone, $templateCode, $params, $mediaUrl, $mediaType = 'image', $sentBy = null, $relatedEntity = null, $relatedId = null)
    {
        if (!$this->config) {
            $this->lastError = "No WhatsApp configuration found";
            return ['success' => false, 'message' => $this->lastError];
        }

        // Clean phone number (and strip leading 91)
        $phone = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $phone));

        // Check for Test Mode
        if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
            $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
            if (!in_array($phone, $allowed)) {
                $err = "Blocked: Recipient $phone not in WhatsApp test whitelist.";
                $this->logToFile($phone, $templateCode, ['success' => false, 'message' => $err]);
                return ['success' => false, 'message' => $err];
            }
        }

        $apiUrl = $this->config['api_url'];

        $templateCode = trim($templateCode);
        $queryParams = [
            'user' => $this->config['api_user'],
            'pass' => $this->config['api_pass'],
            'sender' => $this->config['sender_id'],
            'phone' => $phone,
            'text' => $templateCode,
            'priority' => $this->config['priority'] ?? 'wa',
            'stype' => 'normal',
            'htype' => $mediaType,     // image, video, or document
            'url' => $mediaUrl         // Public URL of media file
        ];

        // Add parameters (Strict Cleaning for Meta & BhashSMS)
        if (!empty($params)) {
            $params = array_map(function ($val) {
                $val = (string) $val;
                // 1. Remove newlines and tabs (Strict Meta Guideline - causes failure)
                $val = str_replace(["\n", "\r", "\t"], " ", $val);
                // 2. Replace commas with spaces (BhashSMS uses comma as variable delimiter)
                $val = str_replace(",", " ", $val);
                // 3. Truncate to 30 chars for safety/conciseness
                return mb_substr(trim($val), 0, 30);
            }, $params);
            $queryParams['Params'] = implode(',', $params);
        }


        // Build URL using RFC3986 (encodes space as %20 instead of +)
        $fullUrl = $apiUrl . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        // Log the message attempt
        $logId = $this->logMessage($templateCode, $phone, $params, $mediaUrl, $sentBy, $relatedEntity, $relatedId);

        // Send the request
        $response = $this->makeRequest($fullUrl);

        // Update log with response
        $this->updateLog($logId, $response);

        // Add file logging for visibility
        $this->logToFile($phone, $templateCode, $response);

        return $response;
    }

    /**
     * Send WhatsApp OTP/Authentication message (stype=auth)
     *
     * @param string $phone Phone number (without country code)
     * @param string $templateCode Template name registered as authentication type
     * @param string $otpCode The OTP value to pass as Params
     * @param int|null $sentBy User ID who sent
     * @param int|null $relatedId Related entity ID
     * @return array Response with success status and message
     */
    public function sendOTP($phone, $templateCode, $otpCode, $sentBy = null, $relatedId = null)
    {
        if (!$this->config) {
            $this->lastError = "No WhatsApp configuration found";
            return ['success' => false, 'message' => $this->lastError];
        }

        $phone = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $phone));

        if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
            $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
            if (!in_array($phone, $allowed)) {
                $err = "Blocked: Recipient $phone not in WhatsApp test whitelist.";
                $this->logToFile($phone, $templateCode, ['success' => false, 'message' => $err]);
                return ['success' => false, 'message' => $err];
            }
        }

        $templateCode = trim($templateCode);
        $queryParams = [
            'user' => $this->config['api_user'],
            'pass' => $this->config['api_pass'],
            'sender' => $this->config['sender_id'],
            'phone' => $phone,
            'text' => $templateCode,
            'priority' => $this->config['priority'] ?? 'wa',
            'stype' => 'auth',
            'Params' => $otpCode
        ];

        $fullUrl = $this->config['api_url'] . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $logId = $this->logMessage($templateCode, $phone, [$otpCode], null, $sentBy, 'otp', $relatedId);
        $response = $this->makeRequest($fullUrl);
        $this->updateLog($logId, $response);
        $this->logToFile($phone, $templateCode, $response);

        return $response;
    }

    /**
     * Send a free-form dynamic WhatsApp message using the dy_msg_001 wrapper template
     * Routes to WHATSAPP_DYNAMIC_API_URL if defined, otherwise falls back to the standard API URL.
     *
     * @param string $phone Phone number (without country code)
     * @param string $message Full message text placed in {{1}}
     * @param int|null $sentBy User ID who sent
     * @param int|null $relatedId Related entity ID
     * @return array Response with success status and message
     */
    public function sendDynamic($phone, $message, $sentBy = null, $relatedId = null)
    {
        if (!$this->config) {
            $this->lastError = "No WhatsApp configuration found";
            return ['success' => false, 'message' => $this->lastError];
        }

        $phone = preg_replace('/^91/', '', preg_replace('/[^0-9]/', '', $phone));

        if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
            $allowed = explode(',', WHATSAPP_TEST_NUMBERS ?? '');
            if (!in_array($phone, $allowed)) {
                $err = "Blocked: Recipient $phone not in WhatsApp test whitelist.";
                $this->logToFile($phone, 'dy_msg_001', ['success' => false, 'message' => $err]);
                return ['success' => false, 'message' => $err];
            }
        }

        // Clean message: strip newlines/tabs and commas (BhashSMS delimiter)
        $message = str_replace(["\n", "\r", "\t", ","], [" ", " ", " ", " "], $message);
        $message = trim($message);

        $queryParams = [
            'user' => $this->config['api_user'],
            'pass' => $this->config['api_pass'],
            'sender' => $this->config['sender_id'],
            'phone' => $phone,
            'text' => 'dy_msg_001',
            'priority' => $this->config['priority'] ?? 'wa',
            'stype' => 'normal',
            'Params' => $message
        ];

        $apiUrl = defined('WHATSAPP_DYNAMIC_API_URL') ? WHATSAPP_DYNAMIC_API_URL : $this->config['api_url'];
        $fullUrl = $apiUrl . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $logId = $this->logMessage('dy_msg_001', $phone, [$message], null, $sentBy, 'dynamic', $relatedId);
        $response = $this->makeRequest($fullUrl);
        $this->updateLog($logId, $response);
        $this->logToFile($phone, 'dy_msg_001', $response);

        return $response;
    }

    /**
     * Make HTTP request to API with retry logic
     * @param string $url Full API URL
     * @param int $maxRetries Maximum number of retry attempts
     * @return array Response
     */
    private function makeRequest($url, $maxRetries = 3)
    {
        $attempt = 0;
        $lastError = '';

        while ($attempt < $maxRetries) {
            $attempt++;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if (!$error && $httpCode >= 200 && $httpCode < 300) {
                $body = trim($response);
                // BhashSMS returns HTTP 200 even for errors — check the response body
                $isApiError = stripos($body, 'error') !== false
                    || stripos($body, 'not allowed') !== false
                    || stripos($body, 'invalid') !== false;

                if ($isApiError) {
                    $lastError = "BhashSMS API error: $body";
                    // API-level errors won't resolve on retry — break immediately
                    break;
                }

                return [
                    'success' => true,
                    'message' => 'Message sent successfully',
                    'http_code' => $httpCode,
                    'response' => $body,
                    'attempts' => $attempt
                ];
            }

            // Store last error
            $lastError = $error ? $error : "HTTP $httpCode";

            // If not last attempt, wait before retry (exponential backoff)
            if ($attempt < $maxRetries) {
                sleep($attempt);
            }
        }

        // All retries failed
        $this->lastError = $lastError;
        return [
            'success' => false,
            'message' => "Failed after $attempt attempts. Last error: " . $lastError,
            'http_code' => 0,
            'response' => null,
            'attempts' => $attempt
        ];
    }

    /**
     * Log message to database
     */
    private function logMessage($templateCode, $phone, $params, $mediaUrl, $sentBy, $relatedEntity, $relatedId)
    {
        $paramsSent = json_encode($params);

        // Attempt to find template_id for the given code
        $templateId = null;
        try {
            $stmt = $this->conn->prepare("SELECT id FROM tbl_whatsapp_templates WHERE template_name = ? LIMIT 1");
            $stmt->execute([$templateCode]);
            $template = $stmt->fetch();
            if ($template) {
                $templateId = $template['id'];
            }
        } catch (PDOException $e) {
            // Ignore lookup error, logging is secondary
        }

        $sql = "INSERT INTO tbl_whatsapp_logs 
                (template_id, recipient_number, template_name, variables, status, created_by, related_entity, related_id, created_at, media_url)
                VALUES (?, ?, ?, ?, 'queued', ?, ?, ?, NOW(), ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $templateId,
            $phone,
            $templateCode,
            $paramsSent,
            $sentBy,
            $relatedEntity,
            $relatedId,
            $mediaUrl
        ]);

        return $this->conn->lastInsertId();
    }

    /**
     * Update log with response
     */
    private function updateLog($logId, $response)
    {
        $status = $response['success'] ? 'sent' : 'failed';
        $apiResponse = json_encode($response);
        $errorMessage = !$response['success'] ? $response['message'] : null;

        $sql = "UPDATE tbl_whatsapp_logs
                SET status = ?, api_response = ?, error_message = ?, sent_at = NOW()
                WHERE id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status, $apiResponse, $errorMessage, $logId]);
    }

    /**
     * Log to file for visibility
     */
    private function logToFile($phone, $templateCode, $response)
    {
        try {
            if (!defined('LOGS_PATH')) {
                require_once __DIR__ . '/../../common/constants.php';
            }
            $log_file = LOGS_PATH . 'whatsapp/whatsapp.log';
            $status = $response['success'] ? 'INFO' : 'ERROR';
            $msg = $response['success'] ? 'sent successfully' : 'failed';
            $respStr = $response['success'] ? "ID: " . ($response['response'] ?? 'N/A') : "Error: " . ($response['message'] ?? 'Unknown');

            $log_entry = "[" . date('Y-m-d H:i:s') . "] $status: WhatsApp Helper $msg to $phone. Template: $templateCode. $respStr\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND);
        } catch (Exception $e) {
            // Ignore logging errors
        }
    }

    /**
     * Get last error message
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}
