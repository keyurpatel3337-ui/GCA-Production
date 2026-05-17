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
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>assets/css/modules/student-portal/student-login.css">
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