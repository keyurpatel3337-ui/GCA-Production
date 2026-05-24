<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$page_title = "My Group Change Requests";
$page_breadcrumb = "Change Requests";

// Get student details
$stmt = $conn->prepare("SELECT CONCAT(surname, ' ', student_name, ' ', fathers_name) as full_name, 
                        mob, group_id FROM tbl_gm_std_registration WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current group name
$current_group_name = 'N/A';
if ($student && $student['group_id']) {
    $stmt_group = $conn->prepare("SELECT group_name FROM tbl_group WHERE id = ?");
    $stmt_group->execute([$student['group_id']]);
    $group = $stmt_group->fetch(PDO::FETCH_ASSOC);
    $current_group_name = $group['group_name'] ?? 'N/A';
}

// Check if student has any pending request
$stmt_pending = $conn->prepare("SELECT COUNT(*) FROM tbl_group_change_requests 
                                WHERE student_id = ? AND status = 'pending'");
$stmt_pending->execute([$student_id]);
$has_pending_request = $stmt_pending->fetchColumn() > 0;

try {
    // Get all requests for this student
    $stmt = $conn->prepare("SELECT gcr.*,
                            cg.group_name as current_group_name,
                            rg.group_name as requested_group_name,
                            u.name as reviewed_by_name
                            FROM tbl_group_change_requests gcr
                            LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
                            LEFT JOIN tbl_group rg ON gcr.requested_group_id = rg.id
                            LEFT JOIN tbl_users u ON gcr.reviewed_by = u.id
                            WHERE gcr.student_id = ?
                            ORDER BY gcr.request_date ASC");
    $stmt->execute([$student_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("My Group Change Requests Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading your requests. Please try again.");
    $requests = [];
}

// Count statistics
$total_requests = count($requests);
$pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approved_count = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$rejected_count = count(array_filter($requests, fn($r) => $r['status'] === 'rejected'));
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/sidebar.php'; ?>
<?php include '../../include/navbar.php'; ?>

<style>
    /* Custom Styles for Group Change Requests */
    .stats-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .stats-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stats-icon.total {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-icon.pending {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-icon.approved {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-icon.rejected {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
    }

    .stats-label {
        color: #7f8c8d;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .request-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .request-card:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .request-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        padding: 1.25rem 1.5rem;
    }

    .request-number {
        font-size: 1.1rem;
        font-weight: 600;
    }

    .request-date {
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .group-change-visual {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: #f8f9fa;
    }

    .group-box {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        text-align: center;
        min-width: 140px;
    }

    .group-box.from {
        background: linear-gradient(135deg, #e0e5ec 0%, #d4d9e0 100%);
    }

    .group-box.to {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .group-box-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.8;
        margin-bottom: 0.25rem;
    }

    .group-box-name {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .arrow-icon {
        font-size: 2rem;
        color: #667eea;
        margin: 0 1.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.1);
            opacity: 0.7;
        }
    }

    .fee-impact {
        padding: 1rem 1.5rem;
        border-top: 1px solid #eee;
    }

    .fee-badge {
        font-size: 1.25rem;
        font-weight: 700;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }

    .fee-badge.increase {
        background: #ffebee;
        color: #c62828;
    }

    .fee-badge.decrease {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .fee-badge.no-change {
        background: #f5f5f5;
        color: #757575;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-badge.pending {
        background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        color: #e65100;
    }

    .status-badge.under_review {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        color: #00796b;
    }

    .status-badge.approved {
        background: linear-gradient(135deg, #c3fae8 0%, #96e6a1 100%);
        color: #1b5e20;
    }

    .status-badge.rejected {
        background: linear-gradient(135deg, #fecdd3 0%, #fda4af 100%);
        color: #b91c1c;
    }

    .status-badge.cancelled {
        background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        color: #475569;
    }

    .request-reason {
        background: #fafbfc;
        border-radius: 8px;
        padding: 1rem;
        margin: 1rem 1.5rem;
        border-left: 4px solid #667eea;
    }

    .request-reason-title {
        font-size: 0.8rem;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
    }

    .request-actions {
        padding: 1rem 1.5rem;
        background: #f8f9fa;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .btn-view {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 0.5rem 1.25rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .btn-cancel-request {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        border: none;
        color: white;
        padding: 0.5rem 1.25rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-cancel-request:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(238, 90, 82, 0.4);
        color: white;
    }

    .btn-new-request {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-new-request:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
        color: white;
    }

    .btn-new-request:disabled {
        background: #bdc3c7;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .empty-state-icon {
        font-size: 5rem;
        color: #dfe6e9;
        margin-bottom: 1.5rem;
    }

    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.75rem;
    }

    .empty-state-text {
        color: #7f8c8d;
        font-size: 1rem;
        margin-bottom: 1.5rem;
    }

    .review-info {
        padding: 0.75rem 1.5rem;
        background: #fff8e1;
        border-top: 1px solid #ffecb3;
        font-size: 0.9rem;
    }

    .current-group-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
</style>




<div class="container-fluid">

    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success'];
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error'];
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Current Group Banner -->
    <div class="current-group-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                    <div>
                        <div class="small opacity-75">Current Group</div>
                        <div class="h4 mb-0 fw-bold"><?php echo htmlspecialchars($current_group_name ?? ''); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if (!$has_pending_request): ?>
                    <a href="change-group-request.php" class="btn-new-request">
                        <i class="fas fa-plus-circle"></i> New Request
                    </a>
                <?php else: ?>
                    <button class="btn-new-request" disabled title="You already have a pending request">
                        <i class="fas fa-clock"></i> Request Pending
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon total text-white me-3">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $total_requests; ?></div>
                        <div class="stats-label">Total Requests</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon pending text-white me-3">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $pending_count; ?></div>
                        <div class="stats-label">Pending</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon approved text-white me-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $approved_count; ?></div>
                        <div class="stats-label">Approved</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stats-icon rejected text-white me-3">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $rejected_count; ?></div>
                        <div class="stats-label">Rejected</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests List -->
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-folder-open"></i>
            </div>
            <h3 class="empty-state-title">No Requests Yet</h3>
            <p class="empty-state-text">You haven't submitted any group change requests.<br>Click the button below to
                submit your first request.</p>
            <a href="change-group-request.php" class="btn-new-request">
                <i class="fas fa-plus-circle"></i> Submit New Request
            </a>
        </div>
    <?php else: ?>

        <?php foreach ($requests as $req): ?>
            <div class="request-card">
                <!-- Header -->
                <div class="request-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="request-number">
                            <i class="fas fa-hashtag me-1"></i>
                            <?php echo htmlspecialchars($req['request_number'] ?? 'REQ-' . $req['id']); ?>
                        </div>
                        <div class="request-date">
                            <i class="far fa-calendar-alt me-1"></i>
                            <?php echo date('d M Y, h:i A', strtotime($req['request_date'])); ?>
                        </div>
                    </div>
                    <div>
                        <?php
                        $status = $req['status'];
                        $status_icon = match ($status) {
                            'pending' => 'fas fa-hourglass-half',
                            'under_review' => 'fas fa-search',
                            'approved' => 'fas fa-check-circle',
                            'rejected' => 'fas fa-times-circle',
                            'cancelled' => 'fas fa-ban',
                            default => 'fas fa-question-circle'
                        };
                        ?>
                        <span class="status-badge <?php echo $status; ?>">
                            <i class="<?php echo $status_icon; ?>"></i>
                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                        </span>
                    </div>
                </div>

                <!-- Group Change Visual -->
                <div class="group-change-visual">
                    <div class="group-box from">
                        <div class="group-box-label">From</div>
                        <div class="group-box-name"><?php echo htmlspecialchars($req['current_group_name'] ?? ''); ?></div>
                    </div>
                    <div class="arrow-icon">
                        <i class="fas fa-long-arrow-alt-right"></i>
                    </div>
                    <div class="group-box to">
                        <div class="group-box-label">To</div>
                        <div class="group-box-name"><?php echo htmlspecialchars($req['requested_group_name'] ?? ''); ?></div>
                    </div>
                </div>

                <!-- Fee Impact -->
                <div class="fee-impact d-flex align-items-center justify-content-between">
                    <div>
                        <strong><i class="fas fa-rupee-sign me-1"></i> Fee Impact:</strong>
                    </div>
                    <div>
                        <?php
                        $diff = $req['fee_difference'] ?? 0;
                        if ($diff > 0) {
                            echo '<span class="fee-badge increase"><i class="fas fa-arrow-up me-1"></i>+₹' . formatIndianCurrency($diff, false) . '</span>';
                        } elseif ($diff < 0) {
                            echo '<span class="fee-badge decrease"><i class="fas fa-arrow-down me-1"></i>-₹' . formatIndianCurrency(abs($diff), false) . '</span>';
                        } else {
                            echo '<span class="fee-badge no-change"><i class="fas fa-equals me-1"></i>No Change</span>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Reason -->
                <?php if (!empty($req['reason'])): ?>
                    <div class="request-reason">
                        <div class="request-reason-title"><i class="fas fa-comment-alt me-1"></i> Reason</div>
                        <div><?php echo nl2br(htmlspecialchars($req['reason'] ?? '')); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Review Info (if reviewed) -->
                <?php if ($req['status'] === 'approved' || $req['status'] === 'rejected'): ?>
                    <div class="review-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-user-tie me-1"></i>
                                Reviewed by:
                                <strong><?php echo htmlspecialchars($req['reviewed_by_name'] ?? 'Principal'); ?></strong>
                            </div>
                            <?php if ($req['review_date']): ?>
                                <div class="text-muted">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('d M Y, h:i A', strtotime($req['review_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($req['review_remarks'])): ?>
                            <div class="mt-2 text-muted">
                                <i class="fas fa-quote-left me-1"></i>
                                <?php echo htmlspecialchars($req['review_remarks'] ?? ''); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="request-actions">
                    <button type="button" class="btn btn-view view-details" data-request-id="<?php echo $req['id']; ?>">
                        <i class="fas fa-eye me-1"></i> View Details
                    </button>
                    <?php if ($req['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-cancel-request cancel-request"
                            data-request-id="<?php echo $req['id']; ?>"
                            data-request-no="<?php echo htmlspecialchars($req['request_number'] ?? 'REQ-' . $req['id']); ?>">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

</div>

<!-- Request Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header"
                style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Request Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading request details...</p>
                </div>
            </div>
        </div>
    </div>



    <?php include '../../include/footer.php'; ?>

    <script>
        $(document).ready(function () {
            // Move modals to body to prevent z-index issues
            $('#detailsModal').appendTo("body");

            // View request details
            $('.view-details').on('click', function () {
                const requestId = $(this).data('request-id');
                $('#detailsModal').modal('show');

                // Reset content
                $('#requestDetailsContent').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading request details...</p>
                </div>
            `);

                $.ajax({
                    url: 'get-request-details.php',
                    method: 'POST',
                    data: {
                        request_id: requestId
                    },
                    success: function (response) {
                        $('#requestDetailsContent').html(response);
                    },
                    error: function () {
                        $('#requestDetailsContent').html(`
                        <div class="alert alert-danger m-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading request details. Please try again.
                        </div>
                    `);
                    }
                });
            });

            // Cancel request - show confirmation
            $('.cancel-request').on('click', function () {
                const requestId = $(this).data('request-id');
                const requestNo = $(this).data('request-no');

                showConfirm({
                    title: 'Cancel Request',
                    message: `Are you sure you want to cancel request <strong>${requestNo}</strong>? This action cannot be undone.`,
                    confirmText: 'Yes, Cancel It',
                    confirmButtonClass: 'btn-danger',
                    onConfirm: function () {
                        $.ajax({
                            url: 'cancel-group-change-request.php',
                            method: 'POST',
                            data: {
                                request_id: requestId
                            },
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    showToast('success', 'Cancelled!', 'Your request has been cancelled successfully.');
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    showToast('error', 'Error', response.message || 'Failed to cancel request.');
                                }
                            },
                            error: function () {
                                showToast('error', 'Error', 'An error occurred. Please try again.');
                            }
                        });
                    }
                });
            });
        });
    </script>