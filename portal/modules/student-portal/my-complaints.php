<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/hostel_db_connect.php';

$page_title = "My Complaints & Support";
include_once '../../include/header.php';
include_once '../../include/navbar.php';
include_once '../../include/sidebar.php';

$student_id = $user_id ?? $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 'TEST_STUDENT_001';

// Fetch current allotment to get bed_id
$allotment_query = "SELECT a.*, b.id as bed_id, r.room_number 
                    FROM allotments a
                    JOIN beds b ON a.bed_id = b.id
                    JOIN rooms r ON b.room_id = r.id
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
    if (isset($_POST['action']) && $_POST['action'] === 'complaint') {
        $category = $_POST['category'] ?? '';
        $description = $_POST['description'] ?? '';
        $bed_id = $current_allotment['bed_id'] ?? 0;

        try {
            $ins = "INSERT INTO hostel_complaints (student_id, bed_id, category, description) 
                    VALUES (:student_id, :bed_id, :category, :description)";
            $stmt = $hostel_conn->prepare($ins);
            $stmt->execute([
                'student_id' => $student_id,
                'bed_id' => $bed_id,
                'category' => $category,
                'description' => $description
            ]);
            echo "<script>alert('Complaint Registered Successfully'); window.location.href='my-complaints.php';</script>";
        } catch (PDOException $e) {
            error_log("Complaint insertion failed: " . $e->getMessage());
            echo "<script>alert('Failed to register complaint. Please try again.');</script>";
        }
    }
}

// Fetch complaints history
try {
    $history_query = "SELECT c.*, b.bed_label, r.room_number 
                      FROM hostel_complaints c 
                      LEFT JOIN beds b ON c.bed_id = b.id 
                      LEFT JOIN rooms r ON b.room_id = r.id 
                      WHERE c.student_id = :student_id 
                      ORDER BY c.created_at DESC";
    $stmt = $hostel_conn->prepare($history_query);
    $stmt->execute(['student_id' => $student_id]);
    $complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    $complaints = [];
    error_log("Error fetching complaints history: " . $e->getMessage());
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-exclamation-circle me-2 text-danger"></i>My Complaints</h2>
            <p class="text-muted">Submit maintenance requests and track resolution status.</p>
        </div>
        <div class="text-end">
            <button class="btn btn-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#complaintForm">
                <i class="fas fa-plus me-1"></i> New Complaint
            </button>
            <a href="hostel-services.php" class="btn btn-outline-secondary btn-sm ms-2">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- New Complaint Form Collapse -->
    <div class="collapse mb-4" id="complaintForm">
        <div class="card border-0 shadow-sm border-top border-4 border-danger">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0"><i class="fas fa-edit me-2 text-danger"></i>File a Maintenance Request</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="complaint">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Category</label>
                            <select name="category" class="form-select form-select-sm" required>
                                <option value="Electrical">Electrical (Fan, Light, Socket)</option>
                                <option value="Plumbing">Plumbing (Tap, Shower, Flush)</option>
                                <option value="Housekeeping">Housekeeping (Cleaning)</option>
                                <option value="Furniture">Furniture (Bed, Table, Wardrobe)</option>
                                <option value="Internet/Wifi">Internet / Wifi</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Allocated Bed/Room</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="<?php echo $current_allotment ? 'Room ' . $current_allotment['room_number'] : 'Not Allotted'; ?>" readonly>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold small">Detailed Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Explain the exact issue..." required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold" <?php echo !$current_allotment ? 'disabled' : ''; ?>>
                        Submit Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Complaint History -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-history me-2 text-secondary"></i>Complaint History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Category</th>
                                    <th>Issue</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($complaints) > 0): ?>
                                    <?php foreach ($complaints as $complaint): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold small"><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></div>
                                                <div class="text-muted extra-small"><?php echo date('h:i A', strtotime($complaint['created_at'])); ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo $complaint['category']; ?></span></td>
                                            <td class="small"><?php echo htmlspecialchars($complaint['description'] ?? ''); ?></td>
                                            <td>
                                                <?php
                                                $status = $complaint['status'] ?? 'Open';
                                                $badge_class = 'bg-warning';
                                                if ($status === 'Closed') $badge_class = 'bg-success';
                                                elseif ($status === 'In Progress') $badge_class = 'bg-primary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                            </td>
                                            <td class="text-end pe-4 small text-muted"><?php echo $complaint['updated_at'] ? date('d M Y', strtotime($complaint['updated_at'])) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No complaints filed yet.</td>
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
