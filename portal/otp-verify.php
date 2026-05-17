<?php
require_once __DIR__ . '/session_config.php';
require_once dirname(__DIR__) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../common/helpers/security_helper.php';
require_once __DIR__ . '/../common/helpers/audit_log_helper.php';

if (!isset($_SESSION['pending_auth_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$user_id = $_SESSION['pending_auth_user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT u.*, r.role_name, r.role_slug FROM tbl_users u INNER JOIN tbl_roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle OTP Initialization (after device approval)
if (isset($_GET['init'])) {
    $device_uuid = getDeviceUUID();
    if (isDeviceAuthorized($conn, $user_id, $device_uuid)) {
        // Priority 1: Google Authenticator
        if (!empty($user['google_auth_enabled']) && $user['google_auth_enabled'] == 1) {
            header('Location: google-auth-verify.php');
            exit;
        }

        // Priority 2: Fallback to Email OTP
        $otp = generateOTP();
        if (saveUserOTP($conn, $user_id, $otp)) {
            sendOTPEmail($conn, $user['email'], $user['name'], $otp);
            $_SESSION['otp_sent_at'] = time();
            // Redirect to itself without ?init=1 to avoid re-sending on refresh
            header('Location: otp-verify.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (verifyUserOTP($conn, $user_id, $otp)) {
        // OTP Verified! 
        // Device is already authorized if we reached this step with our new flow.
        
        // Authorized device - finish login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_slug'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];

        unset($_SESSION['pending_auth_user_id']);
        unset($_SESSION['pending_auth_role']);
        
        // Handle "Trust this device"
        if (isset($_POST['trust_device']) || (isset($_SESSION['trust_device_requested']) && $_SESSION['trust_device_requested'] === true)) {
            $device_uuid = getDeviceUUID();
            trustDevice($conn, $user_id, $device_uuid);
            logAuditAction($conn, 'device_trusted', 'authentication', "Device marked as trusted for user: {$user['email']}");
            unset($_SESSION['trust_device_requested']);
        }

        // Redirect based on role
        $role_redirects = [
            'super_admin' => 'modules/dashboard/admin_dashboard.php',
            'principle' => 'modules/dashboard/principle_dashboard.php',
            'counsellor' => 'modules/dashboard/counsellor_dashboard.php',
            'student' => 'modules/dashboard/student_dashboard.php',
            'accountant' => 'modules/dashboard/accountant_dashboard.php',
            'website_admin' => 'modules/dashboard/website_admin_dashboard.php',
            'maintenance' => 'modules/dashboard/maintenance_dashboard.php',
            'reception' => 'modules/dashboard/reception_dashboard.php',
            'establishment' => 'modules/dashboard/establishment_dashboard.php',
            'grocery_manager' => 'modules/grocery/index.php',
            'wallet_manager' => 'modules/dashboard/wallet_manager_dashboard.php',
            'computer_operator' => 'modules/dashboard/computer_operator_dashboard.php'
        ];

        $location = $role_redirects[$user['role_slug']] ?? 'modules/dashboard/admin_dashboard.php';
        header('Location: ' . $location);
        exit;
    } else {
        $error = 'Invalid or expired verification code.';
    }
}

// Resend OTP logic
if (isset($_GET['resend'])) {
    $otp = generateOTP();
    saveUserOTP($conn, $user_id, $otp);
    sendOTPEmail($conn, $user['email'], $user['name'], $otp);
    $_SESSION['otp_sent_at'] = time(); // Reset timer on resend
    $resend_success = "A new code has been sent to your email.";
}

// Calculate remaining resend time (60 second cooldown)
$otp_sent_at = $_SESSION['otp_sent_at'] ?? time();
$seconds_passed = time() - $otp_sent_at;
$timeLeft = max(0, 60 - $seconds_passed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Login | <?php echo SYSTEM_NAME; ?></title>
    <!-- Google Font: Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/font-awesome/all.min.css">
    <!-- Custom Verification Styles -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/otp-verify.css">
</head>
<body>
    <div class="verify-container">
        <div class="icon-box"><i class="fas fa-shield-alt"></i></div>
        <h2>Verify your login</h2>
        <p>We've sent a 6-digit verification code to<br><span style="color: #0f172a; font-weight: 600;"><?php echo substr($user['email'], 0, 3) . '.....' . substr($user['email'], strpos($user['email'], '@')); ?></span></p>
        
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php
endif; ?>
        <?php if (isset($resend_success)): ?><div class="success"><?php echo $resend_success; ?></div><?php
endif; ?>

        <form method="POST" id="otpForm">
            <input type="hidden" name="otp" id="finalOtp">
            <div class="otp-wrapper">
                <input type="text" class="digit-input" maxlength="1" pattern="\d*" inputmode="numeric" autofocus>
                <input type="text" class="digit-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="digit-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="digit-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="digit-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="digit-input" maxlength="1" pattern="\d*" inputmode="numeric">
            </div>

            <div style="margin-bottom: 1.5rem; text-align: left; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <input type="checkbox" name="trust_device" id="trustDevice" style="width: 16px; height: 16px; cursor: pointer;" <?php echo (isset($_SESSION['trust_device_requested']) && $_SESSION['trust_device_requested']) ? 'checked' : ''; ?>>
                <label for="trustDevice" style="font-size: 0.9rem; color: #475569; cursor: pointer;">Trust this device for 7 days</label>
            </div>

            <button type="submit" class="btn-verify">Verify</button>
        </form>

        <div class="timer-container">
            <div id="countdownBox" style="display: <?php echo $timeLeft > 0 ? 'block' : 'none'; ?>;">Resend code in <span id="timerText">0:<?php echo str_pad($timeLeft, 2, '0', STR_PAD_LEFT); ?></span></div>
            <a href="?resend=1" class="resend-link" id="resendBtn" style="display: <?php echo $timeLeft <= 0 ? 'block' : 'none'; ?>;">Resend Code</a>
        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll('.digit-input');
        const form = document.getElementById('otpForm');
        const finalOtpInput = document.getElementById('finalOtp');

        inputs.forEach((input, index) => {
            // Handle typing
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value.length > 1) {
                    e.target.value = value.slice(0, 1);
                }

                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                checkAndSubmit();
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 6);
                if (/^\d{6}$/.test(pasteData)) {
                    pasteData.split('').forEach((char, i) => {
                        inputs[i].value = char;
                    });
                    checkAndSubmit();
                }
            });
        });

        function checkAndSubmit() {
            let otp = '';
            inputs.forEach(input => otp += input.value);
            if (otp.length === 6) {
                finalOtpInput.value = otp;
                // Optional: Auto-submit
                // form.submit();
            }
        }

        form.addEventListener('submit', (e) => {
            let otp = '';
            inputs.forEach(input => otp += input.value);
            if (otp.length !== 6) {
                e.preventDefault();
                alert('Please enter all 6 digits.');
            } else {
                finalOtpInput.value = otp;
            }
        });

        // Timer Logic
        let timeLeft = <?php echo (int)$timeLeft; ?>;
        const timerText = document.getElementById('timerText');
        const countdownBox = document.getElementById('countdownBox');
        const resendBtn = document.getElementById('resendBtn');

        if (timeLeft > 0) {
            const timer = setInterval(() => {
                timeLeft -= 1;
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    countdownBox.style.display = 'none';
                    resendBtn.style.display = 'inline-block';
                } else {
                    let seconds = timeLeft < 10 ? '0' + timeLeft : timeLeft;
                    timerText.textContent = `0:${seconds}`;
                }
            }, 1000);
        }
    </script>
</body>
</html>
