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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .wait-container { background: white; padding: 3rem; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 450px; text-align: center; }
        .pulse-icon { width: 80px; height: 80px; background: #fff7ed; color: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; font-size: 2rem; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); } 70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(249, 115, 22, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(249, 115, 22, 0); } }
        h2 { color: #1e293b; margin-bottom: 1rem; }
        p { color: #64748b; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem; }
        .device-info { background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 2rem; text-align: left; }
        .device-info label { display: block; font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem; }
        .device-info span { color: #334155; font-weight: 500; }
        .loader { display: inline-block; width: 24px; height: 24px; border: 3px solid #f3f3f3; border-top: 3px solid #2563eb; border-radius: 50%; animation: spin 1s linear infinite; vertical-align: middle; margin-right: 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .status-text { color: #2563eb; font-weight: 600; }
    </style>
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
