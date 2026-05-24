<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is logged in as student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$page_title = "My Division Change Requests";

// Get enrollment info
try {
    $stmt = $conn->prepare("SELECT e.enrollment_id FROM tbl_enrolled_students e 
                           WHERE e.registration_id = ? AND e.is_active = 1");
    $stmt->execute([$student_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment) {
        set_flash_message('error', "You must be enrolled to view division change requests.");
        header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
        exit;
    }

    // Get all requests for this student
    $stmt_requests = $conn->prepare("SELECT dcr.*, 
                                    d_current.division_name as current_division_name,
                                    d_requested.division_name as requested_division_name,
                                    u.name as reviewed_by_name
                                    FROM tbl_division_change_requests dcr
                                    LEFT JOIN tbl_division d_current ON dcr.current_division_id = d_current.id
                                    LEFT JOIN tbl_division d_requested ON dcr.requested_division_id = d_requested.id
                                    LEFT JOIN tbl_users u ON dcr.reviewed_by = u.id
                                    WHERE dcr.enrollment_id = ?
                                    ORDER BY dcr.request_date ASC");
    $stmt_requests->execute([$enrollment['enrollment_id']]);
    $requests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Division Change Requests");
    set_flash_message('error', "Error loading your requests.");
    header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
    exit;
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg'];
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg'];
            ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-exchange-alt"></i> Division Change Requests</h3>
            <a href="request-division-change.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> New Request
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Request Date</th>
                            <th>From Division</th>
                            <th>To Division</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Review Date</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No division change requests found.
                                    <br><a href="request-division-change.php" class="btn btn-primary mt-2">Submit a
                                        Request</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo date('d M Y h:i A', strtotime($request['request_date'])); ?></td>
                                    <td>
                                        <span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($request['current_division_name'] ?? ''); ?></span>
                                        <?php if ($request['current_roll_no']): ?>
                                            <br><small>Roll: <?php echo $request['current_roll_no']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span
                                            class="badge bg-info text-dark"><?php echo htmlspecialchars($request['requested_division_name'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <span class="text-truncate d-inline-block css-my-division-change-requests-1a5c8d"
                                            title="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(substr($request['reason'], 0, 50) . (strlen($request['reason']) > 50 ? '...' : '') ?? ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'bg-warning text-dark',
                                            'under_review' => 'bg-info text-dark',
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger'
                                        ];
                                        $badge_class = $status_badges[$request['status']] ?? 'bg-secondary';
                                        $status_icons = [
                                            'pending' => 'fa-clock',
                                            'under_review' => 'fa-search',
                                            'approved' => 'fa-check-circle',
                                            'rejected' => 'fa-times-circle'
                                        ];
                                        $icon = $status_icons[$request['status']] ?? 'fa-question-circle';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['review_date']): ?>
                                            <?php echo date('d M Y', strtotime($request['review_date'])); ?>
                                            <?php if ($request['reviewed_by_name']): ?>
                                                <br><small>by <?php echo htmlspecialchars($request['reviewed_by_name'] ?? ''); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['review_remarks']): ?>
                                            <span class="text-truncate d-inline-block css-my-division-change-requests-7ecc66"
                                                title="<?php echo htmlspecialchars($request['review_remarks'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($request['review_remarks'] ?? ''); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>