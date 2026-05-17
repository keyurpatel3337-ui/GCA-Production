<?php

/**
 * Centralized Session Configuration
 * Ensures consistent session handling across frontend and backend
 */

// Include request sanitizer first - Auto-sanitizes all $_GET, $_POST, $_COOKIE
require_once __DIR__ . '/common/request-sanitizer.php';

// Determine environment if not already defined
if (!defined('ENVIRONMENT')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, 'gyanmanjari.com') !== false) {
        define('ENVIRONMENT', 'production');
    } else {
        define('ENVIRONMENT', 'development');
    }
}

// Cache-control headers to prevent back-button issues with sensitive data
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

if (session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', ENVIRONMENT === 'production' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');

    // Critical for sharing session between /frontend and /backend
    ini_set('session.cookie_path', '/');

    // Session lifetime (24 hours)
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);

    // Consistent session name
    session_name('GCA_SESSION');

    session_start();

    // Session regeneration for fixation protection
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    // Globally include essential security functions
    require_once __DIR__ . '/common/security_output.php';
    require_once __DIR__ . '/common/csrf.php';
    require_once __DIR__ . '/include/security_headers.php';

    // Include flash message helper for consistent message handling
    require_once dirname(__DIR__) . '/common/helpers/flash_message.php';

    // Migrate old session messages to new flash message system
    migrate_old_session_messages();
}
