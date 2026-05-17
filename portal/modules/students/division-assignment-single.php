<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if user is Super Admin or Principal
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Assign Division & Roll Number";
$page_breadcrumb = "Number -";

// Get student ID
if (!isset($_POST['id'])) {
    set_flash_message('error', "Student enrollment ID is required.");
    header('Location: division-assignment.php');
    exit;
}

$enrollment_id = $_POST['id'];

// Fetch student details
try {
    $sql = "SELECT e.*, 
                           s.student_name, s.surname, s.fathers_name, s.mob, s.aadhaar, s.dob, s.gender,
                           c.course_name,
                           g.group_name,
                           d.division_name,
                           b.board_name,
                           m.medium_name
                           FROM tbl_enrolled_students e
                           LEFT JOIN tbl_gm_std_registration s ON e.registration_id = s.id
                           LEFT JOIN tbl_courses c ON s.course_id = c.id
                           LEFT JOIN tbl_group g ON s.group_id = g.id
                           LEFT JOIN tbl_division d ON e.division_id = d.id
                           LEFT JOIN tbl_boards b ON s.board_id = b.id
                           LEFT JOIN tbl_medium m ON s.medium_id = m.id
                           WHERE e.enrollment_id = ? OR e.registration_id = ?";
    $student = $dbOps->customSelectOne($sql, [$enrollment_id, $enrollment_id]);

    if (!$student) {
        set_flash_message('error', "Student not found.");
        header('Location: division-assignment.php');
        exit;
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Student for Division Assignment");
    set_flash_message('error', "Error fetching student details.");
    header('Location: division-assignment.php');
    exit;
}

// Fetch available course-division mappings for this student's course and group
try {
    $sql = "SELECT cd.*, 
                                     d.division_name,
                                     c.course_name,
                                     g.group_name
                                     FROM tbl_course_division cd
                                     LEFT JOIN tbl_division d ON cd.division_id = d.id
                                     LEFT JOIN tbl_courses c ON cd.course_id = c.id
                                     LEFT JOIN tbl_group g ON cd.group_id = g.id
                                     WHERE cd.course_id = ? 
                                     AND cd.group_id = ? 
                                     AND cd.is_active = 1
                                     ORDER BY d.display_order";
    $available_divisions = $dbOps->customSelect($sql, [$student['course_id'], $student['group_id']]);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Available Divisions");
    $available_divisions = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="row">
            <!-- Student Information -->
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user"></i> Student Information</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$student['student_name']): ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Warning: Incomplete Student Data</h5>
                                <p class="mb-0">The registration record for this student (Student ID:
                                    <?php echo $student['registration_id']; ?>) is missing or incomplete.
                                    Please contact the administrator to resolve this data integrity issue.
                                </p>
                            </div>
                        <?php endif; ?>

                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Enrollment No</th>
                                <td><strong><?php echo htmlspecialchars($student['enrollment_no'] ?? 'N/A'); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Student Name</th>
                                <td>
                                    <?php if ($student['student_name']): ?>
                                        <strong><?php echo htmlspecialchars(trim(($student['surname'] ?? '') . ' ' . $student['student_name'])); ?></strong>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Data
                                            Missing</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Father's Name</th>
                                <td><?php echo htmlspecialchars($student['fathers_name'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th>Mobile</th>
                                <td><?php echo htmlspecialchars($student['mob'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th>DOB</th>
                                <td><?php echo $student['dob'] ? date('d M Y', strtotime($student['dob'])) : '—'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Gender</th>
                                <td><?php echo htmlspecialchars($student['gender'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th>Standard</th>
                                <td><span
                                        class="badge bg-info text-dark"><?php echo htmlspecialchars($student['course_name'] ?? 'Not Assigned'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Board</th>
                                <td><?php echo htmlspecialchars($student['board_name'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th>Medium</th>
                                <td><?php echo htmlspecialchars($student['medium_name'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th>Group</th>
                                <td><span
                                        class="badge bg-success"><?php echo htmlspecialchars($student['group_name'] ?? 'Not Assigned'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Enrollment Date</th>
                                <td><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></td>
                            </tr>
                        </table>

                        <!-- Current Assignment -->
                        <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 5px;">
                            <h5 class="mb-3"><i class="fas fa-info-circle"></i> Current Assignment</h5>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Division:</strong><br>
                                    <?php if ($student['division_id']): ?>
                                        <span class="badge bg-success badge-lg">
                                            <?php echo htmlspecialchars($student['division_name'] ?? 'Unknown'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Assigned</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <strong>Roll Number:</strong><br>
                                    <?php if ($student['roll_no']): ?>
                                        <span class="badge bg-primary badge-lg">
                                            <?php echo $student['roll_no']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Assigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Form -->
            <div class="col-md-7">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Assign/Update Division & Roll Number</h3>
                    </div>
                    <form id="divisionAssignmentForm">
                        <input type="hidden" name="enrollment_id" value="<?php echo $enrollment_id; ?>">

                        <div class="card-body">
                            <?php if (isset($_SESSION['error_msg'])): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo gca_safe_html($_SESSION['error_msg']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Assignment Method <span class="text-danger">*</span></label>
                                <select name="assignment_method" id="assignmentMethod" class="form-control" required>
                                    <option value="">-- Select Method --</option>
                                    <option value="auto">Auto-assign (Next available roll number)</option>
                                    <option value="manual">Manual (Specify division and roll number)</option>
                                </select>
                            </div>

                            <!-- Auto Assignment Section -->
                            <div id="autoAssignSection" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> The system will automatically assign the next available roll number from the selected division.
                                </div>

                                <div class="form-group">
                                    <label>Select Division <span class="text-danger">*</span></label>
                                    <select name="course_division_id" id="courseDivisionAuto" class="form-control">
                                        <option value="">-- Select Division --</option>
                                        <?php foreach ($available_divisions as $div): ?>
                                            <option value="<?php echo $div['id']; ?>"
                                                <?php echo ($student['division_id'] == $div['division_id']) ? 'selected' : ''; ?>
                                                data-current="<?php echo $div['current_roll_no']; ?>"
                                                data-start="<?php echo $div['start_roll_no']; ?>"
                                                data-total="<?php echo $div['total_students']; ?>"
                                                data-max="<?php echo $div['max_capacity'] ?? ''; ?>">
                                                Division <?php echo htmlspecialchars($div['division_name'] ?? ''); ?>
                                                (Next Roll: <?php echo ($div['current_roll_no'] + 1); ?>,
                                                Students: <?php echo $div['total_students']; ?>
                                                <?php if ($div['max_capacity']): ?>
                                                    / <?php echo $div['max_capacity']; ?>
                                                <?php endif; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="autoAssignInfo" class="alert alert-success" style="display: none;">
                                    <strong>Next Roll Number:</strong> <span id="nextRollNumber"></span>
                                </div>

                                <div id="capacityWarningAuto" class="alert alert-warning" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> <span id="warningTextAuto"></span>
                                </div>
                            </div>

                            <!-- Manual Assignment Section -->
                            <div id="manualAssignSection" style="display: none;">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Caution:</strong> Manual assignment may cause roll number conflicts. Use only if necessary.
                                </div>

                                <div class="form-group">
                                    <label>Select Division <span class="text-danger">*</span></label>
                                    <select name="division_id_manual" id="divisionManual" class="form-control">
                                        <option value="">-- Select Division --</option>
                                        <?php foreach ($available_divisions as $div): ?>
                                            <option value="<?php echo $div['division_id']; ?>"
                                                <?php echo ($student['division_id'] == $div['division_id']) ? 'selected' : ''; ?>
                                                data-current="<?php echo $div['current_roll_no']; ?>"
                                                data-total="<?php echo $div['total_students']; ?>"
                                                data-max="<?php echo $div['max_capacity'] ?? ''; ?>"
                                                data-cd-id="<?php echo $div['id']; ?>">
                                                Division <?php echo htmlspecialchars($div['division_name'] ?? ''); ?>
                                                (Current Max Roll: <?php echo $div['current_roll_no']; ?>,
                                                Students: <?php echo $div['total_students']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="course_division_id_manual" id="courseDivisionManual">
                                </div>

                                <div class="form-group">
                                    <label>Roll Number <span class="text-danger">*</span></label>
                                    <input type="number" name="roll_no_manual" id="rollNoManual" class="form-control"
                                        value="<?php echo $student['roll_no'] ?? ''; ?>" min="1"
                                        placeholder="Enter roll number">
                                    <small class="text-muted">Note: Ensure this roll number doesn't conflict with existing assignments.</small>
                                </div>

                                <div id="rollConflictWarning" class="alert alert-danger" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i> <span id="conflictText"></span>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                                <i class="fas fa-save"></i> Assign Division & Roll Number
                            </button>
                            <a href="division-assignment.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Available Divisions Info -->
                <?php if (!empty($available_divisions)): ?>
                    <div class="card">
                        <div class="card-header bg-info">
                            <h3 class="card-title"><i class="fas fa-list"></i> Available Divisions Summary</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Division</th>
                                        <th>Current Roll</th>
                                        <th>Total Students</th>
                                        <th>Max Capacity</th>
                                        <th>Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_divisions as $div): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($div['division_name'] ?? ''); ?></strong></td>
                                            <td><?php echo $div['current_roll_no']; ?></td>
                                            <td><?php echo $div['total_students']; ?></td>
                                            <td><?php echo $div['max_capacity'] ?? 'Unlimited'; ?></td>
                                            <td>
                                                <?php
                                                if ($div['max_capacity']) {
                                                    $available = $div['max_capacity'] - $div['total_students'];
                                                    echo $available > 0 ? "<span class='badge bg-success'>{$available}</span>" : "<span class='badge bg-danger'>Full</span>";
                                                } else {
                                                    echo "<span class='badge bg-info text-dark'>Unlimited</span>";
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        var currentDivisionId = <?php echo $student['division_id'] ?? 'null'; ?>;
        var currentRollNo = <?php echo $student['roll_no'] ?? 'null'; ?>;

        // Assignment method change
        $('#assignmentMethod').on('change', function () {
            var method = $(this).val();

            $('#autoAssignSection').hide();
            $('#manualAssignSection').hide();
            $('#submitBtn').prop('disabled', true);

            if (method === 'auto') {
                $('#autoAssignSection').show();
                $('#courseDivisionAuto').attr('required', true);
                $('#divisionManual').removeAttr('required');
                $('#rollNoManual').removeAttr('required');
            } else if (method === 'manual') {
                $('#manualAssignSection').show();
                $('#divisionManual').attr('required', true);
                $('#rollNoManual').attr('required', true);
                $('#courseDivisionAuto').removeAttr('required');
            }

            checkFormValidity();
        });

        // Auto assignment division change
        $('#courseDivisionAuto').on('change', function () {
            var selected = $(this).find('option:selected');

            if (selected.val()) {
                var nextRoll = parseInt(selected.data('current')) + 1;
                var maxCapacity = parseInt(selected.data('max')) || 0;
                var totalStudents = parseInt(selected.data('total')) || 0;

                $('#nextRollNumber').text(nextRoll);
                $('#autoAssignInfo').show();

                // Check capacity
                if (maxCapacity > 0 && totalStudents >= maxCapacity) {
                    $('#capacityWarningAuto').show();
                    $('#warningTextAuto').html('This division has reached maximum capacity (' + maxCapacity + ').');
                    $('#submitBtn').prop('disabled', true);
                } else {
                    $('#capacityWarningAuto').hide();
                    $('#submitBtn').prop('disabled', false);
                }
            } else {
                $('#autoAssignInfo').hide();
                $('#capacityWarningAuto').hide();
                $('#submitBtn').prop('disabled', true);
            }

            checkFormValidity();
        });

        // Manual assignment division change
        $('#divisionManual').on('change', function () {
            var selected = $(this).find('option:selected');
            var cdId = selected.data('cd-id');
            $('#courseDivisionManual').val(cdId);

            checkRollConflict();
            checkFormValidity();
        });

        // Manual roll number input
        $('#rollNoManual').on('input', function () {
            checkRollConflict();
            checkFormValidity();
        });

        // Division Assignment Form Handler
        $('#divisionAssignmentForm').on('submit', function (e) {
            e.preventDefault();

            $.api.post('students/division-assignment-single-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => {
                            window.location.href = 'list.php';
                        }, 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to assign division'));
        });

        function checkRollConflict() {
            var divisionId = $('#divisionManual').val();
            var rollNo = $('#rollNoManual').val();

            if (divisionId && rollNo) {
                // Skip check if same as current assignment
                if (currentDivisionId == divisionId && currentRollNo == rollNo) {
                    $('#rollConflictWarning').hide();
                    return;
                }

                // Check for low roll numbers that may conflict
                var currentMax = parseInt($('#divisionManual option:selected').data('current')) || 0;
                if (parseInt(rollNo) <= currentMax) {
                    $('#rollConflictWarning').show();
                    $('#conflictText').html('Warning: Roll number ' + rollNo + ' may already be assigned. Current max: ' + currentMax);
                } else {
                    $('#rollConflictWarning').hide();
                }
            } else {
                $('#rollConflictWarning').hide();
            }
        }

        function checkFormValidity() {
            var method = $('#assignmentMethod').val();
            var isValid = false;

            if (method === 'auto') {
                isValid = $('#courseDivisionAuto').val() !== '';
                var maxCapacity = parseInt($('#courseDivisionAuto option:selected').data('max')) || 0;
                var totalStudents = parseInt($('#courseDivisionAuto option:selected').data('total')) || 0;
                if (maxCapacity > 0 && totalStudents >= maxCapacity) {
                    isValid = false;
                }
            } else if (method === 'manual') {
                isValid = $('#divisionManual').val() !== '' && $('#rollNoManual').val() !== '';
            }

            $('#submitBtn').prop('disabled', !isValid);
        }

        // Trigger initial check if editing
        <?php if ($student['division_id']): ?>
            $('#assignmentMethod').val('manual').trigger('change');
        <?php endif; ?>
    });
</script>