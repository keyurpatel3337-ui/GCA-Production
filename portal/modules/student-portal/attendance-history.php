<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/hostel_db_connect.php';

$page_title = "Attendance History";
include_once '../../include/header.php';
include_once '../../include/navbar.php';
include_once '../../include/sidebar.php';

$student_id = $user_id ?? $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 'TEST_STUDENT_001';

// Fetch attendance history
try {
    $attendance_query = "SELECT a.date, a.status, b.bed_label, r.room_number
                        FROM student_attendance a
                        JOIN beds b ON a.bed_id = b.id
                        JOIN rooms r ON b.room_id = r.id
                        WHERE a.student_id = :student_id
                        ORDER BY a.date DESC";
                        
    $stmt = $hostel_conn->prepare($attendance_query);
    $stmt->execute(['student_id' => $student_id]);
    $attendance_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $attendance_logs = [];
    error_log("Error fetching attendance history: " . $e->getMessage());
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-calendar-check me-2 text-success"></i>Attendance History</h2>
            <p class="text-muted">View your night attendance logs and status history.</p>
        </div>
        <a href="hostel-services.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-history me-2 text-secondary"></i>Logs</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Status</th>
                                    <th>Room</th>
                                    <th class="text-end pe-4">Bed Label</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($attendance_logs) > 0): ?>
                                    <?php foreach ($attendance_logs as $log): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold small"><?php echo date('d M Y', strtotime($log['date'])); ?></td>
                                            <td>
                                                <?php
                                                $status = $log['status'] ?? 'Absent';
                                                $badge_class = 'bg-danger';
                                                if ($status === 'Present') $badge_class = 'bg-success';
                                                elseif ($status === 'Leave') $badge_class = 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                            </td>
                                            <td class="small">Room <?php echo $log['room_number']; ?></td>
                                            <td class="text-end pe-4"><span class="badge bg-light text-dark border"><?php echo $log['bed_label']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No attendance logs available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../include/footer.php'; ?>
