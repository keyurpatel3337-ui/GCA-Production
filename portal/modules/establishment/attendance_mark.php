<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if user is Super Admin, Principle or Establishment
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Mark Student Attendance";
$page_breadcrumb = "Attendance -";

// Handle filter form submission
$filters = $_SESSION['student_attendance_filters'] ?? [
    'course_id' => '',
    'group_id' => '',
    'division_id' => '',
    'date' => date('Y-m-d')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $filters['course_id'] = $_POST['course_id'] ?? '';
    $filters['group_id'] = $_POST['group_id'] ?? '';
    $filters['division_id'] = $_POST['division_id'] ?? '';
    $filters['date'] = $_POST['date'] ?? date('Y-m-d');
    $_SESSION['student_attendance_filters'] = $filters;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch Master Data for Filters
try {
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name');
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name');
    $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'display_order');
} catch (PDOException $e) {
    $courses = $groups = $divisions = [];
}

// Fetch Students if division is selected
$students = [];
if ($filters['course_id'] && $filters['division_id']) {
    try {
        $course_condition = "s.course_id = ?";
        $params = [$filters['date'], $filters['course_id'], $filters['division_id'], $filters['group_id'], $filters['group_id']];

        if ($filters['course_id'] === '11th') {
            $course_condition = "s.course_id IN (1, 2)";
            $params = [$filters['date'], $filters['division_id'], $filters['group_id'], $filters['group_id']];
        } elseif ($filters['course_id'] === '12th') {
            $course_condition = "s.course_id IN (4, 5)";
            $params = [$filters['date'], $filters['division_id'], $filters['group_id'], $filters['group_id']];
        } elseif ($filters['course_id'] === 'Reneet') {
            $course_condition = "s.course_id = 6";
            $params = [$filters['date'], $filters['division_id'], $filters['group_id'], $filters['group_id']];
        }

        $query = "SELECT e.enrollment_id, e.roll_no, s.student_name, s.surname, s.fathers_name, s.mob,
                         att.status as current_status
                  FROM tbl_enrolled_students e
                  JOIN tbl_gm_std_registration s ON e.registration_id = s.id
                  LEFT JOIN tbl_student_attendance att ON e.enrollment_id = att.student_id AND att.attendance_date = ?
                  WHERE $course_condition AND e.division_id = ?
                  AND (s.group_id = ? OR ? = '')
                  ORDER BY e.roll_no ASC, s.student_name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Students for Attendance");
    }
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><?php echo $page_title; ?></h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filters -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-filter me-2 text-primary"></i>Select Batch</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $filters['date']; ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Standard</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">-- Select Standard --</option>
                                    <option value="11th" <?php echo ($filters['course_id'] == '11th') ? 'selected' : ''; ?>>11th</option>
                                    <option value="12th" <?php echo ($filters['course_id'] == '12th') ? 'selected' : ''; ?>>12th</option>
                                    <option value="Reneet" <?php echo ($filters['course_id'] == 'Reneet') ? 'selected' : ''; ?>>Reneet</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Group (Optional)</label>
                                <select name="group_id" class="form-select">
                                    <option value="">All Groups</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo $g['id']; ?>" <?php echo ($filters['group_id'] == $g['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g['group_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Division</label>
                                <select name="division_id" class="form-select" required>
                                    <option value="">-- Select Division --</option>
                                    <?php foreach ($divisions as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo ($filters['division_id'] == $d['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['division_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="apply_filters" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Get Students
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($students)): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Attendance Sheet - <?php echo date('d M Y', strtotime($filters['date'])); ?></h5>
                        <div class="text-white small">Total Students: <?php echo count($students); ?></div>
                    </div>
                    <form id="attendanceForm">
                        <input type="hidden" name="date" value="<?php echo $filters['date']; ?>">
                        <input type="hidden" name="division_id" value="<?php echo $filters['division_id']; ?>">
                        
                        <div class="card-body p-0">
                            <?php 
                                $total = count($students);
                                $mid = ceil($total / 2);
                                $chunks = [
                                    array_slice($students, 0, $mid),
                                    array_slice($students, $mid)
                                ];
                            ?>
                            <div class="row g-0">
                                <?php foreach ($chunks as $index => $chunk): ?>
                                    <div class="col-md-6 <?php echo $index === 0 ? 'border-end' : ''; ?>">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="70" class="text-center pt-3 pb-3">No.</th>
                                                        <th class="pt-3 pb-3">Student Name</th>
                                                        <th width="130" class="text-center pt-3 pb-3">
                                                            <div class="form-check d-flex justify-content-center align-items-center">
                                                                <input class="form-check-input select-all-col" type="checkbox" id="selectAll_<?php echo $index; ?>" checked>
                                                                <label class="form-check-label ms-2 fw-bold mb-0" for="selectAll_<?php echo $index; ?>">P</label>
                                                            </div>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($chunk as $s): 
                                                        $isPresent = ($s['current_status'] === null || $s['current_status'] === 'Present');
                                                    ?>
                                                        <tr>
                                                            <td class="text-center fw-bold text-muted small"><?php echo $s['roll_no'] ?: '-'; ?></td>
                                                            <td>
                                                                <span class="fw-bold d-block text-truncate" style="max-width: 200px;">
                                                                    <?php echo htmlspecialchars($s['surname'] . ' ' . $s['student_name'] ?? ''); ?>
                                                                </span>
                                                                <small class="text-muted d-block small"><i class="fas fa-phone-alt me-1"></i> <?php echo $s['mob']; ?></small>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="form-check d-flex justify-content-center">
                                                                    <input class="form-check-input student-check" type="checkbox" 
                                                                           name="attendance[<?php echo $s['enrollment_id']; ?>]" 
                                                                           value="Present" 
                                                                           <?php echo $isPresent ? 'checked' : ''; ?>>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-white py-3 text-center">
                            <button type="submit" class="btn btn-success px-5 py-2 fw-bold shadow-sm" id="saveBtn">
                                <i class="fas fa-save me-1"></i> Save Attendance & Send Alerts
                            </button>
                        </div>
                    </form>
                </div>
            <?php elseif ($filters['division_id']): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No students found in the selected batch.
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Select All / Deselect All logic per column
    $('.select-all-col').on('change', function() {
        const $table = $(this).closest('table');
        $table.find('.student-check').prop('checked', $(this).prop('checked'));
    });

    // Update Select All toggle if individual check is changed
    $('.student-check').on('change', function() {
        const $table = $(this).closest('table');
        const $selectAll = $table.find('.select-all-col');
        const $checks = $table.find('.student-check');
        
        if (!$(this).prop('checked')) {
            $selectAll.prop('checked', false);
        } else if ($checks.filter(':checked').length === $checks.length) {
            $selectAll.prop('checked', true);
        }
    });

    // Handle Form Session via AJAX
    $('#attendanceForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to save attendance? Absent students will receive WhatsApp alerts.')) {
            return;
        }

        const $btn = $('#saveBtn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');

        $.ajax({
            url: 'api_save_attendance.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to save attendance'
                    });
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'System Error',
                    text: 'Something went wrong on the server.'
                });
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php include '../../include/footer.php'; ?>
