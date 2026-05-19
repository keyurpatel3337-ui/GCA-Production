<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once HELPER_ERROR_LOGGER;
require_once PAGINATION_FILE;

// Check if user is Super Admin, Principal, or Counsellor
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle POST - Store filters in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['division_requests_filter']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Merge with existing session to preserve filters during pagination
    $existing = $_SESSION['division_requests_filter'] ?? [];
    $_SESSION['division_requests_filter'] = array_merge($existing, $_POST);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$page_title = "Pending Division Requests";

// Get filter parameters from session
$filters = $_SESSION['division_requests_filter'] ?? [];
$status_filter = $filters['status'] ?? 'pending';

// Pagination parameters
$page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
$perPage = isset($filters['per_page']) ? max(1, min(100, (int) $filters['per_page'])) : 25;
$offset = ($page - 1) * $perPage;

// Build WHERE clause for filters
$whereConditions = [];
$params = [];

// Apply role-based filtering
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    $whereConditions[] = "(dcr.counsellor_id = ? OR e.counsellor_id = ?)";
    $params[] = $user_id;
    $params[] = $user_id;
}

// Apply status filter
if ($status_filter && $status_filter !== 'all') {
    $whereConditions[] = "dcr.status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
try {
    $countSql = "SELECT COUNT(*) as total 
                FROM tbl_division_change_requests dcr
                LEFT JOIN tbl_enrolled_students e ON dcr.enrollment_id = e.enrollment_id
                $whereClause";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRecords = $countResult['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    logDatabaseError($e, "Count Division Requests");
    $totalRecords = 0;
    $totalPages = 1;
}

// Fetch division change requests with pagination
$requests = [];
try {
    $sql = "SELECT dcr.*, 
            s.student_name, s.surname, s.fathers_name, s.mob,
            e.enrollment_no, e.roll_no,
            c.course_name,
            g.group_name,
            d_old.division_name as old_division_name,
            d_new.division_name as new_division_name
            FROM tbl_division_change_requests dcr
            LEFT JOIN tbl_enrolled_students e ON dcr.enrollment_id = e.enrollment_id
            LEFT JOIN tbl_gm_std_registration s ON e.registration_id = s.id
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_group g ON r.group_id = g.id
            LEFT JOIN tbl_division d_old ON dcr.current_division_id = d_old.id
            LEFT JOIN tbl_division d_new ON dcr.requested_division_id = d_new.id
            $whereClause
            ORDER BY dcr.created_at ASC
            LIMIT ? OFFSET ?";

    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt = $conn->prepare($sql);
    $stmt->execute($allParams);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Pending Division Requests");
    $requests = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>



<div class="container-fluid">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo gca_safe_html($_SESSION['success_msg']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-triangle"></i> <?php echo gca_safe_html($_SESSION['error_msg']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Filter Requests</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                    Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>
                                    Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>
                                    Rejected</option>
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filter
                                </button>
                                <button type="submit" name="clear_filters" value="1" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                <i class="fas fa-list"></i> Division Change Requests (Total:
                <?php echo formatIndianCurrency($totalRecords, false); ?>, Showing: <?php echo count($requests); ?>)
            </h3>
            <form method="POST" class="d-inline-block" style="margin:0;">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter ?? ''); ?>">
                <input type="hidden" name="page" value="1">
                <label class="me-2">Per Page:</label>
                <select name="per_page" class="form-select form-select-sm d-inline-block" style="width: auto;"
                    onchange="this.form.submit()">
                    <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="requestsTable">
                    <thead>
                        <tr>
                            <th width="8%">Enrollment No</th>
                            <th width="15%">Student Name</th>
                            <th width="10%">Mobile</th>
                            <th width="12%">Standard</th>
                            <th width="10%">From Division</th>
                            <th width="10%">To Division</th>
                            <th width="8%">Status</th>
                            <th width="10%">Date</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['enrollment_no'] ?? ''); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(trim(($request['surname'] ?? '') . ' ' . ($request['student_name'] ?? ''))); ?></strong>
                                    <br><small
                                        class="text-muted"><?php echo htmlspecialchars($request['fathers_name'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($request['mob'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($request['course_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span
                                        class="badge bg-secondary"><?php echo htmlspecialchars($request['old_division_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span
                                        class="badge bg-info"><?php echo htmlspecialchars($request['new_division_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'warning';
                                    if ($request['status'] === 'approved')
                                        $status_class = 'success';
                                    elseif ($request['status'] === 'rejected')
                                        $status_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success approve-btn" data-id="<?php echo $request['id']; ?>"
                                            title="Approve Request">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger reject-btn" data-id="<?php echo $request['id']; ?>"
                                            title="Reject Request">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    <form method="POST" action="details.php" style="display:inline;margin:0;">
                                        <input type="hidden" name="id"
                                            value="<?php echo $request['registration_id'] ?? $request['enrollment_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" title="View Student">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-3">
                    <?php echo renderPaginationPost($page, $totalPages); ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No division change requests found matching your criteria.
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Approve request
        $('.approve-btn').on('click', function () {
            var requestId = $(this).data('id');
            var btn = $(this);
            var originalHtml = btn.html();

            showConfirm({
                title: 'Approve Division Change',
                message: 'Are you sure you want to approve this division change request?',
                confirmText: 'Yes, Approve',
                confirmButtonClass: 'btn-success',
                onConfirm: function () {
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Approving...');
                    $.api.post('students/division-request-approve', { id: requestId })
                        .then(response => {
                            if (response.success) {
                                showToast('success', 'Approved!', response.message);
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                btn.prop('disabled', false).html(originalHtml);
                                showToast('error', 'Error!', response.error || response.message);
                            }
                        }).catch(error => {
                            btn.prop('disabled', false).html(originalHtml);
                            showToast('error', 'Error!', error.message || 'Failed to approve request');
                        });
                }
            });
        });

        // Reject request
        $('.reject-btn').on('click', function () {
            var requestId = $(this).data('id');
            var btn = $(this);
            var originalHtml = btn.html();

            Swal.fire({
                title: 'Reject Division Change Request',
                input: 'textarea',
                inputPlaceholder: 'Enter reason for rejection (optional)',
                inputLabel: 'Reason for Rejection',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reject',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (!result.isConfirmed) return;
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Rejecting...');
                $.api.post('students/division-request-reject', {
                    id: requestId,
                    reason: result.value || ''
                })
                    .then(response => {
                        if (response.success) {
                            showToast('success', 'Rejected!', response.message);
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            btn.prop('disabled', false).html(originalHtml);
                            showToast('error', 'Error!', response.error || response.message);
                        }
                    }).catch(error => {
                        btn.prop('disabled', false).html(originalHtml);
                        showToast('error', 'Error!', error.message || 'Failed to reject request');
                    });
            });
        });
    });
</script>