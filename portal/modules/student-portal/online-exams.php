<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: " . PORTAL_URL . "/student-login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$page_title = "My Online Exams";

// Fetch student standard and division using registration ID
$std_stmt = $conn->prepare("
    SELECT r.course_id, r.standard as reg_standard, e.division_id 
    FROM tbl_gm_std_registration r
    LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id AND e.is_active = 1
    WHERE r.id = ?
");
$std_stmt->execute([$student_id]);
$student = $std_stmt->fetch();

$course_id = $student ? ($student['course_id'] ?? 0) : 0;
$standard_id = $student ? ($student['reg_standard'] ?? 0) : 0;
$division_id = $student ? ($student['division_id'] ?? null) : null;

// Fetch available exams
$sql = "SELECT e.*, 
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE (e.standard_id = ? OR (e.standard_id IN (SELECT stdid FROM standard WHERE stdnumber = ?) AND e.standard_id IS NOT NULL) OR e.standard_id IS NULL) 
        AND (e.division_id = ? OR e.division_id IS NULL)
        AND e.exam_mode = 'Practice'
        AND e.status IN ('Scheduled', 'Live')
        AND e.end_time >= NOW()
        ORDER BY e.start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id, $course_id, $standard_id, $division_id]);
$exams = $stmt->fetchAll();

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-dark font-weight-bold">Online <span class="text-primary">Exams</span></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            
            <div class="mb-5">
                <h1 class="h2 font-weight-bold text-gray-800">My Assessments</h1>
                <p class="text-muted">Access your scheduled tests and review your performance.</p>
            </div>

            <div class="row">
                <?php if (count($exams) > 0): ?>
                    <?php foreach ($exams as $e): ?>
                        <?php 
                        $is_submitted = ($e['attempt_status'] === 'Submitted');
                        $is_live = (strtotime($e['start_time']) <= time() && strtotime($e['end_time']) >= time());
                        if ($is_submitted) {
                            $status_label = 'Submitted';
                            $status_class = 'bg-secondary';
                        } else {
                            $status_label = $is_live ? 'Live Now' : 'Upcoming';
                            $status_class = $is_live ? 'bg-success' : 'bg-warning';
                        }
                        ?>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 border-0 rounded-lg overflow-hidden">
                                <div class="card-header <?php echo $status_class; ?> text-white py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small font-weight-bold text-uppercase tracking-wider"><?php echo $status_label; ?></span>
                                        <span class="small"><i class="far fa-clock mr-1"></i> <?php echo $e['duration_mins']; ?> Mins</span>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <h5 class="font-weight-bold text-dark mb-3"><?php echo htmlspecialchars($e['title']); ?></h5>
                                    <p class="text-muted small mb-4 line-clamp-2"><?php echo htmlspecialchars($e['description']); ?></p>

                                    <div class="small text-gray-600 mb-2">
                                        <i class="fas fa-calendar-day mr-2 text-primary"></i> 
                                        <strong>Starts:</strong> <?php echo date('M j, Y - g:i A', strtotime($e['start_time'])); ?>
                                    </div>
                                    <div class="small text-gray-600 mb-2">
                                        <i class="fas fa-calendar-check mr-2 text-primary"></i> 
                                        <strong>Ends:</strong> <?php echo date('M j, Y - g:i A', strtotime($e['end_time'])); ?>
                                    </div>
                                    <div class="small text-gray-600">
                                        <i class="fas fa-award mr-2 text-primary"></i> 
                                        <strong>Total Marks:</strong> <?php echo $e['total_marks']; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-0 p-4">
                                    <?php if ($e['attempt_status'] === 'Submitted'): ?>
                                        <button disabled class="btn btn-secondary btn-block disabled py-2">
                                            Already Submitted
                                        </button>
                                    <?php elseif ($is_live): ?>
                                        <a href="take-exam.php?id=<?php echo $e['id']; ?>" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                                            Start Exam Now
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="btn btn-light btn-block disabled py-2 text-muted">
                                            Starts at <?php echo date('g:i A', strtotime($e['start_time'])); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-hourglass-half fa-4x text-gray-200"></i>
                        </div>
                        <h4 class="text-gray-500 font-weight-bold">No Exams Scheduled</h4>
                        <p class="text-muted">Check back later for your upcoming assessments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
