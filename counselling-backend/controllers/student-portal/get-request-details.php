<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(__DIR__)) . '/common/helpers/format_helper.php';

// Allow both student and principal access
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_principal = isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == ROLE_PRINCIPLE;

if (!$is_student && !$is_principal) {
    echo '<div class="alert alert-danger">Unauthorized</div>';
    exit;
}

$request_id = intval($_POST['request_id'] ?? 0);

// For students, validate they can only see their own requests
if ($is_student) {
    $student_id = $_SESSION['student_id'];
} else {
    $student_id = null; // Principal can see all requests
}

try {
    // Build query based on user type
    if ($is_student) {
        // Students can only see their own requests
        $stmt = $conn->prepare("SELECT gcr.*,
                                cg.group_name as current_group_name,
                                rg.group_name as requested_group_name,
                                u.name as reviewed_by_name,
                                s.scholarship_amount, s.additional_scholarship_amount,
                                s.course_id, s.medium_id
                                FROM tbl_group_change_requests gcr
                                LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
                                LEFT JOIN tbl_group rg ON gcr.requested_group_id = rg.id
                                LEFT JOIN tbl_users u ON gcr.reviewed_by = u.id
                                LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
                                WHERE gcr.id = ? AND gcr.student_id = ?");
        $stmt->execute([$request_id, $student_id]);
    } else {
        // Principal can see all requests
        $stmt = $conn->prepare("SELECT gcr.*,
                                cg.group_name as current_group_name,
                                rg.group_name as requested_group_name,
                                u.name as reviewed_by_name,
                                s.scholarship_amount, s.additional_scholarship_amount,
                                s.course_id, s.medium_id
                                FROM tbl_group_change_requests gcr
                                LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
                                LEFT JOIN tbl_group rg ON gcr.requested_group_id = rg.id
                                LEFT JOIN tbl_users u ON gcr.reviewed_by = u.id
                                LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
                                WHERE gcr.id = ?");
        $stmt->execute([$request_id]);
    }

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo '<div class="alert alert-danger">Request not found</div>';
        exit;
    }

    // Get student_id from request for fee calculations
    $student_id = $request['student_id'];

    // Calculate scholarship
    $total_scholarship = ($request['scholarship_amount'] ?? 0) + ($request['additional_scholarship_amount'] ?? 0);

    // Get current group fees
    $stmt = $conn->prepare("SELECT total_fees FROM tbl_fee_config 
                            WHERE course_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$request['course_id'], $request['medium_id'], $request['current_group_id']]);
    $current_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_total_fees = ($current_fee['total_fees'] ?? 0) - $total_scholarship;

    // Get new group fees
    $stmt->execute([$request['course_id'], $request['medium_id'], $request['requested_group_id']]);
    $new_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_total_fees = ($new_fee['total_fees'] ?? 0) - $total_scholarship;

    // Get fees already paid
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM tbl_payments WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $fees_already_paid = $payment_data['total_paid'] ?? 0;

    // Calculate differences
    $fee_difference = $new_total_fees - $current_total_fees;
    $adjusted_pending_amount = $new_total_fees - $fees_already_paid;

    // Get history
    $stmt = $conn->prepare("SELECT h.*, u.name as action_by_name
                            FROM tbl_group_change_history h
                            LEFT JOIN tbl_users u ON h.action_by = u.id
                            WHERE h.request_id = ?
                            ORDER BY h.created_at ASC");
    $stmt->execute([$request_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Get Request Details Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo '<div class="alert alert-danger">An error occurred while loading request details. Please try again.</div>';
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary">Request Information</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <th>Request No:</th>
                <td>REQ-<?php echo $request['id']; ?></td>
            </tr>
            <tr>
                <th>Date:</th>
                <td><?php echo date('d M Y, h:i A', strtotime($request['request_date'])); ?></td>
            </tr>
            <tr>
                <th>From Group:</th>
                <td><?php echo htmlspecialchars($request['current_group_name'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>To Group:</th>
                <td><?php echo htmlspecialchars($request['requested_group_name'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <?php
                    $badge_class = match ($request['status']) {
                        'pending' => 'warning',
                        'under_review' => 'info',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'secondary',
                        default => 'secondary'
                    };
                    ?>
                    <span class="badge bg-<?php echo $badge_class; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="text-primary">Fee Impact</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <th>Current Fees:</th>
                <td class="text-end">₹<?php echo formatIndianCurrency($current_total_fees); ?></td>
            </tr>
            <tr>
                <th>New Fees:</th>
                <td class="text-end">₹<?php echo formatIndianCurrency($new_total_fees); ?></td>
            </tr>
            <tr>
                <th>Already Paid:</th>
                <td class="text-end">₹<?php echo formatIndianCurrency($fees_already_paid); ?></td>
            </tr>
            <tr>
                <th>Difference:</th>
                <td class="text-end">
                    <?php
                    if ($fee_difference > 0) {
                        echo '<span class="text-danger">+₹' . formatIndianCurrency($fee_difference) . '</span>';
                    } elseif ($fee_difference < 0) {
                        echo '<span class="text-success">-₹' . formatIndianCurrency(abs($fee_difference)) . '</span>';
                    } else {
                        echo '<span class="text-muted">₹0.00</span>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th>New Pending:</th>
                <td class="text-end">
                    <strong>₹<?php echo formatIndianCurrency($adjusted_pending_amount); ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <h6 class="text-primary">Reason for Change</h6>
        <div class="alert alert-light">
            <?php echo nl2br(htmlspecialchars($request['reason'] ?? '')); ?>
        </div>
    </div>
</div>

<?php if ($request['review_comments']): ?>
    <div class="row mt-3">
        <div class="col-md-12">
            <h6 class="text-primary">Review Remarks</h6>
            <div class="alert alert-<?php echo $request['status'] === 'approved' ? 'success' : 'danger'; ?>">
                <strong>Reviewed by:</strong> <?php echo htmlspecialchars($request['reviewed_by_name'] ?? ''); ?><br>
                <strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($request['review_date'])); ?><br>
                <strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($request['review_comments'] ?? '')); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($history)): ?>
    <div class="row mt-3">
        <div class="col-md-12">
            <h6 class="text-primary">Request History</h6>
            <div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></small>
                        <p class="mb-0">
                            <strong><?php echo ucwords(str_replace('_', ' ', $h['action_type'])); ?></strong>
                            <?php if ($h['action_by_name']): ?>
                                by <?php echo htmlspecialchars($h['action_by_name'] ?? ''); ?>
                            <?php endif; ?>
                            <?php if ($h['remarks']): ?>
                                <br><small><?php echo htmlspecialchars($h['remarks'] ?? ''); ?></small>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
