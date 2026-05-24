<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is logged in as student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$page_title = "Request Division Change";

// Get student enrollment info
try {
    $op = new Operation();

    $enrollment = $op->readWithJoin(
        'tbl_enrolled_students e',
        [
            'e.*',
            'r.student_name',
            'r.surname',
            'r.mob',
            'r.gender',
            'r.counsellor_id',
            'c.course_name',
            'g.group_name',
            'r.course_id',
            'r.group_id',
            'd.division_name',
            'u.name as counsellor_name'
        ],
        [
            ['type' => 'LEFT', 'table' => 'tbl_gm_std_registration r', 'on' => 'e.registration_id = r.id'],
            ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 'r.course_id = c.id'],
            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 'r.group_id = g.id'],
            ['type' => 'LEFT', 'table' => 'tbl_division d', 'on' => 'e.division_id = d.id'],
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'r.counsellor_id = u.id']
        ],
        ['e.registration_id' => $student_id, 'e.is_active' => 1]
    );

    if (!$enrollment) {
        set_flash_message('error', "You must be enrolled to request a division change.");
        header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
        exit;
    }

    // Check if already has a pending request
    $pending_request = $op->selectOne(
        'tbl_division_change_requests',
        ['*'],
        ['enrollment_id' => $enrollment['enrollment_id'], 'status' => ['pending', 'under_review']]
    );

    // Get available divisions for this course and group
    $stmt_divisions = $conn->prepare("SELECT cd.*, d.division_name,
                                     (SELECT COUNT(*) FROM tbl_enrolled_students WHERE division_id = cd.division_id AND course_id = cd.course_id AND group_id = cd.group_id AND is_active = 1) as current_students
                                     FROM tbl_course_division cd
                                     LEFT JOIN tbl_division d ON cd.division_id = d.id
                                     WHERE cd.course_id = ? AND cd.group_id = ? AND cd.is_active = 1 AND cd.division_id != ?
                                     ORDER BY d.display_order ASC");
    $stmt_divisions->execute([$enrollment['course_id'], $enrollment['group_id'], $enrollment['division_id']]);
    $available_divisions = $stmt_divisions->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Enrollment for Division Change");
    set_flash_message('error', "Error loading your enrollment details.");
    header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requested_division_id = $_POST['requested_division_id'] ?? null;
    $reason = trim($_POST['reason'] ?? '');

    if (empty($requested_division_id) || empty($reason)) {
        set_flash_message('error', "Please select a division and provide a reason for the change.");
    } elseif ($pending_request) {
        set_flash_message('error', "You already have a pending request. Please wait for it to be processed.");
    } elseif (strlen($reason) < 10) {
        set_flash_message('error', "Please provide a more detailed reason (at least 10 characters).");
    } else {
        try {
            $op->insert('tbl_division_change_requests', [
                'enrollment_id' => $enrollment['enrollment_id'],
                'student_id' => $student_id,
                'current_division_id' => $enrollment['division_id'],
                'requested_division_id' => $requested_division_id,
                'current_roll_no' => $enrollment['roll_no'],
                'reason' => $reason,
                'counsellor_id' => $enrollment['counsellor_id'],
                'request_date' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ]);

            set_flash_message('success', "Your division change request has been submitted successfully. You will be notified once it is reviewed.");
            header('Location: my-division-change-requests.php');
            exit;
        } catch (Exception $e) {
            logDatabaseError($e, "Submit Division Change Request");
            set_flash_message('error', "Error submitting your request. Please try again.");
        }
    }
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg'];
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg'];
            ?>
        </div>
    <?php endif; ?>

    <?php if ($pending_request): ?>
        <!-- Pending Request Info -->
        <div class="alert alert-warning">
            <h5><i class="fas fa-clock"></i> Pending Request</h5>
            <p>You already have a division change request pending review. Please wait for it to be processed.</p>
            <a href="my-division-change-requests.php" class="btn btn-warning">View My Requests</a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Current Division Info -->
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle"></i> Current Assignment</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th>Student Name</th>
                                <td><?php echo htmlspecialchars(trim(($enrollment['surname'] ?? '') . ' ' . ($enrollment['student_name'] ?? ''))); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Enrollment No</th>
                                <td><strong><?php echo htmlspecialchars($enrollment['enrollment_no'] ?? ''); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Standard</th>
                                <td><span
                                        class="badge bg-info text-dark"><?php echo htmlspecialchars($enrollment['course_name'] ?? ''); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Group</th>
                                <td><span
                                        class="badge bg-secondary"><?php echo htmlspecialchars($enrollment['group_name'] ?? ''); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Current Division</th>
                                <td>
                                    <?php if ($enrollment['division_name']): ?>
                                        <span
                                            class="badge bg-success fs-5"><?php echo htmlspecialchars($enrollment['division_name'] ?? ''); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Roll Number</th>
                                <td>
                                    <?php if ($enrollment['roll_no']): ?>
                                        <span class="badge bg-primary fs-5"><?php echo $enrollment['roll_no']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($enrollment['counsellor_name']): ?>
                                <tr>
                                    <th>Counsellor</th>
                                    <td><?php echo htmlspecialchars($enrollment['counsellor_name'] ?? ''); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Request Form -->
            <div class="col-md-7">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Submit Division Change Request</h3>
                    </div>
                    <form method="POST">
                        <div class="card-body">
                            <?php if (empty($available_divisions)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    No other divisions are available for your course and group at this time.
                                </div>
                            <?php else: ?>
                                <div class="form-group mb-3">
                                    <label class="form-label">Select New Division <span class="text-danger">*</span></label>
                                    <select name="requested_division_id" class="form-control" required>
                                        <option value="">-- Select Division --</option>
                                        <?php foreach ($available_divisions as $div): ?>
                                            <option value="<?php echo $div['division_id']; ?>">
                                                Division <?php echo htmlspecialchars($div['division_name'] ?? ''); ?>
                                                (Students: <?php echo $div['current_students']; ?>
                                                <?php if ($div['max_capacity']): ?>
                                                    / <?php echo $div['max_capacity']; ?>
                                                    <?php if ($div['current_students'] >= $div['max_capacity']): ?>
                                                        - FULL
                                                    <?php endif; ?>
                                                <?php endif; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Reason for Change <span class="text-danger">*</span></label>
                                    <textarea name="reason" class="form-control" rows="5" required
                                        placeholder="Please explain why you want to change your division (minimum 10 characters)..."
                                        minlength="10"></textarea>
                                    <small class="text-muted">Provide a detailed reason for your request. This will be
                                        reviewed by the administration.</small>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> Your request will be reviewed by the Principal and your assigned
                                    Counsellor.
                                    You will be notified when a decision is made.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer">
                            <?php if (!empty($available_divisions)): ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            <?php endif; ?>
                            <a href="my-division-change-requests.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i> View My Requests
                            </a>
                            <a href="<?php echo PORTAL_URL; ?>/modules/dashboard/student_dashboard.php"
                                class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../include/footer.php'; ?>