<?php
/**
 * My Installment Requests - Counsellor View
 * Displays installment requests created by the current counsellor
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../common/security_output.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is a counsellor
if (!hasRole(ROLE_COUNSELLOR)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "My Installment Requests";
$page_breadcrumb = "Installment Requests";
$counsellor_id = $_SESSION['user_id'];

// Fetch requests created by this counsellor
$requests = $dbOps->customSelect(
    "SELECT ir.*, 
            CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as student_full_name,
            s.aadhaar, s.mob,
            c.course_name,
            u.name as reviewed_by_name
     FROM tbl_installment_requests ir
     JOIN tbl_gm_std_registration s ON ir.student_id = s.id
     LEFT JOIN tbl_courses c ON s.course_id = c.id
     LEFT JOIN tbl_users u ON ir.reviewed_by = u.id
     WHERE s.counsellor_id = ? AND ir.request_type = 'counsellor'
     ORDER BY ir.created_at DESC",
    [$counsellor_id]
);

// Fee component names
$component_names = [
    'school_fee' => 'School Fee',
    'trust_facilities_fee' => 'Trust Facilities Fee',
    'tuition_fee_part2' => 'Tuition Fee Part 2'
];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> You haven't created any installment requests yet.
            <a href="create-request-for-student.php" class="alert-link">Create your first request</a>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Your Installment Requests</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th width="10%">Request No</th>
                                <th width="18%">Student</th>
                                <th width="12%">Fee Component</th>
                                <th width="10%">Amount</th>
                                <th width="8%">Installments</th>
                                <th width="12%">Reason</th>
                                <th width="10%">Status</th>
                                <th width="10%">Requested On</th>
                                <th width="10%">Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($req['request_no'] ?? ''); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($req['student_full_name'] ?? ''); ?><br>
                                        <small class="text-muted">Mob: <?php echo htmlspecialchars($req['mob'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo $component_names[$req['fee_component']] ?? $req['fee_component']; ?></td>
                                    <td><strong>₹<?php echo formatIndianCurrency($req['total_amount']); ?></strong></td>
                                    <td class="text-center"><?php echo $req['requested_installments']; ?></td>
                                    <td><small><?php echo htmlspecialchars($req['reason'] ?? ''); ?></small></td>
                                    <td>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif ($req['status'] === 'approved'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Approved
                                            </span>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle"></i> Rejected
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <?php if ($req['reviewed_at']): ?>
                                            <small>
                                                By: <?php echo htmlspecialchars($req['reviewed_by_name'] ?? 'N/A'); ?><br>
                                                <?php echo date('d-M-Y', strtotime($req['reviewed_at'])); ?>
                                            </small>
                                            <?php if ($req['review_remarks']): ?>
                                                <br><small
                                                    class="text-muted"><?php echo htmlspecialchars($req['review_remarks'] ?? ''); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include '../../include/footer.php'; ?>

</body>

</html>

