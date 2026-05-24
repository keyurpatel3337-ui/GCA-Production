<?php
require_once dirname(__DIR__) . '/common/constants.php';
require_once __DIR__ . '/session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(__DIR__) . '/common/helpers/security_helper.php';
require_once DB_CONNECT_FILE;

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user status
$stmt = $conn->prepare("SELECT google_auth_secret, google_auth_enabled, email, name FROM tbl_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found");
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_secret') {
        // Only generate if not already enabled
        if (!$user['google_auth_enabled']) {
            $new_secret = generateGoogleAuthSecret();
            saveGoogleAuthSecret($conn, $user_id, $new_secret);
            $success = "New secret generated. Please scan the QR code below.";
            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    } elseif ($action === 'enable_2fa') {
        $code = trim($_POST['verification_code'] ?? '');
        if (empty($code)) {
            $error = "Please enter the 6-digit code from your app.";
        } else {
            if (verifyGoogleAuthCode($user['google_auth_secret'], $code)) {
                toggleGoogleAuth($conn, $user_id, true);
                $success = "Google Authenticator has been enabled successfully!";
                $user['google_auth_enabled'] = 1;
            } else {
                $error = "Invalid verification code. Please try again.";
            }
        }
    } elseif ($action === 'disable_2fa') {
        $password = $_POST['password'] ?? '';
        // Verify password for security before disabling
        $stmt_pass = $conn->prepare("SELECT password FROM tbl_users WHERE id = ?");
        $stmt_pass->execute([$user_id]);
        $pass_hash = $stmt_pass->fetchColumn();

        if (password_verify($password, $pass_hash)) {
            toggleGoogleAuth($conn, $user_id, false);
            $success = "Google Authenticator has been disabled.";
            $user['google_auth_enabled'] = 0;
        } else {
            $error = "Incorrect password. 2FA remains enabled.";
        }
    }
}

// Prepare QR data
// Show QR when: (a) not yet enabled, OR (b) user clicked "Add to Another Device"
$show_sync_qr = isset($_GET['sync']) && $_GET['sync'] === '1' && $user['google_auth_enabled'];
$qr_url = '';
if ($user['google_auth_secret'] && (!$user['google_auth_enabled'] || $show_sync_qr)) {
    $qr_url = getGoogleAuthQRUrl($user['email'], $user['google_auth_secret'], 'GCA - ' . $user['name']);
}

$page_title = "Two-Factor Authentication Setup";
$page_breadcrumb = [
    ['title' => 'Home', 'link' => PORTAL_URL . '/index.php'],
    ['title' => 'Security', 'link' => '#'],
    ['title' => '2FA Setup', 'link' => '']
];

include __DIR__ . '/include/header.php';
include __DIR__ . '/include/navbar.php';
include __DIR__ . '/include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white p-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3 me-3">
                            <i class="fas fa-shield-halved fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold">Google Authenticator</h4>
                            <p class="mb-0 opacity-75 small">Enhance your account security with TOTP</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
                            <i class="fas fa-exclamation-circle me-3 fs-4"></i>
                            <div><?php echo htmlspecialchars($error ?? ''); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
                            <i class="fas fa-check-circle me-3 fs-4"></i>
                            <div><?php echo htmlspecialchars($success ?? ''); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($user['google_auth_enabled']): ?>
                        <!-- Status: ENABLED -->
                        <div class="text-center py-4">
                            <div class="status-icon-wrapper mb-4">
                                <div class="pulse-ring"></div>
                                <i class="fas fa-check-circle text-success display-1"></i>
                            </div>
                            <h3 class="fw-bold text-dark">2FA is Active</h3>
                            <p class="text-muted mb-5">Your account is currently protected with Google Authenticator.</p>

                            <hr class="my-4 opacity-50">

                            <!-- Add to Another Device -->  
                            <?php if ($show_sync_qr && $qr_url): ?>
                                <div class="alert alert-info border-0 rounded-4 text-start p-4 mb-4">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-mobile-alt text-info me-2"></i> Scan on Your New Device</h6>
                                    <p class="small text-secondary mb-3">Open <strong>Google Authenticator</strong> on your new phone &rarr; tap <strong>+</strong> &rarr; <strong>Scan a QR code</strong>.</p>
                                    <div class="d-flex justify-content-center mb-3">
                                        <div class="p-3 bg-white rounded-3 border shadow-sm">
                                            <div id="syncQrCode"></div>
                                        </div>
                                    </div>
                                    <p class="small text-muted mb-1">Can't scan? Enter the key manually in your app:</p>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="syncSecretKey" class="form-control font-monospace fw-bold text-center"
                                               value="<?php echo htmlspecialchars($user['google_auth_secret']); ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="copySyncSecret()">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div id="syncCopyMsg" class="text-success small mt-1 css-2fa-setup-93b8ea">✓ Copied!</div>
                                    <div class="mt-3">
                                        <a href="2fa-setup.php" class="btn btn-sm btn-outline-secondary rounded-3">
                                            <i class="fas fa-times me-1"></i> Done / Close
                                        </a>
                                    </div>
                                </div>
                                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                                <script>
                                    var syncUri = <?php echo json_encode($qr_url); ?>;
                                    if (syncUri && document.getElementById('syncQrCode')) {
                                        new QRCode(document.getElementById('syncQrCode'), {
                                            text: syncUri,
                                            width: 200, height: 200,
                                            colorDark: '#000000', colorLight: '#ffffff',
                                            correctLevel: QRCode.CorrectLevel.M
                                        });
                                    }
                                    function copySyncSecret() {
                                        var el = document.getElementById('syncSecretKey');
                                        el.select();
                                        document.execCommand('copy');
                                        var msg = document.getElementById('syncCopyMsg');
                                        msg.style.display = 'block';
                                        setTimeout(function(){ msg.style.display = 'none'; }, 2000);
                                    }
                                </script>
                            <?php else: ?>
                                <div class="text-start p-4 bg-light rounded-4 border mb-4">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-info-circle text-primary me-2"></i> How it works</h6>
                                    <p class="small text-secondary mb-0">Every time you log in from a new device, you will be prompted to enter a 6-digit code from your Google Authenticator app.</p>
                                </div>

                                <!-- New Device Sync Button -->
                                <div class="p-4 border rounded-4 bg-white shadow-sm mb-4 text-start">
                                    <h6 class="fw-bold mb-2"><i class="fas fa-mobile-alt text-primary me-2"></i> Got a New Phone?</h6>
                                    <p class="small text-secondary mb-3">Moving to a new device? Click below to show the QR code so you can add your account to Google Authenticator on the new phone &mdash; without disabling 2FA.</p>
                                    <a href="2fa-setup.php?sync=1" class="btn btn-primary rounded-3 px-4">
                                        <i class="fas fa-qrcode me-2"></i> Add to Another Device
                                    </a>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="btn btn-outline-danger rounded-3 px-4" data-bs-toggle="modal" data-bs-target="#disableMfaModal">
                                <i class="fas fa-power-off me-2"></i> Disable 2FA
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Status: DISABLED -->
                        <?php if (!$user['google_auth_secret']): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-mobile-screen text-muted display-1 mb-4 opacity-25"></i>
                                <h3 class="fw-bold text-dark">Enable 2FA</h3>
                                <p class="text-muted mb-5">Add an extra layer of security to your account using TOTP technology.</p>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="generate_secret">
                                    <button type="submit" class="btn btn-primary btn-lg rounded-3 px-5 py-3 shadow">
                                        <i class="fas fa-key me-2"></i> Begin Setup
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Step-by-Step Setup -->
                            <div class="setup-steps">
                                <div class="step mb-5">
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="step-number bg-primary text-white rounded-circle me-3">1</span>
                                        <h5 class="fw-bold mb-0">Scan QR Code</h5>
                                    </div>
                                    <p class="text-secondary small mb-4 ms-5">Open your Authenticator app and scan this code to link your account.</p>
                                    <div class="text-center ms-md-5 p-4 bg-white rounded-4 border shadow-sm d-inline-block css-2fa-setup-019128">
                                        <!-- QR code rendered locally by qrcode.js — no external API needed -->
                                        <div id="qrcode" class="d-flex justify-content-center mb-3"></div>
                                        <p class="small text-muted mb-1">Can't scan? Enter the key manually:</p>
                                        <div class="input-group input-group-sm mb-0">
                                            <input type="text" id="secretKeyDisplay" class="form-control form-control-sm text-center font-monospace fw-bold"
                                                   value="<?php echo htmlspecialchars($user['google_auth_secret']); ?>" readonly>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copySecret()" title="Copy">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <div id="copyMsg" class="text-success small mt-1 css-2fa-setup-93b8ea">✓ Copied!</div>
                                    </div>
                                </div>

                                <div class="step">
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="step-number bg-primary text-white rounded-circle me-3">2</span>
                                        <h5 class="fw-bold mb-0">Verify Code</h5>
                                    </div>
                                    <p class="text-secondary small mb-4 ms-5">Enter the 6-digit code currently showing in your app to confirm setup.</p>
                                    
                                    <form method="POST" class="ms-md-5">
                                        <input type="hidden" name="action" value="enable_2fa">
                                        <div class="row g-3">
                                            <div class="col-sm-8">
                                                <input type="text" name="verification_code" class="form-control form-control-lg text-center letter-spacing-lg fw-bold" placeholder="000000" maxlength="6" pattern="\d{6}" required autocomplete="off">
                                            </div>
                                            <div class="col-sm-4">
                                                <button type="submit" class="btn btn-primary btn-lg w-100 rounded-3">Verify &amp; Enable</button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="mt-4 ms-md-5">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="generate_secret">
                                            <button type="submit" class="btn btn-link btn-sm text-decoration-none text-muted p-0">
                                                <i class="fas fa-redo me-1"></i> Regenerate QR Code
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- qrcode.js loaded from CDN - renders QR locally, no external image API -->
                            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                            <script>
                                var otpauthUri = <?php echo json_encode($qr_url); ?>;
                                if (otpauthUri && document.getElementById('qrcode')) {
                                    new QRCode(document.getElementById('qrcode'), {
                                        text: otpauthUri,
                                        width: 200,
                                        height: 200,
                                        colorDark: '#000000',
                                        colorLight: '#ffffff',
                                        correctLevel: QRCode.CorrectLevel.M
                                    });
                                }
                                function copySecret() {
                                    var el = document.getElementById('secretKeyDisplay');
                                    el.select();
                                    document.execCommand('copy');
                                    var msg = document.getElementById('copyMsg');
                                    msg.style.display = 'block';
                                    setTimeout(function(){ msg.style.display = 'none'; }, 2000);
                                }
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
            
            <div class="text-center mt-4">
                <?php
                $role_dashboards = [
                    'super_admin'    => 'modules/dashboard/admin_dashboard.php',
                    'principle'      => 'modules/dashboard/principle_dashboard.php',
                    'counsellor'     => 'modules/dashboard/counsellor_dashboard.php',
                    'student'        => 'modules/dashboard/student_dashboard.php',
                    'accountant'     => 'modules/dashboard/accountant_dashboard.php',
                    'website_admin'  => 'modules/dashboard/website_admin_dashboard.php',
                    'maintenance'    => 'modules/dashboard/maintenance_dashboard.php',
                    'reception'      => 'modules/dashboard/reception_dashboard.php',
                    'establishment'  => 'modules/dashboard/establishment_dashboard.php',
                    'wallet_manager' => 'modules/dashboard/wallet_manager_dashboard.php',
                ];
                $role_slug = $_SESSION['role_slug'] ?? 'super_admin';
                $dashboard_url = PORTAL_URL . '/' . ($role_dashboards[$role_slug] ?? 'modules/dashboard/admin_dashboard.php');
                ?>
                <a href="<?php echo $dashboard_url; ?>" class="text-secondary text-decoration-none small">
                    <i class="fas fa-arrow-left me-1"></i> Return to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Disable MFA Modal -->
<div class="modal fade" id="disableMfaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="disable_2fa">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="modal-title fw-bold">Disable Authentication?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-secondary mb-4">To disable Google Authenticator, please enter your account password for verification.</p>
                    <div class="form-group mb-0">
                        <label class="form-label small fw-bold text-muted">Account Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control bg-light border-0" placeholder="Enter password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-toggle="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4">Confirm Disable</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include __DIR__ . '/include/footer.php'; ?>
