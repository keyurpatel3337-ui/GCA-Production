<?php
// Start session first
require_once __DIR__ . '/session_config.php';

require_once dirname(__DIR__) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/common/RateLimiter.php';
require_once __DIR__ . '/common/LoginAttemptTracker.php';
require_once dirname(__DIR__) . '/common/helpers/audit_log_helper.php';

// Apply Rate Limiting (max 30 requests per minute for login page)
applyRateLimit(30);

// Initialize Login Attempt Tracker
$loginTracker = new LoginAttemptTracker($conn, 5, 15, 30); // 5 attempts, 15 min lock, 30 min window

// Fetch system settings from database
try {
    if ($conn === null) {
        throw new Exception('Database connection not available');
    }
    $settings_stmt = $conn->prepare("SELECT setting_key, setting_value FROM tbl_system_settings WHERE category = 'general'");
    $settings_stmt->execute();
    $system_settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $system_settings[$row['setting_key']] = $row['setting_value'];
    }
    $SYSTEM_NAME = $system_settings['system_name'] ?? 'GCA';
} catch (Exception $e) {
    $SYSTEM_NAME = 'GCA';
    error_log('Login page: Unable to fetch system settings - ' . $e->getMessage());
}

$error = '';
$lockInfo = null;

// Fetch specific active roles from database - SQL INJECTION SAFE
try {
    $roles = $dbOps->customSelect("SELECT id, role_name, role_slug FROM tbl_roles WHERE id IN (1, 2, 5, 7, 8, 9, 12, 25, 27, 28, 29, 30) ORDER BY id ASC", []);
    if ($roles === false) {
        $roles = [];
        $error = 'Unable to load roles. Please try again later.';
    }
} catch (Exception $e) {
    $roles = [];
    $error = 'Unable to load roles. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = trim($_POST['role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($role) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Check if account is locked
        if ($loginTracker->isLocked($email)) {
            $lockInfo = $loginTracker->getLockInfo($email);
            $error = "Account temporarily locked due to too many failed login attempts. Please try again in {$lockInfo['remaining_minutes']} minute(s).";
        } else {
            try {
                // Secure login query with prepared statements - SQL INJECTION SAFE
                $sql = "SELECT u.*, r.role_name, r.role_slug 
                        FROM tbl_users u
                        INNER JOIN tbl_roles r ON u.role_id = r.id
                        WHERE u.email = ? AND r.role_slug = ? AND u.status = 'active'";
                $results = $dbOps->customSelect($sql, [$email, $role]);
                $user = !empty($results) ? $results[0] : false;

                if ($user && password_verify($password, $user['password'])) {
                    // Successful password check
                    $loginTracker->recordSuccessfulLogin($email);
                    session_regenerate_id(true);

                    // Handle Trust Device Preference
                    if (isset($_POST['trust_device'])) {
                        $_SESSION['trust_device_requested'] = true;
                    } else {
                        unset($_SESSION['trust_device_requested']);
                    }

                    // MFA & OTP FLOW
                    require_once dirname(__DIR__) . '/common/helpers/security_helper.php';

                    $device_uuid = getDeviceUUID();
                    $device_name = getDeviceName();

                    // Register/Update device attempt
                    registerDeviceAttempt($conn, $user['id'], $device_uuid, $device_name);

                    if (isDeviceTrusted($conn, $user['id'], $device_uuid)) {
                        // Trusted device - bypass OTP/Approval
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role_slug'];
                        $_SESSION['role_id'] = $user['role_id'];
                        $_SESSION['role_name'] = $user['role_name'];

                        // LOG: Trusted Device Login
                        logAuditAction($conn, 'trusted_device_login', 'authentication', "User logged in via trusted device: {$email}");

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
                    }

                    if (isDeviceAuthorized($conn, $user['id'], $device_uuid)) {
                        // Authorized device - check for Google Auth first
                        if (!empty($user['google_auth_enabled']) && $user['google_auth_enabled'] == 1) {
                            $_SESSION['pending_auth_user_id'] = $user['id'];
                            $_SESSION['pending_auth_role'] = $user['role_slug'];

                            // LOG: Google Auth Required
                            logAuditAction($conn, 'google_auth_required', 'authentication', "Google Authenticator verification required for user: {$email}");

                            header('Location: google-auth-verify.php');
                            exit;
                        }

                        // Fallback to traditional OTP
                        $otp = generateOTP();
                        if (saveUserOTP($conn, $user['id'], $otp)) {
                            sendOTPEmail($conn, $user['email'], $user['name'], $otp);

                            $_SESSION['pending_auth_user_id'] = $user['id'];
                            $_SESSION['pending_auth_role'] = $user['role_slug'];
                            $_SESSION['otp_sent_at'] = time();

                            // LOG: OTP Sent
                            logAuditAction($conn, 'otp_sent', 'authentication', "OTP sent to user: {$email}");

                            header('Location: otp-verify.php');
                            exit;
                        } else {
                            $error = 'Unable to generate security code. Please try again.';
                        }
                    } else {
                        // New device - decide between MFA flow or Approval
                        $mfa_enabled = false;
                        if (!empty($user['google_auth_enabled']) && $user['google_auth_enabled'] == 1) {
                            $mfa_enabled = true;
                        }
                        if (!$mfa_enabled) {
                            if (!empty($user['two_fa_enabled']) && $user['two_fa_enabled'] == 1) {
                                $mfa_enabled = true;
                            }
                        }
                        if (!$mfa_enabled) {
                            if (!empty($user['2fa_enabled']) && $user['2fa_enabled'] == 1) {
                                $mfa_enabled = true;
                            }
                        }

                        if ($mfa_enabled) {
                            // User has MFA configured - bypass approval and require MFA
                            // Prefer Google Authenticator if enabled
                            if (!empty($user['google_auth_enabled']) && $user['google_auth_enabled'] == 1) {
                                $_SESSION['pending_auth_user_id'] = $user['id'];
                                $_SESSION['pending_auth_role'] = $user['role_slug'];
                                // LOG: Google Auth Required (new device)
                                logAuditAction($conn, 'google_auth_required', 'authentication', "Google Authenticator verification required for user: {$email} (new device: {$device_name})");
                                header('Location: google-auth-verify.php');
                                exit;
                            }

                            // Fallback to OTP for other MFA setups
                            $otp = generateOTP();
                            if (saveUserOTP($conn, $user['id'], $otp)) {
                                sendOTPEmail($conn, $user['email'], $user['name'], $otp);

                                $_SESSION['pending_auth_user_id'] = $user['id'];
                                $_SESSION['pending_auth_role'] = $user['role_slug'];
                                $_SESSION['otp_sent_at'] = time();

                                // LOG: OTP Sent for MFA (new device)
                                logAuditAction($conn, 'otp_sent', 'authentication', "OTP sent to user: {$email} (new device: {$device_name})");

                                header('Location: otp-verify.php');
                                exit;
                            } else {
                                $error = 'Unable to generate security code. Please try again.';
                            }
                        } else {
                            // New device and no MFA configured - require device approval
                            $_SESSION['pending_auth_user_id'] = $user['id'];
                            $_SESSION['pending_auth_role'] = $user['role_slug'];

                            // LOG: Device Approval Pending
                            logAuditAction($conn, 'device_approval_pending', 'authentication', "Device approval required for user: {$email} (Device: {$device_name})");

                            header('Location: awaiting-approval.php');
                            exit;
                        }
                    }
                } else {
                    // Failed login - record attempt
                    $attemptResult = $loginTracker->recordFailedAttempt($email);

                    // LOG: Failed login attempt
                    logLoginAttempt($conn, $email, 'failed', 'Invalid credentials');

                    if ($attemptResult['locked']) {
                        $error = $attemptResult['message'];
                        // LOG: Account locked
                        logAuditAction($conn, 'account_locked', 'authentication', "Account locked for user: {$email}", [
                            'action_data' => ['email' => $email, 'reason' => 'Too many failed attempts'],
                            'status' => 'warning'
                        ]);
                    } else {
                        $error = 'Invalid email, password, or role selection. ' . $attemptResult['remaining_attempts'] . ' attempt(s) remaining.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'An error occurred. Please try again.';
                error_log('Login error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo htmlspecialchars($SYSTEM_NAME ?? ''); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/portal.css">
</head>

<body>
    <div class="login-container">
        <!-- Left Panel - Branding -->
        <div class="brand-panel">
            <div class="brand-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1 class="brand-name"><?php echo htmlspecialchars($SYSTEM_NAME ?? ''); ?></h1>
            <p class="brand-tagline">Your trusted partner in educational excellence and career development.</p>
            <ul class="brand-features">
                <li>
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    Secure Authentication
                </li>
                <li>
                    <div class="feature-icon"><i class="fas fa-user-lock"></i></div>
                    Role-based Access Control
                </li>
                <li>
                    <div class="feature-icon"><i class="fas fa-clock"></i></div>
                    Session Management
                </li>
            </ul>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="login-panel">
            <div class="login-header">
                <h2>Welcome back</h2>
                <p>Sign in to access your dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error ?? ''); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="role">Select Role <span class="required">*</span></label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="" disabled selected>Choose your role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['role_slug'] ?? ''); ?>" <?php echo (isset($_POST['role']) && $_POST['role'] === $role['role_slug']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group login-checkbox-group">
                    <input type="checkbox" name="trust_device" id="trust_device"
                        class="login-checkbox">
                    <label for="trust_device"
                        class="login-checkbox-label">Trust this device
                        for 7 days</label>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="security-badge">
                <i class="fas fa-lock"></i>
                <span>Secured with SSL encryption</span>
            </div>

            <div class="divider">
                <span>OR</span>
            </div>

            <div class="alt-login">
                <p>Are you a student?</p>
                <a href="modules/student-portal/student-login.php" class="btn-student-login">
                    <i class="fas fa-user-graduate"></i> Student Login Portal
                </a>
            </div>

            <div class="back-home">
                <a href="<?php echo defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php'; ?>"><i
                        class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>