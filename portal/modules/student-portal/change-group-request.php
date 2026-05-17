<?php
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
$page_title = "Change Group Request";
$page_breadcrumb = "Request -";

try {
    // Get student details with current group
    $stmt = $conn->prepare("SELECT s.*, g.group_name, c.course_name, e.enrollment_id, e.school_id,
                            b.board_name, m.medium_name
                            FROM tbl_gm_std_registration s
                            LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id
                            LEFT JOIN tbl_group g ON s.group_id = g.id
                            LEFT JOIN tbl_courses c ON s.course_id = c.id
                            LEFT JOIN tbl_boards b ON s.board_id = b.id
                            LEFT JOIN tbl_medium m ON s.medium_id = m.id
                            WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['enrollment_id']) {
        set_flash_message('error', "Student enrollment not found!");
        header('Location: dashboard.php');
        exit;
    }

    // Calculate total scholarship
    $total_scholarship = ($student['scholarship_amount'] ?? 0) + ($student['additional_scholarship_amount'] ?? 0);

    // Check if there's already a pending request
    $stmt = $conn->prepare("SELECT id, status FROM tbl_group_change_requests 
                            WHERE student_id = ? AND status IN ('pending', 'under_review')");
    $stmt->execute([$student_id]);
    $pending_request = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get available groups
    $stmt = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current fee details
    $stmt = $conn->prepare("SELECT sfa.*, fc.total_fees, fc.course_name, fc.academic_year
                            FROM tbl_student_fee_allocation sfa
                            LEFT JOIN tbl_fee_config fc ON sfa.fee_config_id = fc.id
                            WHERE sfa.student_id = ?");
    $stmt->execute([$student['enrollment_id']]);
    $current_fees = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate paid amount (including token fee)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                            FROM tbl_payments 
                            WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $fees_paid = $payment_data['total_paid'] ?? 0;
} catch (PDOException $e) {
    logError("Change Group Request Page Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading the page. Please try again.");
    $student = null;
    $pending_request = null;
    $groups = [];
    $current_fees = null;
    $fees_paid = 0;
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<?php if ($pending_request): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-info-circle"></i> Pending Request</h5>
        <p>You already have a <?php echo $pending_request['status']; ?> request
            (Request No: <strong>REQ-<?php echo $pending_request['id']; ?></strong>).</p>
        <p>Please wait for it to be reviewed before submitting a new request.</p>
        <a href="my-group-change-requests.php" class="btn btn-sm btn-primary">
            <i class="fas fa-list"></i> View My Requests
        </a>
    </div>
<?php else: ?>
    <!-- Current Details -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Current Details</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong>
                    <?php echo strtoupper(htmlspecialchars($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name'] ?? '')); ?>
                </p>
                <p><strong>Board:</strong> <?php echo htmlspecialchars($student['board_name'] ?? ''); ?></p>
                <p><strong>Medium:</strong> <?php echo htmlspecialchars($student['medium_name'] ?? ''); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Current Group:</strong> <span
                        class="badge bg-info text-dark"><?php echo htmlspecialchars($student['group_name'] ?? ''); ?></span></p>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></p>
                <p><strong>Standard:</strong> <?php echo htmlspecialchars($student['standard'] ?? ''); ?></p>
            </div>
        </div>

        <?php if ($current_fees): ?>
            <hr>
            <h6 class="text-primary">Current Fee Structure</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <th>Total Fees</th>
                    <td>₹<?php echo formatIndianCurrency($current_fees['total_fees']); ?></td>
                </tr>
                <?php if ($total_scholarship > 0): ?>
                    <tr class="table-info">
                        <th><i class="fas fa-award text-warning"></i> Scholarship Discount</th>
                        <td class="text-success">-₹<?php echo formatIndianCurrency($total_scholarship); ?></td>
                    </tr>
                    <tr>
                        <th>Net Fees (After Scholarship)</th>
                        <td><strong>₹<?php echo formatIndianCurrency($current_fees['total_fees'] - $total_scholarship); ?></strong>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Already Paid</th>
                    <td>₹<?php echo formatIndianCurrency($fees_paid); ?></td>
                </tr>
                <tr>
                    <th>Pending</th>
                    <td>₹<?php echo formatIndianCurrency(($current_fees['total_fees'] - $total_scholarship) - $fees_paid); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
    </div>

    <!-- Request Form -->
    <form id="groupChangeForm">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
        <input type="hidden" name="current_group_id" value="<?php echo $student['group_id']; ?>">
        <input type="hidden" name="current_course_id" value="<?php echo $student['course_id']; ?>">
        <input type="hidden" name="current_fee_config_id" value="<?php echo $current_fees['fee_config_id'] ?? ''; ?>">
        <input type="hidden" name="fees_already_paid" value="<?php echo $fees_paid; ?>">
        <input type="hidden" name="scholarship_amount" value="<?php echo $total_scholarship; ?>">

        <div class="form-group">
            <label for="requested_group_id">
                Select New Group <span class="text-danger">*</span>
            </label>
            <select name="requested_group_id" id="requested_group_id" class="form-control" required>
                <option value="">-- Select Group --</option>
                <?php foreach ($groups as $group): ?>
                    <?php if ($group['id'] != $student['group_id']): ?>
                        <option value="<?php echo $group['id']; ?>">
                            <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Fee Difference Preview -->
        <div id="fee_preview" class="alert alert-info" style="display: none;">
            <h6><i class="fas fa-calculator"></i> Fee Impact Preview</h6>
            <div id="fee_preview_content"></div>
        </div>

        <div class="form-group">
            <label for="reason">
                Reason for Group Change <span class="text-danger">*</span>
            </label>
            <textarea name="reason" id="reason" class="form-control" rows="4" required
                placeholder="Please provide a detailed reason for requesting group change..."></textarea>
            <small class="text-muted">Minimum 50 characters required</small>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="confirm" id="confirm" class="form-check-input" required>
            <label class="form-check-label" for="confirm">
                I confirm that the information provided is accurate and I understand that this request will be reviewed by
                the Principal.
            </label>
        </div>
    </form>
<?php endif; ?>
</div>
<div class="card-footer">
    <button type="submit" form="groupChangeForm" class="btn btn-primary">
        <i class="fas fa-paper-plane"></i> Submit Request
    </button>
    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
</div>
</div>
</div>

<div class="col-md-4">
    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title">Important Information</h3>
        </div>
        <div class="card-body">
            <?php if ($total_scholarship > 0): ?>
                <div class="alert alert-warning mb-3">
                    <h6><i class="fas fa-award"></i> Scholarship Information</h6>
                    <p class="mb-1"><strong>Your Scholarship:</strong>
                        ₹<?php echo formatIndianCurrency($total_scholarship); ?>
                    </p>
                    <?php if ($student['scholarship_percentage'] > 0): ?>
                        <p class="mb-1"><small>(<?php echo formatIndianCurrency($student['scholarship_percentage']); ?>%
                                discount)</small></p>
                    <?php endif; ?>
                    <?php if (!empty($student['additional_scholarship_remarks'])): ?>
                        <p class="mb-0">
                            <small><em><?php echo htmlspecialchars($student['additional_scholarship_remarks'] ?? ''); ?></em></small>
                        </p>
                    <?php endif; ?>
                    <hr class="my-2">
                    <p class="mb-0"><small class="text-muted">Your scholarship will be applied to the new group's fees
                            automatically.</small></p>
                </div>
            <?php endif; ?>
            <ul class="list-unstyled">
                <li><i class="fas fa-check text-success"></i> Group change requests are subject to Principal approval
                </li>
                <li><i class="fas fa-check text-success"></i> You will receive confirmation within 48 hours</li>
                <li><i class="fas fa-check text-success"></i> Fee adjustments will be calculated automatically</li>
                <?php if ($total_scholarship > 0): ?>
                    <li><i class="fas fa-check text-success"></i> Your scholarship will remain active in the new group</li>
                <?php endif; ?>
                <li><i class="fas fa-check text-success"></i> Only one pending request is allowed at a time</li>
            </ul>
        </div>
    </div>

    <!-- Previous Requests -->
    <?php
    $stmt = $conn->prepare("SELECT * FROM tbl_group_change_requests 
                                            WHERE student_id = ? 
                                            ORDER BY request_date ASC LIMIT 5");
    $stmt->execute([$student_id]);
    $previous_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($previous_requests)):
        ?>
        <div class="card card-secondary">
            <div class="card-header">
                <h3 class="card-title">Previous Requests</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previous_requests as $req): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($req['request_date'])); ?></td>
                                <td>
                                    <?php
                                    $badge_class = match ($req['status']) {
                                        'pending' => 'warning',
                                        'under_review' => 'info',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'secondary',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($req['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="my-group-change-requests.php" class="btn btn-sm btn-primary">
                    View All Requests
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</div>

</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Fetch fee difference preview when group is selected
        $('#requested_group_id').on('change', function () {
            const groupId = $(this).val();
            if (groupId) {
                $.ajax({
                    url: 'get-fee-difference-preview.php',
                    method: 'POST',
                    data: {
                        student_id: <?php echo $student_id; ?>,
                        current_group_id: <?php echo $student['group_id']; ?>,
                        requested_group_id: groupId,
                        fees_already_paid: <?php echo $fees_paid; ?>
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#fee_preview_content').html(response.html);
                            $('#fee_preview').show();
                        }
                    }
                });
            } else {
                $('#fee_preview').hide();
            }
        });

        // Validate reason length
        $('#reason').on('input', function () {
            const length = $(this).val().length;
            if (length < 50) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        // Form submission validation
        // Form submission validation
        $('#groupChangeForm').on('submit', function (e) {
            e.preventDefault();

            const reason = $('#reason').val();
            if (reason.length < 50) {
                showToast('warning', 'Warning', 'Please provide at least 50 characters for the reason');
                return false;
            }

            const groupName = $('#requested_group_id option:selected').text().trim();

            showConfirm({
                title: 'Confirm Request',
                message: 'Are you sure you want to request change to ' + groupName + '? This request will be sent to the Principal for review.',
                confirmText: 'Yes, Submit Request',
                confirmButtonClass: 'btn-primary',
                onConfirm: function () {
                    $.api.post('student-portal/change-group-request-save', $('#groupChangeForm').serialize())
                        .then(response => {
                            if (response.success) {
                                showToast('success', 'Success!', response.message);
                                setTimeout(() => {
                                    window.location.href = 'my-group-change-requests.php';
                                }, 1500);
                            } else {
                                showToast('error', 'Error!', response.error || response.message);
                            }
                        }).catch(error => showToast('error', 'Error!', error.message || 'Failed to submit request'));
                }
            });
        });
    });
</script>