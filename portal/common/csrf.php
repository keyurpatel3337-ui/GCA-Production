<?php
/**
 * CSRF Protection Functions
 * 
 * Provides token generation and validation to prevent Cross-Site Request Forgery attacks.
 * Include this file and use generateCSRFToken() in forms and validateCSRFToken() in handlers.
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../session_config.php';
}

/**
 * Generate a CSRF token and store in session
 * 
 * @param string $formName Optional form identifier for multiple forms
 * @return string The generated token
 */
function generateCSRFToken(string $formName = 'default'): string
{
    if (empty($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    // Generate a cryptographically secure token
    $token = bin2hex(random_bytes(32));
    
    // Store token with timestamp (tokens expire after 1 hour)
    $_SESSION['csrf_tokens'][$formName] = [
        'token' => $token,
        'time' => time()
    ];
    
    return $token;
}

/**
 * Get CSRF token HTML input field
 * 
 * @param string $formName Optional form identifier
 * @return string HTML hidden input field
 */
function csrfTokenField(string $formName = 'default'): string
{
    $token = generateCSRFToken($formName);
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token ?? '') . '">';
}

/**
 * Validate CSRF token from request
 * 
 * @param string $formName Optional form identifier
 * @param string|null $submittedToken Token from request (defaults to $_POST['csrf_token'])
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken(string $formName = 'default', ?string $submittedToken = null): bool
{
    // Get token from POST if not provided
    if ($submittedToken === null) {
        $submittedToken = $_POST['csrf_token'] ?? '';
    }
    
    // Check if we have stored tokens
    if (empty($_SESSION['csrf_tokens'][$formName])) {
        return false;
    }
    
    $tokenData = $_SESSION['csrf_tokens'][$formName];
    
    // Check if token has expired (1 hour = 3600 seconds)
    if (time() - $tokenData['time'] > 3600) {
        unset($_SESSION['csrf_tokens'][$formName]);
        return false;
    }
    
    // Timing-safe comparison to prevent timing attacks
    $isValid = hash_equals($tokenData['token'], $submittedToken);
    
    // Remove used token (one-time use)
    if ($isValid) {
        unset($_SESSION['csrf_tokens'][$formName]);
    }
    
    return $isValid;
}

/**
 * Validate CSRF token and send error response if invalid (for AJAX)
 * 
 * @param string $formName Optional form identifier
 * @return void Exits with error if invalid
 */
function requireValidCSRFToken(string $formName = 'default'): void
{
    if (!validateCSRFToken($formName)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // AJAX request
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.']);
        } else {
            // Regular form submission
            set_flash_message('error', 'Security validation failed. Please try again.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        }
        exit();
    }
}

/**
 * Get CSRF token value for AJAX requests
 * 
 * @param string $formName Optional form identifier
 * @return string Token value
 */
function getCSRFTokenForAjax(string $formName = 'ajax'): string
{
    return generateCSRFToken($formName);
}
