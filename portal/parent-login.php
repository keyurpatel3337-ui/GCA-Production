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

            <div class="alt-login">
                <p>Are you a Student looking for the Student Portal?</p>
                <a href="<?php echo BASE_URL; ?>/portal/modules/student-portal/student-login.php" class="btn-student-login">
                    <i class="fas fa-user-graduate"></i> Student Login Portal
                </a>
            </div>

            <div class="divider css-parent-login-6ad573">
                <span>OR</span>
            </div>

            <div class="back-home css-parent-login-f8a732">
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