<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Receptionist or Super Admin
if (!hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Reception Dashboard";

// Get reception-specific statistics
try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    // Today's Registrations
    $today_registrations = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // Total Students
    $total_students = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration")->fetchColumn();

    // Today's Pending Appointments
    $pending_appointments = $conn->query("SELECT COUNT(*) FROM tbl_appointments WHERE status = 'pending' AND appointment_date = CURDATE()")->fetchColumn();

    // Yesterday's Registrations (for comparison)
    $yesterday_registrations = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

} catch (Exception $e) {
    logError("Reception Dashboard Error: " . $e->getMessage());
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4 pb-5">
    <?php
    include '../../include/mfa_alert.php';
    ?>

    <!-- Key Metrics Row -->
    <div class="row g-4 mb-5">
        <!-- Today's Registrations -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-primary">
                                <?php echo $today_registrations; ?>
                            </div>
                            <div class="stat-label">Today's Registrations</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="stat-link text-muted small">
                        Yesterday:
                        <?php echo $yesterday_registrations; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Appointments -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-warning">
                                <?php echo $pending_appointments; ?>
                            </div>
                            <div class="stat-label">Pending Today</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <a href="../students/appointments.php" class="stat-link text-warning">
                        View Schedule <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total Database -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-success">
                                <?php echo number_format($total_students); ?>
                            </div>
                            <div class="stat-label">Total Admissions</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=all" class="stat-link text-success">
                        Manage All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Registration -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100 bg-gradient-primary text-white border-0">
                <div class="stat-card h-100 d-flex flex-column justify-content-center align-items-center text-center">
                    <i class="fas fa-plus-circle fa-3x mb-3"></i>
                    <h5 class="fw-bold">New Admission Inquiry</h5>
                    <a href="../students/add.php"
                        class="btn btn-light btn-sm rounded-pill px-4 mt-2 fw-bold text-primary shadow-sm">
                        Start Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Student Search -->
        <div class="col-lg-7">
            <div class="glass-card p-4 h-100">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-search text-primary me-2"></i> Quick Student Lookup
                </h4>
                <form action="../students/students.php" method="GET" class="mb-4">
                    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-white border-0 ps-4">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-0 px-3"
                            placeholder="Search by name, ID or mobile...">
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Search</button>
                    </div>
                </form>

                <h6 class="text-muted text-uppercase mb-3 mt-4 fw-bold small">Recent Admissions</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <tbody>
                            <?php
                            try {
                                $recent = $conn->query("SELECT id, surname, student_name, mob, created_at FROM tbl_gm_std_registration ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($recent as $std): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary-subtle text-primary rounded-circle me-3 d-flex align-items-center justify-content-center css-reception_dashboard-55885f">
                                                    <?php echo strtoupper(substr($std['student_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">
                                                        <?php echo htmlspecialchars($std['surname'] . ' ' . $std['student_name'] ?? ''); ?>
                                                    </div>
                                                    <div class="small text-muted">ID:
                                                        <?php echo $std['id']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><small class="text-muted"><i class="fas fa-phone me-1 small"></i>
                                                <?php echo $std['mob']; ?>
                                            </small></td>
                                        <td class="text-end">
                                            <a href="../students/details.php?id=<?php echo $std['id']; ?>"
                                                class="btn btn-sm btn-light border">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            } catch (Exception $e) {
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Tools -->
        <div class="col-lg-5">
            <div class="glass-card p-4 h-100">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-bolt text-warning me-2"></i> Desk Shortcuts
                </h4>
                <div class="list-group list-group-flush">
                    <a href="../students/appointments.php"
                        class="list-group-item list-group-item-action border-0 py-3 px-0 d-flex align-items-center">
                        <div class="quick-icon bg-info-subtle text-info me-3">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Manage Appointments</div>
                            <div class="small text-muted">Check-in students for sessions</div>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="../payments/payments.php"
                        class="list-group-item list-group-item-action border-0 py-3 px-0 d-flex align-items-center">
                        <div class="quick-icon bg-success-subtle text-success me-3">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Collect Token Fee</div>
                            <div class="small text-muted">Process registration payments</div>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="../students/admission-confirm-list.php"
                        class="list-group-item list-group-item-action border-0 py-3 px-0 d-flex align-items-center">
                        <div class="quick-icon bg-purple-subtle text-purple me-3">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Confirm Admissions</div>
                            <div class="small text-muted">Finalize student enrollment</div>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted small"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../../include/footer.php'; ?>

