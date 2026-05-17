<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$result = ['success' => false, 'message' => '', 'affected_rows' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Update admission_confirmed to NULL for students NOT in tbl_enrolled_students
        $updateQuery = "UPDATE tbl_gm_std_registration 
                       SET admission_confirmed = NULL,
                           admission_confirmed_date = NULL,
                           admission_confirmed_by = NULL
                       WHERE admission_confirmed = 1
                       AND id NOT IN (
                           SELECT registration_id FROM tbl_enrolled_students WHERE registration_id IS NOT NULL
                       )";

        $stmt = $conn->prepare($updateQuery);
        $stmt->execute();

        $affectedRows = $stmt->rowCount();

        // Commit transaction
        $conn->commit();

        // Log success
        error_log("[Reset Admission] SUCCESS: User ID " . $_SESSION['user_id'] . " reset {$affectedRows} admission confirmations");

        $result['success'] = true;
        $result['affected_rows'] = $affectedRows;
        $result['message'] = "Successfully reset admission confirmation for {$affectedRows} student(s).";
    } catch (PDOException $e) {
        // Rollback on error
        $conn->rollBack();

        // Log database error
        logDatabaseError($e, "Reset Admission Confirmation");
        error_log("[Reset Admission] ERROR: User ID " . ($_SESSION['user_id'] ?? 'Unknown') . " - " . $e->getMessage());

        $result['message'] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        // Rollback on any other error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        // Log general error
        error_log("[Reset Admission] EXCEPTION: User ID " . ($_SESSION['user_id'] ?? 'Unknown') . " - " . $e->getMessage());

        $result['message'] = 'Error: ' . $e->getMessage();
    }
}

// Get counts for display
try {
    // Count all confirmed admissions
    $totalResult = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_gm_std_registration 
                               WHERE admission_confirmed = 1", []);
    $totalConfirmed = $totalResult['total'] ?? 0;

    $enrolledResult = $dbOps->customSelectOne("SELECT COUNT(DISTINCT registration_id) as total FROM tbl_enrolled_students WHERE registration_id IS NOT NULL", []);
    $totalEnrolled = $enrolledResult['total'] ?? 0;

    $toBeResetResult = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_gm_std_registration 
                                   WHERE admission_confirmed = 1
                                   AND id NOT IN (SELECT registration_id FROM tbl_enrolled_students WHERE registration_id IS NOT NULL)", []);
    $toBeReset = $toBeResetResult['total'] ?? 0;

    // Get detailed list of students to be reset
    $studentsToReset = $dbOps->customSelect("SELECT r.id, r.student_name, r.surname, r.mob, r.email, 
                                               r.admission_confirmed, r.admission_confirmed_date, r.created_at as registration_date,
                                               c.course_name, b.board_name,
                                               u.name as confirmed_by_name
                                        FROM tbl_gm_std_registration r
                                        LEFT JOIN tbl_courses c ON r.course_id = c.id
                                        LEFT JOIN tbl_boards b ON r.board_id = b.id
                                        LEFT JOIN tbl_users u ON r.admission_confirmed_by = u.id
                                        WHERE r.admission_confirmed = 1
                                        AND r.id NOT IN (SELECT registration_id FROM tbl_enrolled_students WHERE registration_id IS NOT NULL)
                                        ORDER BY r.admission_confirmed_date ASC", []) ?: [];

    // Get statistics by course
    $statsByCourse = $dbOps->customSelect("SELECT c.course_name, COUNT(*) as count
                                  FROM tbl_gm_std_registration r
                                  LEFT JOIN tbl_courses c ON r.course_id = c.id
                                  WHERE r.admission_confirmed = 1
                                  AND r.id NOT IN (SELECT registration_id FROM tbl_enrolled_students WHERE registration_id IS NOT NULL)
                                  GROUP BY r.course_id, c.course_name
                                  ORDER BY count ASC", []) ?: [];

    // Get statistics by confirmed by user
    $statsByUser = $dbOps->customSelect("SELECT u.name as user_name, COUNT(*) as count
                                FROM tbl_gm_std_registration r
                                LEFT JOIN tbl_users u ON r.admission_confirmed_by = u.id
                                WHERE r.admission_confirmed = 1
                                AND r.id NOT IN (SELECT registration_id FROM tbl_enrolled_students WHERE registration_id IS NOT NULL)
                                GROUP BY r.admission_confirmed_by, u.name
                                ORDER BY count ASC", []) ?: [];

    // Get date range
    $dateRange = $dbOps->customSelectOne("SELECT MIN(admission_confirmed_date) as earliest, 
                                          MAX(admission_confirmed_date) as latest
                                   FROM tbl_gm_std_registration
                                   WHERE admission_confirmed = 1
                                   AND id NOT IN (SELECT registration_id FROM tbl_enrolled_students WHERE registration_id IS NOT NULL)", []);

    // Log access to reset page
    error_log("[Reset Admission] Page accessed by User ID " . ($_SESSION['user_id'] ?? 'Unknown') . " - Found {$toBeReset} students to reset");
} catch (PDOException $e) {
    // Log database error
    logDatabaseError($e, "Reset Admission - Fetch Statistics");
    error_log("[Reset Admission] DB ERROR fetching stats: " . $e->getMessage());

    $totalConfirmed = 0;
    $totalEnrolled = 0;
    $toBeReset = 0;
    $studentsToReset = [];
    $statsByCourse = [];
    $statsByUser = [];
    $dateRange = null;
} catch (Exception $e) {
    // Log general error
    error_log("[Reset Admission] EXCEPTION fetching stats: " . $e->getMessage());

    $totalConfirmed = 0;
    $totalEnrolled = 0;
    $toBeReset = 0;
    $studentsToReset = [];
    $statsByCourse = [];
    $statsByUser = [];
    $dateRange = null;
}

$page_title = "Reset Admission Confirmation";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="fas fa-undo-alt"></i> <?php echo $page_title; ?></h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../../modules/dashboard/admin_dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Reset Admission</li>
            </ol>
        </nav>
    </div>

    <?php if ($result['success']): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> <?php echo $result['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (!empty($result['message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error!</strong> <?php echo $result['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h3 class="mb-0"><?php echo number_format($totalConfirmed); ?></h3>
                    <p class="text-muted mb-0">Total Confirmed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-graduate fa-3x text-primary mb-3"></i>
                    <h3 class="mb-0"><?php echo number_format($totalEnrolled); ?></h3>
                    <p class="text-muted mb-0">Enrolled Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-undo-alt fa-3x text-danger mb-3"></i>
                    <h3 class="mb-0"><?php echo number_format($toBeReset); ?></h3>
                    <p class="text-muted mb-0">To Be Reset</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-shield-alt fa-3x text-info mb-3"></i>
                    <h3 class="mb-0"><?php echo number_format($totalEnrolled); ?></h3>
                    <p class="text-muted mb-0">Protected (Skip)</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($toBeReset > 0): ?>
        <!-- Detailed Statistics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Breakdown by Standard</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($statsByCourse)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Standard</th>
                                            <th class="text-end">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statsByCourse as $stat): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($stat['course_name'] ?? 'Unknown'); ?></td>
                                                <td class="text-end"><span
                                                        class="badge bg-secondary"><?php echo $stat['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-user-check me-2"></i>Confirmed By</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($statsByUser)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th class="text-end">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statsByUser as $stat): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($stat['user_name'] ?? 'Unknown'); ?></td>
                                                <td class="text-end"><span
                                                        class="badge bg-secondary"><?php echo $stat['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($dateRange && $dateRange['earliest']): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-calendar me-2"></i>
                        <strong>Date Range:</strong>
                        Confirmations from <strong><?php echo date('d M Y', strtotime($dateRange['earliest'])); ?></strong>
                        to <strong><?php echo date('d M Y', strtotime($dateRange['latest'])); ?></strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Students List -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Students to be Reset (<?php echo count($studentsToReset); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="studentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Standard</th>
                                        <th>Board</th>
                                        <th>Confirmed Date</th>
                                        <th>Confirmed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($studentsToReset as $student): ?>
                                        <tr>
                                            <td><?php echo $student['id']; ?></td>
                                            <td><?php echo htmlspecialchars(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '')); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($student['course_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($student['board_name'] ?? '-'); ?></td>
                                            <td><?php echo $student['admission_confirmed_date'] ? date('d M Y', strtotime($student['admission_confirmed_date'])) : '-'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['confirmed_by_name'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Reset Admission Confirmation Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Current Statistics:</h6>
                        <ul class="mb-0">
                            <li><strong>Total Confirmed Admissions:</strong>
                                <?php echo number_format($totalConfirmed); ?></li>
                            <li><strong>Students in Enrolled Table:</strong>
                                <?php echo number_format($totalEnrolled); ?></li>
                            <li class="text-danger"><strong>Students to be Reset:</strong>
                                <?php echo number_format($toBeReset); ?></li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Warning!</h6>
                        <p class="mb-2">This action will:</p>
                        <ul class="mb-0">
                            <li>Set <code>admission_confirmed</code> to NULL</li>
                            <li>Set <code>admission_confirmed_date</code> to NULL</li>
                            <li>Set <code>admission_confirmed_by</code> to NULL</li>
                            <li><strong class="text-success">SKIP students whose ID exists in
                                    tbl_enrolled_students</strong></li>
                        </ul>
                    </div>

                    <?php if ($toBeReset > 0): ?>
                        <form method="POST" id="resetForm">
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold">Type "RESET" to confirm:</label>
                                <input type="text" class="form-control" id="confirmText" placeholder="Type RESET">
                            </div>
                            <input type="hidden" name="confirm" value="yes">
                            <button type="submit" class="btn btn-danger btn-lg" id="submitBtn" disabled>
                                <i class="fas fa-undo-alt me-2"></i>Reset Admission Confirmation
                            </button>
                            <a href="registered-students.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            No students need to be reset. All confirmed admissions are enrolled.
                        </div>
                        <a href="registered-students.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Students
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Enable submit button only when "RESET" is typed
        $('#confirmText').on('input', function () {
            if ($(this).val().toUpperCase() === 'RESET') {
                $('#submitBtn').prop('disabled', false);
            } else {
                $('#submitBtn').prop('disabled', true);
            }
        });

        // Confirm before submitting
        $('#resetForm').on('submit', function (e) {
            e.preventDefault();

            const form = this;
            showConfirm({
                title: 'Are you absolutely sure?',
                text: 'This will reset admission confirmation for <?php echo $toBeReset; ?> student(s)!',
                icon: 'warning',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, reset it!',
                onConfirm: () => {
                    showToast('info', 'Processing...', 'Resetting admission confirmations...');
                    form.submit();
                }
            });
        });
    });
</script>

