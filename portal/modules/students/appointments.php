<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once PAGINATION_FILE;

// Check access
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Appointments";
$page_breadcrumb = "Appointments";
$counsellor_id = $_SESSION['user_id'];

// Handle POST pagination and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['appointments_pagination']);
    } else {
        $savedPagination = $_SESSION['appointments_pagination'] ?? [];
        $_SESSION['appointments_pagination'] = [
            'page' => $_POST['page'] ?? 1,
            'per_page' => $_POST['per_page'] ?? ($savedPagination['per_page'] ?? 10)
        ];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get pagination parameters from session
$savedPagination = $_SESSION['appointments_pagination'] ?? [];
$page = max(1, (int) ($savedPagination['page'] ?? 1));
$perPage = max(1, min(100, (int) ($savedPagination['per_page'] ?? 10)));
$offset = ($page - 1) * $perPage;

// Get appointments with pagination
try {
    if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)) {
        // Reception/Admin can see all appointments
        $sql = "SELECT a.*, s.surname, s.student_name, s.mob, u.name as counsellor_name
                FROM tbl_appointments a
                INNER JOIN tbl_gm_std_registration s ON a.student_id = s.id
                LEFT JOIN tbl_users u ON a.counsellor_id = u.id
                ORDER BY a.id ASC
                LIMIT ? OFFSET ?";
        $appointments = $dbOps->customSelect($sql, [$perPage, $offset]);

        $countSql = "SELECT COUNT(*) as total FROM tbl_appointments";
        $countResult = $dbOps->customSelect($countSql);
        $totalRecords = $countResult[0]['total'] ?? 0;
    } else {
        // Others see only their own
        $sql = "SELECT a.*, s.surname, s.student_name, s.mob 
                FROM tbl_appointments a
                INNER JOIN tbl_gm_std_registration s ON a.student_id = s.id
                WHERE a.counsellor_id = ?
                ORDER BY a.id ASC
                LIMIT ? OFFSET ?";
        $appointments = $dbOps->customSelect($sql, [$counsellor_id, $perPage, $offset]);

        $countSql = "SELECT COUNT(*) as total FROM tbl_appointments WHERE counsellor_id = ?";
        $countResult = $dbOps->customSelect($countSql, [$counsellor_id]);
        $totalRecords = $countResult[0]['total'] ?? 0;
    }
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Appointments");
    $appointments = [];
    $totalRecords = 0;
    $totalPages = 1;
}

// Get students for appointment booking
try {
    if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)) {
        $sql = "SELECT id, CONCAT(surname, ' ', student_name) as name, mob 
                FROM tbl_gm_std_registration 
                WHERE status = 1 
                ORDER BY surname, student_name";
        $my_students = $dbOps->customSelect($sql);
    } else {
        $sql = "SELECT id, CONCAT(surname, ' ', student_name) as name, mob 
                FROM tbl_gm_std_registration 
                WHERE counsellor_id = ? AND status = 1 
                ORDER BY surname, student_name";
        $my_students = $dbOps->customSelect($sql, [$counsellor_id]);
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Students for Appointments");
    $my_students = [];
}

// Fetch counsellors for Reception/Admin
$counsellors = [];
if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN)) {
    try {
        $counsellors = $dbOps->select('tbl_users', ['id', 'name'], ['role_id' => ROLE_COUNSELLOR, 'status' => 'active'], 'name ASC');
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Counsellors for Appointments");
    }
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

    <section class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-end mb-3">
                <?php if (hasRole(ROLE_RECEPTION)): ?>
                    <a href="appointment-add.php" class="btn btn-primary shadow-sm rounded-pill px-4">
                        <i class="fas fa-plus me-1"></i> Schedule Appointment
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal"
                        data-bs-target="#addAppointmentModal">
                        <i class="fas fa-plus me-1"></i> Schedule Appointment
                    </button>
                <?php endif; ?>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h3 class="card-title fw-bold">Appointment List</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="appointmentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Student Name</th>
                                    <th>Mobile</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <?php if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
                                        <th>Assigned To</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($appointments)): ?>
                                    <?php foreach ($appointments as $apt): ?>
                                        <tr>
                                            <td><?php echo $apt['id']; ?></td>
                                            <td><?php echo htmlspecialchars($apt['surname'] . ' ' . $apt['student_name'] ?? ''); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($apt['mob'] ?? ''); ?></td>
                                            <td><?php echo isset($apt['appointment_date']) ? date('d M Y', strtotime($apt['appointment_date'])) : 'N/A'; ?>
                                            </td>
                                            <td><?php echo isset($apt['appointment_time']) ? date('h:i A', strtotime($apt['appointment_time'])) : 'N/A'; ?>
                                            </td>
                                            <?php if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
                                                <td><?php echo htmlspecialchars($apt['counsellor_name'] ?? 'Unassigned'); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <?php
                                                $status = $apt['status'] ?? 'pending';
                                                $badges = [
                                                    'pending' => 'bg-warning',
                                                    'confirmed' => 'bg-success',
                                                    'completed' => 'bg-info',
                                                    'cancelled' => 'bg-danger'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $badges[$status] ?? 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($apt['notes'] ?? ''); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info"
                                                    onclick="editAppointment(<?php echo htmlspecialchars(json_encode($apt) ?? ''); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="deleteAppointment(<?php echo $apt['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)) ? 9 : 8; ?>" class="text-center">No appointments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="mt-3">
                            <?php
                            echo renderPaginationPost($page, $totalPages);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </section>
</div>

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Schedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAppointmentForm" method="POST" action="appointment-save.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-control select2" required>
                            <option value="">Select Student</option>
                            <?php foreach ($my_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['mob'] . ')' ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (hasRole(ROLE_RECEPTION) || hasRole(ROLE_SUPER_ADMIN)): ?>
                        <div class="mb-3">
                            <label>Assign to Counsellor <span class="text-danger">*</span></label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">Select Counsellor</option>
                                <?php foreach ($counsellors as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="appointment_date" class="form-control" required
                            min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label>Time <span class="text-danger">*</span></label>
                        <input type="time" name="appointment_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-edit"></i> Edit Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAppointmentForm" method="POST" action="appointment-save.php">
                <input type="hidden" name="id" id="edit_apt_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="appointment_date" id="edit_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Time <span class="text-danger">*</span></label>
                        <input type="time" name="appointment_time" id="edit_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editAppointment(apt) {
            $('#edit_apt_id').val(apt.id);
            $('#edit_date').val(apt.appointment_date);
            $('#edit_time').val(apt.appointment_time);
            $('#edit_status').val(apt.status || 'pending');
            $('#edit_notes').val(apt.notes || '');
            $('#editAppointmentModal').modal('show');
        }

        function deleteAppointment(id) {
            if (confirm('Are you sure you want to delete this appointment?')) {
                // Create a form and submit via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'appointment-delete.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <?php include '../../include/footer.php'; ?>