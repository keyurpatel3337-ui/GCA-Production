<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is authorized (Counsellor, Principle, or Super Admin)
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Check if appointment ID is provided
if (!isset($_POST['id'])) {
    set_flash_message('error', "Appointment ID is required");
    header('Location: appointments.php');
    exit;
}

$appointment_id = $_POST['id'];
$page_title = "Appointment Details" ;
$page_breadcrumb = "Details -";

// Get appointment details
try {
    $stmt = $conn->prepare("SELECT a.*, 
                           s.surname, s.student_name, s.mob, s.aadhaar,
                           u.name as counsellor_name, u.email as counsellor_email, u.phone as counsellor_phone
                           FROM tbl_appointments a
                           INNER JOIN tbl_gm_std_registration s ON a.student_id = s.id
                           LEFT JOIN tbl_users u ON a.counsellor_id = u.id
                           WHERE a.id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        set_flash_message('error', "Appointment not found");
        header('Location: appointments.php');
        exit;
    }

    // If counsellor, verify they can only view their own appointments
    if (hasRole(ROLE_COUNSELLOR) && $appointment['counsellor_id'] != $_SESSION['user_id']) {
        set_flash_message('error', "You can only view your own appointments");
        header('Location: appointments.php');
        exit;
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Appointment Details");
    set_flash_message('error', "Error fetching appointment details");
    header('Location: appointments.php');
    exit;
}

// Get status badge class
$status_classes = [
    'pending' => 'warning',
    'confirmed' => 'info',
    'completed' => 'success',
    'cancelled' => 'danger'
];
$badge_class = $status_classes[$appointment['status']] ?? 'secondary';
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-check"></i> Appointment Information
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-<?php echo $badge_class; ?> badge-lg">
                                <?php echo strtoupper($appointment['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="fas fa-calendar"></i> Date & Time</h5>
                                <p class="text-muted">
                                    <strong>Date:</strong>
                                    <?php echo date('d-M-Y', strtotime($appointment['appointment_date'])); ?><br>
                                    <strong>Time:</strong>
                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-info-circle"></i> Status</h5>
                                <p>
                                    <span class="badge badge-<?php echo $badge_class; ?> p-2">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-12">
                                <h5><i class="fas fa-clipboard-list"></i> Purpose</h5>
                                <p class="text-muted">
                                    <?php echo $appointment['purpose'] ? nl2br(htmlspecialchars($appointment['purpose'] ?? '')) : '<em>No purpose specified</em>'; ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($appointment['notes'])): ?>
                            <hr>
                            <div class="row">
                                <div class="col-md-12">
                                    <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                                    <p class="text-muted">
                                        <?php echo nl2br(htmlspecialchars($appointment['notes'] ?? '')); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="row">
                            <div class="col-md-12">
                                <p class="text-muted small">
                                    <strong>Created:</strong>
                                    <?php echo date('d-M-Y h:i A', strtotime($appointment['created_at'])); ?><br>
                                    <strong>Last Updated:</strong>
                                    <?php echo date('d-M-Y h:i A', strtotime($appointment['updated_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Appointments
                        </a>
                        <?php if (hasRole(ROLE_COUNSELLOR) && $appointment['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-info" onclick="confirmAppointment()">
                                <i class="fas fa-check"></i> Confirm
                            </button>
                            <button type="button" class="btn btn-danger" onclick="cancelAppointment()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        <?php endif; ?>
                        <?php if (hasRole(ROLE_COUNSELLOR) && $appointment['status'] === 'confirmed'): ?>
                            <button type="button" class="btn btn-success" onclick="completeAppointment()">
                                <i class="fas fa-check-circle"></i> Mark as Completed
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Student Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-graduate"></i> Student Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($appointment['surname'] . ' ' . $appointment['student_name']); ?>&size=100&background=random"
                                alt="Student" class="img-circle elevation-2" style="width: 100px; height: 100px;">
                        </div>
                        <table class="table table-sm">
                            <tr>
                                <th class="css-appointment-details-8e0008">Name:</th>
                                <td><?php echo htmlspecialchars($appointment['surname'] . ' ' . $appointment['student_name'] ?? ''); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Mobile:</th>
                                <td><?php echo htmlspecialchars($appointment['mob'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Aadhaar:</th>
                                <td><?php echo htmlspecialchars($appointment['aadhaar'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                        <form method="POST" action="../students/details.php" class="css-appointment-details-46dcee">
                            <input type="hidden" name="id" value="<?php echo $appointment['student_id']; ?>">
                            <button type="submit" class="btn btn-primary btn-block btn-sm">
                                <i class="fas fa-eye"></i> View Full Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Counsellor Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-tie"></i> Counsellor Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th class="css-appointment-details-8e0008">Name:</th>
                                <td><?php echo htmlspecialchars($appointment['counsellor_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($appointment['counsellor_email'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo htmlspecialchars($appointment['counsellor_phone'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    function confirmAppointment() {
        if (confirm('Confirm this appointment?')) {
            updateAppointmentStatus('confirmed');
        }
    }

    function cancelAppointment() {
        if (confirm('Cancel this appointment?')) {
            updateAppointmentStatus('cancelled');
        }
    }

    function completeAppointment() {
        if (confirm('Mark this appointment as completed?')) {
            updateAppointmentStatus('completed');
        }
    }

    function updateAppointmentStatus(status) {
        // Collect buttons to handle loading states
        const confirmBtn = $('button[onclick="confirmAppointment()"]');
        const cancelBtn = $('button[onclick="cancelAppointment()"]');
        const completeBtn = $('button[onclick="completeAppointment()"]');

        // Disable all and show loading on the clicked one (simple way for now)
        [confirmBtn, cancelBtn, completeBtn].forEach(btn => btn.prop('disabled', true));

        $.api.post('students/appointment-status', {
            appointment_id: <?php echo $appointment_id; ?>,
            status: status
        }).then(response => {
            if (response.success) {
                if (typeof showToast === 'function') {
                    showToast('success', 'Success', response.message);
                }
                setTimeout(() => location.reload(), 2000);
            } else {
                [confirmBtn, cancelBtn, completeBtn].forEach(btn => btn.prop('disabled', false));
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', response.message || 'Failed to update appointment status');
                } else {
                    alert(response.message || 'Failed to update appointment status');
                }
            }
        }).catch(error => {
            console.error('API Error:', error);
            [confirmBtn, cancelBtn, completeBtn].forEach(btn => btn.prop('disabled', false));
            if (typeof showToast === 'function') {
                showToast('error', 'Error', error.message || 'Error updating appointment status');
            } else {
                alert(error.message || 'Error updating appointment status');
            }
        });
    }
</script>
