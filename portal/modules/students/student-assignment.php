<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    http_response_code(403);
    set_flash_message('error', "Access denied. You don't have permission to assign counsellors.");
    header('Location: ../dashboard/dashboard.php');
    exit;
}

$page_title = "Assign Students to Counsellors";
$page_breadcrumb = "Counsellors -";

// Initialize Operation class
$dbOps = new Operation();

// Get all counsellors - SQL INJECTION SAFE
try {
    $counsellors = $dbOps->select('tbl_users', ['id', 'name', 'email'], [
        'role_id' => ROLE_COUNSELLOR,
        'status' => 'active'
    ], 'name');

    // Ensure $counsellors is an array
    if ($counsellors === false || !is_array($counsellors)) {
        $counsellors = [];
    }
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Counsellors for Assignment");
    $counsellors = [];
}

// Get only unassigned students (pending counsellor assignment) - SQL INJECTION SAFE
try {
    $sql = "SELECT s.id, s.surname, s.student_name, s.fathers_name,
                  CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as full_name, s.mob
                  FROM tbl_gm_std_registration s 
                  WHERE s.status = 1 AND s.counsellor_id IS NULL
                  ORDER BY s.id ASC";

    $students = $dbOps->customSelect($sql);

    // Ensure $students is an array
    if ($students === false || !is_array($students)) {
        $students = [];
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Students for Assignment");
    $students = [];
} catch (Exception $e) {
    logAppError("Fetch Students for Assignment", $e->getMessage());
    $students = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid pb-5">

    <?php display_flash_messages(); ?>

    <!-- Stats Section -->
    <?php
    // Get pending assignment count - SQL INJECTION SAFE
    $pending_count = $dbOps->count('tbl_gm_std_registration', ['counsellor_id' => null, 'status' => 1]);

    // Get total counsellors
    $total_counsellors = count($counsellors);

    // Get total assigned students - SQL INJECTION SAFE
    $assigned_count = $dbOps->customSelectOne(
        "SELECT COUNT(*) as count FROM tbl_gm_std_registration WHERE counsellor_id IS NOT NULL AND status = 1"
    )['count'] ?? 0;
    ?>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body d-flex align-items-center p-4">
                    <div class="flex-grow-1">
                        <h6 class="text-muted text-uppercase fw-bold mb-1 css-student-assignment-1c323c">Pending Assignment</h6>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo $pending_count; ?></h2>
                    </div>
                    <div class="text-warning opacity-25 css-student-assignment-3f60e0"><i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body d-flex align-items-center p-4">
                    <div class="flex-grow-1">
                        <h6 class="text-muted text-uppercase fw-bold mb-1 css-student-assignment-1c323c">Students Assigned</h6>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo $assigned_count; ?></h2>
                    </div>
                    <div class="text-success opacity-25 css-student-assignment-3f60e0"><i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
                <div class="card-body d-flex align-items-center p-4">
                    <div class="flex-grow-1">
                        <h6 class="text-muted text-uppercase fw-bold mb-1 css-student-assignment-1c323c">Active Counsellors</h6>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo $total_counsellors; ?></h2>
                    </div>
                    <div class="text-info opacity-25 css-student-assignment-3f60e0"><i class="fas fa-users-cog"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <!-- Auto Assignment -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div
                    class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-magic text-primary me-2"></i>
                        Auto Assignment</h5>
                </div>
                <div class="card-body p-4">
                    <div id="autoAssignForm">
                        <div class="form-group mb-3">
                            <label class="form-label fw-bold small text-muted">Students Per Counsellor <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" id="studentsPerCounsellor" class="form-control" min="1" max="100"
                                    value="10" required placeholder="10">
                                <button type="button" class="btn btn-outline-secondary" onclick="showAutoAssignInfo()"
                                    title="Info">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Maximum students to assign per counsellor
                                randomly</small>
                        </div>
                        <button type="button" class="btn btn-primary w-100 py-2 fw-medium" id="autoAssignBtn">
                            <i class="fas fa-magic me-2"></i> Auto Assign Students
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Bulk Assignment -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-users text-info me-2"></i> Manual
                        Selection</h5>
                </div>
                <div class="card-body p-4">
                    <div id="bulkAssignForm">
                        <div class="form-group mb-3">
                            <label class="form-label fw-bold small text-muted">Select Target Counsellor <span
                                    class="text-danger">*</span></label>
                            <select id="bulkCounsellor" class="form-control" required>
                                <option value="">-- Choose Counsellor --</option>
                                <?php foreach ($counsellors as $counsellor): ?>
                                    <option value="<?php echo $counsellor['id']; ?>">
                                        <?php echo htmlspecialchars($counsellor['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info flex-grow-1 text-white fw-medium"
                                id="bulkAssignBtn">
                                <i class="fas fa-check me-1"></i> Assign Selected
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-light border" id="selectAll" title="Select All">
                                    <i class="fas fa-check-square"></i>
                                </button>
                                <button type="button" class="btn btn-light border" id="deselectAll"
                                    title="Deselect All">
                                    <i class="fas fa-square"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Select students from the table below first.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Mobile Assignment -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-mobile-alt text-dark me-2"></i> Bulk
                Assignment by Mobile</h5>
        </div>
        <div class="card-body p-4">
            <form id="mobileAssignForm">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Select Target Counsellor <span
                                class="text-danger">*</span></label>
                        <select name="mobile_counsellor_id" id="mobileCounsellor" class="form-control" required>
                            <option value="">-- Choose Counsellor --</option>
                            <?php foreach ($counsellors as $counsellor): ?>
                                <option value="<?php echo $counsellor['id']; ?>">
                                    <?php echo htmlspecialchars($counsellor['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold small text-muted">Mobile Numbers (Comma Separated) <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <textarea name="mobile_numbers" id="mobileNumbers" class="form-control css-student-assignment-6924dc" rows="1"
                                placeholder="9876543210, 9123456789..."></textarea>
                            <button type="button" class="btn btn-dark px-4" id="previewMobileBtn">
                                <i class="fas fa-search me-1"></i> Preview
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Preview Table (Hidden by default) -->
            <div id="mobilePreviewSection" class="mt-4 css-student-assignment-224b51">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Matched Students <span class="badge bg-primary rounded-pill ms-2"
                                    id="previewCount">0</span></h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-secondary"
                                    id="cancelMobileAssign">Cancel</button>
                                <button type="button" class="btn btn-sm btn-success" id="confirmMobileAssign"><i
                                        class="fas fa-check me-1"></i> Confirm</button>
                            </div>
                        </div>
                        <div class="table-responsive bg-white rounded shadow-sm">
                            <table class="table table-hover mb-0" id="mobilePreviewTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="mobilePreviewBody">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unassigned Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold mb-0 text-dark">Unassigned Students List</h5>
            <span class="badge bg-light text-dark border">Total: <?php echo count($students); ?></span>
        </div>
        <div class="card-body p-4">
            <?php if (empty($students)): ?>
                <div class="text-center py-5">
                    <div class="mb-3 text-muted opacity-50 display-1"><i class="fas fa-clipboard-check"></i></div>
                    <h5 class="text-muted">No unassigned students found.</h5>
                    <p class="text-muted small">All students have been assigned to counsellors.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="studentsTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="40" class="text-center">
                                    <input type="checkbox" id="checkAll" class="form-check-input">
                                </th>
                                <th>ID</th>
                                <th>Student Details</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if (empty($student['counsellor_id'])): ?>
                                            <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>"
                                                form="bulkAssignForm" class="student-checkbox form-check-input">
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fw-bold text-muted">#<?php echo $student['id']; ?></span></td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                                        </div>
                                        <div class="small text-muted"><i class="fas fa-phone-alt me-1 css-student-assignment-693f79"></i>
                                            <?php echo htmlspecialchars($student['mob'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($student['counsellor_name'])): ?>
                                            <span
                                                class="badge bg-info-subtle text-info border border-info-subtle"><?php echo htmlspecialchars($student['counsellor_name'] ?? ''); ?></span>
                                        <?php else: ?>
                                            <span
                                                class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (empty($student['counsellor_id'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="assignIndividual(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-plus me-1"></i> Assign
                                            </button>
                                        <?php else: ?>
                                            <span class="text-success small"><i class="fas fa-check-circle"></i> Assigned</span>
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
</div>

<!-- Individual Assignment Modal -->
<div class="modal fade" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Assign Counsellor</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal">&times;</button>
            </div>
            <form action="student-assignment-save.php" method="POST">
                <input type="hidden" name="student_id" id="assignStudentId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Counsellor <span class="text-danger">*</span></label>
                        <select name="counsellor_id" class="form-control" required>
                            <option value="">-- Select Counsellor --</option>
                            <?php foreach ($counsellors as $counsellor): ?>
                                <option value="<?php echo $counsellor['id']; ?>">
                                    <?php echo htmlspecialchars($counsellor['name'] ?? '') . ' (' . htmlspecialchars($counsellor['email'] ?? '') . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="action" value="individual_assign" class="btn btn-primary">
                        Assign Counsellor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        // Store matched students for assignment
        let matchedStudents = [];

        $(document).ready(function () {
            // Check all checkboxes
            $('#checkAll').on('click', function () {
                $('.student-checkbox').prop('checked', this.checked);
            });

            // Select all button
            $('#selectAll').on('click', function () {
                $('.student-checkbox').prop('checked', true);
                $('#checkAll').prop('checked', true);
            });

            // Deselect all button
            $('#deselectAll').on('click', function () {
                $('.student-checkbox').prop('checked', false);
                $('#checkAll').prop('checked', false);
            });

            // Auto assign button
            $('#autoAssignBtn').on('click', async function () {
                const studentsPerCounsellor = parseInt($('#studentsPerCounsellor').val()) || 10;

                if (typeof showConfirm === 'function') {
                    showConfirm({
                        title: 'Auto Assign Students',
                        message: `This will assign up to ${studentsPerCounsellor} students per counsellor. Continue?`,
                        confirmText: 'Yes, Auto Assign',
                        confirmButtonClass: 'btn-success',
                        onConfirm: async function () {
                            const autoAssignBtn = $('#autoAssignBtn');
                            const originalHtml = autoAssignBtn.html();
                            autoAssignBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Assigning...');

                            try {
                                const response = await api.autoAssignCounsellors(studentsPerCounsellor);
                                if (response.success) {
                                    if (typeof showToast === 'function') {
                                        showToast('success', 'Success', response.message || 'Students assigned successfully!');
                                    }
                                    setTimeout(() => location.reload(), 2000);
                                } else {
                                    autoAssignBtn.prop('disabled', false).html(originalHtml);
                                    showToast('error', 'Error', response.message || 'Failed to assign students');
                                }
                            } catch (error) {
                                autoAssignBtn.prop('disabled', false).html(originalHtml);
                                showToast('error', 'Error', 'An error occurred');
                            }
                        }
                    });
}
            });

            // Bulk assign button
            $('#bulkAssignBtn').on('click', async function () {
                const counsellorId = $('#bulkCounsellor').val();
                const studentIds = [];

                $('.student-checkbox:checked').each(function () {
                    studentIds.push($(this).val());
                });

                if (!counsellorId) {
                    showToast('error', 'Error', 'Please select a counsellor');
                    return;
                }

                if (studentIds.length === 0) {
                    showToast('error', 'Error', 'Please select at least one student');
                    return;
                }

                if (typeof showConfirm === 'function') {
                    showConfirm({
                        title: 'Confirm Assignment',
                        message: `Assign ${studentIds.length} student(s) to selected counsellor?`,
                        confirmText: 'Yes, Assign',
                        confirmButtonClass: 'btn-primary',
                        onConfirm: async function () {
                            const bulkAssignBtn = $('#bulkAssignBtn');
                            const originalHtml = bulkAssignBtn.html();
                            bulkAssignBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Assigning...');

                            try {
                                const response = await api.bulkAssignCounsellor(counsellorId, studentIds);
                                if (response.success) {
                                    if (typeof showToast === 'function') {
                                        showToast('success', 'Success', response.message || 'Students assigned successfully!');
                                    }
                                    setTimeout(() => location.reload(), 2000);
                                } else {
                                     bulkAssignBtn.prop('disabled', false).html(originalHtml);
                                    showToast('error', 'Error', response.message || 'Failed to assign students');
                                }
                            } catch (error) {
                                bulkAssignBtn.prop('disabled', false).html(originalHtml);
                                showToast('error', 'Error', 'An error occurred');
                            }
                        }
                    });
                }
            });

            // Preview mobile numbers
            $('#previewMobileBtn').on('click', async function () {
                const counsellorId = $('#mobileCounsellor').val();
                const mobileNumbers = $('#mobileNumbers').val().trim();

                if (!counsellorId) {
                    showToast('error', 'Error', 'Please select a counsellor first');
                    return;
                }

                if (!mobileNumbers) {
                    showToast('error', 'Error', 'Please enter mobile numbers');
                    return;
                }

                const previewMobileBtn = $('#previewMobileBtn');
                const originalHtml = previewMobileBtn.html();
                previewMobileBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Searching...');

                try {
                    const response = await api.previewStudentsByMobile(mobileNumbers);
                    previewMobileBtn.prop('disabled', false).html(originalHtml);
                    if (response.success) {
                        matchedStudents = response.data.students;
                        displayPreview(response.data.students, response.data.not_found);
                    } else {
                        showToast('error', 'Error', response.message || 'Failed to fetch students');
                    }
                } catch (error) {
                    previewMobileBtn.prop('disabled', false).html(originalHtml);
                    showToast('error', 'Error', 'An error occurred while fetching students');
                }
            });

            // Cancel preview
            $('#cancelMobileAssign').on('click', function () {
                $('#mobilePreviewSection').hide();
                matchedStudents = [];
            });

            // Confirm assignment
            $('#confirmMobileAssign').on('click', async function () {
                if (matchedStudents.length === 0) {
                    showToast('error', 'Error', 'No students to assign');
                    return;
                }

                const counsellorId = $('#mobileCounsellor').val();
                const studentIds = matchedStudents.map(s => s.id);

                if (typeof showConfirm === 'function') {
                    showConfirm({
                        title: 'Confirm Assignment',
                        message: `Are you sure you want to assign ${matchedStudents.length} student(s) to this counsellor?`,
                        confirmText: 'Yes, Assign',
                        confirmButtonClass: 'btn-success',
                        onConfirm: async function () {
                            const confirmMobileAssign = $('#confirmMobileAssign');
                            const originalHtml = confirmMobileAssign.html();
                            confirmMobileAssign.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Assigning...');

                            try {
                                const response = await api.bulkAssignCounsellor(counsellorId, studentIds);
                                if (response.success) {
                                    if (typeof showToast === 'function') {
                                        showToast('success', 'Success', response.message || 'Students assigned successfully!');
                                    }
                                    setTimeout(() => location.reload(), 2000);
                                } else {
                                     confirmMobileAssign.prop('disabled', false).html(originalHtml);
                                    showToast('error', 'Error', response.message || 'Failed to assign students');
                                }
                            } catch (error) {
                                confirmMobileAssign.prop('disabled', false).html(originalHtml);
                                showToast('error', 'Error', 'An error occurred');
                            }
                        }
                    });
                }
            });
        });

        // Display preview table
        function displayPreview(students, notFound) {
            const tbody = $('#mobilePreviewBody');
            tbody.empty();

            if (students.length === 0) {
                tbody.html('<tr><td colspan="4" class="text-center text-muted">No students found for the provided mobile numbers</td></tr>');
            } else {
                students.forEach(student => {
                    tbody.append(`
                    <tr>
                        <td>${student.id}</td>
                        <td>${escapeHtml(student.full_name)}</td>
                        <td>${escapeHtml(student.mob)}</td>
                        <td>
                            ${student.counsellor_id
                            ? '<span class="badge bg-warning">Already Assigned</span>'
                            : '<span class="badge bg-success">Available</span>'}
                        </td>
                    </tr>
                `);
                });
            }

            // Show not found numbers if any
            if (notFound && notFound.length > 0) {
                tbody.append(`
                <tr class="table-warning">
                    <td colspan="4">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Not Found:</strong> ${notFound.join(', ')}
                    </td>
                </tr>
            `);
            }

            $('#previewCount').text(students.length);
            $('#mobilePreviewSection').show();
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        // Assign individual student to counsellor
        function assignIndividual(studentId) {
            $('#assignStudentId').val(studentId);
            $('#assignModal').modal('show');
        }

        // Remove counsellor assignment
        function removeAssignment(studentId) {
            showConfirm({
                title: 'Remove Assignment',
                message: 'Are you sure you want to remove this counsellor assignment?',
                confirmText: 'Yes, Remove',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    window.location.href = 'student-assignment-save.php?action=remove&student_id=' + studentId;
                }
            });
        }

        // Show auto assign info
        function showAutoAssignInfo() {
            // Info shown via modal or simple alert
            const infoHtml = `
            <ul class="text-start">
                <li>System will fetch all active counsellors</li>
                <li>Get all unassigned students</li>
                <li>Randomly distribute students to counsellors</li>
                <li>Each counsellor will get maximum students as per the limit</li>
                <li>Distribution is balanced across all counsellors</li>
            </ul>
            <div class="alert alert-info mt-2">
                <i class="fas fa-info-circle"></i> Students already assigned will not be affected.
            </div>
        `;

            if (typeof showConfirm === 'function') {
                showConfirm({
                    title: 'Auto Assignment Process',
                    message: infoHtml,
                    confirmText: 'Got it',
                    confirmButtonClass: 'btn-info',
                    onConfirm: function () { }
                });
}
        }
    </script>