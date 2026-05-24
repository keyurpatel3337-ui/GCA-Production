<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Role-based access control
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT])) {
    die("Access denied!");
}

$page_title = "Auto-Assign Semester 2 Fees";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<main class="app-main">


    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> <?php echo $page_title; ?></h5>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Academic Year</label>
                                <select id="filter_academic_year" class="form-select">
                                    <option value="">Select Year</option>
                                    <?php
                                    try {
                                        $years = $conn->query("SELECT id, year_name FROM tbl_academic_years WHERE is_active = 1 ORDER BY year_name DESC");
                                        if ($years) {
                                            while ($year = $years->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$year['year_name']}'>{$year['year_name']}</option>";
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // Fallback: generate current and next academic year
                                        $current_year = date('Y');
                                        $next_year = (date('Y') + 1);
                                        $fallback_year = $current_year . '-' . substr($next_year, -2);
                                        echo "<option value='{$fallback_year}'>{$fallback_year}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Standard</label>
                                <select id="filter_course" class="form-select">
                                    <option value="">All Standards</option>
                                    <?php
                                    try {
                                        $courses = $conn->query("SELECT id, course_name FROM tbl_courses WHERE is_active = 1 ORDER BY course_name");
                                        if ($courses) {
                                            while ($course = $courses->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$course['id']}'>{$course['course_name']}</option>";
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // Silently fail
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">School</label>
                                <select id="filter_school" class="form-select">
                                    <option value="">All Schools</option>
                                    <?php
                                    try {
                                        $schools = $conn->query("SELECT id, school_code, school_name FROM tbl_schools WHERE is_active = 1 ORDER BY school_code");
                                        if ($schools) {
                                            while ($school = $schools->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$school['id']}'>{$school['school_code']} - {$school['school_name']}</option>";
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // Silently fail
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment Status</label>
                                <select id="filter_payment" class="form-select">
                                    <option value="all">All Students</option>
                                    <option value="paid">Fully Paid Semester 1</option>
                                    <option value="partial">Partially Paid</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Group</label>
                                <select id="filter_group" class="form-select">
                                    <option value="">All Groups</option>
                                    <?php
                                    try {
                                        $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name");
                                        if ($groups) {
                                            while ($group = $groups->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$group['id']}'>{$group['group_name']}</option>";
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // Silently fail
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> This will show all students currently in <strong>Semester
                                        1</strong> who are ready to be promoted to <strong>Semester 2</strong>.
                                    Upon assignment, their <code>current_term_id</code> will be updated to 'Semester
                                    2'.
                                </div>
                                <button class="btn btn-primary" id="loadPreviewBtn">
                                    <i class="fas fa-search"></i> Load Students in Semester 1
                                </button>
                            </div>
                        </div>

                        <!-- Preview Section -->
                        <div id="previewSection" class="d-none">
                            <div class="alert alert-info">
                                <strong>Eligible Students: <span id="eligibleCount">0</span></strong>
                                <span class="ms-3">Already Assigned: <span id="alreadyAssignedCount">0</span></span>
                                <span class="ms-3">To Be Assigned: <span id="toAssignCount">0</span></span>
                            </div>

                            <div class="table-responsive css-auto-assign-semester2-911727">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th><input type="checkbox" id="selectAll"></th>
                                            <th>Enrollment No</th>
                                            <th>Student Name</th>
                                            <th>Current Term</th>
                                            <th>Standard</th>
                                            <th>School</th>
                                            <th>Semester 2 Fee Config</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody">
                                        <!-- Will be populated via AJAX -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-success btn-lg" id="assignSelectedBtn">
                                    <i class="fas fa-check"></i> Assign to Selected Students
                                </button>
                                <button class="btn btn-warning btn-lg" id="assignAllBtn">
                                    <i class="fas fa-check-double"></i> Assign to All Eligible
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Load Preview
        $('#loadPreviewBtn').on('click', function () {
            const filters = {
                academic_year: $('#filter_academic_year').val(),
                course_id: $('#filter_course').val(),
                school_id: $('#filter_school').val(),
                payment_status: $('#filter_payment').val(),
                group_id: $('#filter_group').val()
            };

            if (!filters.academic_year) {
                showToast('warning', 'Warning', 'Please select Academic Year');
                return;
            }

            $.ajax({
                url: '../../../counselling-backend/controllers/fees/load-semester2-preview.php',
                type: 'POST',
                data: filters,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        displayPreview(response.data);
                        $('#previewSection').removeClass('d-none');
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                },
                error: function () {
                    showToast('error', 'Error', 'Failed to load preview');
                }
            });
        });

        function displayPreview(data) {
            $('#eligibleCount').text(data.eligible_count);
            $('#alreadyAssignedCount').text(data.already_assigned_count);
            $('#toAssignCount').text(data.to_assign_count);

            let html = '';
            data.students.forEach(function (student) {
                const canAssign = student.sem2_config_id && !student.already_assigned;
                const statusBadge = student.already_assigned
                    ? '<span class="badge bg-secondary">Already Assigned</span>'
                    : (student.sem2_config_id
                        ? '<span class="badge bg-success">Ready</span>'
                        : '<span class="badge bg-danger">No Config Found</span>');

                html += `<tr class="${canAssign ? '' : 'table-secondary'}">
                        <td>
                            ${canAssign ? `<input type="checkbox" class="student-checkbox" data-enrollment-id="${student.enrollment_id}" data-config-id="${student.sem2_config_id}">` : ''}
                        </td>
                        <td>${student.enrollment_no}</td>
                        <td>${student.student_name}</td>
                        <td><span class="badge bg-primary">${student.current_term}</span></td>
                        <td>${student.course_name}</td>
                        <td>${student.school_code}</td>
                        <td>${student.sem2_config_display || 'Not Found'}</td>
                        <td>${student.post_admission_discount > 0 ? `₹${Math.round(parseFloat(student.post_admission_discount))}` : '-'}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
            });

            $('#previewTableBody').html(html);
        }

        // Select All
        $('#selectAll').on('change', function () {
            $('.student-checkbox').prop('checked', this.checked);
        });

        // Assign Selected
        $('#assignSelectedBtn').on('click', function () {
            const selected = [];
            $('.student-checkbox:checked').each(function () {
                selected.push({
                    enrollment_id: $(this).data('enrollment-id'),
                    fee_config_id: $(this).data('config-id')
                });
            });

            if (selected.length === 0) {
                showToast('warning', 'Warning', 'Please select at least one student');
                return;
            }

            assignFees(selected);
        });

        // Assign All
        $('#assignAllBtn').on('click', function () {
            const all = [];
            $('.student-checkbox').each(function () {
                all.push({
                    enrollment_id: $(this).data('enrollment-id'),
                    fee_config_id: $(this).data('config-id')
                });
            });

            if (all.length === 0) {
                showToast('warning', 'Warning', 'No eligible students found');
                return;
            }

            showConfirm({
                title: 'Confirm Assignment',
                message: `Assign semester 2 fees to ${all.length} student(s)?`,
                confirmText: 'Yes, Assign',
                confirmButtonClass: 'btn-success',
                onConfirm: function () {
                    assignFees(all);
                }
            });
        });

        function assignFees(assignments) {
            $.ajax({
                url: '../../../counselling-backend/controllers/fees/process-semester2-assignment.php',
                type: 'POST',
                data: { assignments: assignments },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast('success', 'Success', `Assigned: ${response.success_count}. Errors: ${response.error_count}`);
                        $('#loadPreviewBtn').click(); // Reload preview
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                },
                error: function () {
                    showToast('error', 'Error', 'Failed to assign fees');
                }
            });
        }
    });
</script>

