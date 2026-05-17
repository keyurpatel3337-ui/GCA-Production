<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/counsellor');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $stats = $response['data'] ?? [];
    $total_students = $stats['total_students'] ?? 0;
    $total_enrolled = $stats['total_enrolled'] ?? 0;
    $pending_confirmations = $stats['pending_confirmations'] ?? 0;
} else {
    // Fallback to default values if API fails
    $total_students = 0;
    $total_enrolled = 0;
    $pending_confirmations = 0;
}
?>
<?php
$page_title = "Counsellor Dashboard";
include '../../include/header.php'; ?>
<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/dashboard/counsellor_dashboard.css">
<?php
include '../../include/navbar.php'; ?>
<?php
include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <?php
    include '../../include/mfa_alert.php';
    ?>
    <?php
    if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle"></i> <?php
            echo gca_safe_html($_SESSION['success_msg']);
            ?>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-triangle"></i> <?php
            echo gca_safe_html($_SESSION['error_msg']);
            ?>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['upload_skipped'])): ?>
        <div class="alert alert-warning alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            <strong><i class="fas fa-info-circle"></i> Skipped Records:</strong>
            <ul class="mb-0 mt-2">
                <?php
                foreach ($_SESSION['upload_skipped'] as $skip): ?>
                    <li><?php
                    echo htmlspecialchars($skip ?? ''); ?></li>
                    <?php
                endforeach; ?>
            </ul>
            <?php
            unset($_SESSION['upload_skipped']); ?>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['upload_errors'])): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            <strong><i class="fas fa-exclamation-circle"></i> Upload Errors:</strong>
            <ul class="mb-0 mt-2">
                <?php
                foreach ($_SESSION['upload_errors'] as $error): ?>
                    <li><?php
                    echo htmlspecialchars($error ?? ''); ?></li>
                    <?php
                endforeach; ?>
            </ul>
            <?php
            unset($_SESSION['upload_errors']); ?>
        </div>
        <?php
    endif; ?>


    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $pending_appointments ?? 0; ?></div>
                            <div class="stat-label">Pending Apps</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/students/appointments.php?status=pending"
                        class="stat-link text-info">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $today_appointments ?? 0; ?></div>
                            <div class="stat-label">Today's Apps</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/students/appointments.php?date=today"
                        class="stat-link text-success">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $total_students ?? 0; ?></div>
                            <div class="stat-label">My Students</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/students/students.php?view=all"
                        class="stat-link text-warning">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $total_sessions ?? 0; ?></div>
                            <div class="stat-label">Total Sessions</div>
                        </div>
                        <div class="stat-icon bg-icon-danger">
                            <i class="fas fa-comments"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/students/sessions.php" class="stat-link text-danger">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card card-enhanced">
                <div class="card-header bg-gradient-primary">
                    <h3 class="card-title text-white">Upcoming Appointments</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-enhanced mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-calendar-times fs-4 mb-2 d-block"></i>
                                        No upcoming appointments
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="section-title">
        <i class="fas fa-bolt text-warning"></i> Quick Actions
    </h4>

    <div class="row g-3 mb-5">
        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/students/appointments.php" class="quick-action-btn">
                <div class="quick-icon bg-soft-primary text-primary bg-opacity-10 counsellor_dashboard-custom-1">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="quick-info">
                    <strong>My Calendar</strong>
                    <span>View Apps</span>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/students/students.php?view=all" class="quick-action-btn">
                <div class="quick-icon bg-soft-success text-success bg-opacity-10 counsellor_dashboard-custom-2">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="quick-info">
                    <strong>My Students</strong>
                    <span>View All</span>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/students/sessions.php?action=add" class="quick-action-btn">
                <div class="quick-icon bg-soft-info text-info bg-opacity-10 counsellor_dashboard-custom-3">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="quick-info">
                    <strong>Add Session</strong>
                    <span>New Entry</span>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="reports.php?action=create" class="quick-action-btn">
                <div class="quick-icon bg-soft-warning text-warning bg-opacity-10 counsellor_dashboard-custom-4">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="quick-info">
                    <strong>Create Report</strong>
                    <span>Generate</span>
                </div>
            </a>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>