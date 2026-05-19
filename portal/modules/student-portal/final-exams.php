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

// Fetch available Final exams
$sql = "SELECT e.*, 
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE (e.standard_id = ? OR (e.standard_id IN (SELECT stdid FROM standard WHERE stdnumber = ?) AND e.standard_id IS NOT NULL) OR e.standard_id IS NULL) 
        AND (e.division_id = ? OR e.division_id IS NULL)
        AND e.exam_mode = 'Final'
        AND e.status IN ('Scheduled', 'Live')
        AND e.end_time >= NOW()
        ORDER BY e.start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id, $course_id, $standard_id, $division_id]);
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
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }
        
        .exam-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        .terminal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(13, 148, 136, 0.1);
            border: 1px solid rgba(13, 148, 136, 0.2);
            color: #0f766e;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .student-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #334155;
        }
        
        .main-content {
            padding: 3rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }
        
        .premium-table-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            width: 100%;
        }
        
        .table-responsive {
            margin-bottom: 0;
        }
        
        .premium-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .premium-table th {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            padding: 1.1rem 1.5rem;
            border: none;
            vertical-align: middle;
        }
        
        .premium-table td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            transition: background-color 0.15s ease;
        }
        
        .premium-table tbody tr:hover td {
            background-color: #f8fafc;
        }
        
        .premium-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .exam-title-text {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.05rem;
            margin-bottom: 4px;
        }
        
        .exam-desc-text {
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 0;
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .schedule-stacked {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .schedule-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            color: #475569;
        }
        
        .schedule-item i {
            width: 14px;
            font-size: 0.85rem;
        }
        
        .schedule-item.starts i {
            color: #0d9488;
        }
        
        .schedule-item.ends i {
            color: #ef4444;
        }
        
        .exam-status-badge {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
        }
        .status-live {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .status-upcoming {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        .status-submitted {
            background: rgba(99, 102, 241, 0.15);
            color: #4f46e5;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-table-action {
            padding: 0.55rem 1.25rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            transition: all 0.2s ease-in-out;
            border: none;
            width: 100%;
            display: inline-block;
            text-align: center;
        }
        
        .btn-table-live {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #ffffff !important;
            box-shadow: 0 4px 6px rgba(13, 148, 136, 0.15);
        }
        
        .btn-table-live:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(13, 148, 136, 0.25);
            text-decoration: none;
        }

        .btn-table-locked {
            background-color: #f1f5f9;
            color: #94a3b8 !important;
            cursor: not-allowed;
        }

        .btn-table-submitted {
            background-color: rgba(99, 102, 241, 0.1);
            color: #4f46e5 !important;
            border: 1px solid rgba(99, 102, 241, 0.2);
            cursor: default;
        }
        
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        .empty-state i {
            color: #94a3b8;
            margin-bottom: 1.25rem;
        }
    </style>
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
            
            <a href="final-exams.php?action=logout" class="btn btn-outline-danger btn-sm font-weight-bold px-3 py-2" style="border-radius: 8px;">
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
                                        <th style="width: 35%;">Exam Details</th>
                                        <th style="width: 25%;">Schedule</th>
                                        <th style="width: 12%; text-align: center;">Duration</th>
                                        <th style="width: 12%; text-align: center;">Total Marks</th>
                                        <th style="width: 12%; text-align: center;">Status</th>
                                        <th style="width: 16%; text-align: center;">Action</th>
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
                                            <td style="text-align: center; font-weight: 600; color: #475569;">
                                                <i class="far fa-clock mr-1 text-muted"></i> <?php echo $e['duration_mins']; ?> Mins
                                            </td>
                                            <td style="text-align: center; font-weight: 600; color: #475569;">
                                                <i class="fas fa-star mr-1 text-muted" style="color: #eab308 !important;"></i> <?php echo $e['total_marks']; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="exam-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                            </td>
                                            <td style="text-align: center;">
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

    <footer class="text-center py-4 text-muted small" style="border-top: 1px solid #e2e8f0; margin-top: 5rem; background-color: #ffffff;">
        &copy; <?php echo date('Y'); ?> Gyanmanjari Career Academy. All connections are securely logged.
    </footer>
</body>
</html>
