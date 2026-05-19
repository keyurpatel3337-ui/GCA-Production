<?php
require_once __DIR__ . '/session_config.php';
require_once dirname(__DIR__) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

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

$messages = get_flash_messages(false);
$error_msg = isset($messages['error']) ? $messages['error'][0]['message'] : '';
$success_msg = isset($messages['success']) ? $messages['success'][0]['message'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Login -
        <?php echo htmlspecialchars($SYSTEM_NAME ?? ''); ?>
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .login-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .brand-panel {
            flex: 0 0 380px;
            background: linear-gradient(180deg, #4f46e5 0%, #3730a3 100%);
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
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-control::placeholder {
            color: #94a3b8;
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
            color: #4f46e5;
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: #4f46e5;
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
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
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
            color: #4f46e5;
        }

        @media (max-width: 768px) {
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

            .login-panel {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Left Panel - Branding -->
        <div class="brand-panel">
            <div class="brand-logo">
                <i class="fas fa-users"></i>
            </div>
            <h1 class="brand-name">
                <?php echo htmlspecialchars($SYSTEM_NAME ?? ''); ?>
            </h1>
            <p class="brand-tagline">Parent Portal - Manage all your children's educational activities in one place.</p>
            <ul class="brand-features">
                <li>
                    <div class="feature-icon"><i class="fas fa-child"></i></div>
                    Multi-Child Dashboard
                </li>
                <li>
                    <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    Unified Fee Management
                </li>
                <li>
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    Consolidated Results
                </li>
            </ul>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="login-panel">
            <div class="login-header">
                <h2>Parent Login</h2>
                <p>Sign in to your centralized parent account</p>
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

            <form method="POST" action="parent-login-process.php" id="parentLoginForm">
                <div class="form-group">
                    <label for="mobile">Registered Mobile Number <span class="required">*</span></label>
                    <input type="text" id="mobile" name="mobile" class="form-control"
                        placeholder="Enter 10-digit Mobile Number" pattern="[0-9]{10}" maxlength="10" required>
                    <small class="text-muted">Enter the father's mobile number used during registration</small>
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

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="security-badge">
                <i class="fas fa-lock"></i>
                <span>Secured with bit-level encryption</span>
            </div>

            <div class="divider">
                <span>OR</span>
            </div>

            <div class="back-home">
                <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
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