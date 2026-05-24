<?php
require_once dirname(__DIR__) . '/common/constants.php';
require_once __DIR__ . '/session_config.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once ENV_CONFIG_FILE;
require_once dirname(__DIR__) . '/common/helpers/security_helper.php';
require_once dirname(__DIR__) . '/common/helpers/audit_log_helper.php';

// Check if there is a pending authentication
if (!isset($_SESSION['pending_auth_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['pending_auth_user_id'];
$error = '';

// Fetch user info including 2FA secret
$stmt = $conn->prepare("SELECT u.*, r.role_name, r.role_slug FROM tbl_users u INNER JOIN tbl_roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || $user['google_auth_enabled'] != 1) {
    // If not enabled or user not found, something is wrong
    unset($_SESSION['pending_auth_user_id']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (empty($code)) {
        $error = "Please enter the 6-digit code.";
    } else {
        if (verifyGoogleAuthCode($user['google_auth_secret'], $code)) {
            // Success! Complete login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_slug'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            // Handle Trust Device if requested from login
            if (isset($_SESSION['trust_device_requested'])) {
                $device_uuid = getDeviceUUID();
                trustDevice($conn, $user['id'], $device_uuid);
                unset($_SESSION['trust_device_requested']);
            }

            // Clear pending auth
            unset($_SESSION['pending_auth_user_id']);
            unset($_SESSION['pending_auth_role']);

            // LOG: Successful MFA Login
            logAuditAction($conn, 'google_auth_success', 'authentication', "Google Authenticator verification successful for user: {$user['email']}");

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
                'computer_operator' => 'modules/dashboard/computer_operator_dashboard.php',
                'dept_head' => 'modules/dashboard/dept_head_dashboard.php',
                'assistant_teacher' => 'modules/dashboard/assistant_teacher_dashboard.php',
                'teacher' => 'modules/dashboard/teacher_dashboard.php',
                'oes_data_entry_operator' => 'modules/dashboard/oes_data_entry_dashboard.php'
            ];

            $location = $role_redirects[$user['role_slug']] ?? 'modules/dashboard/admin_dashboard.php';
            header('Location: ' . $location);
            exit;
        } else {
            $error = "Invalid verification code. Please check your app and try again.";
        }
    }
}

// Fallback to Email OTP
if (isset($_GET['action']) && $_GET['action'] === 'email') {
    $otp = generateOTP();
    if (saveUserOTP($conn, $user['id'], $otp)) {
        sendOTPEmail($conn, $user['email'], $user['name'], $otp);
        $_SESSION['otp_sent_at'] = time();
        header('Location: otp-verify.php');
        exit;
    }
}

// System name fallback
$SYSTEM_NAME = "GCA Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification - <?php echo $SYSTEM_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
</head>
<body>
    <div class="verify-card text-center">
        <div class="icon-box">
            <i class="fas fa-shield-halved"></i>
        </div>
        <h3 class="fw-bold mb-2">Two-Factor Security</h3>
        <p class="text-muted mb-4">Open the <strong>Google Authenticator</strong> app on your mobile to view your 6-digit code.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-3 small py-2 mb-4">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <input type="text" name="code" class="form-control otp-input" placeholder="000 000" maxlength="6" autofocus required autocomplete="off">
            </div>
            <button type="submit" class="btn btn-verify mb-3">
                Verify & Sign In
            </button>
        </form>

        <div class="mt-4 pt-3 border-top">
            <p class="small text-muted mb-2">Can't access your app?</p>
            <a href="?action=email" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                <i class="fas fa-envelope me-1"></i> Use Email OTP instead
            </a>
            <div class="mt-3">
                <p class="small text-muted mb-0">Blocked? <a href="logout.php" class="text-decoration-none text-primary">Logout</a></p>
            </div>
        </div>
    </div>
</body>
</html>
