<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student (either regular login or student-specific login)
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$student_id = $is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null);

if (!$is_student_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Book Appointment" ;
$page_breadcrumb = "Appointment -";

// Get counsellors list
try {
    $stmt = $conn->query("SELECT u.id, u.name, u.email, u.phone 
                          FROM tbl_users u 
                          WHERE u.role_id = " . ROLE_COUNSELLOR . " AND u.status = 'active'
                          ORDER BY u.name");
    $counsellors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Counsellors");
    $counsellors = [];
}

// Get student's assigned counsellor
try {
    $stmt = $conn->prepare("SELECT counsellor_id FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$student_id]);
    $assigned_counsellor = $stmt->fetch(PDO::FETCH_ASSOC);
    $assigned_counsellor_id = $assigned_counsellor['counsellor_id'] ?? null;
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Assigned Counsellor");
    $assigned_counsellor_id = null;
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Appointment Details</h3>
                    </div>
                    <form id="appointmentForm">
                        <div class="card-body">
                            <?php if ($assigned_counsellor_id): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> You have an assigned counsellor. We recommend booking with them.
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Select Counsellor <span class="text-danger">*</span></label>
                                <select name="counsellor_id" class="form-control" required>
                                    <option value="">-- Select Counsellor --</option>
                                    <?php foreach ($counsellors as $counsellor): ?>
                                        <option value="<?php echo $counsellor['id']; ?>"
                                            <?php echo ($counsellor['id'] == $assigned_counsellor_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($counsellor['name'] ?? ''); ?>
                                            <?php echo ($counsellor['id'] == $assigned_counsellor_id) ? ' (Assigned)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Preferred Date <span class="text-danger">*</span></label>
                                <input type="date" name="appointment_date" class="form-control"
                                    min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Preferred Time <span class="text-danger">*</span></label>
                                <select name="appointment_time" class="form-control" required>
                                    <option value="">-- Select Time --</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="12:00">12:00 PM</option>
                                    <option value="14:00">02:00 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="16:00">04:00 PM</option>
                                    <option value="17:00">05:00 PM</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Purpose of Appointment <span class="text-danger">*</span></label>
                                <select name="purpose" class="form-control" required>
                                    <option value="">-- Select Purpose --</option>
                                    <option value="Career Guidance">Career Guidance</option>
                                    <option value="Course Selection">Course Selection</option>
                                    <option value="Academic Issues">Academic Issues</option>
                                    <option value="Personal Counselling">Personal Counselling</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Additional Notes</label>
                                <textarea name="notes" class="form-control" rows="3"
                                    placeholder="Please provide any additional information..."></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> Book Appointment
                            </button>
                            <a href="my-appointments.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">Important Information</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Appointment requests are subject to counsellor availability</li>
                            <li><i class="fas fa-check text-success"></i> You will receive confirmation within 24 hours</li>
                            <li><i class="fas fa-check text-success"></i> Please arrive 5 minutes early</li>
                            <li><i class="fas fa-check text-success"></i> You can reschedule or cancel up to 2 hours before</li>
                        </ul>
                    </div>
                </div>

                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">Contact Information</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Office Hours:</strong><br>
                            Monday - Friday: 9:00 AM - 5:00 PM<br>
                            Saturday: 9:00 AM - 1:00 PM</p>
                        <p><strong>Emergency Contact:</strong><br>
                            Phone: +91-XXXXXXXXXX</p>
                    </div>
                </div>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Appointment Form Handler
        $('#appointmentForm').on('submit', function(e) {
            e.preventDefault();

            const appointmentDate = $('input[name="appointment_date"]').val();
            const appointmentTime = $('input[name="appointment_time"]').val();

            if (!appointmentDate || !appointmentTime) {
                showToast('warning', 'Warning', 'Please select both date and time for appointment');
                return false;
            }

            $.api.post('student-portal/appointment-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => {
                            window.location.href = 'my-appointments.php';
                        }, 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to book appointment'));
        });
    });
</script>
