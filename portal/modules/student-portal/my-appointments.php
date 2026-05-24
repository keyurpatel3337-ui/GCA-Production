<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student or Parent
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent_login = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if (!$is_student_login && !$is_parent_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = $is_parent_login ? ($_SESSION['active_student_id'] ?? null) : ($is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null));

$page_title = "My Appointments";
$page_breadcrumb = "Appointments -";

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filter'])) {
        unset($_SESSION['my_appointments_filter']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $_SESSION['my_appointments_filter'] = $_POST['status'] ?? 'all';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filter status from session
$status_filter = $_SESSION['my_appointments_filter'] ?? 'all';

// Build query based on filter
$query = "SELECT a.*, u.name as counsellor_name, u.email as counsellor_email, u.phone as counsellor_phone
          FROM tbl_appointments a
          LEFT JOIN tbl_users u ON a.counsellor_id = u.id
          WHERE a.student_id = ?";

if ($status_filter != 'all') {
    $query .= " AND a.status = ?";
}

$query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

try {
    $stmt = $conn->prepare($query);
    if ($status_filter != 'all') {
        $stmt->execute([$student_id, $status_filter]);
    } else {
        $stmt->execute([$student_id]);
    }
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Appointments");
    $appointments = [];
}

// Get counts for summary
try {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM tbl_appointments WHERE student_id = ? GROUP BY status");
    $stmt->execute([$student_id]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $counts = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php echo array_sum($counts); ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <form method="POST" style="display:inline;margin:0;">
                    <input type="hidden" name="status" value="all">
                    <button type="submit" class="small-box-footer"
                        style="border:none;background:none;color:inherit;padding:6px 10px;cursor:pointer;width:100%;text-align:center;display:block;">
                        View All <i class="fas fa-arrow-circle-right"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php echo $counts['pending'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <form method="POST" style="display:inline;margin:0;">
                    <input type="hidden" name="status" value="pending">
                    <button type="submit" class="small-box-footer"
                        style="border:none;background:none;color:inherit;padding:6px 10px;cursor:pointer;width:100%;text-align:center;display:block;">
                        View Pending <i class="fas fa-arrow-circle-right"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php echo $counts['confirmed'] ?? 0; ?></h3>
                    <p>Confirmed</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <form method="POST" style="display:inline;margin:0;">
                    <input type="hidden" name="status" value="confirmed">
                    <button type="submit" class="small-box-footer"
                        style="border:none;background:none;color:inherit;padding:6px 10px;cursor:pointer;width:100%;text-align:center;display:block;">
                        View Confirmed <i class="fas fa-arrow-circle-right"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3><?php echo $counts['completed'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <form method="POST" style="display:inline;margin:0;">
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="small-box-footer"
                        style="border:none;background:none;color:inherit;padding:6px 10px;cursor:pointer;width:100%;text-align:center;display:block;">
                        View Completed <i class="fas fa-arrow-circle-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php
                if ($status_filter == 'all') {
                    echo 'All Appointments';
                } else {
                    echo ucfirst($status_filter) . ' Appointments';
                }
                ?>
            </h3>
            <div class="card-tools">
                <a href="appointments.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Book New Appointment
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($appointments)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No appointments found.
                    <a href="appointments.php" class="alert-link">Book your first appointment</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Counsellor</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></strong><br>
                                        <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($apt['counsellor_name'] ?? ''); ?><br>
                                        <small
                                            class="text-muted"><?php echo htmlspecialchars($apt['counsellor_email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($apt['purpose'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $status = $apt['status'];
                                        $badge_class = 'secondary';
                                        switch ($status) {
                                            case 'pending':
                                                $badge_class = 'warning';
                                                break;
                                            case 'confirmed':
                                                $badge_class = 'success';
                                                break;
                                            case 'completed':
                                                $badge_class = 'primary';
                                                break;
                                            case 'cancelled':
                                                $badge_class = 'danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                            <a href="appointment-cancel.php?id=<?php echo $apt['id']; ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>