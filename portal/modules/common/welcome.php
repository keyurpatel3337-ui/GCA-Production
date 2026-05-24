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


<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Welcome Card -->
            <div class="card text-center" style="margin-top: 3rem;">
                <div class="card-body" style="padding: 3rem;">
                    <!-- Icon -->
                    <div style="margin-bottom: 2rem;">
                        <div
                            style="width: 100px; height: 100px; margin: 0 auto; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-rocket" style="font-size: 3rem; color: white;"></i>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                    <h2 style="font-size: 2rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">
                        Welcome to <?php echo $page_name; ?>!
                    </h2>

                    <p
                        style="font-size: 1.125rem; color: #64748b; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                        Hello <strong><?php echo htmlspecialchars($user_name ?? ''); ?></strong>
                        (<?php echo $role_display; ?>)!
                        This page is currently under development. We're working hard to bring you amazing features.
                    </p>

                    <!-- Features Coming Soon -->
                    <div style="background: #f8fafc; border-radius: 12px; padding: 2rem; margin-bottom: 2rem;">
                        <h4 style="font-size: 1.25rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem;">
                            <i class="fas fa-star" style="color: #f59e0b;"></i> Coming Soon
                        </h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div style="padding: 1rem;">
                                    <i class="fas fa-check-circle"
                                        style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                                    <p style="margin: 0; color: #475569; font-weight: 500;">Intuitive Interface</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div style="padding: 1rem;">
                                    <i class="fas fa-bolt"
                                        style="font-size: 2rem; color: #f59e0b; margin-bottom: 0.5rem;"></i>
                                    <p style="margin: 0; color: #475569; font-weight: 500;">Fast Performance</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div style="padding: 1rem;">
                                    <i class="fas fa-shield-alt"
                                        style="font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem;"></i>
                                    <p style="margin: 0; color: #475569; font-weight: 500;">Secure & Reliable</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
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
                    <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                        <p style="color: #94a3b8; font-size: 0.875rem; margin: 0;">
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
                            <i class="fas fa-question-circle"
                                style="font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem;"></i>
                            <h5 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Need Help?</h5>
                            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Contact support for assistance
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-book" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                            <h5 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Documentation</h5>
                            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Learn how to use the system</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-lightbulb"
                                style="font-size: 2rem; color: #f59e0b; margin-bottom: 0.5rem;"></i>
                            <h5 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Suggestions</h5>
                            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Share your ideas with us</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php include '../../include/footer.php'; ?>