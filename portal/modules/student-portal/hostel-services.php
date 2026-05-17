<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;

$page_title = "Hostel Services";
include_once '../../include/header.php';
include_once '../../include/navbar.php';
include_once '../../include/sidebar.php';

require_once dirname(dirname(dirname(__DIR__))) . '/common/hostel_db_connect.php';

$student_id = $user_id ?? $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 'TEST_STUDENT_001'; // Fallback for testing

// Fetch current allotment
$allotment_query = "SELECT a.*, b.bed_label, b.id as bed_id, r.id as room_id, r.room_number, f.floor_number, w.name as wing_name 
                    FROM allotments a
                    JOIN beds b ON a.bed_id = b.id
                    JOIN rooms r ON b.room_id = r.id
                    JOIN floors f ON r.floor_id = f.id
                    JOIN wings w ON f.wing_id = w.id
                    WHERE a.student_id = :student_id AND a.is_active = 1";
try {
    $stmt = $hostel_conn->prepare($allotment_query);
    $stmt->execute(['student_id' => $student_id]);
    $current_allotment = $stmt->fetch();
} catch (PDOException $e) {
    $current_allotment = null;
    error_log("Error fetching allotment: " . $e->getMessage());
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'room_change') {
                $reason = $_POST['reason'] ?? '';
                $current_bed_id = $current_allotment['bed_id'] ?? 0;

                $ins = "INSERT INTO room_change_requests (student_id, current_bed_id, reason) 
                        VALUES (:student_id, :current_bed_id, :reason)";
                $stmt = $hostel_conn->prepare($ins);
                $stmt->execute([
                    'student_id' => $student_id,
                    'current_bed_id' => $current_bed_id,
                    'reason' => $reason
                ]);
                echo "<script>alert('Room Change Request Submitted Successfully'); window.location.href='hostel-services.php';</script>";
            } elseif ($_POST['action'] === 'complaint') {
                $category = $_POST['category'] ?? '';
                $description = $_POST['description'] ?? '';
                $bed_id = $current_allotment['bed_id'] ?? 0;

                $ins = "INSERT INTO hostel_complaints (student_id, bed_id, category, description) 
                        VALUES (:student_id, :bed_id, :category, :description)";
                $stmt = $hostel_conn->prepare($ins);
                $stmt->execute([
                    'student_id' => $student_id,
                    'bed_id' => $bed_id,
                    'category' => $category,
                    'description' => $description
                ]);
                echo "<script>alert('Complaint Registered Successfully'); window.location.href='hostel-services.php';</script>";
            } elseif ($_POST['action'] === 'leave_request') {
                $type = $_POST['leave_type'] ?? '';
                $from = $_POST['from_date'] ?? '';
                $to = $_POST['to_date'] ?? '';
                $reason = $_POST['reason'] ?? '';

                $ins = "INSERT INTO student_leaves (student_id, leave_type, from_date, to_date, reason) 
                        VALUES (:student_id, :leave_type, :from_date, :to_date, :reason)";
                $stmt = $hostel_conn->prepare($ins);
                $stmt->execute([
                    'student_id' => $student_id,
                    'leave_type' => $type,
                    'from_date' => $from,
                    'to_date' => $to,
                    'reason' => $reason
                ]);
                echo "<script>alert('Leave Request Submitted Successfully'); window.location.href='hostel-services.php';</script>";
            }
        } catch (PDOException $e) {
            error_log("Form submission failed: " . $e->getMessage());
            echo "<script>alert('Action Failed. Please try again.');</script>";
        }
    }
}

try {
    // Fetch Previous Requests
    $stmt = $hostel_conn->prepare("SELECT * FROM room_change_requests WHERE student_id = :student_id ORDER BY created_at ASC");
    $stmt->execute(['student_id' => $student_id]);
    $requests = $stmt->fetchAll();

    // Fetch Previous Complaints
    $stmt = $hostel_conn->prepare("SELECT * FROM hostel_complaints WHERE student_id = :student_id ORDER BY created_at ASC");
    $stmt->execute(['student_id' => $student_id]);
    $complaints = $stmt->fetchAll();

    // Fetch Previous Leaves
    $stmt = $hostel_conn->prepare("SELECT * FROM student_leaves WHERE student_id = :student_id ORDER BY created_at ASC");
    $stmt->execute(['student_id' => $student_id]);
    $leaves = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching history: " . $e->getMessage());
    $requests = $complaints = $leaves = [];
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold text-dark"><i class="fas fa-hotel me-2"></i>Hostel Services</h2>
            <p class="text-muted">Manage your room stay and submit requests below.</p>
        </div>
    </div>

    <!-- Quick Links Row -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <a href="my-room-assets.php" class="card border-0 shadow-sm text-decoration-none h-100 card-enhanced">
                <div class="card-body text-center p-3">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-flex mb-2">
                        <i class="fas fa-boxes text-primary fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 small">Room Assets</h6>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="attendance-history.php" class="card border-0 shadow-sm text-decoration-none h-100 card-enhanced">
                <div class="card-body text-center p-3">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-flex mb-2">
                        <i class="fas fa-calendar-check text-success fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 small">Attendance</h6>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="my-complaints.php" class="card border-0 shadow-sm text-decoration-none h-100 card-enhanced">
                <div class="card-body text-center p-3">
                    <div class="bg-danger bg-opacity-10 p-3 rounded-circle d-inline-flex mb-2">
                        <i class="fas fa-exclamation-circle text-danger fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 small">Complaints</h6>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="my-wallet.php" class="card border-0 shadow-sm text-decoration-none h-100 card-enhanced">
                <div class="card-body text-center p-3">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle d-inline-flex mb-2">
                        <i class="fas fa-wallet text-warning fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 small">Wallet & Fines</h6>
                </div>
            </a>
        </div>
    </div>

    <link rel="stylesheet" href="<?= PORTAL_URL ?>/assets/css/modules/student-portal/hostel-services.css">

    <div class="row">
        <!-- Current Allotment Info -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>My Current Room</h5>
                </div>
                <div class="card-body">
                    <?php if ($current_allotment): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                    <i class="fas fa-bed text-primary fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Room Number</small>
                                    <span class="fw-bold fs-5"><?php echo $current_allotment['room_number']; ?></span>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Wing:</span>
                                    <span class="fw-medium"><?php echo $current_allotment['wing_name']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Floor:</span>
                                    <span class="fw-medium"><?php echo $current_allotment['floor_number']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Bed Label:</span>
                                    <span class="badge bg-info"><?php echo $current_allotment['bed_label']; ?></span>
                                </li>
                            </ul>
                    <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bed-pulse text-muted fa-3x mb-3"></i>
                                <p class="text-muted">No active room allotment found.</p>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Roommates & Bed Status -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-users me-2 text-success"></i>Roommates & Bed Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($current_allotment):
                        $room_id = $current_allotment['room_id'];
                        $beds_query = "SELECT b.bed_label, b.is_occupied, a.student_id 
                                       FROM beds b 
                                       LEFT JOIN allotments a ON b.id = a.bed_id AND a.is_active = 1 
                                       WHERE b.room_id = :room_id 
                                       ORDER BY b.bed_label ASC";
                        try {
                            $stmt = $hostel_conn->prepare($beds_query);
                            $stmt->execute(['room_id' => $room_id]);
                            $beds = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            $beds = [];
                        }
                        ?>
                            <div class="row g-3">
                                <?php foreach ($beds as $bed): ?>
                                        <div class="col-6">
                                            <div class="p-3 border rounded text-center <?php echo $bed['is_occupied'] ? 'bg-light' : 'bg-white'; ?>">
                                                <div class="fw-bold mb-1">Bed <?php echo $bed['bed_label']; ?></div>
                                                <?php if ($bed['is_occupied']): ?>
                                                        <?php if ($bed['student_id'] === $student_id): ?>
                                                                <span class="badge bg-primary">You</span>
                                                        <?php else: ?>
                                                                <span class="badge bg-secondary">Roommate</span>
                                                        <?php endif; ?>
                                                <?php else: ?>
                                                        <span class="badge bg-success border text-white">Available</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                    <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bed-pulse text-muted fa-3x mb-3"></i>
                                <p class="text-muted">No active room allotment found.</p>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Room Change Request Form -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-exchange-alt me-2 text-warning"></i>Request Room Change</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="room_change">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Reason for Request</label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="Mention why you want to change your room..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 py-2 fw-bold" <?php echo !$current_allotment ? 'disabled' : ''; ?>>
                            Submit Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Leave Application Form -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-success">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-calendar-plus me-2 text-success"></i>Apply for Leave</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="leave_request">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small">Leave Type</label>
                            <select name="leave_type" class="form-select form-select-sm" required>
                                <option value="Home Visit">Home Visit</option>
                                <option value="Medical">Medical</option>
                                <option value="Academic">Academic</option>
                                <option value="Local Outing">Local Outing</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label font-weight-bold small">From</label>
                                <input type="date" name="from_date" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label font-weight-bold small">To</label>
                                <input type="date" name="to_date" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small">Reason</label>
                            <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Brief explanation..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold" <?php echo !$current_allotment ? 'disabled' : ''; ?>>
                            Request Leave
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row">
        <!-- Complaint Form -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Hostel Complaint</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="complaint">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Electrical">Electrical</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Housekeeping">Housekeeping</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Explain the issue in detail..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 py-2 fw-bold" <?php echo !$current_allotment ? 'disabled' : ''; ?>>
                            Register Complaint
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help & Support (Staff Directory) -->
        <div class="col-md-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-headset me-2 text-info"></i>Hostel Helpline & Staff</h5>
                    <span class="badge bg-light text-dark border">Emergency: 108</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Staff Name</th>
                                    <th>Designation</th>
                                    <th>Contact No</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $staff_q = "SELECT name, role, contact_no FROM hostel_staff WHERE is_active = 1 AND role IN ('Warden', 'Caretaker', 'Maintenance') ORDER BY role ASC";
                                try {
                                    $stmt = $hostel_conn->query($staff_q);
                                    $staff_list = $stmt->fetchAll();
                                } catch (PDOException $e) {
                                    $staff_list = [];
                                }
                                if (count($staff_list) > 0):
                                    foreach ($staff_list as $staff):
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width:36px; height:36px;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                    <span class="fw-bold text-dark"><?= $staff['name']; ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-opacity-10 text-<?= $staff['role'] == 'Warden' ? 'primary' : 'secondary'; ?> bg-<?= $staff['role'] == 'Warden' ? 'primary' : 'secondary'; ?>"><?= $staff['role']; ?></span></td>
                                            <td class="fw-medium"><?= $staff['contact_no']; ?></td>
                                            <td class="text-end pe-4">
                                                <a href="tel:<?= $staff['contact_no']; ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                                    <i class="fas fa-phone-alt me-1"></i> Call
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No staff directory available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- History Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <ul class="nav nav-tabs card-header-tabs" id="hostelTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button">Room Change Requests</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="complaints-tab" data-bs-toggle="tab" data-bs-target="#complaints" type="button">My Complaints</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves" type="button">My Leaves</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body tab-content">
                    <div class="tab-pane fade show active" id="requests">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($requests) > 0): ?>
                                        <?php foreach ($requests as $row): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y - h:i A', strtotime($row['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($row['status'] == 'Approved' ? 'success' : ($row['status'] == 'Rejected' ? 'danger' : 'warning')); ?>">
                                                            <?php echo $row['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?php echo $row['warden_remarks'] ?: '-'; ?></small></td>
                                                </tr>
                                        <?php endforeach; ?>
                                <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-3">No change requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="complaints">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Issue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($complaints) > 0): ?>
                                        <?php foreach ($complaints as $row): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo $row['category']; ?></span></td>
                                                    <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($row['status'] == 'Closed' ? 'success' : ($row['status'] == 'Open' ? 'danger' : 'primary')); ?>">
                                                            <?php echo $row['status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-3">No complaints found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="leaves">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Dates</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($leaves) > 0): ?>
                                    <?php foreach ($leaves as $row): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold small"><?php echo date('d M Y', strtotime($row['from_date'])); ?></div>
                                                <div class="text-muted extra-small">to <?php echo date('d M Y', strtotime($row['to_date'])); ?></div>
                                            </td>
                                            <td><span class="badge bg-soft-success text-success border border-success"><?php echo $row['leave_type']; ?></span></td>
                                            <td class="small"><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo ($row['status']=='Approved'?'success':($row['status']=='Rejected'?'danger':'warning')); ?>">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-3">No leave history found.</td></tr>
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


