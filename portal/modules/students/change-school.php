<?php
/**
 * Change Student School
 * Enhanced version with single student, bulk by mobile, and bulk by checkbox
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Principle or Super Admin
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Change Student School";
$page_breadcrumb = "School -";

try {
    // Get schools
    $stmt = $conn->query("SELECT id, school_name FROM tbl_schools WHERE is_active = 1 ORDER BY school_name ASC");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all enrolled students with their current school
    $stmt = $conn->prepare("SELECT e.enrollment_id, e.enrollment_no, r.school_id, 
                            r.id as student_id, r.surname, r.student_name, r.fathers_name, 
                            r.mob, r.aadhaar, r.standard, s.school_name,
                            CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) as full_name
                            FROM tbl_enrolled_students e
                            INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                            LEFT JOIN tbl_schools s ON r.school_id = s.id
                            WHERE e.is_active = 1
                            ORDER BY r.surname, r.student_name");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Change School Page Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading the page. Please try again.");
    $schools = [];
    $students = [];
}

?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success'] ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error'] ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php ?>
    <?php endif; ?>

    <!-- Method Selection Tabs -->
    <ul class="nav nav-tabs mb-3" id="changeSchoolTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button"
                role="tab">
                <i class="fas fa-user"></i> Single Student
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mobile-tab" data-bs-toggle="tab" data-bs-target="#mobile" type="button"
                role="tab">
                <i class="fas fa-mobile-alt"></i> Bulk by Mobile Numbers
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="checkbox-tab" data-bs-toggle="tab" data-bs-target="#checkbox" type="button"
                role="tab">
                <i class="fas fa-check-square"></i> Bulk by Selection
            </button>
        </li>
    </ul>

    <div class="tab-content" id="changeSchoolTabContent">
        <!-- Single Student Tab -->
        <div class="tab-pane fade show active" id="single" role="tabpanel">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user"></i> Change School for Single Student</h3>
                </div>
                <div class="card-body">
                    <form id="singleChangeForm" method="POST" action="change-school-process.php">
                        <input type="hidden" name="change_type" value="single">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="single_student_id" class="form-label">Select Student <span
                                            class="text-danger">*</span></label>
                                    <select name="student_id" id="single_student_id" class="form-control select2"
                                        required>
                                        <option value="">-- Select Student --</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['enrollment_id']; ?>"
                                                data-school="<?php echo htmlspecialchars($student['school_name'] ?? 'Not Assigned'); ?>"
                                                data-school-id="<?php echo $student['school_id'] ?? 0; ?>"
                                                data-standard="<?php echo htmlspecialchars($student['standard'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($student['enrollment_no'] . ' - ' . $student['full_name'] ?? ''); ?>
                                                (<?php echo htmlspecialchars($student['mob'] ?? ''); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="single_new_school" class="form-label">New School <span
                                            class="text-danger">*</span></label>
                                    <select name="new_school_id" id="single_new_school" class="form-control select2"
                                        required>
                                        <option value="">-- Select School --</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>">
                                                <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="single_student_info" class="alert alert-info" style="display: none;">
                            <strong>Current School:</strong> <span id="current_school_name"></span><br>
                            <strong>Standard:</strong> <span id="current_standard"></span>
                        </div>

                        <div class="mb-3">
                            <label for="single_reason" class="form-label">Reason for Change</label>
                            <textarea name="reason" id="single_reason" class="form-control" rows="3"
                                placeholder="Enter reason for school change (optional)"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change School
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bulk by Mobile Numbers Tab -->
        <div class="tab-pane fade" id="mobile" role="tabpanel">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-mobile-alt"></i> Bulk School Change by Mobile Numbers
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Enter Mobile Numbers <span
                                        class="text-danger">*</span></label>
                                <textarea id="mobile_numbers" class="form-control" rows="4"
                                    placeholder="Enter mobile numbers separated by commas or new lines, e.g.:&#10;9876543210, 9123456789&#10;9998887776"></textarea>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Enter mobile numbers separated by commas or
                                    new lines
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New School <span class="text-danger">*</span></label>
                                <select id="mobile_new_school" class="form-control select2" required>
                                    <option value="">-- Select School --</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo $school['id']; ?>">
                                            <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason for Change</label>
                                <textarea id="mobile_reason" class="form-control" rows="2"
                                    placeholder="Enter reason (optional)"></textarea>
                            </div>
                            <button type="button" class="btn btn-info" id="previewMobileBtn">
                                <i class="fas fa-eye"></i> Preview Students
                            </button>
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <div id="mobilePreviewSection" class="mt-4" style="display: none;">
                        <div class="alert alert-info">
                            <strong><i class="fas fa-users"></i> Students Found:</strong>
                            <span id="mobilePreviewCount">0</span> student(s) matched
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Enrollment No</th>
                                        <th>Full Name</th>
                                        <th>Mobile</th>
                                        <th>Current School</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="mobilePreviewBody">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div id="mobileNotFoundSection" class="alert alert-warning mt-2" style="display: none;">
                            <strong><i class="fas fa-exclamation-triangle"></i> Not Found:</strong>
                            <span id="mobileNotFoundList"></span>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-success" id="confirmMobileBtn">
                                <i class="fas fa-check"></i> Confirm School Change
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelMobileBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk by Checkbox Selection Tab -->
        <div class="tab-pane fade" id="checkbox" role="tabpanel">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-check-square"></i> Bulk School Change by Selection</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">New School <span class="text-danger">*</span></label>
                            <select id="checkbox_new_school" class="form-control select2" required>
                                <option value="">-- Select School --</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>">
                                        <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reason for Change</label>
                            <input type="text" id="checkbox_reason" class="form-control"
                                placeholder="Enter reason (optional)">
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-success" id="bulkChangeBtn">
                            <i class="fas fa-exchange-alt"></i> Change School for Selected
                        </button>
                        <button type="button" class="btn btn-secondary" id="selectAllBtn">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-secondary" id="deselectAllBtn">
                            <i class="fas fa-square"></i> Deselect All
                        </button>
                        <span class="ms-3 text-muted" id="selectedCount">0 students selected</span>
                    </div>

                    <div class="table-responsive">
                        <table id="studentsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="checkAll">
                                    </th>
                                    <th>Enrollment No</th>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Standard</th>
                                    <th>Current School</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="student-checkbox"
                                                value="<?php echo $student['enrollment_id']; ?>"
                                                data-school-id="<?php echo $student['school_id'] ?? 0; ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($student['enrollment_no'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['standard'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($student['school_name'] ?? 'Not Assigned'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current School Distribution -->
    <div class="card card-secondary mt-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Current School Distribution</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th class="text-center">Enrolled Students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $school_counts = [];
                        foreach ($students as $student) {
                            $school_name = $student['school_name'] ?? 'Not Assigned';
                            $school_id = $student['school_id'] ?? 0;
                            if (!isset($school_counts[$school_id])) {
                                $school_counts[$school_id] = [
                                    'name' => $school_name,
                                    'count' => 0
                                ];
                            }
                            $school_counts[$school_id]['count']++;
                        }

                        foreach ($school_counts as $data):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['name'] ?? ''); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo $data['count']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    // Store matched students for mobile bulk
    let mobileMatchedStudents = [];
    const studentsData = <?php echo json_encode($students); ?>;

    $(document).ready(function () {
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Initialize DataTable
        $('#studentsTable').DataTable({
            pageLength: 25,
            order: [[2, 'asc']],
            columnDefs: [
                { orderable: false, targets: 0 }
            ]
        });

        // ========== Single Student Section ==========
        $('#single_student_id').on('change', function () {
            const selected = $(this).find('option:selected');
            if (selected.val()) {
                $('#current_school_name').text(selected.data('school'));
                $('#current_standard').text(selected.data('standard'));
                $('#single_student_info').show();
            } else {
                $('#single_student_info').hide();
            }
        });

        $('#single_new_school').on('change', function () {
            const selectedStudent = $('#single_student_id').find('option:selected');
            const currentSchoolId = selectedStudent.data('school-id');
            const newSchoolId = $(this).val();

            if (currentSchoolId && currentSchoolId == newSchoolId) {
                if (typeof showToast === 'function') {
                    showToast('warning', 'Warning', 'The student is already in this school. Please select a different school.');
                } else {
                    alert('The student is already in this school. Please select a different school.');
                }
                $(this).val('').trigger('change');
            }
        });

        $('#singleChangeForm').on('submit', function (e) {
            e.preventDefault();
            const studentName = $('#single_student_id option:selected').text();
            const newSchool = $('#single_new_school option:selected').text();

            if (confirm(`Are you sure you want to change school for:\n${studentName}\nto\n${newSchool}?`)) {
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Changing...');
                this.submit();
            }
        });

        // ========== Mobile Numbers Section ==========
        $('#previewMobileBtn').on('click', function () {
            const mobileNumbers = $('#mobile_numbers').val().trim();
            const newSchoolId = $('#mobile_new_school').val();

            if (!mobileNumbers) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'Please enter mobile numbers');
                } else {
                    alert('Please enter mobile numbers');
                }
                return;
            }

            if (!newSchoolId) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'Please select a school first');
                } else {
                    alert('Please select a school first');
                }
                return;
            }

            // Parse mobile numbers
            const mobiles = mobileNumbers.split(/[,\n]/).map(m => m.trim()).filter(m => m.length > 0);

            if (mobiles.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'No valid mobile numbers found');
                } else {
                    alert('No valid mobile numbers found');
                }
                return;
            }

            // Find matching students
            mobileMatchedStudents = [];
            const notFound = [];

            mobiles.forEach(mobile => {
                const student = studentsData.find(s => s.mob === mobile);
                if (student) {
                    mobileMatchedStudents.push(student);
                } else {
                    notFound.push(mobile);
                }
            });

            // Display preview
            const tbody = $('#mobilePreviewBody');
            tbody.empty();

            if (mobileMatchedStudents.length === 0) {
                tbody.html('<tr><td colspan="5" class="text-center text-muted">No students found for the provided mobile numbers</td></tr>');
            } else {
                mobileMatchedStudents.forEach(student => {
                    tbody.append(`
                        <tr>
                            <td>${escapeHtml(student.enrollment_no)}</td>
                            <td>${escapeHtml(student.full_name)}</td>
                            <td>${escapeHtml(student.mob)}</td>
                            <td>${escapeHtml(student.school_name || 'Not Assigned')}</td>
                            <td><span class="badge bg-success">Found</span></td>
                        </tr>
                    `);
                });
            }

            $('#mobilePreviewCount').text(mobileMatchedStudents.length);

            if (notFound.length > 0) {
                $('#mobileNotFoundList').text(notFound.join(', '));
                $('#mobileNotFoundSection').show();
            } else {
                $('#mobileNotFoundSection').hide();
            }

            $('#mobilePreviewSection').slideDown();
        });

        $('#cancelMobileBtn').on('click', function () {
            $('#mobilePreviewSection').slideUp();
            mobileMatchedStudents = [];
        });

        $('#confirmMobileBtn').on('click', function () {
            if (mobileMatchedStudents.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'No students to update');
                } else {
                    alert('No students to update');
                }
                return;
            }

            const newSchoolId = $('#mobile_new_school').val();
            const newSchoolName = $('#mobile_new_school option:selected').text();
            const reason = $('#mobile_reason').val();

            if (confirm(`Are you sure you want to change school for ${mobileMatchedStudents.length} student(s) to ${newSchoolName}?`)) {
                // Create form and submit
                const confirmBtn = $(this);
                confirmBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Changing...');

                const form = $('<form>', {
                    method: 'POST',
                    action: 'change-school-process'
                });
                form.append($('<input>', { type: 'hidden', name: 'change_type', value: 'mobile_bulk' }));
                form.append($('<input>', { type: 'hidden', name: 'new_school_id', value: newSchoolId }));
                form.append($('<input>', { type: 'hidden', name: 'reason', value: reason }));
                mobileMatchedStudents.forEach(s => {
                    form.append($('<input>', { type: 'hidden', name: 'enrollment_ids[]', value: s.enrollment_id }));
                });
                $('body').append(form);
                form.submit();
            }
        });

        // ========== Checkbox Selection Section ==========
        function updateSelectedCount() {
            const count = $('.student-checkbox:checked').length;
            $('#selectedCount').text(count + ' students selected');
        }

        $('#checkAll').on('change', function () {
            $('.student-checkbox').prop('checked', this.checked);
            updateSelectedCount();
        });

        $('#selectAllBtn').on('click', function () {
            $('.student-checkbox').prop('checked', true);
            $('#checkAll').prop('checked', true);
            updateSelectedCount();
        });

        $('#deselectAllBtn').on('click', function () {
            $('.student-checkbox').prop('checked', false);
            $('#checkAll').prop('checked', false);
            updateSelectedCount();
        });

        $(document).on('change', '.student-checkbox', function () {
            updateSelectedCount();
        });

        $('#bulkChangeBtn').on('click', function () {
            const selectedIds = [];
            $('.student-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'Please select at least one student');
                } else {
                    alert('Please select at least one student');
                }
                return;
            }

            const newSchoolId = $('#checkbox_new_school').val();
            if (!newSchoolId) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'Please select a school first');
                } else {
                    alert('Please select a school first');
                }
                return;
            }

            const newSchoolName = $('#checkbox_new_school option:selected').text();
            const reason = $('#checkbox_reason').val();

            if (confirm(`Are you sure you want to change school for ${selectedIds.length} student(s) to ${newSchoolName}?`)) {
                // Create form and submit
                const bulkBtn = $(this);
                bulkBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Changing...');

                const form = $('<form>', {
                    method: 'POST',
                    action: 'change-school-process'
                });
                form.append($('<input>', { type: 'hidden', name: 'change_type', value: 'checkbox_bulk' }));
                form.append($('<input>', { type: 'hidden', name: 'new_school_id', value: newSchoolId }));
                form.append($('<input>', { type: 'hidden', name: 'reason', value: reason }));
                selectedIds.forEach(id => {
                    form.append($('<input>', { type: 'hidden', name: 'enrollment_ids[]', value: id }));
                });
                $('body').append(form);
                form.submit();
            }
        });
    });

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
</script>