<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Establishment Admin or Super Admin
if (!hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Establishment Dashboard";

// Get institutional statistics
try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    // Total Counsellors (Staff)
    $staff_count = $conn->query("SELECT COUNT(*) FROM tbl_users WHERE role_id = " . ROLE_COUNSELLOR . " AND status = 'active'")->fetchColumn();

    // Academic Stats
    $courses_count = $conn->query("SELECT COUNT(*) FROM tbl_courses")->fetchColumn();
    $boards_count = $conn->query("SELECT COUNT(*) FROM tbl_boards")->fetchColumn();
    $years_count = $conn->query("SELECT COUNT(*) FROM tbl_academic_years")->fetchColumn();

    // Student Overview
    $total_students = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration")->fetchColumn();
    $enrolled_students = $conn->query("SELECT COUNT(*) FROM tbl_enrolled_students WHERE is_active = 1")->fetchColumn();

} catch (Exception $e) {
    logError("Establishment Dashboard Error: " . $e->getMessage());
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/dashboard/establishment_dashboard.css">

<div class="container-fluid py-4 pb-5">
    <?php
    include '../../include/mfa_alert.php';
    ?>

    <!-- Key Metrics Row -->
    <div class="row g-4 mb-5">
        <!-- Institutional Staff -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-primary">
                                <?php echo $staff_count; ?>
                            </div>
                            <div class="stat-label">Active Counsellors</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                    <a href="../settings/users.php?role=counsellor" class="stat-link text-primary">
                        Manage Staff <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Academic Config -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-success">
                                <?php echo $courses_count; ?>
                            </div>
                            <div class="stat-label">Active Courses</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <a href="../academics/courses.php" class="stat-link text-success">
                        Course Config <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total Registrations -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-info">
                                <?php echo number_format($total_students); ?>
                            </div>
                            <div class="stat-label">Total Registrations</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=all" class="stat-link text-info">
                        View Students <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Final Enrollment -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100 bg-gradient-success text-white border-0">
                <div class="stat-card h-100 d-flex flex-column justify-content-center align-items-center text-center">
                    <div class="stat-value mb-1">
                        <?php echo number_format($enrolled_students); ?>
                    </div>
                    <div class="stat-label text-white opacity-75 mb-3">Enrolled Students</div>
                    <a href="../students/students.php?view=enrolled"
                        class="btn btn-light btn-sm rounded-pill px-4 fw-bold text-success shadow-sm">
                        Manage Admissions
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Academic Management -->
        <div class="col-lg-6">
            <div class="glass-card p-4 h-100">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-graduation-cap text-primary me-2"></i> Academic Management
                </h4>
                <div class="row g-3">
                    <div class="col-6">
                        <a href="../academics/boards.php" class="quick-action-btn">
                            <div class="quick-icon bg-primary-subtle text-primary">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="quick-info">
                                <strong>Boards</strong>
                                <span>
                                    <?php echo $boards_count; ?> Configured
                                </span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../academics/academic-years.php" class="quick-action-btn">
                            <div class="quick-icon bg-success-subtle text-success">
                                <div class="fas fa-calendar-alt"></div>
                            </div>
                            <div class="quick-info">
                                <strong>Sessions</strong>
                                <span>
                                    <?php echo $years_count; ?> Active
                                </span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../academics/courses.php" class="quick-action-btn">
                            <div class="quick-icon bg-info-subtle text-info">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="quick-info">
                                <strong>Mediums</strong>
                                <span>Config</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../academics/groups.php" class="quick-action-btn">
                            <div class="quick-icon bg-warning-subtle text-warning">
                                <i class="fas fa-users-class"></i>
                            </div>
                            <div class="quick-info">
                                <strong>Groups</strong>
                                <span>Batching</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapid Actions -->
        <div class="col-lg-6">
            <div class="glass-card p-4 h-100">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-bolt text-warning me-2"></i> Institutional Actions
                </h4>
                <div class="list-group list-group-flush">
                    <a href="../reports/consolidated_report.php"
                        class="list-group-item list-group-item-action border-0 py-3 px-0 d-flex align-items-center">
                        <div class="quick-icon bg-danger-subtle text-danger me-3">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Consolidated Reports</div>
                            <div class="small text-muted">View institutional performance metrics</div>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="../settings/users.php"
                        class="list-group-item list-group-item-action border-0 py-3 px-0 d-flex align-items-center">
                        <div class="quick-icon bg-purple-subtle text-purple me-3">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Staff Directory</div>
                            <div class="small text-muted">Manage roles and permissions</div>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="../settings/config.php"
                        class="list-group-item list-group-item-action border-0 py-3 px-0 d-flex align-items-center">
                        <div class="quick-icon bg-secondary-subtle text-secondary me-3">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div>
                            <div class="fw-bold">System Configuration</div>
                            <div class="small text-muted">Primary institution settings</div>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>