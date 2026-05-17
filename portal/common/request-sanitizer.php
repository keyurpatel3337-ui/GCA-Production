<?php

/**
 * Global Request Sanitizer
 * Auto-sanitizes all $_GET and $_POST superglobals to prevent XSS attacks
 * 
 * This file should be included at the very beginning of application bootstrap
 * to ensure all user inputs are sanitized before processing
 * 
 * @package    CounsellingPortal
 * @subpackage Security
 * @author     Security Team
 * @version    1.2
 */

// Prevent multiple execution even if included via different physical paths
if (defined('GCA_REQUEST_SANITIZED')) {
  return;
}
define('GCA_REQUEST_SANITIZED', true);

/**
 * Sanitize superglobal arrays recursively
 * Removes HTML tags and encodes special characters to prevent XSS
 */
if (!function_exists('gca_portal_sanitize_request')) {
  function gca_portal_sanitize_request()
  {
    // Backup original values for debugging if needed
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
      $GLOBALS['_GET_ORIGINAL'] = $_GET;
      $GLOBALS['_POST_ORIGINAL'] = $_POST;
    }

    // Sanitize GET parameters
    if (!empty($_GET)) {
      array_walk_recursive($_GET, function (&$value, $key) {
        if (is_string($value)) {
          // Trim whitespace
          $value = trim($value);

          // Remove HTML and PHP tags
          $value = strip_tags($value);

          // Encode special characters to prevent XSS
          $value = htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
      });
    }

    // Sanitize POST parameters
    if (!empty($_POST)) {
      $richTextFields = ['description', 'content', 'message', 'notes'];
      array_walk_recursive($_POST, function (&$value, $key) use ($richTextFields) {
        if (is_string($value)) {
          // Trim whitespace
          $value = trim($value);

          // Remove HTML and PHP tags (except for rich text fields)
          // Rich text fields are preserved for WYSIWYG editors
          if (!in_array($key, $richTextFields)) {
            $value = strip_tags($value);
            // Encode special characters to prevent XSS
            $value = htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
          }
          // Rich text fields: only encode dangerous scripts but preserve HTML structure
          else {
            // For rich text, remove script tags but keep other HTML
            $value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
            $value = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $value);
            $value = preg_replace('#on\w+\s*=\s*["\'].*?["\']#i', '', $value); // Remove event handlers
          }
        }
      });
    }

    // Sanitize COOKIE parameters (less aggressive)
    if (!empty($_COOKIE)) {
      array_walk_recursive($_COOKIE, function (&$value, $key) {
        if (is_string($value)) {
          $value = htmlspecialchars(trim($value) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
      });
    }
  }
}

/**
 * Log sanitization activity (optional)
 */
if (!function_exists('gca_portal_log_security')) {
  function gca_portal_log_security()
  {
    if (defined('LOG_SECURITY') && LOG_SECURITY === true) {
      $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'get_params' => count($_GET),
        'post_params' => count($_POST)
      ];

      // Log to file if security logging is enabled
      $log_file = __DIR__ . '/../../logs/security-sanitization.log';
      if (is_writable(dirname($log_file))) {
        file_put_contents($log_file, json_encode($log_data) . PHP_EOL, FILE_APPEND);
      }
    }
  }
}

// Auto-execute sanitization
gca_portal_sanitize_request();

// Optional: Log activity
if (defined('LOG_SECURITY') && LOG_SECURITY === true) {
  gca_portal_log_security();
}
