<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(__DIR__)) . '/common/pagination.php';

// Check if user has one of the allowed roles
$allowed_roles = [ROLE_ACCOUNTANT, ROLE_RECEPTION, ROLE_PRINCIPLE, ROLE_SUPER_ADMIN, ROLE_ESTABLISHMENT];
if (!hasAnyRole($allowed_roles)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Cancellation Approvals";
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
 
// Get filters
$search_filter = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$totalPages = 0;
$totalRecords = 0;
$baseUrl = '';
$requests = [];

try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    // Handle Approval/Rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
        $request_id = (int) $_POST['request_id'];
        $action = $_POST['action']; // 'approved' or 'rejected'
        $comment = $_POST['comment'] ?? '';

        $field_prefix = '';
        if (hasRole(ROLE_ACCOUNTANT))
            $field_prefix = 'account';
        elseif (hasRole(ROLE_RECEPTION))
            $field_prefix = 'reception';
        elseif (hasRole(ROLE_PRINCIPLE))
            $field_prefix = 'principal';
        elseif (hasRole(ROLE_SUPER_ADMIN))
            $field_prefix = 'principal'; // Super Admin acts as Principal for now

        if ($field_prefix) {
            $stmt = $conn->prepare("UPDATE tbl_admission_cancellations SET 
                {$field_prefix}_status = ?, 
                {$field_prefix}_comment = ?, 
                {$field_prefix}_updated_by = ?, 
                {$field_prefix}_updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$action, $comment, $user_id, $request_id]);

            // Check if all approved
            $stmt = $conn->prepare("SELECT * FROM tbl_admission_cancellations WHERE id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($req['account_status'] === 'approved' && $req['reception_status'] === 'approved' && $req['principal_status'] === 'approved') {
                // Finalize Cancellation
                $conn->prepare("UPDATE tbl_admission_cancellations SET final_status = 'cancelled' WHERE id = ?")->execute([$request_id]);
                $conn->prepare("UPDATE tbl_gm_std_registration SET status = 'cancelled' WHERE id = ?")->execute([$req['student_id']]);
                set_flash_message('success', 'Request approved and Admission finalized as Cancelled.');
            } else if ($action === 'rejected') {
                $conn->prepare("UPDATE tbl_admission_cancellations SET final_status = 'rejected' WHERE id = ?")->execute([$request_id]);
                set_flash_message('warning', 'Request has been rejected.');
            } else {
                set_flash_message('success', 'Approval recorded successfully.');
            }
        }
    }

    // Fetch All Requests with Search and Filter
    $base_sql = "FROM tbl_admission_cancellations c 
                 JOIN tbl_gm_std_registration s ON c.student_id = s.id 
                 JOIN tbl_users u ON c.requested_by = u.id 
                 WHERE 1=1";
    $params = [];

    if (!empty($search_filter)) {
        $base_sql .= " AND (s.student_name LIKE ? OR s.surname LIKE ? OR s.fathers_name LIKE ? OR s.id LIKE ?)";
        $search_param = "%$search_filter%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_filter;
    }

    if (!empty($status_filter)) {
        if ($status_filter === 'pending') {
            $base_sql .= " AND c.final_status IN ('initiated', 'pending')";
        } else {
            $base_sql .= " AND c.final_status = ?";
            $params[] = $status_filter;
        }
    }

    // Count total records for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) " . $base_sql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);

    // Fetch paginated requests
    $stmt = $conn->prepare("SELECT c.*, s.surname, s.student_name, s.fathers_name, 
                         CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as full_name,
                         u.name as initiator_name 
                         " . $base_sql . " 
                         ORDER BY c.created_at ASC 
                         LIMIT ? OFFSET ?");
    
    $params[] = (int)$perPage;
    $params[] = (int)$offset;
    
    // PDO doesn't like mixing positional params with LIMIT/OFFSET unless handled carefully
    // We'll bind them separately if needed, but since they are at the end, it should work if we pass them in execute
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Base URL for pagination links
    $baseUrl = 'cancellation-requests.php?search=' . urlencode($search_filter) . '&status=' . urlencode($status_filter);

} catch (Exception $e) {
    logError("Approvals Error: " . $e->getMessage());
    set_flash_message('error', 'An error occurred.');
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4 pb-5">
    <div class="mb-4 mt-2 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-check-double me-2 text-primary"></i> Cancellation Workflow</h4>
            <p class="text-muted small mb-0">Review and authorize student admission cancellation requests.</p>
        </div>
        <div>
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Student Name/ID..." value="<?php echo htmlspecialchars($search_filter ?? ''); ?>" style="width: 200px;">
                <select name="status" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Finalized</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                <?php if (!empty($search_filter) || !empty($status_filter)): ?>
                    <a href="cancellation-requests.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-redo"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="glass-card p-4">
        <h5 class="fw-bold mb-4">All Cancellation Requests</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Student & Reason</th>
                        <th>Initiated By</th>
                        <th>Department Reviews</th>
                        <th class="text-end">Final Status / Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">No pending requests found.</td>
                        </tr>
                    <?php else:
                        foreach ($requests as $req): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($req['full_name'] ?? ''); ?>
                                    </div>
                                    <small class="text-muted">ID:
                                        <?php echo $req['student_id']; ?>
                                    </small>
                                    <div class="mt-1 small text-primary"><i class="fas fa-info-circle me-1"></i>
                                        <?php echo htmlspecialchars($req['reason'] ?? ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-bold">
                                        <?php echo htmlspecialchars($req['initiator_name'] ?? ''); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($req['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <!-- Account Review -->
                                        <div class="review-item">
                                            <div class="badge <?php echo $req['account_status'] === 'approved' ? 'bg-success' : ($req['account_status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'); ?> w-100 text-start">
                                                <i class="fas fa-wallet me-2"></i> Account: <?php echo ucfirst($req['account_status']); ?>
                                            </div>
                                            <?php if (!empty($req['account_comment'])): ?>
                                                <div class="small text-muted mt-1 px-2 border-start ms-2"><?php echo htmlspecialchars($req['account_comment'] ?? ''); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Reception Review -->
                                        <div class="review-item">
                                            <div class="badge <?php echo $req['reception_status'] === 'approved' ? 'bg-success' : ($req['reception_status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'); ?> w-100 text-start">
                                                <i class="fas fa-concierge-bell me-2"></i> Reception: <?php echo ucfirst($req['reception_status']); ?>
                                            </div>
                                            <?php if (!empty($req['reception_comment'])): ?>
                                                <div class="small text-muted mt-1 px-2 border-start ms-2"><?php echo htmlspecialchars($req['reception_comment'] ?? ''); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Principal Review -->
                                        <div class="review-item">
                                            <div class="badge <?php echo $req['principal_status'] === 'approved' ? 'bg-success' : ($req['principal_status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'); ?> w-100 text-start">
                                                <i class="fas fa-user-shield me-2"></i> Principal: <?php echo ucfirst($req['principal_status']); ?>
                                            </div>
                                            <?php if (!empty($req['principal_comment'])): ?>
                                                <div class="small text-muted mt-1 px-2 border-start ms-2"><?php echo htmlspecialchars($req['principal_comment'] ?? ''); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-column align-items-end gap-2">
                                        <?php if ($req['final_status'] === 'cancelled'): ?>
                                            <span class="badge bg-success py-2 px-3"><i class="fas fa-check-circle me-1"></i> FINALIZED: CANCELLED</span>
                                        <?php elseif ($req['final_status'] === 'rejected'): ?>
                                            <span class="badge bg-danger py-2 px-3"><i class="fas fa-times-circle me-1"></i> FINAL REJECTED</span>
                                        <?php else: ?>
                                            <?php
                                            $can_approve = false;
                                            if (hasRole(ROLE_ACCOUNTANT) && $req['account_status'] === 'pending') $can_approve = true;
                                            if (hasRole(ROLE_RECEPTION) && $req['reception_status'] === 'pending') $can_approve = true;
                                            if (hasRole(ROLE_PRINCIPLE) && $req['principal_status'] === 'pending') $can_approve = true;
                                            if (hasRole(ROLE_SUPER_ADMIN)) $can_approve = true;

                                            if ($can_approve): ?>
                                                <button class="btn btn-sm btn-primary fw-bold"
                                                    onclick="openApprovalModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['full_name'] ?? ''); ?>')">
                                                    Review Request
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info py-2 px-3"><i class="fas fa-hourglass-half me-1"></i> PENDING OTHERS</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="mt-4 border-top pt-3">
                <?php echo renderPagination($page, $totalPages, $baseUrl, 2, $totalRecords, 'requests'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Review Cancellation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="cancellation-requests.php" method="POST">
                <input type="hidden" name="request_id" id="modal_request_id">
                <div class="modal-body p-4">
                    <p>Student: <strong id="modal_student_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Your Comment</label>
                        <textarea name="comment" class="form-control" rows="3" placeholder="Add remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-between">
                    <button type="submit" name="action" value="rejected"
                        class="btn btn-danger px-4 fw-bold">Reject</button>
                    <button type="submit" name="action" value="approved"
                        class="btn btn-success px-4 fw-bold">Approve</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApprovalModal(id, name) {
            document.getElementById('modal_request_id').value = id;
            document.getElementById('modal_student_name').innerText = name;
            var modal = new bootstrap.Modal(document.getElementById('approvalModal'));
            modal.show();
        }
    </script>

    <style>
        .welcome-banner {
            padding: 2.5rem;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            border-radius: 20px;
            color: white;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
        }

        .badge {
            border-radius: 4px;
            padding: 6px 10px;
            font-weight: 500;
        }
    </style>

    <?php include '../../include/footer.php'; ?>

