// Check access - Super Admin, Principal, or Counsellor
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
header('Location: ' . BASE_URL . '/index.php');
exit;
}

$page_title = "Review Division Change Request" ;
$user_id = $_SESSION['user_id'];

// Get request ID
if (!isset($_POST['id'])) {
set_flash_message('error', "Request ID is required.");
header('Location: pending-division-requests.php');
exit;
}

$request_id = $_POST['id'];

// Fetch request details
try {
$op = new Operation();

$request = $op->readWithJoin(
'tbl_division_change_requests dcr',
[
'dcr.*',
'r.student_name',
'r.surname',
'r.mob',
'r.gender',
'r.aadhaar',
'e.enrollment_no',
'e.enrollment_id',
'r.course_id',
'r.group_id',
'e.roll_no as current_enrollment_roll',
'c.course_name',
'g.group_name',
'd_current.division_name as current_division_name',
'd_requested.division_name as requested_division_name',
'uc.name as counsellor_name',
'ur.name as reviewed_by_name'
],
[
['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 'dcr.enrollment_id = e.enrollment_id'],
['type' => 'LEFT', 'table' => 'tbl_gm_std_registration r', 'on' => 'e.registration_id = r.id'],
['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 'r.course_id = c.id'],
['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 'r.group_id = g.id'],
['type' => 'LEFT', 'table' => 'tbl_division d_current', 'on' => 'dcr.current_division_id = d_current.id'],
['type' => 'LEFT', 'table' => 'tbl_division d_requested', 'on' => 'dcr.requested_division_id = d_requested.id'],
['type' => 'LEFT', 'table' => 'tbl_users uc', 'on' => 'dcr.counsellor_id = uc.id'],
['type' => 'LEFT', 'table' => 'tbl_users ur', 'on' => 'dcr.reviewed_by = ur.id']
],
['dcr.id' => $request_id]
);
$request = is_array($request) && !isset($request[0]) ? $request : (isset($request[0]) ? $request[0] : null);

if (!$request) {
set_flash_message('error', "Request not found.");
header('Location: pending-division-requests.php');
exit;
}

// Check if counsellor has access to this request
if (hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
if ($request['counsellor_id'] != $user_id) {
set_flash_message('error', "You don't have access to this request.");
header('Location: pending-division-requests.php');
exit;
}
}

// Get requested division details
$stmt_div = $conn->prepare("SELECT cd.*,
(SELECT COUNT(*) FROM tbl_enrolled_students WHERE division_id = cd.division_id AND course_id = cd.course_id AND group_id
= cd.group_id AND is_active = 1) as current_students
FROM tbl_course_division cd
WHERE cd.course_id = ? AND cd.group_id = ? AND cd.division_id = ? AND cd.is_active = 1");
$stmt_div->execute([$request['course_id'], $request['group_id'], $request['requested_division_id']]);
$requested_division = $stmt_div->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
logDatabaseError($e, "Fetch Division Change Request for Review");
set_flash_message('error', "Error loading request details.");
header('Location: pending-division-requests.php');
exit;
}

// Check if division has capacity
$has_capacity = true;
if ($requested_division && $requested_division['max_capacity']) {
$has_capacity = $requested_division['current_students'] < $requested_division['max_capacity']; } ?>
    <?php include '../../include/header.php'; ?>
    <?php include '../../include/navbar.php'; ?>
    <?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg'];
                ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Request Details -->
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle"></i> Request Details</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="35%">Student</th>
                                <td>
                                    <strong><?php echo htmlspecialchars(trim(($request['surname'] ?? '') . ' ' . ($request['student_name'] ?? ''))); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Enrollment No</th>
                                <td><?php echo htmlspecialchars($request['enrollment_no'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><a
                                        href="tel:<?php echo $request['mob']; ?>"><?php echo htmlspecialchars($request['mob'] ?? ''); ?></a>
                                </td>
                            </tr>
                            <tr>
                                <th>Gender</th>
                                <td><?php echo htmlspecialchars(ucfirst($request['gender']) ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Standard</th>
                                <td><span
                                        class="badge bg-info text-dark"><?php echo htmlspecialchars($request['course_name'] ?? ''); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Group</th>
                                <td><span
                                        class="badge bg-secondary"><?php echo htmlspecialchars($request['group_name'] ?? ''); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Counsellor</th>
                                <td><?php echo htmlspecialchars($request['counsellor_name'] ?? 'Not Assigned'); ?></td>
                            </tr>
                            <tr>
                                <th>Request Date</th>
                                <td><?php echo date('d M Y h:i A', strtotime($request['request_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pending' => 'bg-warning text-dark',
                                        'under_review' => 'bg-info text-dark',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger'
                                    ];
                                    $badge_class = $status_badges[$request['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Division Change Info -->
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Division Change</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-5">
                                <h6 class="text-muted">Current Division</h6>
                                <span
                                    class="badge bg-danger fs-5"><?php echo htmlspecialchars($request['current_division_name'] ?? ''); ?></span>
                                <?php if ($request['current_roll_no']): ?>
                                    <p class="mb-0 mt-2">Roll No:
                                        <strong><?php echo $request['current_roll_no']; ?></strong></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-2 d-flex align-items-center justify-content-center">
                                <i class="fas fa-arrow-right fa-2x text-muted"></i>
                            </div>
                            <div class="col-5">
                                <h6 class="text-muted">Requested Division</h6>
                                <span
                                    class="badge bg-success fs-5"><?php echo htmlspecialchars($request['requested_division_name'] ?? ''); ?></span>
                                <?php if ($requested_division): ?>
                                    <p class="mb-0 mt-2">
                                        Students: <strong><?php echo $requested_division['current_students']; ?></strong>
                                        <?php if ($requested_division['max_capacity']): ?>
                                            / <?php echo $requested_division['max_capacity']; ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$has_capacity): ?>
                                        <span class="badge bg-danger">Division Full</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reason -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-comment"></i> Reason for Change</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($request['reason'] ?? '')); ?></p>
                    </div>
                </div>
            </div>

            <!-- Review Action -->
            <div class="col-md-6">
                <?php if ($request['status'] === 'pending' || $request['status'] === 'under_review'): ?>
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-gavel"></i> Take Action</h3>
                        </div>
                        <form method="POST" action="process-division-request-save.php">
                            <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                            <div class="card-body">
                                <?php if (!$has_capacity): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Warning:</strong> The requested division is at maximum capacity.
                                        Approving this request may exceed the limit.
                                    </div>
                                <?php endif; ?>

                                <div class="form-group mb-3">
                                    <label class="form-label">Decision <span class="text-danger">*</span></label>
                                    <div class="d-grid gap-2">
                                        <label class="btn btn-outline-success">
                                            <input type="radio" name="action" value="approve" required>
                                            <i class="fas fa-check-circle"></i> Approve Request
                                        </label>
                                        <label class="btn btn-outline-danger">
                                            <input type="radio" name="action" value="reject" required>
                                            <i class="fas fa-times-circle"></i> Reject Request
                                        </label>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <label class="btn btn-outline-info">
                                                <input type="radio" name="action" value="under_review" required>
                                                <i class="fas fa-search"></i> Mark as Under Review
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="4"
                                        placeholder="Add any remarks about your decision..."></textarea>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> If approved, the student's division will be changed and roll
                                    numbers
                                    will be recalculated automatically for both divisions based on gender priority (female
                                    first).
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Submit Decision
                                </button>
                                <a href="pending-division-requests.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Already Processed -->
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-check"></i> Already Processed</h3>
                        </div>
                        <div class="card-body">
                            <div
                                class="alert alert-<?php echo ($request['status'] === 'approved') ? 'success' : 'danger'; ?>">
                                <h5>
                                    <i
                                        class="fas fa-<?php echo ($request['status'] === 'approved') ? 'check-circle' : 'times-circle'; ?>"></i>
                                    Request <?php echo ucfirst($request['status']); ?>
                                </h5>
                                <?php if ($request['review_date']): ?>
                                    <p><strong>Review Date:</strong>
                                        <?php echo date('d M Y h:i A', strtotime($request['review_date'])); ?></p>
                                <?php endif; ?>
                                <?php if ($request['reviewed_by_name']): ?>
                                    <p><strong>Reviewed By:</strong>
                                        <?php echo htmlspecialchars($request['reviewed_by_name'] ?? ''); ?></p>
                                <?php endif; ?>
                                <?php if ($request['review_remarks']): ?>
                                    <p><strong>Remarks:</strong><br><?php echo nl2br(htmlspecialchars($request['review_remarks'] ?? '')); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($request['status'] === 'approved' && $request['new_roll_no']): ?>
                                    <p><strong>New Roll Number:</strong> <?php echo $request['new_roll_no']; ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="pending-division-requests.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <style>
        .btn-outline-success:has(input:checked),
        .btn-outline-danger:has(input:checked),
        .btn-outline-info:has(input:checked) {
            color: white !important;
        }

        .btn-outline-success:has(input:checked) {
            background-color: #198754;
        }

        .btn-outline-danger:has(input:checked) {
            background-color: #dc3545;
        }

        .btn-outline-info:has(input:checked) {
            background-color: #0dcaf0;
        }
    </style>SESSION['error_msg']);