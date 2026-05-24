<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Establishment Admin or Super Admin
if (!hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Initiate Cancellation";
$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : null;
$student_data = null;

try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    if ($student_id) {
        $stmt = $conn->prepare("SELECT id, surname, student_name, fathers_name, mob FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Handle Cancellation Initiation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reason']) && $student_id) {
        $reason = $_POST['reason'];

        // Check if a request already exists
        $stmt = $conn->prepare("SELECT id FROM tbl_admission_cancellations WHERE student_id = ? AND final_status IN ('initiated', 'pending')");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            set_flash_message('warning', 'A cancellation request for this student is already in progress.');
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_admission_cancellations (student_id, reason, requested_by) VALUES (?, ?, ?)");
            $stmt->execute([$student_id, $reason, $_SESSION['user_id']]);
            set_flash_message('success', 'Admission cancellation request initiated successfully.');
            header("Location: initiate-cancellation.php");
            exit;
        }
    }

    // Handle Cancellation Termination
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'terminate_request') {
        $req_id = (int) $_POST['request_id'];

        // Only allow termination if it's still pending/initiated
        $stmt = $conn->prepare("UPDATE tbl_admission_cancellations SET final_status = 'rejected' WHERE id = ? AND final_status IN ('initiated', 'pending')");
        if ($stmt->execute([$req_id])) {
            set_flash_message('success', 'Cancellation request terminated successfully.');
        } else {
            set_flash_message('error', 'Failed to terminate request.');
        }
        header("Location: initiate-cancellation.php");
        exit;
    }
} catch (Exception $e) {
    logError("Initiate Cancellation Error: " . $e->getMessage());
    set_flash_message('error', 'An error occurred while processing your request.');
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4 pb-5">
    <!-- Welcome Banner -->
    <div class="mb-4 mt-2">
        <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-user-times me-2 text-danger"></i> Admission Cancellation
        </h4>
        <p class="text-muted small mb-0">Initiate formal cancellation requests and monitor approval status.</p>
    </div>

    <div class="row g-4">
        <!-- Student Search -->
        <div class="col-lg-5">
            <div class="glass-card p-4 h-100">
                <h5 class="fw-bold mb-4">Select Student for Cancellation</h5>
                <form action="initiate-cancellation.php" method="GET" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search ID or Name..."
                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search'] ?? '') : ''; ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>

                <?php if (isset($_GET['search'])): ?>
                    <div class="list-group list-group-flush overflow-auto css-initiate-cancellation-790d50">
                        <?php
                        $search_term = $_GET['search'];
                        $search_wildcard = '%' . $search_term . '%';
                        $stmt = $conn->prepare("SELECT id, surname, student_name, fathers_name, CONCAT(surname, ' ', student_name, ' ', fathers_name) as full_name FROM tbl_gm_std_registration 
                                               WHERE student_name LIKE ? 
                                               OR surname LIKE ? 
                                               OR fathers_name LIKE ? 
                                               OR id LIKE ? 
                                               OR mob LIKE ? 
                                               OR CONCAT(surname, ' ', student_name, ' ', fathers_name) LIKE ?");
                        $stmt->execute([$search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard]);
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <div
                                class="list-group-item d-flex justify-content-between align-items-center <?php echo $student_id == $row['id'] ? 'bg-light' : ''; ?>">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></div>
                                    <small class="text-muted">ID: <?php echo $row['id']; ?></small>
                                </div>
                                <a href="initiate-cancellation.php?student_id=<?php echo $row['id']; ?>&search=<?php echo urlencode($search_term); ?>"
                                    class="btn btn-sm btn-outline-danger">Select</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Request Form Area -->
        <div class="col-lg-7">
            <?php if ($student_data): ?>
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">Request Formal Cancellation</h5>
                    <div class="p-3 bg-danger-subtle rounded-3 mb-4 border border-danger-subtle d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-danger fa-2x me-3"></i>
                        <div>
                            <div class="fw-bold text-danger">Identity Verification</div>
                            <small class="text-dark">You are initiating a cancellation for <strong>
                                    <?php echo htmlspecialchars($student_data['surname'] . ' ' . $student_data['student_name'] ?? ''); ?>
                                    (ID:
                                    <?php echo $student_id; ?>)
                                </strong>.</small>
                        </div>
                    </div>

                    <form action="initiate-cancellation.php?student_id=<?php echo $student_id; ?>" method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Reason for Cancellation <span
                                    class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="5"
                                placeholder="Please provide detailed reason for admission cancellation..."
                                required></textarea>
                            <div class="form-text mt-2 small">This reason will be visible to Account, Reception, and
                                Principal for approval.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger py-2 fw-bold"
                                onclick="return confirm('Are you sure you want to initiate this cancellation request?');">
                                <i class="fas fa-paper-plane me-2"></i> Submit Cancellation Request
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">Recent Cancellation Requests</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->query("SELECT c.*, s.surname, s.student_name FROM tbl_admission_cancellations c JOIN tbl_gm_std_registration s ON c.student_id = s.id ORDER BY c.created_at ASC LIMIT 20");
                                $recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (empty($recent_requests)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No recent requests.</td>
                                    </tr>
                                <?php else:
                                    foreach ($recent_requests as $req):
                                        $approved_count = 0;
                                        if ($req['account_status'] === 'approved') $approved_count++;
                                        if ($req['reception_status'] === 'approved') $approved_count++;
                                        if ($req['principal_status'] === 'approved') $approved_count++;
                                        $percentage = ($approved_count / 3) * 100;

                                        $badge_class = $req['final_status'] === 'cancelled' ? 'bg-success' :
                                            ($req['final_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');

                                        $approval_badge = function($status) {
                                            if ($status === 'approved') return 'bg-success';
                                            if ($status === 'rejected') return 'bg-danger';
                                            return 'bg-secondary';
                                        };
                                        $approval_icon = function($status) {
                                            if ($status === 'approved') return 'fa-check';
                                            if ($status === 'rejected') return 'fa-times';
                                            return 'fa-clock';
                                        };
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($req['surname'] . ' ' . $req['student_name'] ?? ''); ?></div>
                                                <small class="text-muted">ID: <?php echo $req['student_id']; ?> &nbsp;|&nbsp; <?php echo date('d M Y', strtotime($req['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badge_class; ?> text-uppercase" style="font-size:0.65rem;">
                                                    <?php echo $req['final_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress mb-2 css-initiate-cancellation-aa262b">
                                                    <div class="progress-bar bg-success css-initiate-cancellation-f22989"></div>
                                                </div>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <span class="badge <?php echo $approval_badge($req['account_status']); ?>" style="font-size:0.6rem;" title="Account">
                                                        <i class="fas <?php echo $approval_icon($req['account_status']); ?> me-1"></i>Account
                                                    </span>
                                                    <span class="badge <?php echo $approval_badge($req['reception_status']); ?>" style="font-size:0.6rem;" title="Reception">
                                                        <i class="fas <?php echo $approval_icon($req['reception_status']); ?> me-1"></i>Reception
                                                    </span>
                                                    <span class="badge <?php echo $approval_badge($req['principal_status']); ?>" style="font-size:0.6rem;" title="Principal">
                                                        <i class="fas <?php echo $approval_icon($req['principal_status']); ?> me-1"></i>Principal
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <?php if (in_array($req['final_status'], ['initiated', 'pending'])): ?>
                                                    <form method="POST" action="initiate-cancellation.php"
                                                        onsubmit="return confirm('Are you sure you want to terminate this request?');">
                                                        <input type="hidden" name="action" value="terminate_request">
                                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2 fw-bold">
                                                            <i class="fas fa-ban me-1"></i>Terminate
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<?php include '../../include/footer.php'; ?>

