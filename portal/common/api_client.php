<?php

/**
 * API Client for Backend Communication
 * Handles all HTTP requests to the backend API
 */

class APIClient
{
    private $baseURL;
    private $timeout = 60; // Increased timeout for slow API requests
    private $connectTimeout = 20; // Increased connection timeout

    public function __construct()
    {
        // Load environment config if not already loaded
        if (!defined('BACKEND_URL')) {
            require_once dirname(__DIR__) . '/../env.config.php';
        }
        $this->baseURL = BACKEND_URL;
    }

    /**
     * Make a GET request to the API
     */
    public function get($route, $params = [])
    {
        $url = $this->baseURL . '/index.php?route=' . $route;

        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        return $this->request($url, 'GET');
    }

    /**
     * Make a POST request to the API
     */
    public function post($route, $data = [], $params = [])
    {
        $url = $this->baseURL . '/index.php?route=' . $route;

        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        return $this->request($url, 'POST', $data);
    }


    /**
     * Make HTTP request using cURL
     */
    private function request($url, $method = 'GET', $data = null, $retries = 2)
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $retries) {
            $ch = curl_init();

            // Set URL
            curl_setopt($ch, CURLOPT_URL, $url);

            // Return response instead of outputting
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Include session cookies
            if (session_id()) {
                curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
            }

            // Set timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout); // Use instance variable

            // Follow redirects
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

            // Headers
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json'
            ];

            // POST request
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // SSL settings - disable verification for all requests in development
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            // Execute request
            // IMPORTANT: Close session to prevent deadlock if calling same server
            if (session_id()) {
                session_write_close();
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);

            // Handle errors
            if ($error) {
                $lastError = $error;
                $attempt++;

                // If this wasn't the last attempt, wait before retrying
                if ($attempt <= $retries) {
                    // Exponential backoff: wait 1s, then 2s
                    usleep($attempt * 1000000);
                    error_log("API Request retry $attempt/$retries: URL=$url, Error=$error");
                    continue;
                }

                // Log the full error details for debugging
                error_log("API Request Failed after $retries retries: URL=$url, Error=$error, HTTP Code=$httpCode, Total Time=" . ($info['total_time'] ?? 'N/A'));
                return [
                    'success' => false,
                    'error' => 'Connection error: ' . $error,
                    'http_code' => $httpCode
                ];
            }

            // Try to decode JSON response
            $decoded = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Check if 'data' field is a JSON string and decode it
                if (isset($decoded['data']) && is_string($decoded['data'])) {
                    // Remove BOM and other invisible characters
                    $dataString = trim($decoded['data']);
                    $dataString = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $dataString); // Remove non-printable chars
                    $dataString = str_replace(["\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"], '', $dataString); // Remove BOM

                    $dataDecoded = json_decode($dataString, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $decoded = $dataDecoded;
                    }
                }
                return $decoded;
            }

            // Return raw response if not JSON
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'data' => $response,
                'http_code' => $httpCode
            ];
        }
    }

    /**
     * Make a request with form data (multipart/form-data)
     */
    public function postFormData($route, $data = [])
    {
        $url = $this->baseURL . '/index.php?route=' . $route;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        if (session_id()) {
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if (session_id()) {
            session_write_close();
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $decoded = json_decode($response, true);
        return $decoded ?: ['success' => false, 'error' => 'Invalid response'];
    }
}

// Helper functions for quick API access
function api_get($route, $params = [])
{
    $client = new APIClient();
    return $client->get($route, $params);
}

function api_post($route, $data = [])
{
    $client = new APIClient();
    return $client->post($route, $data);
}
