<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Accountant, Principal or Super Admin
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Merge GET and POST, POST takes priority for pagination support
// Initialize variables with defaults
$requests = [];
$counts = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$status_filter = $_POST['status'] ?? $_GET['status'] ?? 'all';
$search = $_POST['search'] ?? $_GET['search'] ?? '';

$api = new APIClient();
$response = $api->get('payments/installment-requests', array_merge($_GET, $_POST));

if ($response && isset($response['success']) && $response['success']) {
    $requests = $response['data']['requests'] ?? [];
    $counts = $response['data']['counts'] ?? ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    $status_filter = $response['data']['applied_filters']['status'] ?? $status_filter;
    $search = $response['data']['applied_filters']['search'] ?? $search;
} else {
    set_flash_message('error', $response['error'] ?? 'Failed to load installment requests');
}

$page_title = "Installment Requests";
$page_breadcrumb = "Installment Requests";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">

    <!-- Filter and Search -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All
                            (<?php echo $counts['total']; ?>)</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending
                            (<?php echo $counts['pending']; ?>)</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>
                            Approved (<?php echo $counts['approved']; ?>)</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>
                            Rejected (<?php echo $counts['rejected']; ?>)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                        placeholder="Search by request no, student name, aadhaar..."
                        value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <a href="installment-requests.php" class="btn btn-secondary"><i class="fas fa-redo"></i>
                            Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Installment Requests</h3>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No installment requests found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th width="8%">Request No</th>
                                <th width="15%">Student Details</th>
                                <th width="10%">Standard</th>
                                <th width="12%">Fee Component</th>
                                <th width="8%">Amount</th>
                                <th width="5%">Installments</th>
                                <th width="15%">Reason</th>
                                <th width="8%">Status</th>
                                <th width="10%">Requested On</th>
                                <th width="9%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['request_no'] ?? 'N/A'); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['student_full_name'] ?? 'N/A'); ?></strong><br>
                                        <small class="text-muted">
                                            Aadhaar: <?php echo htmlspecialchars($req['aadhaar'] ?? 'N/A'); ?><br>
                                            Mobile: <?php echo htmlspecialchars($req['mob'] ?? 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['course_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $component_names = [
                                            'school_fee' => 'School Fee',
                                            'trust_facilities_fee' => 'Trust Facilities Fee',
                                            'tuition_fee_part2' => 'Tuition Fee Part 2'
                                        ];
                                        echo $component_names[$req['fee_component']] ?? $req['fee_component'];
                                        ?>
                                    </td>
                                    <td>₹<?php echo formatIndianCurrency($req['total_amount']); ?></td>
                                    <td class="text-center"><?php echo $req['requested_installments']; ?></td>
                                    <td><small><?php echo htmlspecialchars($req['reason'] ?? ''); ?></small></td>
                                    <td>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($req['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                        <br>
                                        <?php
                                        $request_type = $req['request_type'] ?? 'student';
                                        $type_badges = [
                                            'student' => '<span class="badge bg-secondary"><i class="fas fa-user"></i> Student</span>',
                                            'counsellor' => '<span class="badge bg-info"><i class="fas fa-user-tie"></i> Counsellor</span>',
                                            'direct' => '<span class="badge bg-primary"><i class="fas fa-bolt"></i> Direct</span>'
                                        ];
                                        echo $type_badges[$request_type] ?? $type_badges['student'];
                                        ?>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <button class="btn btn-success btn-sm"
                                                onclick="reviewRequest(<?php echo $req['id']; ?>, 'approve')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                onclick="reviewRequest(<?php echo $req['id']; ?>, 'reject')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Reviewed by: <?php echo htmlspecialchars($req['reviewed_by_name'] ?? 'N/A'); ?>
                                                <?php if ($req['reviewed_at']): ?>
                                                    <br><small><?php echo date('d-M-Y', strtotime($req['reviewed_at'])); ?></small>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Installment Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" id="request_id" name="request_id">
                    <input type="hidden" id="action" name="action">

                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <input type="text" class="form-control" id="action_display" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"
                            placeholder="Enter remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        function reviewRequest(requestId, action) {
            $('#request_id').val(requestId);
            $('#action').val(action);
            $('#action_display').val(action === 'approve' ? 'Approve' : 'Reject');
            $('#reviewModal').modal('show');
        }

        $(document).ready(function () {
            // Move modal to body to prevent z-index issues
            $('#reviewModal').appendTo("body");

            $('#reviewForm').on('submit', function (e) {
                e.preventDefault();

                $.api.post('payments/installment-requests', $(this).serialize()).then(response => {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                }).catch(error => {
                    alert('An error occurred. Please try again.');
                });
            });
        });
    </script>

    </body>

    </html>