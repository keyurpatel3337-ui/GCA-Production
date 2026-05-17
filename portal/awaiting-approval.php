<?php
require_once __DIR__ . '/session_config.php';
require_once dirname(__DIR__) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../common/helpers/security_helper.php';

if (!isset($_SESSION['pending_auth_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['pending_auth_user_id'];
$device_uuid = getDeviceUUID();

// Check if already approved (in case it was approved while waiting)
if (isDeviceAuthorized($conn, $user_id, $device_uuid)) {
    // Should proceed to full session logic, but let's just let the AJAX handle it for simplicity
    // or provide a "Continue" button.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awaiting Approval</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/vendor/font-awesome/all.min.css">
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/approvals/awaiting-approval.css">
</head>
<body>
    <div class="wait-container">
        <div class="pulse-icon"><i class="fas fa-shield-alt"></i></div>
        <h2>Approval Required</h2>
        <p>This is a new device. For your security, please approve this login request from your <strong>Primary Device</strong>.</p>
        
        <div class="device-info">
            <label>Current Device</label>
            <span><?php echo htmlspecialchars(getDeviceName() ?? ''); ?></span>
        </div>

        <div class="status-box">
            <div class="loader"></div>
            <span class="status-text">Waiting for approval...</span>
        </div>
    </div>

    <script>
        // Check approval status every 3 seconds
        async function checkStatus() {
            try {
                const response = await fetch('ajax/check-approval.php');
                const data = await response.json();
                
                if (data.authorized) {
                    window.location.href = 'otp-verify.php?init=1'; // Redirect back to verify to complete session
                }
            } catch (error) {
                console.error('Error checking approval:', error);
            }
        }
        
        setInterval(checkStatus, 3000);
    </script>
</body>
</html>
