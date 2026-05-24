<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once HELPER_ERROR_LOGGER;
require_once PAGINATION_FILE;

// Check if user is Super Admin or Principal
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle POST - Store filters in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['division_shuffle_filter']);
    } else {
        // Start with existing filters or defaults
        $currentFilters = $_SESSION['division_shuffle_filter'] ?? [
            'course_id' => '',
            'group_id' => '',
            'division_id' => '',
            'per_page' => 50,
            'page' => 1
        ];

        if (isset($_POST['page'])) {
            // Pagination request: Only update page (and per_page if present)
            $currentFilters['page'] = $_POST['page'];
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
        } else {
            // Filter request: Update filters and reset page
            $currentFilters['course_id'] = $_POST['course_id'] ?? '';
            $currentFilters['group_id'] = $_POST['group_id'] ?? '';
            $currentFilters['division_id'] = $_POST['division_id'] ?? '';
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
            $currentFilters['page'] = 1;
        }

        $_SESSION['division_shuffle_filter'] = $currentFilters;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$page_title = "Division Shuffle";

// Get filter parameters from session
$filters = $_SESSION['division_shuffle_filter'] ?? [
    'course_id' => '',
    'group_id' => '',
    'division_id' => '',
    'per_page' => 50,
    'page' => 1
];

$filter_course = $filters['course_id'];
$filter_group = $filters['group_id'];
$filter_division = $filters['division_id'];
$perPage = $filters['per_page'];
$page = $filters['page'];

// Fetch courses
try {
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name');
} catch (PDOException $e) {
    $courses = [];
}

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

// Fetch course-division mappings for target selection
try {
    $sql = "SELECT cd.*, c.course_name, g.group_name, d.division_name
            FROM tbl_course_division cd
            LEFT JOIN tbl_courses c ON cd.course_id = c.id
            LEFT JOIN tbl_group g ON cd.group_id = g.id
            LEFT JOIN tbl_division d ON cd.division_id = d.id
            WHERE cd.is_active = 1
            ORDER BY c.course_name, g.group_name, d.division_name";
    $course_divisions = $dbOps->customSelect($sql);
} catch (PDOException $e) {
    $course_divisions = [];
}

// Build query to fetch students
$students = [];
$totalRecords = 0;
$totalPages = 1;

if ($filter_division) {
    // Pagination parameters
    $offset = ($page - 1) * $perPage;

    try {
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM tbl_enrolled_students WHERE division_id = ?";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->execute([$filter_division]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalRecords = $countResult['total'] ?? 0;
        $totalPages = ceil($totalRecords / $perPage);

        // Fetch students with pagination
        $query = "SELECT e.*, 
                  s.student_name, s.surname, s.fathers_name, s.mob,
                  c.course_name,
                  g.group_name,
                  d.division_name
                  FROM tbl_enrolled_students e
                  LEFT JOIN tbl_gm_std_registration s ON e.registration_id = s.id
                  LEFT JOIN tbl_courses c ON s.course_id = c.id
                  LEFT JOIN tbl_group g ON s.group_id = g.id
                  LEFT JOIN tbl_division d ON e.division_id = d.id
                  WHERE e.division_id = ?
                  ORDER BY e.roll_no ASC
                  LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);
        $stmt->execute([$filter_division, (int)$perPage, (int)$offset]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Students for Division Shuffle");
        $students = [];
    }
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>



<div class="container-fluid">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo gca_safe_html($_SESSION['success_msg']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-triangle"></i> <?php echo gca_safe_html($_SESSION['error_msg']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Select Source Division</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="filterForm">
                <input type="hidden" name="per_page" id="per_page_hidden" value="<?php echo $perPage; ?>">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Standard</label>
                            <select name="course_id" id="course_id" class="form-control">
                                <option value="">-- Select Standard --</option>
                                <option value="11th" <?php echo $filter_course == '11th' ? 'selected' : ''; ?>>11th</option>
                                <option value="12th" <?php echo $filter_course == '12th' ? 'selected' : ''; ?>>12th</option>
                                <option value="Reneet" <?php echo $filter_course == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Group</label>
                            <select name="group_id" id="group_id" class="form-control">
                                <option value="">-- Select Group --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo $filter_group == $group['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Division</label>
                            <select name="division_id" id="division_id" class="form-control">
                                <option value="">-- Select Division --</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?php echo $division['id']; ?>" <?php echo $filter_division == $division['id'] ? 'selected' : ''; ?>>
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
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Load Students
                                </button>
                                <button type="submit" name="clear_filters" value="1" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Shuffle Form -->
    <?php if (!empty($students)): ?>
        <div class="card">
            <div class="card-header bg-info">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Shuffle Students to Another Division</h3>
            </div>
            <div class="card-body">
                <form id="shuffleForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Target Standard-Division</strong> <span class="text-danger">*</span></label>
                                <select name="target_course_division_id" id="target_course_division_id" class="form-control"
                                    required>
                                    <option value="">-- Select Target Division --</option>
                                    <?php foreach ($course_divisions as $cd): ?>
                                        <option value="<?php echo $cd['id']; ?>"
                                            data-current="<?php echo $cd['current_roll_no']; ?>"
                                            data-max="<?php echo $cd['max_capacity'] ?? ''; ?>"
                                            data-total="<?php echo $cd['total_students']; ?>">
                                            <?php echo htmlspecialchars($cd['course_name'] . ' - ' . $cd['group_name'] . ' - Division ' . $cd['division_name'] ?? ''); ?>
                                            (Students:
                                            <?php echo $cd['total_students']; ?>         <?php if ($cd['max_capacity']): ?> /
                                                <?php echo $cd['max_capacity']; ?>         <?php endif; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><strong>Reassign Roll Numbers</strong></label>
                                <select name="reassign_roll" class="form-control">
                                    <option value="1">Yes - Auto-assign new roll numbers</option>
                                    <option value="0">No - Keep existing roll numbers</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-success btn-block" id="shuffleBtn" disabled>
                                <i class="fas fa-random"></i> Shuffle Selected Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Students List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <i class="fas fa-list"></i> Students in Selected Division
                </h3>
                <h6 class="mb-0">Found <strong><?php echo formatIndianCurrency($totalRecords, false); ?></strong> students
                    matching criteria.</h6>
                <?php if ($totalRecords > 0): ?>
                    <div>
                        <label class="me-2">Per Page:</label>
                        <select class="form-select form-select-sm d-inline-block" style="width: auto;"
                            onchange="document.getElementById('per_page_hidden').value=this.value; document.getElementById('filterForm').submit();">
                            <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="<?php echo $totalRecords; ?>" <?php echo $perPage >= $totalRecords ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="3%">
                                    <input type="checkbox" id="selectAll" class="select-all-checkbox">
                                </th>
                                <th width="8%">Roll No</th>
                                <th width="20%">Student Name</th>
                                <th width="12%">Father's Name</th>
                                <th width="12%">Mobile</th>
                                <th width="15%">Standard</th>
                                <th width="10%">Group</th>
                                <th width="10%">Division</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="student_ids[]"
                                            value="<?php echo $student['enrollment_id']; ?>" form="shuffleForm"
                                            class="student-checkbox">
                                    </td>
                                    <td>
                                        <?php if ($student['roll_no']): ?>
                                            <span class="badge bg-primary"><?php echo $student['roll_no']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(trim(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? ''))); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['fathers_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['mob'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span
                                            class="badge bg-success"><?php echo htmlspecialchars($student['division_name'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <a onclick="document.getElementById('form_details_student['registration_id']').submit()"
                                            style="cursor:pointer" class="btn btn-sm btn-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-3">
                        <?php
                        echo renderPaginationPost($page, $totalPages);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($filter_division): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No students found in the selected division.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Please select a source division to load students for shuffling.
        </div>
    <?php endif; ?>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Select/Deselect all checkboxes
        $('#selectAll').on('change', function () {
            $('.student-checkbox').prop('checked', this.checked);
            updateShuffleButton();
        });

        // Update button state when individual checkboxes change
        $('.student-checkbox').on('change', function () {
            updateShuffleButton();
            // Update select all checkbox
            var allChecked = $('.student-checkbox:checked').length === $('.student-checkbox').length;
            $('#selectAll').prop('checked', allChecked);
        });

        // Target division selection change
        $('#target_course_division_id').on('change', function () {
            updateShuffleButton();
        });

        function updateShuffleButton() {
            var selectedCount = $('.student-checkbox:checked').length;
            var targetSelected = $('#target_course_division_id').val();

            if (selectedCount > 0 && targetSelected) {
                $('#shuffleBtn').prop('disabled', false);
                $('#shuffleBtn').html('<i class="fas fa-random"></i> Shuffle ' + selectedCount + ' Student(s)');
            } else {
                $('#shuffleBtn').prop('disabled', true);
                $('#shuffleBtn').html('<i class="fas fa-random"></i> Shuffle Selected Students');
            }
        }

        // Submit shuffle form
        $('#shuffleForm').on('submit', function (e) {
            e.preventDefault();
            var selectedCount = $('.student-checkbox:checked').length;

            if (selectedCount === 0) {
                if (typeof showToast === 'function') {
                    showToast('warning', 'Warning', 'Please select at least one student.');
                } else {
                    alert('Please select at least one student.');
                }
                return false;
            }

            if (!$('#target_course_division_id').val()) {
                if (typeof showToast === 'function') {
                    showToast('warning', 'Warning', 'Please select a target division.');
                } else {
                    alert('Please select a target division.');
                }
                return false;
            }

            if (confirm('Are you sure you want to move ' + selectedCount + ' student(s) to the selected division?')) {
                const btn = $('#shuffleBtn');
                const originalHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Shuffling...');

                $.api.post('students/division-shuffle-save', $(this).serialize())
                    .then(response => {
                        if (response.success) {
                            if (typeof showToast === 'function') {
                                showToast('success', 'Success!', response.message);
                            }
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            btn.prop('disabled', false).html(originalHtml);
                            if (typeof showToast === 'function') {
                                showToast('error', 'Error!', response.error || response.message);
                            } else {
                                alert(response.error || response.message);
                            }
                        }
                    }).catch(error => {
                        btn.prop('disabled', false).html(originalHtml);
                        if (typeof showToast === 'function') {
                            showToast('error', 'Error!', error.message || 'Failed to shuffle students');
                        } else {
                            alert(error.message || 'Failed to shuffle students');
                        }
                    });
            }
        });
    });
</script>
