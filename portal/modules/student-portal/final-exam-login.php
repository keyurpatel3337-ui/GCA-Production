<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

// Fetch system settings
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
    <title>Secure Exam Terminal - <?php echo htmlspecialchars($SYSTEM_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <script type="text/javascript">
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };
    </script>
    
    
</head>
<body>
    <div class="login-card">
        <div class="header">
            <span class="badge-terminal">
                <i class="fas fa-lock mr-1"></i> Secure Terminal Locked
            </span>
            <h1>Final Exam Login</h1>
            <p>Access your assigned exam terminal</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <div><?php echo htmlspecialchars($error_msg); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle mr-2"></i>
                <div><?php echo htmlspecialchars($success_msg); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="final-exam-login-process.php" id="loginForm">
            <div class="form-group mb-3">
                <label for="aadhaar">Aadhaar / Mobile Number</label>
                <div class="input-wrapper">
                    <input type="text" id="aadhaar" name="aadhaar" class="form-control" 
                           placeholder="Enter Aadhaar or Mobile Number" pattern="[0-9]{10,12}" maxlength="12" required>
                    <i class="fas fa-id-card input-icon"></i>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="password">Exam Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggle-icon"></i>
                    </button>
                </div>
            </div>

            <div class="form-group mb-4">
                <label for="captcha">Security Verification Code</label>
                <div class="captcha-row">
                    <div class="captcha-img-box">
                        <img src="captcha.php?<?php echo time(); ?>" id="captcha_img" class="captcha-img" alt="Captcha" onclick="refreshCaptcha()">
                        <a href="javascript:void(0)" onclick="refreshCaptcha()" class="captcha-refresh">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </a>
                    </div>
                    <div class="css-final-exam-login-49cdf8">
                        <input type="text" id="captcha" name="captcha" class="form-control css-final-exam-login-2c0fc0" placeholder="Enter Code" maxlength="6" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-terminal"></i> Enter Secure Exam
            </button>
        </form>

        <div class="terminal-footer">
            <div><i class="fas fa-shield-alt text-success mr-1"></i> Connection Encrypted (SSL)</div>
            <div>
                <a href="student-login.php">
                    <i class="fas fa-arrow-left mr-1"></i> Go to Standard Student Portal
                </a>
            </div>
        </div>
    </div>

    <script>
        function refreshCaptcha() {
            document.getElementById('captcha_img').src = 'captcha.php?' + Math.random();
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

        setInterval(refreshCaptcha, 300000);

        document.getElementById('loginForm').addEventListener('submit', function (e) {
            const aadhaar = document.getElementById('aadhaar').value;
            const captcha = document.getElementById('captcha').value;

            if (!/^[0-9]{10,12}$/.test(aadhaar)) {
                alert('Please enter a valid 10-digit Mobile or 12-digit Aadhaar number');
                e.preventDefault();
                return false;
            }

            if (captcha.length !== 6) {
                alert('Please enter the 6-digit verification code');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
