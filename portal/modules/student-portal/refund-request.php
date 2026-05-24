<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Tighten access: Only parents are allowed to request refunds (fees/wallet-related)
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if ($is_student) {
    $_SESSION['error'] = 'Access Denied: Fees and Wallet are managed exclusively by Parents.';
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

if (!$is_parent) {
    header('Location: ../../parent-login.php');
    exit;
}

$student_id = $is_parent ? ($_SESSION['active_student_id'] ?? null) : ($_SESSION['student_id'] ?? null);

if (!$student_id) {
    $_SESSION['error'] = "Invalid session. Please login again.";
    header('Location: ' . ($is_parent ? '../parent-portal/dashboard.php' : 'student-login.php'));
    exit;
}

// Error message handling
$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

// Fetch student details
try {
    $op = new Operation();

    $student = $op->readWithJoin(
        'tbl_gm_std_registration s',
        ['s.*', 'c.course_name', 'b.batch_name', 'm.medium_name', 'g.group_name'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
            ['type' => 'LEFT', 'table' => 'tbl_batches b', 'on' => 's.batch_id = b.id'],
            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id']
        ],
        ['s.id' => $student_id]
    );
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Student Details for Refund");
}

// Fetch refund requests
try {
    $requests = $op->readAll('tbl_refund_requests', ['student_id' => $student_id], 'created_at DESC');
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Refund Requests");
    $requests = [];
}

$page_title = "Refund Request";
$page_breadcrumb = "Refund -";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_msg ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline card-tabs">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" id="refundTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="new-request-tab" data-bs-toggle="pill" href="#new-request"
                                role="tab">
                                <i class="fas fa-plus-circle me-1"></i> New Refund Request
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="request-history-tab" data-bs-toggle="pill" href="#request-history"
                                role="tab">
                                <i class="fas fa-history me-1"></i> Request History
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="refundTabContent">
                        <!-- New Request Tab -->
                        <div class="tab-pane fade show active" id="new-request" role="tabpanel">
                            <div class="row">
                                <div class="col-md-7">
                                    <form action="refund-request-save.php" method="POST" id="refundForm"
                                        enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Select Fee Component <span
                                                    class="text-danger">*</span></label>
                                            <select name="fee_component" class="form-select" required>
                                                <option value="">-- Select Component --</option>
                                                <option value="token_fee">Token Fee</option>
                                                <option value="school_fee">School Fee</option>
                                                <option value="trust_facilities_fee">Trust Facilities Fee</option>
                                                <option value="tuition_fee_part2">Tuition Fee Part 2</option>
                                            </select>
                                            <div class="form-text">Select the fee component you are requesting a
                                                refund for.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Reason for Refund <span
                                                    class="text-danger">*</span></label>
                                            <textarea name="reason" class="form-control" rows="4"
                                                placeholder="Please provide a detailed reason for your refund request..."
                                                required></textarea>
                                        </div>

                                        <div class="row g-3 mb-4">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Account Holder Name <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="account_holder" class="form-control"
                                                    placeholder="As per bank records" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Bank Name <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="bank_name" class="form-control"
                                                    placeholder="e.g. SBI, HDFC" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Account Number <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="account_number" class="form-control"
                                                    placeholder="Enter full account number" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">IFSC Code <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="ifsc_code" class="form-control"
                                                    placeholder="11-digit IFSC code" required maxlength="11"
                                                    onkeyup="this.value = this.value.toUpperCase()">
                                            </div>
                                        </div>

                                        <div class="mb-4 text-center border p-3 rounded bg-light">
                                            <h6 class="fw-bold mb-3"><i class="fas fa-file-upload me-2"></i>Upload
                                                Bank Proof</h6>
                                            <div class="d-flex justify-content-center">
                                                <div class="upload-btn-wrapper">
                                                    <button type="button" class="btn btn-outline-primary"
                                                        onclick="document.getElementById('proof_file').click()">
                                                        <i class="fas fa-cloud-upload-alt me-2"></i> Choose
                                                        Passbook/Cheque Image
                                                    </button>
                                                    <input type="file" name="proof_file" id="proof_file"
                                                        accept="image/*,.pdf" style="display:none"
                                                        onchange="updateFileName(this)">
                                                </div>
                                            </div>
                                            <div id="file-name" class="mt-2 text-muted small">No file chosen</div>
                                            <div class="form-text mt-1 text-danger small">* Image of cancelled
                                                cheque or first page of bank passbook is mandatory.</div>
                                        </div>

                                        <div class="d-grid shadow-sm">
                                            <button type="submit" class="btn btn-primary btn-lg shadow-sm py-3 fw-bold">
                                                <i class="fas fa-paper-plane me-2"></i> Submit Refund Request
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <div class="col-md-5">
                                    <div class="alert alert-info border-primary">
                                        <h5 class="alert-heading fw-bold"><i class="fas fa-info-circle me-2"></i>Refund
                                            Policy</h5>
                                        <hr>
                                        <ul class="mb-0 ps-3">
                                            <li class="mb-2">Refund requests are processed within 15-20 working days
                                                after approval.</li>
                                            <li class="mb-2">The management's decision on refund eligibility will be
                                                final.</li>
                                            <li class="mb-2">Ensure all bank details are accurate to avoid
                                                transaction failure.</li>
                                            <li class="mb-2">Refund will be processed to the original payment source
                                                where applicable.</li>
                                            <li class="mb-0">Deductions may apply as per the institution's
                                                cancellation policy.</li>
                                        </ul>
                                    </div>

                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-light fw-bold">
                                            <i class="fas fa-user-check me-2 text-primary"></i>Verification Details
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between border-bottom py-2">
                                                <span class="text-muted">Aadhaar:</span>
                                                <span
                                                    class="fw-bold"><?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between border-bottom py-2">
                                                <span class="text-muted">Mobile:</span>
                                                <span
                                                    class="fw-bold"><?php echo htmlspecialchars($student['mob'] ?? ''); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between py-2">
                                                <span class="text-muted">Course:</span>
                                                <span
                                                    class="fw-bold"><?php echo htmlspecialchars($student['course_name'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History Tab -->
                        <div class="tab-pane fade" id="request-history" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Req ID</th>
                                            <th>Fee Component</th>
                                            <th>Amount Applied</th>
                                            <th>Requested On</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($requests)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <i class="fas fa-folder-open f-3x text-muted mb-3 d-block"></i>
                                                    <span class="text-muted">No refund requests found.</span>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($requests as $request): ?>
                                                <tr>
                                                    <td>#<?php echo $request['id']; ?></td>
                                                    <td>
                                                        <span
                                                            class="fw-bold"><?php echo ucwords(str_replace('_', ' ', $request['fee_component'])); ?></span>
                                                    </td>
                                                    <td>₹<?php echo formatIndianCurrency($request['amount'] ?? 0); ?></td>
                                                    <td><?php echo date('d M Y, h:i A', strtotime($request['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'bg-secondary';
                                                        if ($request['status'] == 'approved')
                                                            $badge_class = 'bg-success';
                                                        elseif ($request['status'] == 'pending')
                                                            $badge_class = 'bg-warning text-dark';
                                                        elseif ($request['status'] == 'rejected')
                                                            $badge_class = 'bg-danger';
                                                        elseif ($request['status'] == 'processed')
                                                            $badge_class = 'bg-info text-white';
                                                        ?>
                                                        <span
                                                            class="badge <?php echo $badge_class; ?> px-3 py-2 text-uppercase">
                                                            <?php echo $request['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                            onclick="viewDetails(<?php echo htmlspecialchars(json_encode($request) ?? ''); ?>)">
                                                            <i class="fas fa-eye me-1"></i> Details
                                                        </button>
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
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal for details -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content overflow-hidden">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2 text-white"></i>Request Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="modal-content">
                <!-- Content will be injected by JS -->
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            if (input.files.length > 0) {
                var fileName = input.files[0].name;
                document.getElementById('file-name').innerHTML = '<i class="fas fa-file-image me-1 text-primary"></i>' + fileName;
            }
        }

        function viewDetails(request) {
            const content = `
            <div class="list-group list-group-flush">
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-5 text-muted">Request ID:</div>
                        <div class="col-7 fw-bold">#${request.id}</div>
                    </div>
                </div>
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-5 text-muted">Fee Component:</div>
                        <div class="col-7 fw-bold">${request.fee_component.replace('_', ' ').toUpperCase()}</div>
                    </div>
                </div>
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-5 text-muted">Reason:</div>
                        <div class="col-7">${request.reason}</div>
                    </div>
                </div>
                <div class="list-group-item bg-light">
                    <h6 class="fw-bold mb-2">Bank Details</h6>
                    <div class="row mb-1">
                        <div class="col-5 text-muted small">Account Holder:</div>
                        <div class="col-7 small fw-bold">${request.account_holder}</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-5 text-muted small">Bank Name:</div>
                        <div class="col-7 small">${request.bank_name}</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-5 text-muted small">Account No:</div>
                        <div class="col-7 small fw-bold">${request.account_number}</div>
                    </div>
                    <div class="row">
                        <div class="col-5 text-muted small">IFSC Code:</div>
                        <div class="col-7 small">${request.ifsc_code}</div>
                    </div>
                </div>
                ${request.admin_remark ? `
                <div class="list-group-item bg-light-warning">
                    <div class="row">
                        <div class="col-5 text-muted fw-bold">Admin Remark:</div>
                        <div class="col-7 text-danger fw-bold">${request.admin_remark}</div>
                    </div>
                </div>
                ` : ''}
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-5 text-muted">Applied On:</div>
                        <div class="col-7">${new Date(request.created_at).toLocaleString()}</div>
                    </div>
                </div>
            </div>
        `;
            document.getElementById('modal-content').innerHTML = content;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
    </script>

    <?php include '../../include/footer.php'; ?>