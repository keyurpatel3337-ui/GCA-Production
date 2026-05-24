<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/hostel_db_connect.php';

$page_title = "My Room Assets";
include_once '../../include/header.php';
include_once '../../include/navbar.php';
include_once '../../include/sidebar.php';

$student_id = $user_id ?? $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 'TEST_STUDENT_001';

// Fetch current allotment
$allotment_query = "SELECT a.*, b.bed_label, r.room_number, f.floor_number, w.name as wing_name 
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

// Hardcoded assets as per guide guideline
$assets = [
    ['name' => 'Bed Frame', 'icon' => 'fa-bed', 'condition' => 'Good', 'assigned_date' => $current_allotment['allotment_date'] ?? 'N/A'],
    ['name' => 'Study Table', 'icon' => 'fa-table', 'condition' => 'Good', 'assigned_date' => $current_allotment['allotment_date'] ?? 'N/A'],
    ['name' => 'Ergonomic Chair', 'icon' => 'fa-chair', 'condition' => 'Excellent', 'assigned_date' => $current_allotment['allotment_date'] ?? 'N/A'],
    ['name' => 'Wardrobe', 'icon' => 'fa-door-closed', 'condition' => 'Good', 'assigned_date' => $current_allotment['allotment_date'] ?? 'N/A']
];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-dark"><i class="fas fa-boxes me-2 text-primary"></i>My Room Assets</h2>
                <p class="text-muted">List of furniture and fixtures assigned to your room.</p>
            </div>
            <a href="hostel-services.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($current_allotment): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-white-50 small d-block">Allotted Room</span>
                            <h3 class="fw-bold mb-0"><?php echo $current_allotment['room_number']; ?></h3>
                            <small class="text-white-50"><?php echo $current_allotment['wing_name']; ?> Wing, Floor <?php echo $current_allotment['floor_number']; ?></small>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-circle">
                            <i class="fas fa-door-open fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($assets as $asset): ?>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-light p-3 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 css-my-room-assets-13ce31">
                                <i class="fas <?php echo $asset['icon']; ?> text-primary fs-4"></i>
                            </div>
                            <h5 class="fw-bold mb-1"><?php echo $asset['name']; ?></h5>
                            <span class="badge bg-success bg-opacity-10 text-success mb-2"><?php echo $asset['condition']; ?></span>
                            <div class="text-muted small">Assigned: <?php echo $asset['assigned_date'] != 'N/A' ? date('d M Y', strtotime($asset['assigned_date'])) : 'Allotment Date'; ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12 text-center py-5">
                <i class="fas fa-box-open text-muted fa-4x mb-3"></i>
                <h4>No Assets Found</h4>
                <p class="text-muted">You do not have any room allotment yet.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../../include/footer.php'; ?>
