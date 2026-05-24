<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Restrict strictly to Super Admin and Principal as requested
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Fetch Exam Info
$exam_stmt = $conn->prepare("SELECT e.*, s.stdtext 
                             FROM tbl_oes_exams e 
                             LEFT JOIN standard s ON e.standard_id = s.stdid 
                             WHERE e.id = ?");
$exam_stmt->execute([$exam_id]);
$exam = $exam_stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("<div style='font-family: sans-serif; text-align: center; margin-top: 100px;'><h1 style='color: #ef4444;'>Exam Not Found</h1><p>The specified exam does not exist or has been deleted.</p><a href='manage-exams.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px;'>Back to Manage Exams</a></div>");
}

$page_title = "Student Results - " . htmlspecialchars($exam['title']) . " | OES";

// Fetch Student Attempts
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = " WHERE se.exam_id = ? ";
$params = [$exam_id];

if ($search_query !== '') {
    $where .= " AND (
        s.student_name LIKE ? 
        OR s.surname LIKE ? 
        OR s.gr_no LIKE ? 
        OR CONCAT(s.surname, ' ', s.student_name) LIKE ? 
        OR CONCAT(s.student_name, ' ', s.surname) LIKE ?
    ) ";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query = "SELECT se.*, s.student_name, s.surname, s.gr_no 
          FROM tbl_oes_student_exams se 
          JOIN tbl_gm_std_registration s ON se.student_id = s.id 
          $where 
          ORDER BY se.start_timestamp DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <!-- Exam Meta Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-lg p-3" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="text-muted small font-weight-bold">EXAM MODE</div>
                        <div class="h4 font-weight-bold text-primary mt-1"><?= $exam['exam_mode'] === 'Final' ? 'Proctored Final' : 'Practice Test' ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-lg p-3" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                        <div class="text-muted small font-weight-bold">TOTAL MARKS</div>
                        <div class="h4 font-weight-bold text-success mt-1"><?= $exam['total_marks'] ?> Marks</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-lg p-3" style="background: linear-gradient(135deg, #faf5ff, #f3e8ff);">
                        <div class="text-muted small font-weight-bold">DURATION</div>
                        <div class="h4 font-weight-bold text-purple mt-1"><?= $exam['duration_mins'] ?> Minutes</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-lg p-3" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                        <div class="text-muted small font-weight-bold">TOTAL PARTICIPANTS</div>
                        <div class="h4 font-weight-bold text-warning mt-1"><?= count($attempts) ?> Students</div>
                    </div>
                </div>
            </div>

            <!-- Search and List -->
            <div class="card shadow-sm border-0" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-4 d-flex justify-content-between align-items-center flex-wrap" style="gap: 15px;">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-user-graduate mr-2 text-primary"></i> Participant Attempts</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap" style="max-width: 600px; width: 100%; justify-content: flex-end;">
                        <form method="GET" class="d-flex align-items-center" style="max-width: 300px; width: 100%;">
                            <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                            <div class="input-group" style="width: 100%;">
                                <input type="text" name="search" class="form-control" placeholder="Search by name or roll number..." value="<?= htmlspecialchars($search_query) ?>" style="border-radius: 10px 0 0 10px;">
                                <button class="btn btn-primary px-3" type="submit" style="border-radius: 0 10px 10px 0;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        <a href="manage-exams.php" class="btn btn-outline-secondary px-3" style="border-radius: 10px; font-weight: 600; white-space: nowrap;">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Exams
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 border-0 rounded-start">Student Details</th>
                                    <th class="py-3 border-0">Roll Number</th>
                                    <th class="py-3 border-0">Start / Submission Time</th>
                                    <th class="py-3 border-0 text-center">Score</th>
                                    <th class="py-3 border-0 text-center">Status</th>
                                    <th class="pe-4 py-3 border-0 rounded-end text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attempts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-users-slash fa-3x mb-3 text-light"></i>
                                            <h5>No Attempts Recorded</h5>
                                            <p class="small">No students have launched or submitted this exam yet.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attempts as $att): ?>
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3">
                                            <div class="font-weight-bold text-dark"><?= htmlspecialchars($att['surname'] . ' ' . $att['student_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-weight-bold"><?= htmlspecialchars($att['gr_no'] ?: 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <div class="small"><b>Started:</b> <?= date('d M Y, h:i A', strtotime($att['start_timestamp'])) ?></div>
                                            <div class="small text-muted"><b>Ended:</b> <?= $att['submit_timestamp'] ? date('d M Y, h:i A', strtotime($att['submit_timestamp'])) : 'N/A' ?></div>
                                        </td>
                                        <td class="text-center">
                                            <div class="h5 mb-0 font-weight-bold text-dark"><?= $att['total_score'] ?> / <?= $exam['total_marks'] ?></div>
                                            <div class="small text-muted">Correct: <span class="text-success font-weight-bold"><?= $att['correct_answers'] ?></span> | Wrong: <span class="text-danger font-weight-bold"><?= $att['wrong_answers'] ?></span></div>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            if ($att['status'] === 'Submitted') {
                                                echo '<span class="badge bg-success py-2 px-3 rounded-pill"><i class="fas fa-check-circle mr-1"></i> Submitted</span>';
                                            } else {
                                                echo '<span class="badge bg-warning text-dark py-2 px-3 rounded-pill"><i class="fas fa-spinner fa-spin mr-1"></i> Ongoing</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="view-student-responses.php?attempt_id=<?= $att['id'] ?>" class="btn btn-sm btn-outline-primary font-weight-bold px-3 py-2" style="border-radius: 8px;">
                                                <i class="fas fa-eye mr-1"></i> View Responses
                                            </a>
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
</main>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
