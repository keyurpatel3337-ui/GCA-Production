<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once HELPER_ERROR_LOGGER;

// Check if user is authorized (Counsellor, Principle, or Super Admin)
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Check if student_id or id (session_id) is provided
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : (isset($_POST['student_id']) ? intval($_POST['student_id']) : 0);
$session_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($student_id == 0 && $session_id == 0) {
    set_flash_message('error', "Student or Session ID is required");
    header('Location: sessions.php');
    exit;
}

$page_title = "Counselling Student History";
$page_breadcrumb = "Session History -";

try {
    $op = new Operation();

    // If we have a session ID but no student ID, find the student ID
    if ($student_id == 0 && $session_id > 0) {
        $session = $op->selectOne('tbl_sessions', ['student_id'], ['id' => $session_id]);
        if ($session) {
            $student_id = $session['student_id'];
        } else {
            set_flash_message('error', "Session record not found");
            header('Location: sessions.php');
            exit;
        }
    }

    // Get student details
    $student_data = $op->readWithJoin(
        'tbl_gm_std_registration s',
        ['s.*', 'u.name as current_counsellor'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 's.counsellor_id = u.id']
        ],
        ['s.id' => $student_id]
    );

    if (!$student_data) {
        set_flash_message('error', "Student record not found");
        header('Location: sessions.php');
        exit;
    }

    // Security check: Counsellors can only see students assigned to them
    if (hasRole(ROLE_COUNSELLOR) && $student_data['counsellor_id'] != $_SESSION['user_id']) {
        set_flash_message('error', "You can only view history for students assigned to you");
        header('Location: sessions.php');
        exit;
    }

    // Get all sessions for this student
    $all_sessions = $op->selectWithJoin(
        'tbl_sessions cs',
        ['cs.*', 'u.name as recorded_by'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'cs.counsellor_id = u.id']
        ],
        ['cs.student_id' => $student_id],
        'cs.session_date DESC, cs.id DESC'
    );

    // Ensure it's always an array
    if (!is_array($all_sessions)) {
        $all_sessions = [];
    }

    // Get test marks for this student (only if table exists)
    $test_marks = [];
    try {
        $result = $op->selectWithJoin(
            'tbl_test_marks tm',
            ['tm.*', 'ps.paper_set_name', 'ps.paper_code'],
            [
                ['type' => 'LEFT', 'table' => 'tbl_paper_sets ps', 'on' => 'tm.paper_set_id = ps.id']
            ],
            ['tm.student_id' => $student_id],
            'tm.test_date DESC'
        );
        // Ensure it's always an array
        $test_marks = is_array($result) ? $result : [];
    } catch (Exception $e) {
        // Table may not exist - ignore and continue without test marks
        $test_marks = [];
    }
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Session History");
    set_flash_message('error', "Error fetching history details");
    header('Location: sessions.php');
    exit;
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>



<div class="container-fluid">
    <!-- Student Header Card -->
    <div class="card card-outline card-primary shadow-sm mb-4 border-0">
        <div class="card-body p-0">
            <div class="row g-0">
                <div class="col-md-auto bg-primary text-white p-4 d-flex flex-column align-items-center justify-content-center css-session-details-684a19">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center mb-2 css-session-details-14ff6c">
                        <?php echo strtoupper(substr($student_data['surname'], 0, 1) . substr($student_data['student_name'], 0, 1)); ?>
                    </div>
                    <div class="text-center">
                        <span class="badge bg-white text-primary text-uppercase mb-1">Student ID</span><br>
                        <span class="fw-bold fs-5">#<?php echo $student_id; ?></span>
                    </div>
                </div>
                <div class="col p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h2 class="fw-bold mb-1 text-uppercase text-dark">
                                <?php echo htmlspecialchars($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . $student_data['fathers_name'] ?? ''); ?>
                            </h2>
                            <p class="text-muted mb-3"><i
                                    class="fas fa-phone-alt me-2"></i><?php echo htmlspecialchars($student_data['mob'] ?? ''); ?>
                            </p>

                            <div class="d-flex gap-3 mt-2">
                                <div class="bg-light p-2 px-3 rounded text-center">
                                    <small class="text-muted text-uppercase d-block mb-0">Total Sessions</small>
                                    <span class="fw-bold h5 mb-0"><?php echo count($all_sessions); ?></span>
                                </div>
                                <div class="bg-light p-2 px-3 rounded text-center">
                                    <small class="text-muted text-uppercase d-block mb-0">Assigned To</small>
                                    <span
                                        class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($student_data['current_counsellor'] ?? 'Unassigned'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <a href="sessions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to List
                                </a>
                                <form method="POST" action="create-session.php" class="css-session-details-5f8f4a">
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i> New Session
                                    </button>
                                </form>
                                <form method="POST" action="details.php" class="css-session-details-5f8f4a">
                                    <input type="hidden" name="id" value="<?php echo $student_id; ?>">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-user-graduate me-1"></i> Full Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Marks Section -->
    <?php if (!empty($test_marks)): ?>
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i> Student Test Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($test_marks as $tm): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div
                                class="card h-100 border-<?php echo $tm['test_type'] === 'omr_mcq' ? 'primary' : 'success'; ?>">
                                <div
                                    class="card-header bg-<?php echo $tm['test_type'] === 'omr_mcq' ? 'primary' : 'success'; ?> text-white py-2">
                                    <strong><?php echo htmlspecialchars($tm['test_name'] ?? ''); ?></strong>
                                    <span class="badge bg-light text-dark float-end">
                                        <?php echo $tm['test_type'] === 'omr_mcq' ? 'OMR MCQ' : 'Descriptive'; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <?php
                                        $pct = $tm['percentage'];
                                        $color = $pct >= 80 ? 'success' : ($pct >= 60 ? 'primary' : ($pct >= 40 ? 'warning' : 'danger'));
                                        ?>
                                        <h2 class="text-<?php echo $color; ?> mb-0"><?php echo formatIndianCurrency($pct); ?>%
                                        </h2>
                                        <small class="text-muted"><?php echo formatIndianCurrency($tm['obtained_marks']); ?> /
                                            <?php echo formatIndianCurrency($tm['total_marks']); ?></small>
                                    </div>
                                    <p class="mb-1 small"><i class="far fa-calendar text-muted me-1"></i>
                                        <?php echo date('d M Y', strtotime($tm['test_date'])); ?></p>
                                    <?php if ($tm['test_type'] === 'omr_mcq'): ?>
                                        <p class="mb-0 small">
                                            <span class="text-success"><i class="fas fa-check"></i>
                                                <?php echo $tm['correct_answers'] ?? 0; ?></span> |
                                            <span class="text-danger"><i class="fas fa-times"></i>
                                                <?php echo $tm['wrong_answers'] ?? 0; ?></span> |
                                            <span class="text-secondary"><?php echo $tm['unanswered'] ?? 0; ?> skipped</span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-light py-2">
                                    <form method="POST" action="../test-marks/view.php" class="css-session-details-f8e39d">
                                        <input type="hidden" name="id" value="<?php echo $tm['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-end mt-2">
                    <a href="../test-marks/add.php?student_id=<?php echo $student_id; ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add Test Marks
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-light border mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-info-circle text-muted me-2"></i> No test marks recorded for this student
                    yet.</span>
                <a href="../test-marks/add.php?student_id=<?php echo $student_id; ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Add Test Marks
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <h5 class="fw-bold mb-4"><i class="fas fa-history me-2 text-primary"></i> Timeline of Counselling
                Sessions</h5>

            <?php if (empty($all_sessions)): ?>
                <div class="alert alert-info py-4 text-center">
                    <i class="fas fa-info-circle fa-2x mb-3 d-block"></i>
                    <p class="mb-0">No counselling sessions have been recorded for this student yet.</p>
                    <a onclick="document.getElementById('form_create-session_student_id').submit()" class="css-session-details-b202c6"
                        class="btn btn-primary mt-3">Record First Session</a>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php
                    $current_date = "";
                    foreach ($all_sessions as $session):
                        $session_date = date('d M Y', strtotime($session['session_date']));
                        if ($current_date != $session_date):
                            $current_date = $session_date;
                            ?>
                            <div class="time-label">
                                <span class="bg-primary px-3"><?php echo $session_date; ?></span>
                            </div>
                        <?php endif; ?>

                        <div>
                            <i class="fas fa-comments bg-info"></i>
                            <div class="timeline-item card shadow-sm border-0 mb-4">
                                <span class="time text-muted"><i class="fas fa-clock fs-7"></i>
                                    <?php echo date('h:i A', strtotime($session['created_at'])); ?></span>
                                <h3 class="timeline-header border-bottom py-3 px-4">
                                    <span
                                        class="badge bg-<?php echo ($session['status'] == 'completed') ? 'success' : 'warning'; ?> float-end mt-1">
                                        <?php echo ucfirst($session['status']); ?>
                                    </span>
                                    <span
                                        class="text-primary fw-bold fs-5"><?php echo htmlspecialchars($session['session_topic'] ?? 'General Session'); ?></span>
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-user-tie me-1"></i> Recorded by:
                                        <strong><?php echo htmlspecialchars($session['recorded_by'] ?? 'N/A'); ?></strong>
                                        <span class="mx-2">|</span>
                                        <i class="fas fa-hourglass-half me-1"></i> Duration:
                                        <strong><?php echo $session['session_duration']; ?> mins</strong>
                                    </div>
                                </h3>

                                <div class="timeline-body p-4 bg-white">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h6 class="fw-bold text-dark mb-2"><i
                                                    class="fas fa-sticky-note text-warning me-2"></i> Session Notes:</h6>
                                            <div
                                                class="p-3 bg-light rounded text-dark mb-4 border-start border-warning border-4">
                                                <?php echo nl2br(htmlspecialchars($session['session_notes'] ?? '')); ?>
                                            </div>
                                        </div>

                                        <?php if (!empty($session['recommendations'])): ?>
                                            <div class="col-md-12">
                                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-lightbulb text-info me-2"></i>
                                                    Recommendations:</h6>
                                                <div class="p-3 bg-light rounded text-dark border-start border-info border-4">
                                                    <?php echo nl2br(htmlspecialchars($session['recommendations'] ?? '')); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($session['follow_up_required']): ?>
                                        <div class="mt-3">
                                            <span class="badge bg-danger rounded-pill py-2 px-3">
                                                <i class="far fa-calendar-check me-1"></i>
                                                Follow-up Scheduled:
                                                <strong><?php echo date('d M Y', strtotime($session['follow_up_date'])); ?></strong>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="timeline-footer p-3 bg-light text-end">
                                    <small class="text-muted">ID: #<?php echo $session['id']; ?> | Created:
                                        <?php echo date('d M Y', strtotime($session['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div>
                        <i class="fas fa-clock bg-gray"></i>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

</div>

<?php include '../../include/footer.php'; ?>