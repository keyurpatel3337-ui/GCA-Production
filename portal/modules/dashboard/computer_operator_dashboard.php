<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Computer Operator or Super Admin
if (!hasRole(ROLE_COMPUTER_OPERATOR) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/computer-operator');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $stats = $response['data'] ?? [];
    $today_registrations = $stats['today_registrations'] ?? 0;
    $yesterday_registrations = $stats['yesterday_registrations'] ?? 0;
    $total_registrations = $stats['total_registrations'] ?? 0;
    $total_enrolled = $stats['total_enrolled'] ?? 0;

    // Detailed Stats
    $standard_details = $stats['standard_details'] ?? [];
    $group_stats = $stats['groups'] ?? [];
    $medium_stats = $stats['mediums'] ?? [];
    $fee_stats = $stats['fee_status'] ?? [];
    $recent_modified = $stats['recent_modified'] ?? [];
} else {
    // Fallback to defaults
    $today_registrations = 0;
    $yesterday_registrations = 0;
    $total_registrations = 0;
    $total_enrolled = 0;
    $standard_details = [];
    $group_stats = [];
    $medium_stats = [];
    $fee_stats = [];
    $recent_modified = [];
}

$page_title = "Computer Operator Dashboard";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4 pb-5">
    <?php
    include '../../include/mfa_alert.php';
    ?>

    <div class="welcome-banner mb-4">
        <h2 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p class="mb-0 opacity-75">Student Data Management Portal</p>
    </div>

    <!-- Key Metrics Row -->
    <div class="row g-4 mb-5">
        <!-- Today's Registrations -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-primary">
                                <?php echo formatIndianCurrency($today_registrations, false); ?>
                            </div>
                            <div class="stat-label">Today's Registrations</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="stat-link text-muted small">
                        Yesterday: <?php echo $yesterday_registrations; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Registrations -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-success">
                                <?php echo formatIndianCurrency($total_registrations, false); ?>
                            </div>
                            <div class="stat-label">Total Registrations</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=all" class="stat-link text-success">
                        Browse Records <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Enrolled Students -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-warning">
                                <?php echo formatIndianCurrency($total_enrolled, false); ?>
                            </div>
                            <div class="stat-label">Enrolled Students</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=enrolled" class="stat-link text-warning">
                        View Enrolled <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Registration -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card h-100 bg-gradient-primary text-white border-0">
                <div class="stat-card h-100 d-flex flex-column justify-content-center align-items-center text-center py-3">
                    <i class="fas fa-user-edit fa-2x mb-2"></i>
                    <h6 class="fw-bold mb-1">New Entry</h6>
                    <a href="../students/add.php"
                        class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-primary shadow-sm mt-1">
                        Register
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Standard-wise Performance Row -->
    <h4 class="section-title mt-5">
        <i class="fas fa-graduation-cap text-primary"></i> Standard-wise Performance
    </h4>
    <div class="row g-4 mb-5">
        <?php 
        $std_icons = ['11th' => 'fa-book-open', '12th' => 'fa-graduation-cap', 'Reneet' => 'fa-redo'];
        $std_colors = ['11th' => 'info', '12th' => 'primary', 'Reneet' => 'danger'];
        
        foreach ($standard_details as $name => $data): 
            $color = $std_colors[$name] ?? 'secondary';
            $icon = $std_icons[$name] ?? 'fa-user-graduate';
        ?>
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100 overflow-hidden">
                <div class="p-3 border-bottom border-light bg-<?php echo $color; ?>-subtle d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-<?php echo $color; ?>">
                        <i class="fas <?php echo $icon; ?> me-2"></i><?php echo $name; ?>
                    </h5>
                    <span class="badge bg-<?php echo $color; ?> text-white rounded-pill">Total: <?php echo $data['registered']; ?></span>
                </div>
                <div class="p-4">
                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-light text-center border">
                                <div class="small text-muted mb-1">Reg.</div>
                                <div class="h6 mb-0 fw-bold"><?php echo $data['registered']; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-light text-center border">
                                <div class="small text-muted mb-1">Enrolled</div>
                                <div class="h6 mb-0 fw-bold text-primary"><?php echo $data['enrolled']; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-success-subtle text-center border border-success-subtle">
                                <div class="small text-success mb-1">Paid</div>
                                <div class="h6 mb-0 fw-bold text-success"><?php echo $data['paid']; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-warning-subtle text-center border border-warning-subtle">
                                <div class="small text-warning mb-1">Partial</div>
                                <div class="h6 mb-0 fw-bold text-warning"><?php echo $data['partial']; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-danger-subtle text-center border border-danger-subtle">
                                <div class="small text-danger mb-1">Pending</div>
                                <div class="h6 mb-0 fw-bold text-danger"><?php echo $data['pending']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 pt-3 border-top">
                        <div class="mb-3">
                            <div class="small fw-bold text-muted mb-2"><i class="fas fa-users me-1"></i>Groups</div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($data['groups'] as $g): ?>
                                    <span class="badge bg-white text-dark border fw-normal"><?php echo $g['name']; ?>: <strong><?php echo $g['count']; ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="small fw-bold text-muted mb-2"><i class="fas fa-language me-1"></i>Mediums</div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($data['mediums'] as $m): ?>
                                    <span class="badge bg-white text-dark border fw-normal"><?php echo $m['name']; ?>: <strong><?php echo $m['count']; ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Detailed Analysis Row -->
    <h4 class="section-title">
        <i class="fas fa-chart-pie text-success"></i> Demographic & Payment Analysis
    </h4>
    <div class="row g-4 mb-5">
        <!-- Group Wise -->
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100">
                <div class="p-3 border-bottom border-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-users text-success me-2"></i>Group Wise Breakdown</h6>
                </div>
                <div class="p-3">
                    <?php 
                    if (!empty($group_stats)):
                        foreach ($group_stats as $g): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-medium text-muted"><?php echo $g['name']; ?></span>
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2"><?php echo $g['count']; ?> Students</span>
                        </div>
                        <?php endforeach; 
                    else:
                        echo '<div class="text-center py-4 text-muted">No data available</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <!-- Medium Wise -->
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100">
                <div class="p-3 border-bottom border-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-language text-info me-2"></i>Medium Wise Distribution</h6>
                </div>
                <div class="p-3">
                    <?php 
                    if (!empty($medium_stats)):
                        foreach ($medium_stats as $m): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-medium text-muted"><?php echo $m['name']; ?></span>
                            <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2"><?php echo $m['count']; ?> Students</span>
                        </div>
                        <?php endforeach; 
                    else:
                        echo '<div class="text-center py-4 text-muted">No data available</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <!-- Fee Status Wise -->
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100">
                <div class="p-3 border-bottom border-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-money-bill-wave text-warning me-2"></i>Overall Fee Standing</h6>
                </div>
                <div class="p-3">
                    <?php 
                    $fee_colors = ['paid' => 'success', 'pending' => 'danger', 'partial' => 'warning', 'overdue' => 'dark'];
                    if (!empty($fee_stats)):
                        foreach ($fee_stats as $f): 
                            $color = $fee_colors[strtolower($f['name'])] ?? 'secondary';
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-medium text-muted text-capitalize"><?php echo $f['name']; ?></span>
                            <span class="badge bg-<?php echo $color; ?>-subtle text-<?php echo $color; ?> rounded-pill px-3 py-2"><?php echo $f['count']; ?> Students</span>
                        </div>
                        <?php endforeach; 
                    else:
                        echo '<div class="text-center py-4 text-muted">No data available</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Student Search -->
        <div class="col-lg-12">
            <div class="glass-card p-4">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-search text-primary me-2"></i> Find & Update Student Record
                </h4>
                <form action="../students/students.php" method="GET" class="mb-4">
                    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-white border-0 ps-4">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-0 px-3"
                            placeholder="Search by name, Aadhaar, ID or mobile...">
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Search</button>
                    </div>
                </form>

                <h6 class="text-muted text-uppercase mb-3 mt-4 fw-bold small">Recently Modified Records</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <tbody>
                            <?php
                            if (!empty($recent_modified)):
                                foreach ($recent_modified as $std): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary-subtle text-primary rounded-circle me-3 d-flex align-items-center justify-content-center css-computer_operator_dashboard-55885f">
                                                    <?php echo strtoupper(substr($std['student_name'] ?? 'S', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">
                                                         <?php echo htmlspecialchars(($std['surname'] ?? '') . ' ' . ($std['student_name'] ?? '') . ' ' . ($std['fathers_name'] ?? '')); ?>
                                                     </div>
                                                    <div class="small text-muted">ID: <?php echo $std['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><small class="text-muted"><i class="fas fa-phone me-1 small"></i> <?php echo $std['mob']; ?></small></td>
                                        <td class="text-end">
                                            <a href="../students/edit-student.php?id=<?php echo $std['id']; ?>"
                                                class="btn btn-sm btn-info text-white"><i class="fas fa-edit me-1"></i> Edit</a>
                                            <a href="../students/details.php?id=<?php echo $std['id']; ?>"
                                                class="btn btn-sm btn-light border">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else:
                                echo '<tr><td colspan="3" class="text-center py-3 text-muted">No recently modified records</td></tr>';
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../../include/footer.php'; ?>
