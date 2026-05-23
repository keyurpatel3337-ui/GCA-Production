<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once PAGINATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if user is Super Admin or Principal
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Division & Roll Number Assignment";
$page_breadcrumb = "Assignment -";

// Handle filter form submission via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If it's a reset request (can be done via POST too)
    if (isset($_POST['reset'])) {
        unset($_SESSION['division_filters']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Determine if we are filtering or paging
    // If apply_filters is set, we reset page to 1
    // If page is set and no apply_filters, we keep filters and update page

    $filters = $_SESSION['division_filters'] ?? [
        'course_id' => '',
        'group_id' => '',
        'division_id' => '',
        'status' => 'unassigned',
        'page' => 1
    ];

    if (isset($_POST['apply_filters'])) {
        $filters['course_id'] = $_POST['course_id'] ?? '';
        $filters['group_id'] = $_POST['group_id'] ?? '';
        $filters['division_id'] = $_POST['division_id'] ?? '';
        $filters['status'] = $_POST['status'] ?? 'unassigned';
        $filters['page'] = 1; // Reset page on new filter
    } elseif (isset($_POST['page'])) {
        $filters['page'] = max(1, (int) $_POST['page']);
        // We might also receive filter values in pagination post, so we should update them just in case
        // But usually hidden inputs handle this. Let's update if present.
        if (isset($_POST['course_id']))
            $filters['course_id'] = $_POST['course_id'];
        if (isset($_POST['group_id']))
            $filters['group_id'] = $_POST['group_id'];
        if (isset($_POST['division_id']))
            $filters['division_id'] = $_POST['division_id'];
        if (isset($_POST['status']))
            $filters['status'] = $_POST['status'];
    }

    $_SESSION['division_filters'] = $filters;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle GET reset for convenience
if (isset($_GET['reset'])) {
    unset($_SESSION['division_filters']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['division_filters'] ?? [
    'course_id' => '',
    'group_id' => '',
    'division_id' => '',
    'status' => 'unassigned',
    'page' => 1
];

$course_filter = $filters['course_id'];
$group_filter = $filters['group_id'];
$division_filter = $filters['division_id'];
$status_filter = $filters['status'];
$page = max(1, (int) $filters['page']);
$perPage = 50;
$offset = ($page - 1) * $perPage;


// Fetch groups
try {
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name');
} catch (PDOException $e) {
    $groups = [];
}

// Fetch divisions
try {
    $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'display_order');
} catch (PDOException $e) {
    $divisions = [];
}

// Fetch available course-division mappings
try {
    $sql = "SELECT cd.*, c.course_name, g.group_name, d.division_name
            FROM tbl_course_division cd
            LEFT JOIN tbl_courses c ON cd.course_id = c.id
            LEFT JOIN tbl_group g ON cd.group_id = g.id
            LEFT JOIN tbl_division d ON cd.division_id = d.id
            WHERE cd.is_active = 1
            ORDER BY c.course_name, g.group_name, d.division_name";
    $course_divisions = $dbOps->customSelect($sql);
    if ($course_divisions === false) {
        $course_divisions = [];
    }
} catch (PDOException $e) {
    $course_divisions = [];
}

// Build query to fetch students based on filters
$where_conditions = [];
$params = [];

if ($status_filter === 'unassigned') {
    $where_conditions[] = "(e.division_id IS NULL OR e.roll_no IS NULL)";
} elseif ($status_filter === 'assigned') {
    $where_conditions[] = "e.division_id IS NOT NULL AND e.roll_no IS NOT NULL";
}

if ($course_filter) {
    if ($course_filter === '11th') {
        $where_conditions[] = "s.course_id = 1";
    } elseif ($course_filter === '12th') {
        $where_conditions[] = "s.course_id = 2";
    } elseif ($course_filter === 'Reneet') {
        $where_conditions[] = "s.course_id = 3";
    } else {
        $where_conditions[] = "s.course_id = ?";
        $params[] = $course_filter;
    }
}

if ($group_filter) {
    $where_conditions[] = "s.group_id = ?";
    $params[] = $group_filter;
}

if ($division_filter) {
    $where_conditions[] = "e.division_id = ?";
    $params[] = $division_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
try {
    $countQuery = "SELECT COUNT(*) as total FROM tbl_enrolled_students e $where_clause";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalRecords = $countResult['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    logDatabaseError($e, "Count Students for Division Assignment");
    $totalRecords = 0;
    $totalPages = 1;
}

// Fetch students with pagination
try {
    $query = "SELECT e.*, 
              s.student_name, s.surname, s.fathers_name, s.mob, s.aadhaar,
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
              $where_clause
              ORDER BY e.enrollment_id ASC
              LIMIT " . intval($perPage) . " OFFSET " . intval($offset);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Students for Division Assignment");
    $students = [];
}
?>

<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content pt-3">
        <div class="container-fluid">    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg'];
            unset($_SESSION['success_msg']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_msg'];
            unset($_SESSION['error_msg']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Filters</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="unassigned" <?php echo $status_filter === 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                                <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>
                                    Assigned</option>
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Standard</label>
                            <select name="course_id" class="form-control">
                                <option value="">-- All Standards --</option>
                                <option value="11th" <?php echo $course_filter == '11th' ? 'selected' : ''; ?>>11th</option>
                                <option value="12th" <?php echo $course_filter == '12th' ? 'selected' : ''; ?>>12th</option>
                                <option value="Reneet" <?php echo $course_filter == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Group</label>
                            <select name="group_id" class="form-control">
                                <option value="">-- All Groups --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo $group_filter == $group['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Division</label>
                            <select name="division_id" class="form-control">
                                <option value="">-- All Divisions --</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?php echo $division['id']; ?>" <?php echo $division_filter == $division['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($division['division_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <input type="hidden" name="apply_filters" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <button type="submit" name="reset" value="1" class="btn btn-secondary" formnovalidate>
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    <?php if (!empty($students) && $status_filter === 'unassigned'): ?>
        <div class="card">
            <div class="card-header bg-info">
                <h3 class="card-title"><i class="fas fa-tasks"></i> Bulk Assignment</h3>
            </div>
            <div class="card-body">
                <form id="bulkAssignForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Select Standard-Division <span class="text-danger">*</span></label>
                                <select name="course_division_id" class="form-control" required>
                                    <option value="">-- Select Standard-Division --</option>
                                    <?php foreach ($course_divisions as $cd): ?>
                                        <option value="<?php echo $cd['id']; ?>"
                                            data-current="<?php echo $cd['current_roll_no']; ?>"
                                            data-max="<?php echo $cd['max_capacity'] ?? ''; ?>"
                                            data-total="<?php echo $cd['total_students']; ?>">
                                            <?php echo htmlspecialchars($cd['course_name'] . ' - ' . $cd['group_name'] . ' - Division ' . $cd['division_name'] ?? ''); ?>
                                            (Current Roll: <?php echo $cd['current_roll_no']; ?>,
                                            Students: <?php echo $cd['total_students']; ?>
                                            <?php if ($cd['max_capacity']): ?>
                                                / <?php echo $cd['max_capacity']; ?>
                                            <?php endif; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-success btn-block" id="bulkAssignBtn" disabled>
                                <i class="fas fa-check-double"></i> Assign Selected Students
                            </button>
                        </div>
                    </div>
                    <div id="capacityWarning" class="alert alert-warning mt-2" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i> <span id="warningText"></span>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Students List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Students (<?php echo count($students); ?> / <?php echo $totalRecords; ?>)
            </h3>
        </div>
        <div class="card-body">
            <?php if (count($students) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <?php if ($status_filter === 'unassigned'): ?>
                                    <th width="3%">
                                        <input type="checkbox" id="selectAll" class="select-all-checkbox">
                                    </th>
                                <?php endif; ?>
                                <th width="7%">Enrollment No</th>
                                <th width="15%">Student Name</th>
                                <th width="10%">Mobile</th>
                                <th width="12%">Standard</th>
                                <th width="8%">Group</th>
                                <th width="8%">Division</th>
                                <th width="7%">Roll No</th>
                                <th width="10%">Enrollment Date</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <?php if ($status_filter === 'unassigned'): ?>
                                        <td>
                                            <input type="checkbox" name="student_ids[]"
                                                value="<?php echo $student['enrollment_id']; ?>" form="bulkAssignForm"
                                                class="student-checkbox" data-course="<?php echo $student['course_id']; ?>"
                                                data-group="<?php echo $student['group_id']; ?>">
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($student['enrollment_no'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($student['student_name']): ?>
                                            <strong><?php echo htmlspecialchars(trim(($student['surname'] ?? '') . ' ' . $student['student_name'])); ?></strong>
                                            <br>
                                            <small
                                                class="text-muted"><?php echo htmlspecialchars($student['fathers_name'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Data
                                                    Missing</strong></span>
                                            <br>
                                            <small class="text-muted">Registration record not found (ID:
                                                <?php echo $student['registration_id']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($student['division_id']): ?>
                                            <span class="badge bg-success">
                                                <?php echo htmlspecialchars($student['division_name'] ?? ''); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($student['roll_no']): ?>
                                            <span class="badge bg-primary"><?php echo $student['roll_no']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></td>
                                    <td>
                                        <a href="division-assignment-single.php?enrollment_id=<?php echo $student['enrollment_id']; ?>"
                                            class="btn btn-sm btn-info" title="Assign/Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="details.php?id=<?php echo $student['registration_id']; ?>"
                                            class="btn btn-sm btn-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <?php
                            // Pass current filters to pagination helper so they are included in POST forms 
                            // (Already done by $_SESSION usually, but here we pass explicitly if helper doesn't read session, 
                            // which it doesn't. But wait, renderPaginationPost puts hidden inputs.
                            // We need to pass the FILTER values as extra params to renderPaginationPost 
                            // so they are persisted when clicking page 2.
                    
                            $extraParams = [
                                'status' => $status_filter,
                                'course_id' => $course_filter,
                                'group_id' => $group_filter,
                                'division_id' => $division_filter
                            ];

                            echo renderPaginationPost($page, $totalPages, 2, $perPage, $extraParams);
                            ?>
                        </div>
                        <div class="text-muted">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of
                            <?php echo $totalRecords; ?> records
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No students found matching your criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>
        </div>
    </section>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Select/Deselect all checkboxes
        $('#selectAll').on('change', function () {
            $('.student-checkbox').prop('checked', this.checked);
            updateBulkAssignButton();
        });

        // Update button state when individual checkboxes change
        $('.student-checkbox').on('change', function () {
            updateBulkAssignButton();

            // Update select all checkbox
            var allChecked = $('.student-checkbox:checked').length === $('.student-checkbox').length;
            $('#selectAll').prop('checked', allChecked);
        });

        // Course division selection change
        $('select[name="course_division_id"]').on('change', function () {
            checkCapacity();
            updateBulkAssignButton();
        });

        function updateBulkAssignButton() {
            var selectedCount = $('.student-checkbox:checked').length;
            var divisionSelected = $('select[name="course_division_id"]').val();

            if (selectedCount > 0 && divisionSelected) {
                $('#bulkAssignBtn').prop('disabled', false);
                $('#bulkAssignBtn').html('<i class="fas fa-check-double"></i> Assign ' + selectedCount + ' Student(s)');
            } else {
                $('#bulkAssignBtn').prop('disabled', true);
                $('#bulkAssignBtn').html('<i class="fas fa-check-double"></i> Assign Selected Students');
            }

            checkCapacity();
        }

        function checkCapacity() {
            var selectedOption = $('select[name="course_division_id"] option:selected');
            var selectedCount = $('.student-checkbox:checked').length;

            if (selectedOption.val() && selectedCount > 0) {
                var currentTotal = parseInt(selectedOption.data('total')) || 0;
                var maxCapacity = parseInt(selectedOption.data('max')) || 0;
                var newTotal = currentTotal + selectedCount;

                if (maxCapacity > 0 && newTotal > maxCapacity) {
                    $('#capacityWarning').show();
                    $('#warningText').html('Warning: This will exceed the maximum capacity of ' + maxCapacity +
                        '. Current: ' + currentTotal + ', After assignment: ' + newTotal);
                } else {
                    $('#capacityWarning').hide();
                }
            } else {
                $('#capacityWarning').hide();
            }
        }

        // Validate form and submit via AJAX
        $('#bulkAssignForm').on('submit', function (e) {
            e.preventDefault();
            var selectedCount = $('.student-checkbox:checked').length;

            if (selectedCount === 0) {
                showToast('warning', 'Warning', 'Please select at least one student.');
                return false;
            }

            showConfirm({
                title: 'Confirm Division Assignment',
                message: `Are you sure you want to assign <strong>${selectedCount}</strong> student(s) to the selected division?`,
                confirmText: 'Yes, Assign',
                confirmButtonClass: 'btn-success',
                onConfirm: function () {
                    const btn = $('#bulkAssignForm button[type="submit"]');
                    const originalHtml = btn.html();
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Assigning...');

                    var courseDivisionId = $('select[name="course_division_id"]').val();
                    var studentIds = [];
                    $('.student-checkbox:checked').each(function () {
                        studentIds.push($(this).val());
                    });

                    var formData = 'course_division_id=' + encodeURIComponent(courseDivisionId);
                    $.each(studentIds, function (index, value) {
                        formData += '&student_ids[]=' + encodeURIComponent(value);
                    });

                    $.api.post('students/division-assignment-bulk-save', formData)
                        .then(response => {
                            if (response.success) {
                                showToast('success', 'Success!', response.message);
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                btn.prop('disabled', false).html(originalHtml);
                                showToast('error', 'Error!', response.error || response.message);
                            }
                        }).catch(error => {
                            btn.prop('disabled', false).html(originalHtml);
                            showToast('error', 'Error!', error.message || 'Failed to assign students');
                        });
                }
            });
        });
    });
</script>
