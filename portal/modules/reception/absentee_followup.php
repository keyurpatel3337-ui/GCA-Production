<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin, Principle or Reception
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_RECEPTION)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Absentee Follow-up (Reception)";
$page_breadcrumb = "Reception -";

$date = $_GET['date'] ?? date('Y-m-d');

// Fetch today's absentees
try {
    $query = "SELECT att.*, 
                     s.student_name, s.surname, s.fathers_name, s.mob,
                     c.course_name, d.division_name
              FROM tbl_student_attendance att
              JOIN tbl_enrolled_students e ON att.student_id = e.enrollment_id
              JOIN tbl_gm_std_registration s ON e.registration_id = s.id
              LEFT JOIN tbl_courses c ON s.course_id = c.id
              LEFT JOIN tbl_division d ON att.division_id = d.id
              WHERE att.attendance_date = ? AND att.status = 'Absent'
              ORDER BY d.division_name ASC, s.student_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$date]);
    $absentees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Absentees for Reception");
    $absentees = [];
}

// Handle Remark Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_remark'])) {
    $attendance_id = $_POST['attendance_id'];
    $remark = $_POST['remark'];
    
    try {
        $updateSql = "UPDATE tbl_student_attendance 
                     SET reception_remark = ?, 
                         reception_call_done = 1, 
                         followup_by = ?, 
                         updated_at = NOW() 
                     WHERE id = ?";
        $upStmt = $conn->prepare($updateSql);
        $upStmt->execute([$remark, $_SESSION['user_id'], $attendance_id]);
        $_SESSION['success_msg'] = "Remark saved successfully.";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?date=' . $date);
        exit;
    } catch (PDOException $e) {
        $error_msg = "Failed to save remark: " . $e->getMessage();
    }
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><?php echo $page_title; ?></h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Date Filter -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                    <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Today's Absent Students - <?php echo date('d M Y', strtotime($date)); ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Division</th>
                                    <th>Student Details</th>
                                    <th>Parent Contact</th>
                                    <th>Current Remark</th>
                                    <th width="120" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($absentees)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                                            No absentees found for this date.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($absentees as $row): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['division_name'] ?? ''); ?></td>
                                            <td>
                                                <span class="fw-bold d-block text-primary"><?php echo htmlspecialchars($row['surname'] . ' ' . $row['student_name'] ?? ''); ?></span>
                                                <small class="text-muted">Standard: <?php echo $row['course_name']; ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($row['fathers_name'] ?? ''); ?></div>
                                                <div class="text-success fw-bold"><i class="fas fa-phone-alt me-1"></i> <?php echo $row['mob']; ?></div>
                                            </td>
                                            <td>
                                                <?php if ($row['reception_remark']): ?>
                                                    <span class="badge bg-info-subtle text-info border p-2">
                                                        <i class="fas fa-comment-dots me-1"></i> <?php echo htmlspecialchars($row['reception_remark'] ?? ''); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">No remark entered yet.</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn <?php echo $row['reception_call_done'] ? 'btn-outline-success' : 'btn-danger'; ?> btn-sm" 
                                                        onclick="openRemarkModal(<?php echo htmlspecialchars(json_encode($row) ?? ''); ?>)">
                                                    <i class="fas fa-phone-volume me-1"></i> <?php echo $row['reception_call_done'] ? 'Update' : 'Call'; ?>
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
    </section>
</div>

<!-- Remark Modal -->
<div class="modal fade" id="remarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Parent Follow-up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="attendance_id" id="modal_att_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Student:</label>
                        <div id="modal_student_name" class="p-2 border rounded bg-light"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Parent Mobile:</label>
                        <div class="p-2 border rounded bg-light">
                            <a href="tel:" id="modal_parent_mob" class="text-decoration-none fw-bold text-success"></a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Call Remarks <span class="text-danger">*</span></label>
                        <textarea name="remark" id="modal_remark" class="form-control" rows="4" placeholder="Enter reason for absence told by parent..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="submit_remark" class="btn btn-danger">Save Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRemarkModal(data) {
    $('#modal_att_id').val(data.id);
    $('#modal_student_name').text(data.surname + ' ' + data.student_name);
    $('#modal_parent_mob').text(data.mob).attr('href', 'tel:' + data.mob);
    $('#modal_remark').val(data.reception_remark || '');
    $('#remarkModal').modal('show');
}
</script>

<?php include '../../include/footer.php'; ?>
