<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Redirect if already logged in as student
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Fetch system settings from database
try {
    $settings_stmt = $conn->prepare("SELECT setting_key, setting_value FROM tbl_system_settings WHERE category = 'general'");
    $settings_stmt->execute();
    $system_settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $system_settings[$row['setting_key']] = $row['setting_value'];
    }
    $SYSTEM_NAME = $system_settings['system_name'] ?? 'Gyanmanjari Career Academy';
} catch (Exception $e) {
    $SYSTEM_NAME = 'Gyanmanjari Career Academy';
}

// Note: Flash messages are now handled automatically by the flash message system
// Any set_flash_message() calls before redirect will display here

// Keep error/success variables for backwards compatibility with the rest of the code
$flash_msgs = get_flash_messages(false);
$error_msg = '';
$success_msg = '';

foreach ($flash_msgs as $msg) {
    if ($msg['type'] === 'error') $error_msg = $msg['message'];
    if ($msg['type'] === 'success') $success_msg = $msg['message'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - <?php echo htmlspecialchars($SYSTEM_NAME ?? ''); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script type="text/javascript">
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            padding: 2rem;
        }

        /* Main Login Container */
        .login-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        /* Left Panel - Branding */
        .brand-panel {
            flex: 0 0 380px;
            background: linear-gradient(180deg, #0d9488 0%, #0f766e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            position: relative;
            text-align: center;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .brand-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
        }

        .brand-tagline {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
            max-width: 280px;
        }

        .brand-features {
            list-style: none;
            width: 100%;
            text-align: left;
        }

        .brand-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.9);
            padding: 0.75rem 0;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand-features li:last-child {
            border-bottom: none;
        }

        .feature-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon i {
            font-size: 0.85rem;
            color: white;
        }

        /* Right Panel - Login Form */
        .login-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border-left: 4px solid #ef4444;
            color: #dc2626;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border-left: 4px solid #10b981;
            color: #059669;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: #0d9488;
            background: white;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .help-text {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.375rem;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 0;
        }

        .toggle-password:hover {
            color: #0d9488;
        }

        .captcha-group {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .captcha-image-wrapper {
            flex: 0 0 120px;
        }

        .captcha-image {
            width: 120px;
            height: 45px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .captcha-image:hover {
            border-color: #0d9488;
        }

        .captcha-refresh {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #0d9488;
            font-size: 0.75rem;
            margin-top: 0.375rem;
            cursor: pointer;
            text-decoration: none;
        }

        .captcha-refresh:hover {
            text-decoration: underline;
        }

        .captcha-input {
            flex: 1;
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: #0d9488;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: #0f766e;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #10b981;
            font-size: 0.8rem;
            margin-top: 1.5rem;
        }

        .security-badge i {
            font-size: 0.75rem;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            padding: 0 1rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }

        .alt-login {
            text-align: center;
        }

        .alt-login p {
            color: #64748b;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }

        .btn-admin-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-admin-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
        }

        .btn-parent-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-parent-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .back-home {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-home a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s ease;
        }

        .back-home a:hover {
            color: #0d9488;
        }

        /* Mobile Responsive & Laptop Optimization */
        @media (max-width: 1400px) {
            body {
                padding: 1rem;
            }

            .login-container {
                max-width: 800px;
                border-radius: 16px;
            }

            .brand-panel {
                flex: 0 0 320px;
                padding: 2rem 1.5rem;
            }

            .login-panel {
                padding: 2rem;
            }

            .login-header {
                margin-bottom: 1rem;
            }

            .login-header h2 {
                font-size: 1.35rem;
            }

            .brand-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 1rem;
                padding: 0.5rem;
            }

            .brand-logo i {
                font-size: 1.75rem;
            }

            .brand-name {
                font-size: 1.25rem;
            }

            .brand-tagline {
                margin-bottom: 1rem;
                font-size: 0.8rem;
            }

            .brand-features {
                display: none;
            }

            .brand-tagline {
                margin-bottom: 0;
                font-size: 0.8rem;
            }

            .divider {
                margin: 0.75rem 0;
            }

            .security-badge {
                margin-top: 0.75rem;
            }

            .back-home {
                margin-top: 0.75rem;
            }

            .form-group {
                margin-bottom: 0.75rem;
            }

            .form-control {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .btn-login {
                padding: 0.65rem;
            }

            .captcha-image {
                height: 35px;
            }

            .help-text {
                display: none; /* Hide help text to save space */
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .login-container {
                flex-direction: column;
                max-width: 450px;
            }

            .brand-panel {
                flex: none;
                padding: 2rem;
            }

            .brand-features {
                display: none;
            }

            .brand-tagline {
                margin-bottom: 0;
            }

            .login-panel {
                padding: 2rem;
            }

            .captcha-group {
                flex-direction: column;
            }

            .captcha-image-wrapper {
                flex: none;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Left Panel - Branding -->
        <div class="brand-panel">
            <div class="brand-logo">
                <i class="fas fa-user-graduate"></i>
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
                <p>Sign in to access your student dashboard</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_msg ?? ''); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_msg ?? ''); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="student-login-process.php" id="studentLoginForm">
                <div class="form-group">
                    <label for="aadhaar">Aadhaar / Mobile Number <span class="required">*</span></label>
                    <input type="text" id="aadhaar" name="aadhaar" class="form-control"
                        placeholder="Enter Aadhaar or Mobile Number" pattern="[0-9]{10,12}" maxlength="12" required>
                    <div class="help-text">Enter your 12-digit Aadhaar or 10-digit registered Mobile number</div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="captcha">Captcha Code <span class="required">*</span></label>
                    <div class="captcha-group">
                        <div class="captcha-image-wrapper">
                            <img src="captcha.php?<?php echo time(); ?>" id="captcha_image" class="captcha-image"
                                alt="Captcha" onclick="refreshCaptcha()">
                            <a href="javascript:void(0)" onclick="refreshCaptcha()" class="captcha-refresh">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </a>
                        </div>
                        <div class="captcha-input">
                            <input type="text" id="captcha" name="captcha" class="form-control" placeholder="Enter code"
                                maxlength="6" required>
                        </div>
                    </div>
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
                <p>Are you a Parent looking for Fee & Wallet management?</p>
                <a href="<?php echo BASE_URL; ?>/portal/parent-login.php" class="btn-parent-login" style="margin-bottom: 1.25rem;">
                    <i class="fas fa-user-friends"></i> Parent Login Portal
                </a>
                <p>Are you an Admin, Principal, or Counsellor?</p>
                <a href="<?php echo PORTAL_URL; ?>/login.php" class="btn-admin-login">
                    <i class="fas fa-user-shield"></i> Admin Login Portal
                </a>
            </div>

            <div class="back-home">
                <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        function refreshCaptcha() {
            document.getElementById('captcha_image').src = 'captcha.php?' + Math.random();
            document.getElementById('captcha').value = '';
        }

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

        // Auto-refresh captcha every 5 minutes
        setInterval(refreshCaptcha, 300000);

        // Validate form before submission
        document.getElementById('studentLoginForm').addEventListener('submit', function (e) {
            const aadhaar = document.getElementById('aadhaar').value;
            const captcha = document.getElementById('captcha').value;

            if (!/^[0-9]{10,12}$/.test(aadhaar)) {
                alert('Please enter a valid 10-digit Mobile or 12-digit Aadhaar number');
                e.preventDefault();
                return false;
            }

            if (captcha.length !== 6) {
                alert('Please enter the 6-digit captcha code');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>

</html>