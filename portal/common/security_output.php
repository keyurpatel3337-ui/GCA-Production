<?php

/**
 * Security Output Functions
 * 
 * Centralized functions for safe HTML output to prevent XSS attacks.
 * All functions are prefixed with gca_ to avoid collisions with other environments.
 */

// Prevent multiple execution even if included via different physical paths
if (defined('GCA_SECURITY_OUTPUT_LOADED')) {
    return;
}
define('GCA_SECURITY_OUTPUT_LOADED', true);

/**
 * Safely escape string for HTML output
 */
if (!function_exists('gca_safe_html')) {
    function gca_safe_html($string, int $flags = ENT_QUOTES): string
    {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars((string) $string, $flags, 'UTF-8');
    }
}

/**
 * Safely escape string for HTML attribute output
 */
if (!function_exists('gca_safe_attr')) {
    function gca_safe_attr($string): string
    {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Safely output integer value
 */
if (!function_exists('gca_safe_int')) {
    function gca_safe_int($value): int
    {
        return (int) $value;
    }
}

/**
 * Display and clear session success message
 */
if (!function_exists('gca_display_success_message')) {
    function gca_display_success_message(): string
    {
        if (!empty($_SESSION['success'])) {
            $message = gca_safe_html($_SESSION['success']);
            unset($_SESSION['success']);
            return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-alert="dismiss" aria-label="Close"></button>
            </div>';
        }
        return '';
    }
}

/**
 * Display and clear session error message
 */
if (!function_exists('gca_display_error_message')) {
    function gca_display_error_message(): string
    {
        $html = '';
        if (!empty($_SESSION['error'])) {
            $message = gca_safe_html($_SESSION['error']);
            unset($_SESSION['error']);
            $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
        if (!empty($_SESSION['error_msg'])) {
            $message = gca_safe_html($_SESSION['error_msg']);
            unset($_SESSION['error_msg']);
            $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
        return $html;
    }
}

/**
 * Display all session messages
 */
if (!function_exists('gca_display_session_messages')) {
    function gca_display_session_messages(): string
    {
        return gca_display_success_message() . gca_display_error_message();
    }
}

/**
 * Sanitize URL for redirect
 */
if (!function_exists('gca_safe_redirect_url')) {
    function gca_safe_redirect_url(string $url, string $default = '/'): string
    {
        $url = trim($url);
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return $default;
        }
        if (substr($url, 0, 2) === '//') {
            return $default;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            $parsed = parse_url($url);
            $current_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            if (!isset($parsed['host']) || $parsed['host'] !== $current_host) {
                return $default;
            }
        }
        return $url;
    }
}

/**
 * Validate and sanitize ID parameter
 */
if (!function_exists('gca_get_safe_id')) {
    function gca_get_safe_id(string $key, string $method = 'GET'): ?int
    {
        $source = $method === 'POST' ? $_POST : $_GET;
        if (!isset($source[$key])) {
            return null;
        }
        $id = filter_var($source[$key], FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            return null;
        }
        return $id;
    }
}

/**
 * Safely escape string for JavaScript context
 */
if (!function_exists('gca_safe_js')) {
    function gca_safe_js($string): string
    {
        if ($string === null) {
            return "''";
        }
        return json_encode((string) $string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * Safely escape URL for attributes
 */
if (!function_exists('gca_safe_url')) {
    function gca_safe_url($url): string
    {
        if ($url === null) {
            return '';
        }
        $url = preg_replace('/^(javascript|data|vbscript):/i', '', (string) $url);
        return htmlspecialchars($url ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Display session message with auto-escaping
 */
if (!function_exists('gca_echo_session_safe')) {
    function gca_echo_session_safe(string $key): void
    {
        if (isset($_SESSION[$key])) {
            echo gca_safe_html($_SESSION[$key]);
        }
    }
}

/**
 * Get session value with auto-escaping
 */
if (!function_exists('gca_get_session_safe')) {
    function gca_get_session_safe(string $key, string $default = ''): string
    {
        if (isset($_SESSION[$key])) {
            return gca_safe_html($_SESSION[$key]);
        }
        return $default;
    }
}


// Compatibility aliases removed to prevent fatal redeclaration errors with un-updated Production files.
// All Development files have been migrated to use gca_* prefixed functions.
