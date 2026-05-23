<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Tighten access: Only parents are allowed to manage installments
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

$student_id = $_SESSION['active_student_id'] ?? $_SESSION['student_id'];

$page_title = "My Installment Requests";
$page_breadcrumb = "Installment Requests";

// Fetch student's installment requests
$requests = $dbOps->customSelect("SELECT ir.*, 
                       c.course_name
                       FROM tbl_installment_requests ir
                       LEFT JOIN tbl_gm_std_registration s ON ir.student_id = s.id
                       LEFT JOIN tbl_courses c ON s.course_id = c.id
                       WHERE ir.student_id = ?
                       ORDER BY ir.created_at ASC", [$student_id]);

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> You haven't submitted any installment requests yet.
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
                                <th width="12%">Request No</th>
                                <th width="15%">Fee Component</th>
                                <th width="10%">Amount</th>
                                <th width="8%">Installments</th>
                                <th width="20%">Reason</th>
                                <th width="10%">Status</th>
                                <th width="12%">Requested On</th>
                                <th width="13%">Reviewed On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($req['request_no'] ?? ''); ?></strong></td>
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
                                    <td><strong>₹<?php echo formatIndianCurrency($req['total_amount']); ?></strong></td>
                                    <td class="text-center"><?php echo $req['requested_installments']; ?></td>
                                    <td><small><?php echo htmlspecialchars($req['reason'] ?? ''); ?></small></td>
                                    <td>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock"></i> Pending Review
                                            </span>
                                        <?php elseif ($req['status'] === 'approved'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Approved
                                            </span>
                                            <?php if ($req['review_remarks']): ?>
                                                <br><small
                                                    class="text-muted"><?php echo htmlspecialchars($req['review_remarks'] ?? ''); ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle"></i> Rejected
                                            </span>
                                            <?php if ($req['review_remarks']): ?>
                                                <br><small
                                                    class="text-danger"><?php echo htmlspecialchars($req['review_remarks'] ?? ''); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d-M-Y H:i', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <?php if ($req['reviewed_at']): ?>
                                            <?php echo date('d-M-Y H:i', strtotime($req['reviewed_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not reviewed yet</span>
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