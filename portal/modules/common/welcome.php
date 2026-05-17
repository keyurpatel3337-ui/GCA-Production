<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get page info from URL parameter or use default
$page_name = isset($_POST['page']) ? htmlspecialchars($_POST['page'] ?? '') : 'Page';
$page_title = "Welcome to " . $page_name;
$page_breadcrumb = $page_name;

// Get user info
$user_name = $_SESSION['user_name'] ?? 'User';
$role_display = 'USER';
if (hasRole(ROLE_SUPER_ADMIN)) {
    $role_display = 'Super Admin';
} elseif (hasRole(ROLE_PRINCIPLE)) {
    $role_display = 'Principal';
} elseif (hasRole(ROLE_COUNSELLOR)) {
    $role_display = 'Counsellor';
} elseif (hasRole(ROLE_STUDENT)) {
    $role_display = 'Student';
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/common/welcome.css">

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Welcome Card -->
            <div class="card text-center welcome-custom-1">
                <div class="card-body welcome-custom-2">
                    <!-- Icon -->
                    <div class="welcome-custom-3">
                        <div class="welcome-icon-container">
                            <i class="fas fa-rocket welcome-icon"></i>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                    <h2 class="welcome-title">
                        Welcome to <?php echo $page_name; ?>!
                    </h2>

                    <p class="welcome-description">
                        Hello <strong><?php echo htmlspecialchars($user_name ?? ''); ?></strong>
                        (<?php echo $role_display; ?>)!
                        This page is currently under development. We're working hard to bring you amazing features.
                    </p>

                    <!-- Features Coming Soon -->
                    <div class="welcome-features-container">
                        <h4 class="welcome-features-title">
                            <i class="fas fa-star welcome-star-icon"></i> Coming Soon
                        </h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="welcome-feature-item">
                                    <i class="fas fa-check-circle welcome-feature-icon-1"></i>
                                    <p class="welcome-feature-text">Intuitive Interface</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="welcome-feature-item">
                                    <i class="fas fa-bolt welcome-feature-icon-2"></i>
                                    <p class="welcome-feature-text">Fast Performance</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="welcome-feature-item">
                                    <i class="fas fa-shield-alt welcome-feature-icon-3"></i>
                                    <p class="welcome-feature-text">Secure & Reliable</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="welcome-actions">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Go Back
                        </a>
                        <a href="<?php
                        if (hasRole(ROLE_SUPER_ADMIN)) {
                            echo '../dashboard/admin_dashboard.php';
                        } elseif (hasRole(ROLE_PRINCIPLE)) {
                            echo '../dashboard/principle_dashboard.php';
                        } elseif (hasRole(ROLE_COUNSELLOR)) {
                            echo '../dashboard/counsellor_dashboard.php';
                        } else {
                            echo '../student-portal/student_dashboard.php';
                        }
                        ?>" class="btn btn-primary">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    </div>

                    <!-- Additional Info -->
                    <div class="welcome-footer-info">
                        <p class="welcome-footer-text">
                            <i class="fas fa-info-circle"></i>
                            If you believe this is an error, please contact your system administrator.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="row mt-4">
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-question-circle welcome-quicklink-icon-1"></i>
                            <h5 class="welcome-quicklink-title">Need Help?</h5>
                            <p class="welcome-quicklink-text">Contact support for assistance
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-book welcome-quicklink-icon-2"></i>
                            <h5 class="welcome-quicklink-title">Documentation</h5>
                            <p class="welcome-quicklink-text">Learn how to use the system</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-lightbulb welcome-quicklink-icon-3"></i>
                            <h5 class="welcome-quicklink-title">Suggestions</h5>
                            <p class="welcome-quicklink-text">Share your ideas with us</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php include '../../include/footer.php'; ?>