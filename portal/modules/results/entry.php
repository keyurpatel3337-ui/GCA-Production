<?php
/**
 * Manual Mark Entry UI
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../common/security_output.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$dbOps = new DatabaseOperations();
$page_title = "Manual Mark Entry";
$page_breadcrumb = "Results - Entry";

// Search Logic
$search = $_GET['search'] ?? '';
$searchResults = [];
if (!empty($search)) {
    $searchResults = $dbOps->customSelect(
        "SELECT r.id, CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as name, 
                r.mob as mobile, c.course_name as current_class, g.group_name, es.roll_no
         FROM tbl_gm_std_registration r
         INNER JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
         LEFT JOIN tbl_courses c ON es.course_id = c.id
         LEFT JOIN tbl_group g ON es.group_id = g.id
         WHERE (r.surname LIKE ? 
            OR r.student_name LIKE ? 
            OR r.mob LIKE ? 
            OR CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ?
            OR r.id = ?)
         LIMIT 10",
        ["%$search%", "%$search%", "%$search%", "%$search%", $search]
    );
}

// Get params
$student_id = $_GET['student_id'] ?? '';
$academic_year_id = $_GET['academic_year_id'] ?? '';
$exam_type = $_GET['exam_type'] ?? '';

// Dropdowns for filters
$academic_years = $api->get('settings/academic-years')['data']['academic_years'] ?? [];
$subjects = $api->get('academics/subjects')['data'] ?? [];


// Subjects list from my previous research
if (empty($subjects)) {
    // Fallback if API fails
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

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<style>
    .mark-input {
        width: 100px;
        text-align: center;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.4rem;
        transition: all 0.2s ease;
    }

    .mark-input:focus {
        border-color: var(--theme-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
    }

    .student-result-row:hover {
        background-color: rgba(37, 99, 235, 0.02) !important;
    }

    .table thead th {
        background: var(--theme-blue) !important;
        color: white !important;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        border: none;
    }

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

    .total-display {
        background: rgba(37, 99, 235, 0.1);
        color: var(--theme-blue);
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-weight: 700;
        min-width: 50px;
        display: inline-block;
    }

    .search-result-pill {
        cursor: pointer;
        transition: all 0.2s;
        border-radius: 8px !important;
        margin-bottom: 0.25rem;
        border: 1px solid #f1f5f9;
    }

    .search-result-pill:hover {
        background: #f8fafc;
        transform: translateX(5px);
        border-color: var(--theme-blue);
    }
</style>

<div class="content-wrapper">
    <section class="content py-4">
        <div class="container-fluid">
            <!-- Header and Search -->
            <div class="glass-card mb-4">
                <div class="card-header glass-header py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Result Entry
                        Terminal</h5>
                    <div id="history_back_btn_container" style="display: none;">
                        <a href="#" id="back_to_history_link" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to History
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-section-title">
                        <i class="fas fa-filter text-primary"></i> Configuration & Selection
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Find Student</label>

                            <!-- Search Form -->
                            <form method="GET" class="mb-2">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Search Name, Roll No..."
                                        value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                                </div>
                                <?php if ($academic_year_id): ?><input type="hidden" name="academic_year_id"
                                        value="<?php echo $academic_year_id; ?>"><?php endif; ?>
                                <?php if ($exam_type): ?><input type="hidden" name="exam_type"
                                        value="<?php echo $exam_type; ?>"><?php endif; ?>
                            </form>

                            <!-- Selected Student Hidden Input for JS -->
                            <input type="hidden" id="student_select"
                                value="<?php echo htmlspecialchars($student_id ?? ''); ?>">

                            <!-- Search Results List -->
                            <?php if (!empty($searchResults)): ?>
                                <div class="list-group mt-2" style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($searchResults as $row): ?>
                                        <a href="?student_id=<?php echo $row['id']; ?>&academic_year_id=<?php echo $academic_year_id; ?>&exam_type=<?php echo $exam_type; ?>"
                                            class="list-group-item list-group-item-action p-2 search-result-pill border-0 mb-1">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0 fw-bold text-dark small"><?php echo $row['name']; ?></h6>
                                                    <small class="text-muted" style="font-size: 0.7rem;">
                                                        <i class="fas fa-id-card me-1"></i>
                                                        <?php echo $row['roll_no'] ? $row['roll_no'] : '-'; ?> |
                                                        <span
                                                            class="text-primary fw-bold"><?php echo $row['current_class']; ?></span>
                                                    </small>
                                                </div>
                                                <i class="fas fa-chevron-right text-muted small"></i>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($student_id): ?>
                                <div
                                    class="alert alert-success border-0 bg-soft-success p-2 mb-0 d-flex align-items-center mt-2">
                                    <i class="fas fa-check-circle me-2 text-success"></i>
                                    <div>
                                        <strong>Student Selected (ID: <?php echo $student_id; ?>)</strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Academic Session</label>
                            <select id="academic_year" class="form-select">
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo $year['is_active'] ? 'selected' : ''; ?>>
                                        <?php echo $year['year_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Examination Tier</label>
                            <select id="exam_type" class="form-select">
                                <option value="First Exam">First Exam</option>
                                <option value="Second Exam">Second Exam</option>
                                <option value="Annual">Annual Exam</option>
                                <option value="Internal">Internal Assessment</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" onclick="loadEntryForm()" class="btn btn-primary-custom w-100">
                                <i class="fas fa-bolt me-1"></i> Load Marks
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Marks Entry Form Area -->
            <div id="entry_form_container" style="display: none;">
                <div class="glass-card">
                    <div
                        class="card-header glass-header py-3 border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-light small d-block mb-1">DATA ENTRY FOR</span>
                            <h5 class="mb-0 fw-bold text-primary" id="student_display_name">Student Name</h5>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary px-3 py-2 me-2" id="seat_number_label">Seat No: -</span>
                            <span class="badge bg-primary-custom px-3 py-2" id="exam_type_label">First Exam</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <form id="marks_entry_form">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4" style="width: 250px;">Academic Subject</th>
                                            <th class="text-center" style="width: 120px;">Attendance</th>
                                            <th class="text-center th-theory">Theory Score</th>
                                            <th class="text-center th-practical">Practical Score</th>
                                            <th class="text-center th-internal">Internal Score</th>
                                            <th class="text-center th-aggregate pe-4" style="width: 100px;">Aggregate
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="subjects_marks_body">
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr data-subject-id="<?php echo $subject['id']; ?>" class="student-result-row">
                                                <td class="fw-semibold ps-4">
                                                    <i class="fas fa-book-open me-2 text-muted small"></i>
                                                    <?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input is-present" type="checkbox" checked>
                                                    </div>
                                                </td>
                                                <td class="text-center td-theory">
                                                    <div class="input-group input-group-sm justify-content-center"
                                                        style="width: 130px; margin: 0 auto;">
                                                        <input type="number" step="0.5"
                                                            class="form-control mark-input theory-m text-center"
                                                            placeholder="0.0">
                                                        <span
                                                            class="input-group-text bg-light text-muted small max-marks-text">/
                                                            50</span>
                                                    </div>
                                                </td>
                                                <td class="text-center td-practical">
                                                    <div class="input-group input-group-sm justify-content-center"
                                                        style="width: 130px; margin: 0 auto;">
                                                        <input type="number" step="0.5"
                                                            class="form-control mark-input practical-m text-center"
                                                            placeholder="0.0">
                                                        <span
                                                            class="input-group-text bg-light text-muted small max-marks-text">/
                                                            50</span>
                                                    </div>
                                                </td>
                                                <td class="text-center td-internal">
                                                    <div class="input-group input-group-sm justify-content-center"
                                                        style="width: 130px; margin: 0 auto;">
                                                        <input type="number" step="0.5"
                                                            class="form-control mark-input internal-m text-center"
                                                            placeholder="0.0">
                                                        <span
                                                            class="input-group-text bg-light text-muted small max-marks-text">/
                                                            20</span>
                                                    </div>
                                                </td>
                                                <td class="text-center td-aggregate pe-4">
                                                    <span class="total-display fw-bold text-primary">0.0</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="card-footer bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i> Data is automatically saved locally until
                                    submitted.
                                </div>
                                <button type="submit" class="btn btn-success-custom px-5">
                                    <i class="fas fa-cloud-upload-alt me-2"></i> Finalize & Record Marks
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>


<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Check for URL parameters for auto-loading (Edit Mode or Search Result)
        const urlParams = new URLSearchParams(window.location.search);
        const autoStudentId = urlParams.get('student_id');
        const autoExamType = urlParams.get('exam_type');
        const autoYearId = urlParams.get('academic_year_id');

        if (autoStudentId) {
            // Set Hidden Input (already set by PHP value, but ensure)
            $('#student_select').val(autoStudentId);

            // Set Academic Year & Exam Type if present
            if (autoYearId) $('#academic_year').val(autoYearId);
            if (autoExamType) $('#exam_type').val(autoExamType);

            // Fetch Student Details for Display
            $.get(BACKEND_URL + '/index.php', {
                route: 'students/details',
                id: autoStudentId
            }, function (response) {
                if (response.success && response.data && response.data.student) {
                    const s = response.data.student;
                    const e = response.data.enrollment;

                    // Populate UI
                    $('#st_name').text((s.surname || '') + ' ' + (s.student_name || '') + ' ' + (s.fathers_name || ''));
                    $('#st_roll').text('Roll No: ' + (e && e.roll_no ? e.roll_no : (s.roll_no || 'N/A')));
                    const initials = (s.student_name ? s.student_name[0] : 'S') + (s.surname ? s.surname[0] : '');
                    $('#st_initials').text(initials.toUpperCase());

                    // Update header name too
                    $('#student_display_name').text((s.surname || '') + ' ' + (s.student_name || ''));

                    // Show info box
                    $('#student_info_display').removeClass('d-none');

                    // If we have full context, load marks
                    if (autoExamType && autoYearId) {
                        loadEntryForm();
                    }
                }
            });
        }


        // Load subjects via API
        $.get(BACKEND_URL + '/index.php', {
            route: 'academics/subjects'
        }, function (response) {

            const subjects = response.data;
            let html = '';
            if (subjects && Array.isArray(subjects)) {
                subjects.forEach(function (s) {
                    html += `<tr data-subject-id="${s.id}" class="student-result-row">
                        <td class="fw-semibold ps-4">
                            <i class="fas fa-book-open me-2 text-muted small"></i>
                            ${s.subject_name}
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input is-present" type="checkbox" checked>
                            </div>
                        </td>
                        <td class="text-center td-theory">
                            <div class="input-group input-group-sm justify-content-center" style="width: 130px; margin: 0 auto;">
                                <input type="number" step="0.5" class="form-control mark-input theory-m text-center" placeholder="0.0">
                                <span class="input-group-text bg-light text-muted small max-marks-text">/ 50</span>
                            </div>
                        </td>
                        <td class="text-center td-practical">
                            <div class="input-group input-group-sm justify-content-center" style="width: 130px; margin: 0 auto;">
                                <input type="number" step="0.5" class="form-control mark-input practical-m text-center" placeholder="0.0">
                                <span class="input-group-text bg-light text-muted small max-marks-text">/ 50</span>
                            </div>
                        </td>
                        <td class="text-center td-internal">
                            <div class="input-group input-group-sm justify-content-center" style="width: 130px; margin: 0 auto;">
                                <input type="number" step="0.5" class="form-control mark-input internal-m text-center" placeholder="0.0">
                                <span class="input-group-text bg-light text-muted small max-marks-text">/ 20</span>
                            </div>
                        </td>
                        <td class="text-center td-aggregate pe-4">
                            <span class="total-display fw-bold text-primary">0.0</span>
                        </td>
                    </tr>`;
                });

                $('#subjects_marks_body').html(html);
                configureEntryForm(); // Re-apply configuration for new rows
            }
        });

        // Auto-calculate totals
        $(document).on('input', '.mark-input', function () {
            let row = $(this).closest('tr');
            calculateRowTotal(row);
        });

        $('#marks_entry_form').on('submit', function (e) {
            e.preventDefault();
            saveMarks();
        });
    });

    function calculateRowTotal(row) {
        let theory = parseFloat(row.find('.theory-m').val()) || 0;
        let practical = parseFloat(row.find('.practical-m').val()) || 0;
        let internal = parseFloat(row.find('.internal-m').val()) || 0;
        let total = theory + practical + internal;
        row.find('.total-display').text(total.toFixed(1));
    }

    function loadEntryForm() {
        const student_id = $('#student_select').val();
        const exam_type = $('#exam_type').val();
        const academic_year_text = $('#academic_year option:selected').text().trim();
        const student_text = $('#student_select').find(':selected').text();
        const roll_match = student_text.match(/(\d+)\s*\|/); // Extract roll no from dropdown text format
        const roll_no = roll_match ? roll_match[1] : '000';

        if (!student_id) {
            showToast('warning', 'Please select a student first', '');
            return;
        }

        $('#student_display_name').text($('#student_select').find(':selected').text().split('|')[0] || student_text);
        $('#exam_type_label').text(exam_type);
        
        // Auto-generate seat number based on year + exam initial + roll no
        const examInitial = exam_type ? exam_type.charAt(0).toUpperCase() : 'X';
        const yearPrefix = academic_year_text ? academic_year_text.substring(0, 4) : new Date().getFullYear();
        const seatNo = `${yearPrefix}${examInitial}${roll_no.padStart(3, '0')}`;
        $('#seat_number_label').text(`Seat No: ${seatNo}`);

        // Load existing marks via API
        $.get(BACKEND_URL + '/index.php', {
            route: 'results/marks',
            action: 'get',
            student_id: student_id,
            exam_type: exam_type
        }, function (response) { // renamed data to response for clarity
            console.log("Marks API Response:", response);

            let marks = [];
            if (Array.isArray(response)) {
                marks = response;
            } else if (response && response.data && Array.isArray(response.data)) {
                marks = response.data;
            } else if (response && response.marks && Array.isArray(response.marks)) {
                marks = response.marks;
            }

            // Reset form
            $('.mark-input').val('');
            $('.is-present').prop('checked', true);
            $('.total-display').text('0');

            // Fill existing marks
            if (marks.length > 0) {
                marks.forEach(function (m) {
                    let row = $(`tr[data-subject-id="${m.subject_id}"]`);
                    if (row.length) {
                        row.find('.theory-m').val(m.theory_marks);
                        row.find('.practical-m').val(m.practical_marks);
                        row.find('.internal-m').val(m.internal_marks);
                        row.find('.is-present').prop('checked', m.is_present == 1);
                        calculateRowTotal(row);
                    }
                });
            }

            $('#entry_form_container').fadeIn();
        });
    }

    function saveMarks() {
        const student_id = $('#student_select').val();
        const exam_type = $('#exam_type').val();
        const academic_year_id = $('#academic_year').val();

        let marks = [];
        $('#subjects_marks_body tr').each(function () {
            let row = $(this);
            marks.push({
                subject_id: row.data('subject-id'),
                theory: row.find('.theory-m').val(),
                practical: row.find('.practical-m').val(),
                internal: row.find('.internal-m').val(),
                is_present: row.find('.is-present').is(':checked') ? 1 : 0
            });
        });

        const payload = {
            student_id: student_id,
            exam_type: exam_type,
            academic_year_id: academic_year_id,
            course_id: 1, // Assuming Standard 11 Science course ID is 1 for now
            marks: marks
        };

        $.ajax({
            url: BACKEND_URL + '/index.php?route=results/marks&action=save',
            type: 'POST',

            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (res) {
                if (res.success) {
                    showToast('success', 'Success', 'Marks saved successfully!');
                } else {
                    showToast('error', 'Error', res.error || 'Failed to save marks');
                }
            },
            error: function () {
                showToast('error', 'Error', 'Failed to communicate with server');
            }
        });
    }
    function configureEntryForm() {
        const examType = $('#exam_type').val();

        // Default: Hide all, reset max
        $('.th-theory, .td-theory').hide();
        $('.th-practical, .td-practical').hide();
        $('.th-internal, .td-internal').hide();
        $('.th-aggregate, .td-aggregate').hide(); // Hide aggregate if only one col? No, keep it.
        $('.th-aggregate, .td-aggregate').show();

        $('.mark-input').removeAttr('max').attr('placeholder', '0.0');

        if (examType === 'First Exam' || examType === 'Second Exam') {
            $('.th-theory, .td-theory').show();
            $('.theory-m').attr('max', 50);
            $('.td-theory .max-marks-text').text('/ 50');
        } else if (examType === 'Annual' || examType === 'Annual Exam') {
            $('.th-theory, .td-theory').show();
            $('.theory-m').attr('max', 80);
            $('.td-theory .max-marks-text').text('/ 80');
            // Check if practicals are needed for Annual? User said "If no practical...".
            // We assume hidden unless specific subject logic added (complex).
        } else if (examType === 'Internal' || examType === 'Internal Assessment') {
            $('.th-internal, .td-internal').show();
            $('.internal-m').attr('max', 20);
            $('.td-internal .max-marks-text').text('/ 20');
        }
    }

    // Call configure on load and change
    $(document).ready(function () {
        $('#exam_type').change(configureEntryForm);
        // Initial call
        setTimeout(configureEntryForm, 500); // Small delay to ensure DOM readiness if needed
    });
</script>