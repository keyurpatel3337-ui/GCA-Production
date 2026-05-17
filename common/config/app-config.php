<?php
/**
 * Application Configuration Helper
 * Unified version that uses env.config.php constants
 * Path: common/config/app-config.php
 */

require_once __DIR__ . '/../../env.config.php';

/**
 * Get SMTP Configuration
 * Returns SMTP config from env.config.php constants
 */
if (!function_exists('getSmtpConfig')) {
    function getSmtpConfig($conn = null)
    {
        return [
            'smtp_host' => SMTP_HOST,
            'smtp_port' => SMTP_PORT,
            'smtp_username' => SMTP_USERNAME,
            'smtp_password' => SMTP_PASSWORD,
            'smtp_encryption' => SMTP_ENCRYPTION,
            'smtp_from_email' => SMTP_FROM_EMAIL,
            'smtp_from_name' => SMTP_FROM_NAME,
            'smtp_timeout' => 30,
            'is_active' => 1,
        ];
    }
}

/**
 * Get Payment Gateway Configuration
 * Returns gateway config from env.config.php constants
 */
if (!function_exists('getPaymentGatewayConfig')) {
    function getPaymentGatewayConfig($gateway = 'easebuzz')
    {
        if ($gateway === 'easebuzz') {
            return [
                'gateway_name' => 'easebuzz',
                'api_key' => EASEBUZZ_MERCHANT_KEY,
                'api_secret' => EASEBUZZ_SALT,
                'api_url' => EASEBUZZ_API_URL,
                'environment' => EASEBUZZ_ENV,
                'is_active' => 1,
            ];
        }
        return false;
    }
}

/**
 * Get WhatsApp Configuration
 * Returns WhatsApp config from env.config.php constants
 */
if (!function_exists('getWhatsAppConfig')) {
    function getWhatsAppConfig($provider = null)
    {
        return [
            'provider_name' => WHATSAPP_PROVIDER,
            'api_key' => WHATSAPP_API_KEY,
            'api_secret' => WHATSAPP_API_SECRET,
            'api_url' => WHATSAPP_API_URL,
            'sender_number' => WHATSAPP_SENDER,
            'is_active' => 1,
        ];
    }
}

/**
 * Backward compatibility wrapper
 */
if (!function_exists('getActiveWhatsAppProviderConfig')) {
    function getActiveWhatsAppProviderConfig()
    {
        return getWhatsAppConfig();
    }
}
?>