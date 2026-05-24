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
        unset($_SESSION['leave_applications_filter']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $existing = $_SESSION['leave_applications_filter'] ?? [];
    $_SESSION['leave_applications_filter'] = array_merge($existing, $_POST);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$page_title = "Student Leave Applications";

$filters = $_SESSION['leave_applications_filter'] ?? [];
$status_filter = $filters['status'] ?? 'pending';

$page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
$perPage = isset($filters['per_page']) ? max(1, min(100, (int) $filters['per_page'])) : 25;
$offset = ($page - 1) * $perPage;

$whereConditions = [];
$params = [];

// Apply role-based filtering for counsellors (only their students)
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    $user_id = $_SESSION['user_id'];
    $whereConditions[] = "s.counsellor_id = ?";
    $params[] = $user_id;
}

if ($status_filter && $status_filter !== 'all') {
    $whereConditions[] = "l.status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $countSql = "SELECT COUNT(*) as total 
                 FROM tbl_student_leaves l 
                 LEFT JOIN tbl_gm_std_registration s ON l.student_id = s.id 
                 $whereClause";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRecords = $countResult['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    logDatabaseError($e, "Count Leave Applications");
    $totalRecords = 0;
    $totalPages = 1;
}

$requests = [];
try {
    $sql = "SELECT l.*, 
            s.student_name, s.surname, s.fathers_name, s.mob,
            e.enrollment_no, e.roll_no,
            c.course_name, g.group_name
            FROM tbl_student_leaves l
            LEFT JOIN tbl_gm_std_registration s ON l.student_id = s.id
            LEFT JOIN tbl_enrolled_students e ON l.student_id = e.registration_id
            LEFT JOIN tbl_courses c ON s.course_id = c.id
            LEFT JOIN tbl_group g ON s.group_id = g.id
            $whereClause
            ORDER BY l.applied_at ASC
            LIMIT ? OFFSET ?";

    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt = $conn->prepare($sql);
    $stmt->execute($allParams);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Leave Applications");
    $requests = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Filter Applications</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>
                                    >Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>
                                    >Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>
                                    >Rejected</option>
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary mt-4">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button type="submit" name="clear_filters" value="1" class="btn btn-secondary mt-4 ms-2">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests List -->
    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                <i class="fas fa-clipboard-list"></i> Leave Applications (Total:
                <?php echo $totalRecords; ?>, Showing:
                <?php echo count($requests); ?>)
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

        <div class="card-body p-0">
            <?php if (count($requests) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center"
                        id="requestsTable">
                        <thead class="bg-light">
                            <tr>
                                <th width="15%">Student Name</th>
                                <th width="12%">Course / Group</th>
                                <th width="10%">Leave Type</th>
                                <th width="12%">Duration</th>
                                <th width="15%">Reason</th>
                                <th width="8%">Doc</th>
                                <th width="8%">Status</th>
                                <th width="12%">Applied On</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td class="text-start">
                                        <strong>
                                            <?php echo htmlspecialchars(trim(($request['surname'] ?? '') . ' ' . ($request['student_name'] ?? ''))); ?>
                                        </strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($request['enrollment_no'] ?? 'Not Enrolled'); ?>
                                        </small>
                                        <br><small class="text-muted"><i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($request['mob'] ?? ''); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="d-block fw-bold">
                                            <?php echo htmlspecialchars($request['course_name'] ?? 'N/A'); ?>
                                        </small>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($request['group_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><span class="badge bg-info">
                                            <?php echo htmlspecialchars($request['leave_type'] ?? ''); ?>
                                        </span></td>
                                    <td>
                                        <?php
                                        $dStart = date('d M Y', strtotime($request['start_date']));
                                        $dEnd = date('d M Y', strtotime($request['end_date']));
                                        $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / 86400 + 1;
                                        echo "<span class='d-block fw-bold'>$dStart</span> to <span class='d-block fw-bold'>$dEnd</span>";
                                        echo "<small class='text-muted'>($days days)</small>";
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-outline-dark view-reason"
                                            data-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>">
                                            <i class="fas fa-eye"></i> View Reason
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ($request['doc_path']): ?>
                                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($request['doc_path'] ?? ''); ?>"
                                                target="_blank" class="btn btn-xs btn-info" title="View Document">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = match ($request['status']) {
                                            'approved' => 'success',
                                            'pending' => 'warning',
                                            'rejected' => 'danger',
                                            'cancelled' => 'secondary',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><small>
                                            <?php echo date('d M Y, h:i A', strtotime($request['applied_at'])); ?>
                                        </small></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-success approve-btn mb-1"
                                                data-id="<?php echo $request['id']; ?>" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger reject-btn mb-1"
                                                data-id="<?php echo $request['id']; ?>" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php elseif (in_array($request['status'], ['approved', 'rejected'])): ?>
                                            <button type="button" class="btn btn-xs btn-outline-secondary view-remarks"
                                                data-remarks="<?php echo htmlspecialchars($request['remarks'] ?? 'No remarks'); ?>">
                                                <i class="fas fa-comment"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-3 p-3">
                        <?php echo renderPaginationPost($page, $totalPages); ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info border-0 rounded-0 mb-0">
                    <i class="fas fa-info-circle"></i> No leave applications found matching your criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Reason/Remarks -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="infoModalLabel">Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p id="infoModalContent" class="mb-0 fs-5 text-dark"></p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        const infoModal = new bootstrap.Modal(document.getElementById('infoModal'));

        $('.view-reason').on('click', function () {
            $('#infoModalLabel').text('Leave Reason');
            $('#infoModalContent').text($(this).data('reason'));
            infoModal.show();
        });

        $('.view-remarks').on('click', function () {
            $('#infoModalLabel').text('Admin Remarks');
            $('#infoModalContent').text($(this).data('remarks'));
            infoModal.show();
        });

        // Handle AJAX Approval/Rejection
        function updateLeaveStatus(id, newStatus, remarks) {
            $.ajax({
                url: 'api/update-leave-status.php',
                type: 'POST',
                data: { id: id, status: newStatus, remarks: remarks },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        if (typeof showToast === 'function') showToast('success', 'Success!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        if (typeof showToast === 'function') showToast('error', 'Error!', response.message || 'Action failed.');
                    }
                },
                error: function () {
                    if (typeof showToast === 'function') showToast('error', 'Error!', 'Network/Server error.');
                }
            });
        }

        $('.approve-btn').on('click', function () {
            const leaveId = $(this).data('id');
            showConfirm({
                title: 'Approve Leave',
                message: 'Are you sure you want to approve this leave request? You can add optional remarks.',
                confirmText: 'Yes, Approve',
                confirmButtonClass: 'btn-success',
                input: 'textarea',
                inputPlaceholder: 'Add optional remarks here...',
                onConfirm: function (remarks) {
                    updateLeaveStatus(leaveId, 'approved', remarks || '');
                }
            });
        });

        $('.reject-btn').on('click', function () {
            const leaveId = $(this).data('id');
            showConfirm({
                title: 'Reject Leave',
                message: 'Are you sure you want to reject this leave request? Please provide a reason.',
                confirmText: 'Yes, Reject',
                confirmButtonClass: 'btn-danger',
                input: 'textarea',
                inputPlaceholder: 'Reason for rejection (Required)',
                onConfirm: function (remarks) {
                    if (!remarks || remarks.trim() === '') {
                        if (typeof showToast === 'function') showToast('error', 'Error!', 'Rejection reason is mandatory.');
                        return;
                    }
                    updateLeaveStatus(leaveId, 'rejected', remarks);
                }
            });
        });
    });
</script>