<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check access
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Schedule Appointment";
$page_breadcrumb = "Schedule Appointment";

// Get search queries and IDs
$search = $_GET['search'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$staff_search = $_GET['staff_search'] ?? '';
$staff_id = $_GET['staff_id'] ?? '';

$searchResults = [];
$selectedStudent = null;
$staffResults = [];
$selectedStaff = null;

// Student Search logic
if (!empty($search)) {
    try {
        $sql = "SELECT id, CONCAT(surname, ' ', student_name) as name, mob, email 
                FROM tbl_gm_std_registration 
                WHERE (surname LIKE ? OR student_name LIKE ? OR mob LIKE ? OR email LIKE ? OR id = ?)";

        $params = ["%$search%", "%$search%", "%$search%", "%$search%", $search];

        if (!hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
            $sql .= " AND counsellor_id = ?";
            $params[] = $_SESSION['user_id'];
        }

        $sql .= " LIMIT 10";
        $results = $dbOps->customSelect($sql, $params);

        // Filter results if a student is already selected (User requirement: only show selected student)
        if (!empty($student_id)) {
            $searchResults = array_filter($results, function ($r) use ($student_id) {
                return $r['id'] == $student_id;
            });
        } else {
            $searchResults = $results;
        }

    } catch (PDOException $e) {
        logDatabaseError($e, "Search Students for Appointment");
    }
}

// Selected student details
if (!empty($student_id)) {
    try {
        $sql = "SELECT id, CONCAT(surname, ' ', student_name) as name, mob 
                FROM tbl_gm_std_registration 
                WHERE id = ?";
        $params = [$student_id];

        if (!hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
            $sql .= " AND counsellor_id = ?";
            $params[] = $_SESSION['user_id'];
        }

        $res = $dbOps->customSelect($sql, $params);
        if (!empty($res)) {
            $selectedStudent = $res[0];
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Selected Student");
    }
}

// Staff Search logic (Only for Reception/Admin)
if ((hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN)) && !empty($staff_search)) {
    try {
        $sql = "SELECT id, name, role_id FROM tbl_users 
                WHERE role_id IN (?, ?) AND status = 'active' AND name LIKE ? 
                LIMIT 5";
        $staffResults = $dbOps->customSelect($sql, [ROLE_PRINCIPLE, ROLE_COUNSELLOR, "%$staff_search%"]);
    } catch (PDOException $e) {
        logDatabaseError($e, "Search Staff for Appointment");
    }
}

// Selected Staff details
if (!empty($staff_id) && (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN))) {
    try {
        $sql = "SELECT id, name, role_id FROM tbl_users WHERE id = ? AND role_id IN (?, ?) AND status = 'active'";
        $res = $dbOps->customSelect($sql, [$staff_id, ROLE_PRINCIPLE, ROLE_COUNSELLOR]);
        if (!empty($res)) {
            $selectedStaff = $res[0];
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Selected Staff");
    }
}

// Default staff if not specified and not admin/reception
if (empty($staff_id) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN)) {
    $staff_id = $_SESSION['user_id'];
}

?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
                        <li class="breadcrumb-item active"><?php echo $page_breadcrumb; ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Step 1: Student Selection -->
                <div class="card card-enhanced border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <label class="form-label fw-bold mb-2"><i class="fas fa-search me-2 text-primary"></i> Step 1:
                            Select Student</label>
                        <form method="GET" class="row g-2 align-items-center mb-3">
                            <div class="col">
                                <div class="input-group input-group-lg border rounded-pill overflow-hidden">
                                    <span class="input-group-text bg-white border-0 ps-3">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-0 ps-2"
                                        placeholder="Search by student name, ID or mobile..."
                                        value="<?php echo htmlspecialchars($search ?? ''); ?>" autocomplete="off">
                                    <?php if ($staff_id): ?>
                                        <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">Search</button>
                            </div>
                        </form>

                        <?php if (!empty($searchResults)): ?>
                            <div class="list-group list-group-flush border rounded overflow-hidden shadow-sm">
                                <?php foreach ($searchResults as $row): ?>
                                    <a href="?student_id=<?php echo $row['id']; ?>&search=<?php echo urlencode($search); ?>&staff_id=<?php echo $staff_id; ?>&staff_search=<?php echo urlencode($staff_search); ?>"
                                        class="list-group-item list-group-item-action p-3 d-flex justify-content-between align-items-center <?php echo $student_id == $row['id'] ? 'bg-primary bg-opacity-10 fw-bold border-primary border-start border-4' : ''; ?>">
                                        <div>
                                            <h6 class="mb-1 <?php echo $student_id == $row['id'] ? 'text-primary' : ''; ?>">
                                                <?php echo htmlspecialchars($row['name'] ?? ''); ?></h6>
                                            <small class="text-muted">ID: <?php echo $row['id']; ?> |
                                                <?php echo $row['mob']; ?></small>
                                        </div>
                                        <?php if ($student_id == $row['id']): ?>
                                            <span class="badge bg-primary rounded-pill"><i class="fas fa-check me-1"></i>
                                                Selected</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Staff Search (Reception/Admin Only) -->
                <?php if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN)): ?>
                    <div class="card card-enhanced border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <label class="form-label fw-bold mb-2"><i class="fas fa-user-tie me-2 text-primary"></i>Assign
                                To (Principal/Counsellor)</label>
                            <form method="GET" class="row g-2 align-items-center mb-3">
                                <div class="col">
                                    <div class="input-group input-group-lg border rounded-pill overflow-hidden">
                                        <span class="input-group-text bg-white border-0 ps-3">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text" name="staff_search" class="form-control border-0 ps-2"
                                            placeholder="Search staff name..."
                                            value="<?php echo htmlspecialchars($staff_search ?? ''); ?>" autocomplete="off">
                                        <?php if ($staff_id): ?>
                                            <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                                        <?php endif; ?>
                                        <?php if ($student_id): ?>
                                            <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                        <?php endif; ?>
                                        <?php if ($search): ?>
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-outline-primary btn-lg rounded-pill px-4">Find
                                        Staff</button>
                                </div>
                            </form>

                            <?php if (!empty($staffResults) || !empty($selectedStaff)):
                                $displayStaff = !empty($staffResults) ? $staffResults : [$selectedStaff];
                                // If staff is selected, only show that one (User requirement)
                                if (!empty($selectedStaff)) {
                                    $displayStaff = [$selectedStaff];
                                }
                                ?>
                                <div class="list-group list-group-flush border rounded overflow-hidden shadow-sm">
                                    <?php foreach ($displayStaff as $row): ?>
                                        <a href="?staff_id=<?php echo $row['id']; ?>&staff_search=<?php echo urlencode($staff_search); ?>&student_id=<?php echo $student_id; ?>&search=<?php echo urlencode($search); ?>"
                                            class="list-group-item list-group-item-action p-3 d-flex justify-content-between align-items-center <?php echo $staff_id == $row['id'] ? 'bg-success bg-opacity-10 fw-bold border-success border-start border-4' : ''; ?>">
                                            <div>
                                                <h6 class="mb-1 <?php echo $staff_id == $row['id'] ? 'text-success' : ''; ?>">
                                                    <?php echo htmlspecialchars($row['name'] ?? ''); ?></h6>
                                                <small
                                                    class="text-muted"><?php echo $row['role_id'] == ROLE_PRINCIPLE ? 'Principal' : 'Counsellor'; ?></small>
                                            </div>
                                            <?php if ($staff_id == $row['id']): ?>
                                                <span class="badge bg-success rounded-pill"><i class="fas fa-check me-1"></i>
                                                    Selected</span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Step 3: Appointment Details -->
                <?php if ($selectedStudent && ($selectedStaff || !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_SUPER_ADMIN))): ?>
                    <div class="card card-enhanced border-0 shadow-lg overflow-hidden animate__animated animate__fadeInUp">
                        <div class="card-header bg-gradient-primary text-white p-4 border-0">
                            <div class="d-flex align-items-center">
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 me-3">
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                                <div>
                                    <h5 class="card-title text-white mb-0">Finalize Appointment</h5>
                                    <small class="text-white text-opacity-75">
                                        For: <?php echo htmlspecialchars($selectedStudent['name'] ?? ''); ?>
                                        <?php if ($selectedStaff): ?>
                                            | Assigned to: <?php echo htmlspecialchars($selectedStaff['name'] ?? ''); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <form action="appointment-save.php" method="POST" id="appointmentForm">
                            <input type="hidden" name="student_id" value="<?php echo $selectedStudent['id']; ?>">
                            <input type="hidden" name="staff_id"
                                value="<?php echo $selectedStaff ? $selectedStaff['id'] : $staff_id; ?>">

                            <div class="card-body p-4 p-lg-5">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold"><i
                                                class="fas fa-calendar-day text-muted me-2"></i>Appointment Date <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i
                                                    class="fas fa-calendar-alt text-muted"></i></span>
                                            <input type="date" name="appointment_date" class="form-control border-start-0"
                                                required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold"><i
                                                class="fas fa-clock text-muted me-2"></i>Appointment Time <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i
                                                    class="fas fa-clock text-muted"></i></span>
                                            <input type="time" name="appointment_time" class="form-control border-start-0"
                                                required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold"><i
                                                class="fas fa-sticky-note text-muted me-2"></i>Notes</label>
                                        <textarea name="notes" class="form-control" rows="4"
                                            placeholder="Mention the purpose or any specific instructions for this appointment..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-light p-4 d-flex justify-content-end gap-3 border-0">
                                <a href="appointment-add.php" class="btn btn-outline-secondary px-4">
                                    <i class="fas fa-sync me-1"></i> Start Over
                                </a>
                                <button type="submit" class="btn btn-primary px-5 shadow-sm">
                                    <i class="fas fa-paper-plane me-1"></i> Schedule Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-light rounded-circle d-inline-flex p-4 mb-3">
                            <i class="fas fa-clipboard-list fa-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">
                            <?php
                            if (!$selectedStudent)
                                echo "Step 1: Search and select a student";
                            else if (!$selectedStaff && (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN)))
                                echo "Step 2: Search and select a staff member";
                            ?>
                        </h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .card-enhanced {
        border-radius: 1.25rem !important;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card-enhanced:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
    }

    .input-group-text {
        color: #6c757d;
        background-color: #f8f9fa;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1);
    }

    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 0.5rem;
        min-height: 45px;
        display: flex;
        align-items: center;
    }
</style>

<script>
    $(document).ready(function () {
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap-5',
                placeholder: 'Search and select...',
                width: '100%'
            });
        }
    });
</script>

<?php include '../../include/footer.php'; ?>