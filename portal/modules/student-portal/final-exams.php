<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if student is logged in via final exam isolated terminal
if (!isset($_SESSION['final_student_id'])) {
    header("Location: final-exam-login.php");
    exit();
}

$student_id = $_SESSION['final_student_id'];
$student_name = $_SESSION['final_student_name'];

// Fetch student standard and division using registration ID
$std_stmt = $conn->prepare("
    SELECT r.course_id, r.standard as reg_standard_num, r.medium_id, r.group_id, e.division_id, s.stdid, s.stdnumber 
    FROM tbl_gm_std_registration r
    LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id AND e.is_active = 1
    LEFT JOIN standard s ON s.stdnumber = r.standard AND (
        (r.standard = 13)
        OR (r.medium_id = 1 AND s.stdtext LIKE '%Gujarati%')
        OR (r.medium_id = 2 AND s.stdtext LIKE '%English%')
    )
    WHERE r.id = ?
");
$std_stmt->execute([$student_id]);
$student = $std_stmt->fetch();

$course_id = $student ? ($student['course_id'] ?? 0) : 0;
$standard_id = $student ? ($student['stdid'] ?? 0) : 0;
$std_number = $student ? ($student['stdnumber'] ?? 0) : 0;
$group_id = $student ? ($student['group_id'] ?? null) : null;
$division_id = $student ? ($student['division_id'] ?? null) : null;

// Fetch available Final exams
$sql = "SELECT e.*, 
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE (e.standard_id = ? OR e.standard_id IS NULL) 
        AND (e.group_id = ? OR e.group_id IS NULL)
        AND (e.division_id = ? OR e.division_id IS NULL)
        AND e.exam_mode = 'Final'
        AND e.status IN ('Scheduled', 'Live')
        AND e.end_time >= NOW()
        ORDER BY e.start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id, $standard_id, $group_id, $division_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle terminal logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    header("Location: final-exam-login.php?msg=logged_out");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Exam Terminal | GCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    
</head>
<body>
    <!-- Secure Header -->
    <header class="exam-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <span class="terminal-badge">
                    <i class="fas fa-shield-alt mr-1"></i> Secure Mode
                </span>
                <div class="student-name ml-3">
                    <i class="fas fa-user-graduate text-muted mr-2"></i> <?php echo htmlspecialchars($student_name); ?>
                </div>
            </div>
            
            <a href="final-exams.php?action=logout" class="btn btn-outline-danger btn-sm font-weight-bold px-3 py-2 css-final-exams-cb968a">
                <i class="fas fa-power-off mr-2"></i> Exit Secure Terminal
            </a>
        </div>
    </header>

    <!-- Main Dashboard Container -->
    <main class="main-content">
        <div class="mb-5">
            <h1 class="section-title">Assigned Evaluations</h1>
            <p class="text-muted">Proctored examination terminal. Unauthorized tabs or window switches will be logged.</p>
        </div>

        <div class="row">
            <?php if (count($exams) > 0): ?>
                <div class="col-12">
                    <div class="premium-table-card">
                        <div class="table-responsive">
                            <table class="table premium-table">
                                <thead>
                                    <tr>
                                        <th class="css-final-exams-3af624">Exam Details</th>
                                        <th class="css-final-exams-a294a4">Schedule</th>
                                        <th class="css-final-exams-8727f8">Duration</th>
                                        <th class="css-final-exams-8727f8">Total Marks</th>
                                        <th class="css-final-exams-8727f8">Status</th>
                                        <th class="css-final-exams-036a69">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $e): ?>
                                        <?php 
                                        $is_submitted = ($e['attempt_status'] === 'Submitted');
                                        $is_live = (strtotime($e['start_time']) <= time() && strtotime($e['end_time']) >= time());
                                        if ($is_submitted) {
                                            $status_label = 'Submitted';
                                            $status_class = 'status-submitted';
                                        } else {
                                            $status_label = $is_live ? 'Live Now' : 'Upcoming';
                                            $status_class = $is_live ? 'status-live' : 'status-upcoming';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="exam-title-text"><?php echo htmlspecialchars($e['title']); ?></div>
                                                <div class="exam-desc-text"><?php echo htmlspecialchars($e['description'] ?: 'No instructions provided.'); ?></div>
                                            </td>
                                            <td>
                                                <div class="schedule-stacked">
                                                    <div class="schedule-item starts">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <span><strong>Starts:</strong> <?php echo date('M j, Y - g:i A', strtotime($e['start_time'])); ?></span>
                                                    </div>
                                                    <div class="schedule-item ends">
                                                        <i class="fas fa-calendar-check"></i>
                                                        <span><strong>Ends:</strong> <?php echo date('M j, Y - g:i A', strtotime($e['end_time'])); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="css-final-exams-985dfa">
                                                <i class="far fa-clock mr-1 text-muted"></i> <?php echo $e['duration_mins']; ?> Mins
                                            </td>
                                            <td class="css-final-exams-985dfa">
                                                <i class="fas fa-star mr-1 text-muted css-final-exams-6f4211"></i> <?php echo $e['total_marks']; ?>
                                            </td>
                                            <td class="css-final-exams-cdd8ca">
                                                <span class="exam-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                            </td>
                                            <td class="css-final-exams-cdd8ca">
                                                <?php if ($e['attempt_status'] === 'Submitted'): ?>
                                                    <button disabled class="btn btn-table-action btn-table-submitted font-weight-bold">
                                                        <i class="fas fa-check-double mr-1"></i> Done
                                                    </button>
                                                <?php elseif ($is_live): ?>
                                                    <a href="take-final-exam.php?id=<?php echo $e['id']; ?>" class="btn btn-table-action btn-table-live">
                                                        Start <i class="fas fa-arrow-right ml-1"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button disabled class="btn btn-table-action btn-table-locked font-weight-bold" title="Locked until start time">
                                                        <i class="fas fa-lock mr-1"></i> Locked
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-hourglass-empty fa-3x mb-3"></i>
                        <h3 class="font-weight-bold">No Active Examinations</h3>
                        <p class="text-muted">There are no proctored final exams scheduled for your standard or division at this moment.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="text-center py-4 text-muted small css-final-exams-476147">
        &copy; <?php echo date('Y'); ?> Gyanmanjari Career Academy. All connections are securely logged.
    </footer>
</body>
</html>
