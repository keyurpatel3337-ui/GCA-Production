<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
// Check if user is Principal or Admin
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Group Change Requests - Dashboard";
$page_breadcrumb = "- Dashboard";

// Get summary statistics
try {
    $stats = [];

    // Total requests
    $sql = "SELECT COUNT(*) as count FROM tbl_group_change_requests";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Pending requests
    $sql = "SELECT COUNT(*) as count FROM tbl_group_change_requests WHERE status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Under review
    $sql = "SELECT COUNT(*) as count FROM tbl_group_change_requests WHERE status = 'under_review'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['under_review'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Approved
    $sql = "SELECT COUNT(*) as count FROM tbl_group_change_requests WHERE status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['approved'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Rejected
    $sql = "SELECT COUNT(*) as count FROM tbl_group_change_requests WHERE status = 'rejected'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['rejected'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Recent requests (last 10)
    $stmt = $conn->prepare("SELECT gcr.*, 
                            s.surname, s.student_name, s.fathers_name,
                            cg.group_name as current_group_name,
                            rg.group_name as requested_group_name
                            FROM tbl_group_change_requests gcr
                            LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
                            LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
                            LEFT JOIN tbl_group rg ON gcr.requested_group_id = rg.id
                            ORDER BY gcr.request_date DESC
                            LIMIT 10");
    $stmt->execute();
    $recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Group Change Dashboard Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading dashboard data.");
    $stats = ['total' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
    $recent_requests = [];
}

?>
    <?php include '../../include/sidebar.php'; ?>
    <?php include '../../include/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-exchange-alt"></i> Group Change Requests Dashboard</h2>
            <div>
                <a href="pending-requests.php" class="btn btn-primary">
                    <i class="fas fa-clock"></i> Pending Requests
                </a>
                <a href="request-history.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i> Request History
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo gca_safe_html($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo gca_safe_html($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="card stat-card total">
                    <div class="card-body text-center">
                        <div class="stat-number text-info"><?php echo $stats['total']; ?></div>
                        <div class="text-muted">Total Requests</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="card stat-card pending">
                    <div class="card-body text-center">
                        <div class="stat-number text-warning"><?php echo $stats['pending']; ?></div>
                        <div class="text-muted">Pending</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="card stat-card review">
                    <div class="card-body text-center">
                        <div class="stat-number text-primary"><?php echo $stats['under_review']; ?></div>
                        <div class="text-muted">Under Review</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="card stat-card approved">
                    <div class="card-body text-center">
                        <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                        <div class="text-muted">Approved</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="card stat-card rejected">
                    <div class="card-body text-center">
                        <div class="stat-number text-danger"><?php echo $stats['rejected']; ?></div>
                        <div class="text-muted">Rejected</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <a href="group-change-report.php" class="text-decoration-none">
                            <i class="fas fa-chart-bar fa-3x text-secondary mb-2"></i>
                            <div class="text-muted">View Reports</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list"></i> Recent Requests</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_requests)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No requests found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Current Group</th>
                                    <th>Requested Group</th>
                                    <th>Fee Impact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $req): ?>
                                    <?php
                                    $badge_class = match ($req['status']) {
                                        'pending' => 'warning',
                                        'under_review' => 'info',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'secondary'
                                    };

                                    $fee_diff = $req['fee_difference'] ?? 0;
                                    if ($fee_diff > 0) {
                                        $fee_class = 'text-danger';
                                        $fee_icon = 'fa-arrow-up';
                                        $fee_text = '+?' . formatIndianCurrency($fee_diff, false);
                                    } elseif ($fee_diff < 0) {
                                        $fee_class = 'text-success';
                                        $fee_icon = 'fa-arrow-down';
                                        $fee_text = '-?' . formatIndianCurrency(abs($fee_diff), false);
                                    } else {
                                        $fee_class = 'text-muted';
                                        $fee_icon = 'fa-minus';
                                        $fee_text = 'No Change';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong>REQ-<?php echo $req['id']; ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($req['request_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($req['surname'] . ' ' . $req['student_name'] . ' ' . $req['fathers_name'] ?? ''); ?>
                                        </td>
                                        <td><span
                                                class="badge bg-secondary"><?php echo htmlspecialchars($req['current_group_name'] ?? ''); ?></span>
                                        </td>
                                        <td><span
                                                class="badge bg-primary"><?php echo htmlspecialchars($req['requested_group_name'] ?? ''); ?></span>
                                        </td>
                                        <td class="<?php echo $fee_class; ?>">
                                            <i class="fas <?php echo $fee_icon; ?>"></i> <?php echo $fee_text; ?>
                                        </td>
                                        <td><span
                                                class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span>
                                        </td>
                                        <td>
                                            <a href="review-group-change-request.php?id=<?php echo $req['id']; ?>"
                                                class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="pending-requests.php" class="btn btn-outline-primary">
                            View All Requests <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>

</html>

