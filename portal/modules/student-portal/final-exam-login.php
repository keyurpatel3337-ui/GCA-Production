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
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f1f5f9;
            padding: 1.5rem;
        }

        .login-card {
            width: 100%;
            max-width: 480px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            position: relative;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .badge-terminal {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(13, 148, 136, 0.1);
            border: 1px solid rgba(13, 148, 136, 0.2);
            color: #0f766e;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
        }
        
        .header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }
        
        .form-control {
            width: 100%;
            height: 48px;
            padding: 0.5rem 1rem 0.5rem 2.75rem;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
            color: #1e293b;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: #0d9488;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }
        .form-control:focus ~ .input-icon {
            color: #0d9488;
        }
        
        .toggle-password {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            outline: none;
        }
        .toggle-password:hover {
            color: #64748b;
        }
        
        .captcha-row {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .captcha-img-box {
            flex: 0 0 130px;
            text-align: center;
        }
        .captcha-img {
            width: 100%;
            height: 44px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            cursor: pointer;
        }
        .captcha-refresh {
            display: block;
            font-size: 0.72rem;
            color: #0d9488;
            margin-top: 4px;
            text-decoration: none;
        }
        .captcha-refresh:hover {
            color: #0f766e;
            text-decoration: none;
        }
        
        .btn-submit {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 1rem;
        }
        
        .btn-submit:hover {
            box-shadow: 0 6px 16px rgba(13, 148, 136, 0.25);
            transform: translateY(-1px);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .terminal-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .terminal-footer a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 600;
        }
        .terminal-footer a:hover {
            color: #0f766e;
            text-decoration: underline;
        }
    </style>
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
                    <div style="flex: 1;">
                        <input type="text" id="captcha" name="captcha" class="form-control" style="padding-left: 1rem;" placeholder="Enter Code" maxlength="6" required>
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
