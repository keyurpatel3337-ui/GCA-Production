<?php
/**
 * Bulk Marks Entry UI
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../common/security_output.php';
require_once DB_CONNECT_FILE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
global $conn;

$page_title = "Bulk Marks Entry";
$page_breadcrumb = "Results - Bulk Entry";

// Fetch Dropdowns
$academic_years = $api->get('settings/academic-years')['data']['academic_years'] ?? [];
$subjects = $api->get('academics/subjects')['data'] ?? [];
$courses = [];
$groups = [];
$divisions = [];
try {
    $stmt = $conn->query("SELECT id, course_name FROM tbl_courses WHERE is_active = 1 ORDER BY id");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT id, division_name FROM tbl_division WHERE is_active = 1 ORDER BY display_order");
    $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

if (empty($subjects)) {
    $subjects = [
        ['id' => 1, 'subject_name' => 'Physics'],
        ['id' => 2, 'subject_name' => 'Chemistry'],
        ['id' => 3, 'subject_name' => 'Biology'],
        ['id' => 4, 'subject_name' => 'Mathematics'],
        ['id' => 5, 'subject_name' => 'English'],
        ['id' => 6, 'subject_name' => 'Computer'],
        ['id' => 7, 'subject_name' => 'Sanskrit']
    ];
}

$selected_year = $_GET['academic_year_id'] ?? '';
$selected_course = $_GET['course_id'] ?? '';
$selected_group = $_GET['group_id'] ?? '';
$selected_division = $_GET['division_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';
$selected_exam = $_GET['exam_type'] ?? '';

$success_msg = "";
$error_msg = "";

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    $post_year = $_POST['academic_year_id'];
    $post_course = $_POST['course_id'];
    $post_group = $_POST['group_id'] ?? '';
    $post_division = $_POST['division_id'] ?? '';
    $post_subject = $_POST['subject_id'];
    $post_exam = $_POST['exam_type'];
    $marks_data = $_POST['marks'] ?? [];

    $conn->beginTransaction();
    try {
        $check_sql = "SELECT id FROM tbl_student_exam_marks WHERE student_id = ? AND subject_id = ? AND exam_type = ?";
        $stmt_check = $conn->prepare($check_sql);

        $update_sql = "UPDATE tbl_student_exam_marks SET 
                       theory_marks = ?, practical_marks = ?, internal_marks = ?, 
                       total_marks = ?, is_present = ?, academic_year_id = ?, 
                       course_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt_update = $conn->prepare($update_sql);

        $insert_sql = "INSERT INTO tbl_student_exam_marks (
                       student_id, academic_year_id, course_id, exam_type, 
                       subject_id, theory_marks, practical_marks, internal_marks, 
                       total_marks, is_present, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt_insert = $conn->prepare($insert_sql);

        $success_count = 0;
        foreach ($marks_data as $student_id => $m) {
            $student_course_id = $m['course_id']; // Use the student's actual course_id from hidden input
            $theory = (float) ($m['theory'] ?? 0);
            $practical = (float) ($m['practical'] ?? 0);
            $internal = (float) ($m['internal'] ?? 0);
            $total = $theory + $practical + $internal;
            $is_present = 1; // Assuming always present for bulk entry

            $stmt_check->execute([$student_id, $post_subject, $post_exam]);
            $existing = $stmt_check->fetch();

            if ($existing) {
                $stmt_update->execute([$theory, $practical, $internal, $total, $is_present, $post_year, $student_course_id, $existing['id']]);
            } else {
                $stmt_insert->execute([$student_id, $post_year, $student_course_id, $post_exam, $post_subject, $theory, $practical, $internal, $total, $is_present]);
            }
            $success_count++;
        }
        $conn->commit();
        $success_msg = "Successfully saved marks for $success_count students!";

        // Re-assign GET variables to keep the UI state
        $selected_year = $post_year;
        $selected_course = $post_course;
        $selected_group = $post_group;
        $selected_division = $post_division;
        $selected_subject = $post_subject;
        $selected_exam = $post_exam;
    } catch (Exception $e) {
        $conn->rollBack();
        $error_msg = "Error saving marks: " . $e->getMessage();
    }
}

$students = [];
if ($selected_year && $selected_course && $selected_subject && $selected_exam) {
    try {
        $where_clauses = ["r.academic_year_id = ?", "es.is_active = 1"];
        $params = [$selected_subject, $selected_exam, $selected_year, $selected_year];

        if ($selected_course) {
            if ($selected_course === '11th') {
                $where_clauses[] = "es.course_id IN (1, 2)";
            } elseif ($selected_course === '12th') {
                $where_clauses[] = "es.course_id IN (4, 5)";
            } elseif ($selected_course === 'Reneet') {
                $where_clauses[] = "es.course_id = 6";
            } else {
                $where_clauses[] = "es.course_id = ?";
                $params[] = $selected_course;
            }
        }

        if ($selected_group) {
            $where_clauses[] = "es.group_id = ?";
            $params[] = $selected_group;
        }
        if ($selected_division) {
            $where_clauses[] = "es.division_id = ?";
            $params[] = $selected_division;
        }

        $where_sql = implode(" AND ", $where_clauses);

        $sql = "SELECT r.id as student_id, es.course_id, CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as name, 
                es.roll_no, m.theory_marks, m.practical_marks, m.internal_marks
                FROM tbl_gm_std_registration r
                INNER JOIN tbl_enrolled_students es ON es.registration_id = r.id
                LEFT JOIN tbl_student_exam_marks m ON m.student_id = r.id 
                    AND m.subject_id = ? AND m.exam_type = ? AND m.academic_year_id = ? AND m.course_id = es.course_id
                WHERE $where_sql
                ORDER BY es.roll_no DESC, r.student_name desc";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_msg = "Error fetching students: " . $e->getMessage();
    }
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<style>
    .glass-header {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .form-section-title {
        font-size: 0.875rem;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .mark-input {
        width: 80px;
        text-align: center;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.4rem;
    }

    .mark-input:focus {
        border-color: var(--theme-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
    }

    .table thead th {
        background: var(--theme-blue) !important;
        color: white !important;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        border: none;
        vertical-align: middle;
    }

    .btn-success-custom {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-success-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2), 0 4px 6px -2px rgba(16, 185, 129, 0.1);
        background: linear-gradient(135deg, #047857 0%, #059669 100%);
        color: white;
    }

    .btn-success-custom:active {
        transform: translateY(0);
    }

    .marks-footer {
        background: white;
        padding: 1.5rem;
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 1rem;
    }
</style>

<div class="content-wrapper">
    <section class="content py-4">
        <div class="container-fluid">

            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Configuration Card -->
            <div class="glass-card mb-4">
                <div class="card-header glass-header py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-users-cog me-2 text-primary"></i>Bulk Marks
                        Entry</h5>
                    <div class="card-tools">
                        <a href="results.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Results
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Academic Session</label>
                                <select name="academic_year_id" class="form-select" required>
                                    <option value="">Select Year</option>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>" <?php echo ($selected_year == $year['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($year['year_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Standard</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">Select Standard</option>
                                    <option value="11th" <?php echo ($selected_course == '11th') ? 'selected' : ''; ?>>11th</option>
                                    <option value="12th" <?php echo ($selected_course == '12th') ? 'selected' : ''; ?>>12th</option>
                                    <option value="Reneet" <?php echo ($selected_course == 'Reneet') ? 'selected' : ''; ?>>Reneet</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Group</label>
                                <select name="group_id" class="form-select">
                                    <option value="">All Groups</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>" <?php echo ($selected_group == $group['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Division</label>
                                <select name="division_id" class="form-select">
                                    <option value="">All Divisions</option>
                                    <?php foreach ($divisions as $division): ?>
                                        <option value="<?php echo $division['id']; ?>" <?php echo ($selected_division == $division['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($division['division_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Subject</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo ($selected_subject == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Examination</label>
                                <select name="exam_type" class="form-select" required>
                                    <option value="">Select Exam</option>
                                    <option value="First Exam" <?php echo ($selected_exam == 'First Exam') ? 'selected' : ''; ?>>First Exam</option>
                                    <option value="Second Exam" <?php echo ($selected_exam == 'Second Exam') ? 'selected' : ''; ?>>Second Exam</option>
                                    <option value="Annual" <?php echo ($selected_exam == 'Annual') ? 'selected' : ''; ?>>
                                        Annual Exam</option>
                                    <option value="Internal" <?php echo ($selected_exam == 'Internal') ? 'selected' : ''; ?>>Internal Assessment</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Load
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mark Entry Table -->
            <?php if ($selected_year && $selected_course && $selected_subject && $selected_exam): ?>
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Enter Marks</h5>
                            <small class="text-muted">Use the <strong>Tab</strong> key to navigate quickly down the
                                columns.</small>
                        </div>
                        <div style="width: 300px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="studentSearch" class="form-control border-start-0 ps-0"
                                    placeholder="Search by name or roll no...">
                            </div>
                        </div>
                    </div>
                    <?php if (count($students) > 0): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="academic_year_id"
                                value="<?php echo htmlspecialchars($selected_year ?? ''); ?>">
                            <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($selected_course ?? ''); ?>">
                            <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($selected_group ?? ''); ?>">
                            <input type="hidden" name="division_id" value="<?php echo htmlspecialchars($selected_division ?? ''); ?>">
                            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject ?? ''); ?>">
                            <input type="hidden" name="exam_type" value="<?php echo htmlspecialchars($selected_exam ?? ''); ?>">

                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="sticky-top">
                                        <tr>
                                            <th class="ps-4">Roll No</th>
                                            <th>Seat No</th>
                                            <th>Student Name</th>
                                            <th class="text-center col-theory">Theory Score</th>
                                            <th class="text-center col-internal">Internal Score</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentTableBody">
                                        <?php
                                        $tab_index_theory = 1;
                                        $tab_index_practical = 1000;
                                        $tab_index_internal = 2000;

                                        // Pre-calculate prefixes for Seat No
                                        $examInitial = $selected_exam ? strtoupper(substr($selected_exam, 0, 1)) : 'X';
                                        $yearText = '';
                                        foreach ($academic_years as $ay) {
                                            if ($ay['id'] == $selected_year) {
                                                $yearText = substr($ay['year_name'], 0, 4);
                                                break;
                                            }
                                        }
                                        if (empty($yearText))
                                            $yearText = date('Y');

                                        foreach ($students as $student):
                                            $rollNumFormatted = str_pad($student['roll_no'] ?: '0', 3, '0', STR_PAD_LEFT);
                                            $seatNo = "{$yearText}{$examInitial}{$rollNumFormatted}";
                                            ?>
                                            <tr>
                                                <td class="ps-4 fw-bold">
                                                    <?php echo htmlspecialchars($student['roll_no'] ?: '-' ?? ''); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $seatNo; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($student['name'] ?? ''); ?>
                                                </td>
                                                <td class="text-center col-theory">
                                                    <input type="hidden" name="marks[<?php echo $student['student_id']; ?>][course_id]" value="<?php echo $student['course_id']; ?>">
                                                    <input type="number" step="0.5"
                                                        name="marks[<?php echo $student['student_id']; ?>][theory]"
                                                        class="form-control mark-input mx-auto"
                                                        value="<?php echo htmlspecialchars($student['theory_marks'] ?? ''); ?>"
                                                        tabindex="<?php echo $tab_index_theory++; ?>">
                                                </td>
                                                <td class="text-center col-internal">
                                                    <input type="number" step="0.5"
                                                        name="marks[<?php echo $student['student_id']; ?>][internal]"
                                                        class="form-control mark-input mx-auto"
                                                        value="<?php echo htmlspecialchars($student['internal_marks'] ?? ''); ?>"
                                                        tabindex="<?php echo $tab_index_internal++; ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="marks-footer">
                                <button type="submit" name="bulk_save" class="btn btn-success-custom px-5 py-2 fw-bold">
                                    <i class="fas fa-save me-2"></i> Save All Marks
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-clipboard-list fa-3x mb-3 text-light"></i>
                            <h5>No Students Found</h5>
                            <p>No active students are enrolled in the selected standard for this academic year.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Dynamic column visibility based on exam type, mimicking entry.php logic
        function configureColumns() {
            const examType = $('select[name="exam_type"]').val();

            $('.col-theory').hide();
            $('.col-internal').hide();

            if (examType === 'First Exam' || examType === 'Second Exam' || examType === 'Annual' || examType === 'Annual Exam') {
                $('.col-theory').show();
            } else if (examType === 'Internal' || examType === 'Internal Assessment') {
                $('.col-internal').show();
            } else {
                // Show all if unselected or unknown
                $('.col-theory, .col-internal').show();
            }
        }

        // Check on load
        configureColumns();

        // Live Search Filtering
        $('#studentSearch').on('keyup', function () {
            const value = $(this).val().toLowerCase();
            $("#studentTableBody tr").filter(function () {
                // Search in Name (column 2) and Roll No (column 0)
                const name = $(this).find('td:nth-child(3)').text().toLowerCase();
                const roll = $(this).find('td:nth-child(1)').text().toLowerCase();
                $(this).toggle(name.indexOf(value) > -1 || roll.indexOf(value) > -1);
            });
        });

        // Enter Key Navigation (act like Tab)
        $(document).on('keydown', '.mark-input', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                const currentTabIndex = parseInt($(this).attr('tabindex'));
                const nextElement = $('[tabindex="' + (currentTabIndex + 1) + '"]');

                if (nextElement.length > 0) {
                    nextElement.focus();
                } else {
                    // If no next element in current column, try to find the first element of the next column
                    // This depends on the specific tabindex ranges (1, 1000, 2000)
                    let nextGroupStart = 1;
                    if (currentTabIndex < 1000) nextGroupStart = 1000;
                    else if (currentTabIndex < 2000) nextGroupStart = 2000;
                    else return; // End of internal column

                    const nextGroupElement = $('[tabindex="' + nextGroupStart + '"]');
                    if (nextGroupElement.length > 0) {
                        nextGroupElement.focus();
                    }
                }
            }
        });

        // Restrict input to numbers and dot only
        $(document).on('keypress', '.mark-input', function (e) {
            const charCode = (e.which) ? e.which : e.keyCode;
            // Allow dot (46) if not already present
            if (charCode === 46) {
                if ($(this).val().indexOf('.') !== -1) {
                    e.preventDefault();
                    return false;
                }
                return true;
            }
            // Allow numbers (48-57)
            if (charCode >= 48 && charCode <= 57) {
                return true;
            }
            // Block everything else (except control keys like Backspace, but keypress usually only triggers for printable chars)
            // Note: backspace, tab, enter are handled by browser/other listeners
            if (charCode > 31) {
                e.preventDefault();
                return false;
            }
        });
    });
</script>
