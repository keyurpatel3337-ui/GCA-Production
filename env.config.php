<?php
if (!defined('APP_INIT')) {
    header('HTTP/1.1 403 Forbidden');
    die("Direct access to configuration files is prohibited.");
}

/**
 * Environment Configuration
 * Single source of truth for environment settings
 * 
 * 🌐 DEPLOYMENT: AWS EC2 Instance with Elastic IP
 * Domain: gyanmanjari.com (pointed to AWS Elastic IP)
 * Auto-detects: Local (WAMP/XAMPP) vs Production (AWS/Live Server)
 */

// Get current host information
$current_host = $_SERVER['HTTP_HOST'] ?? '';
if (empty($current_host)) {
    $current_host = $_SERVER['SERVER_NAME'] ?? '';
}
$script_path = $_SERVER['SCRIPT_FILENAME'] ?? '';

// Initialize variables with defaults (production - AWS)
$host = "localhost"; // MySQL on same EC2 instance
$dbname = "counselling"; // Production database name
$username = "root"; // Database user
$password = "GCA_Secure_#2026_Portal"; // Production database password
$is_local = false;

// Determine environment (auto-detection)
// 🚀 PRODUCTION MODE - Set $is_local = false to force production
$force_production = false; // Set to true for production mode

if ($force_production) {
    // 🌐 PRODUCTION MODE ENFORCED
    $is_local = false;
} elseif (
    stripos($current_host, 'localhost') !== false ||
    stripos($current_host, '127.0.0.1') !== false ||
    stripos($script_path, 'wamp64') !== false ||
    stripos($script_path, 'xampp') !== false ||
    stripos($script_path, 'C:') !== false || // Windows drive
    stripos($script_path, 'D:') !== false
) {
    // 💻 Local Development Environment (WAMP/XAMPP on Windows)
    $host = "localhost";
    $dbname = "counselling";
    $username = "root";
    $password = "GCA_Secure_#2026_Portal";
    $is_local = true;
}

// Define URLs and Paths
if (!defined('BASE_URL')) {
    if ($is_local) {
        // Local development URLs - Dynamic to support Virtual Hosts
        $protocol = "http";
        // If accessed via the primary domain gyanmanjari.com, the docroot is already GCA-Production
        if (stripos($current_host, 'gyanmanjari.com') !== false) {
            define('BASE_URL', $protocol . '://' . $current_host);
        } else {
            define('BASE_URL', $protocol . '://' . $current_host . '/GCA-Production');
        }
        define('PORTAL_URL', BASE_URL . '/portal');
        define('BACKEND_URL', BASE_URL . '/counselling-backend');
    } else {
        // Production URLs
        define('BASE_URL', 'https://gyanmanjari.com');
        define('PORTAL_URL', 'https://gyanmanjari.com/portal');
        define('BACKEND_URL', 'https://gyanmanjari.com/counselling-backend');
    }
}

// Ensure BACKEND_URL is always defined
if (!defined('BACKEND_URL')) {
    define('BACKEND_URL', $is_local ? 'http://gyanmanjari.com/counselling-backend' : 'https://gyanmanjari.com/counselling-backend');
}

// System Name - used in page titles and headers
if (!defined('SYSTEM_NAME')) {
    define('SYSTEM_NAME', 'GCA');
}

// Additional URL definitions
if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', $is_local ? 'https://gyanmanjari.com' : 'https://gyanmanjari.com');
}

// API and Asset URLs
if (!defined('API_URL')) {
    define('API_URL', BACKEND_URL . '/api');
}

if (!defined('WALLET_API_URL')) {
    // For local dev, use the allowed domain gyanmanjari.com with the /wallet alias
    define('WALLET_API_URL', $is_local ? 'http://gyanmanjari.com/wallet/api/v1' : 'https://gyanmanjari.com/wallet/api/v1');
}

if (!defined('GCA_PORTAL_KEY')) {
    define('GCA_PORTAL_KEY', 'GCA_PORTAL_KEY_99xyz');
}

if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', FRONTEND_URL . '/public/assets');
}

if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', FRONTEND_URL . '/uploads');
}

// Environment constant
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', $is_local ? 'development' : 'production');
}

// =============================================================================
// SERVICE CREDENTIALS
// =============================================================================

// Easebuzz Payment Gateway
if (!defined('EASEBUZZ_MERCHANT_KEY'))
    define('EASEBUZZ_MERCHANT_KEY', '0A3POSOAV2');
if (!defined('EASEBUZZ_SALT'))
    define('EASEBUZZ_SALT', '3UE56EYWDT');
if (!defined('EASEBUZZ_ENV'))
    define('EASEBUZZ_ENV', 'prod');
if (!defined('EASEBUZZ_API_URL'))
    define('EASEBUZZ_API_URL', 'https://pay.easebuzz.in');

// SMTP Configuration - Security Emails
if (!defined('SMTP_HOST'))
    define('SMTP_HOST', 'mail.gyanmanjari.co.in'); // Updated as per user settings image

if (!defined('SMTP_PORT'))
    define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME'))
    define('SMTP_USERNAME', 'security@gyanmanjari.co.in');
if (!defined('SMTP_PASSWORD'))
    define('SMTP_PASSWORD', 'Gyanmanjari$2026');
if (!defined('SMTP_ENCRYPTION'))
    define('SMTP_ENCRYPTION', 'tls');

if (!defined('SMTP_FROM_EMAIL'))
    define('SMTP_FROM_EMAIL', 'security@gyanmanjari.co.in');
if (!defined('SMTP_FROM_NAME'))
    define('SMTP_FROM_NAME', SYSTEM_NAME . 'Security');

// SMTP Configuration - Regular/Support Emails
if (!defined('SMTP_REGULAR_HOST'))
    define('SMTP_REGULAR_HOST', 'mail.gyanmanjari.co.in'); // Updated as per user settings image

if (!defined('SMTP_REGULAR_PORT'))
    define('SMTP_REGULAR_PORT', 587);
if (!defined('SMTP_REGULAR_USERNAME'))
    define('SMTP_REGULAR_USERNAME', 'support@gyanmanjari.co.in');
if (!defined('SMTP_REGULAR_PASSWORD'))
    define('SMTP_REGULAR_PASSWORD', 'Gyanmanjari$2026');
if (!defined('SMTP_REGULAR_ENCRYPTION'))
    define('SMTP_REGULAR_ENCRYPTION', 'tls');

if (!defined('SMTP_REGULAR_FROM_EMAIL'))
    define('SMTP_REGULAR_FROM_EMAIL', 'support@gyanmanjari.co.in');
if (!defined('SMTP_REGULAR_FROM_NAME'))
    define('SMTP_REGULAR_FROM_NAME', SYSTEM_NAME . ' Support');

// WhatsApp Configuration
if (!defined('WHATSAPP_PROVIDER'))
    define('WHATSAPP_PROVIDER', 'bhashsms');
if (!defined('WHATSAPP_API_KEY'))
    define('WHATSAPP_API_KEY', '098765');
if (!defined('WHATSAPP_API_SECRET'))
    define('WHATSAPP_API_SECRET', 'GYANMANJARI_CAREER');
if (!defined('WHATSAPP_API_URL'))
    define('WHATSAPP_API_URL', 'http://bhashsms.com/api/sendmsgutil.php');
if (!defined('WHATSAPP_SENDER'))
    define('WHATSAPP_SENDER', 'BUZWAP');
if (!defined('WHATSAPP_DYNAMIC_API_URL'))
    define('WHATSAPP_DYNAMIC_API_URL', 'http://bhashsms.com/api/sendmsg.php');

// WhatsApp Sandbox/Test Mode
if (!defined('WHATSAPP_TEST_MODE'))
    define('WHATSAPP_TEST_MODE', false); // Production Mode
if (!defined('WHATSAPP_TEST_NUMBERS'))
    define('WHATSAPP_TEST_NUMBERS', '7990965567'); // Comma-separated test numbers

// Error Reporting
if (defined('ENVIRONMENT')) {
    if (ENVIRONMENT === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
}

// =============================================================================
// BACKUP CONFIGURATION
// =============================================================================
/**
 * BACKUP_PATH: The root directory where backups will be saved.
 * For local devices, specify the drive letter (e.g., 'E:/backups' or 'D:/portal_backups').
 * For AWS, this might be a local folder or a mapped network drive.
 */
if (!defined('BACKUP_PATH')) {
    define('BACKUP_PATH', 'D:/portal_backups');

}

/**
 * DATABASE BACKUP CREDENTIALS (for remote backups)
 * If you run the backup script from a local device at home to backup AWS,
 * set these to your AWS database credentials.
 */
if (!defined('DB_BACKUP_HOST'))
    define('DB_BACKUP_HOST', $host);
if (!defined('DB_BACKUP_USER'))
    define('DB_BACKUP_USER', $username);
if (!defined('DB_BACKUP_PASS'))
    define('DB_BACKUP_PASS', $password);
