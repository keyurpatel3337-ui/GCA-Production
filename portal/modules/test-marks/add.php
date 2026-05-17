<?php

/**
 * Test Marks Module - Add New Test Marks
 * Add marks for OMR MCQ or Descriptive tests
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;

require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once __DIR__ . '/data-helpers.php';

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get pre-selected student ID from URL if provided
$selected_student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

// Get dropdown data using helper functions
$students = getStudentsForDropdown();
$paper_sets = getPaperSetsForDropdown();
$answer_keys = getAnswerKeysForDropdown();

$page_title = "Add Test Marks";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>assets/css/modules/test-marks/add.css">



<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Test Marks</h3>
        </div>
        <form action="process.php" method="POST" id="testMarksForm">
            <input type="hidden" name="action" value="add">

            <div class="card-body">
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <?php echo gca_safe_html($_SESSION['error_msg']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Basic Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><strong>Student</strong> <span class="text-danger">*</span></label>
                        <div class="student-search-wrapper">
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="student_search" class="form-control student-search-input"
                                    placeholder="Search by Student ID, Name, or Mobile Number" autocomplete="off">
                            </div>
                            <input type="hidden" name="student_id" id="student_id" required>
                            <input type="hidden" name="enrollment_id" id="enrollment_id" value="">
                            <div id="student_search_results"></div>
                            </input>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Type at least 2 characters to search
                            </small>
                        </div>
                        <div class="col-md-6">
                            <div id="student_details"></div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Test Type</strong> <span
                                    class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="test_type" id="typeOMR" value="omr_mcq"
                                    checked>
                                <label class="btn btn-outline-primary" for="typeOMR">
                                    <i class="fas fa-qrcode"></i> OMR MCQ Based
                                </label>
                                <input type="radio" class="btn-check" name="test_type" id="typeDescriptive"
                                    value="descriptive">
                                <label class="btn btn-outline-success" for="typeDescriptive">
                                    <i class="fas fa-pencil-alt"></i> Descriptive Based
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Test Date</strong> <span
                                    class="text-danger">*</span></label>
                            <input type="date" name="test_date" id="test_date" class="form-control" required
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label"><strong>Test Name</strong> <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="test_name" id="test_name" class="form-control" required
                                placeholder="e.g., Entrance Test, Unit Test 1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Status</strong></label>
                            <select name="status" class="form-select">
                                <option value="evaluated">Evaluated</option>
                                <option value="pending">Pending</option>
                                <option value="verified">Verified</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <!-- OMR MCQ Section -->
                    <div id="omrSection">
                        <h5 class="mb-3"><i class="fas fa-qrcode text-primary"></i> OMR MCQ Test Details</h5>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Paper Set</label>
                                <select name="paper_set_id" id="paper_set_id" class="form-select">
                                    <option value="">-- Select Paper Set --</option>
                                    <?php foreach ($paper_sets as $ps): ?>
                                        <option value="<?php echo $ps['id']; ?>"
                                            data-questions="<?php echo $ps['total_questions']; ?>">
                                            <?php echo htmlspecialchars($ps['paper_set_name'] ?? ''); ?>
                                            (<?php echo $ps['paper_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Questions</label>
                                <input type="number" name="total_questions" id="total_questions" class="form-control"
                                    value="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Marks</label>
                                <input type="number" name="total_marks" id="total_marks" class="form-control"
                                    step="0.01" value="100">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label text-success"><i class="fas fa-check"></i> Correct
                                    Answers</label>
                                <input type="number" name="correct_answers" id="correct_answers" class="form-control"
                                    min="0" value="0" onchange="calculateOMRMarks()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-danger"><i class="fas fa-times"></i> Wrong
                                    Answers</label>
                                <input type="number" name="wrong_answers" id="wrong_answers" class="form-control"
                                    min="0" value="0" onchange="calculateOMRMarks()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary"><i class="fas fa-minus"></i>
                                    Unanswered</label>
                                <input type="number" name="unanswered" id="unanswered" class="form-control" min="0"
                                    value="0" readonly>
                            </div>
                        </div>

                        <div class="card bg-light mb-3">
                            <div class="card-header">
                                <strong>Difficulty Level Breakdown</strong>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="text-success">Low Level</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label small">Correct</label>
                                                <input type="number" name="low_level_correct"
                                                    class="form-control form-control-sm" min="0" value="0">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Wrong</label>
                                                <input type="number" name="low_level_wrong"
                                                    class="form-control form-control-sm" min="0" value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-warning">Medium Level</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label small">Correct</label>
                                                <input type="number" name="medium_level_correct"
                                                    class="form-control form-control-sm" min="0" value="0">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Wrong</label>
                                                <input type="number" name="medium_level_wrong"
                                                    class="form-control form-control-sm" min="0" value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-danger">High Level</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label small">Correct</label>
                                                <input type="number" name="high_level_correct"
                                                    class="form-control form-control-sm" min="0" value="0">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Wrong</label>
                                                <input type="number" name="high_level_wrong"
                                                    class="form-control form-control-sm" min="0" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descriptive Section -->
                    <div id="descriptiveSection" style="display: none;">
                        <h5 class="mb-3"><i class="fas fa-pencil-alt text-success"></i> Descriptive Test Details
                        </h5>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Total Marks</label>
                                <input type="number" name="desc_total_marks" id="desc_total_marks" class="form-control"
                                    step="0.01" value="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Obtained Marks</label>
                                <input type="number" name="desc_obtained_marks" id="desc_obtained_marks"
                                    class="form-control" step="0.01" value="0" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Calculated Percentage</label>
                                <input type="text" id="desc_percentage" class="form-control" readonly value="0%">
                            </div>
                        </div>

                        <div class="card bg-light mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>Subject-wise Marks Breakdown</strong>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addSubjectRow()">
                                    <i class="fas fa-plus"></i> Add Subject
                                </button>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered" id="subjectMarksTable">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th width="120">Total Marks</th>
                                            <th width="120">Obtained</th>
                                            <th width="100">%</th>
                                            <th width="60">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="text" name="subjects[0][name]" class="form-control"
                                                    placeholder="e.g., Physics"></td>
                                            <td><input type="number" name="subjects[0][total]"
                                                    class="form-control subject-total" step="0.01" value="25"
                                                    onchange="calculateSubjectPercent(this)"></td>
                                            <td><input type="number" name="subjects[0][obtained]"
                                                    class="form-control subject-obtained" step="0.01" value="0" min="0"
                                                    onchange="calculateSubjectPercent(this)"></td>
                                            <td><span class="subject-percent badge bg-secondary">0%</span></td>
                                            <td><button type="button" class="btn btn-sm btn-danger"
                                                    onclick="removeSubjectRow(this)"><i
                                                        class="fas fa-trash"></i></button></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <input type="hidden" name="subject_marks_json" id="subject_marks_json" value="[]">
                            </div>
                        </div>
                    </div>

                    <!-- Obtained Marks Display -->
                    <div class="card bg-primary text-white mb-3">
                        <div class="card-body text-center">
                            <h4 class="mb-0">
                                Obtained Marks: <span id="displayObtainedMarks">0</span> / <span
                                    id="displayTotalMarks">100</span>
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                Percentage: <span id="displayPercentage">0%</span>
                            </h4>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label"><strong>Remarks</strong></label>
                            <textarea name="remarks" class="form-control" rows="3"
                                placeholder="Add any remarks or notes about the test..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-success-custom btn-lg px-5 fw-bold">
                        <i class="fas fa-save me-2"></i> Save Test Marks
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
        </form>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    let subjectRowIndex = 1;

    $(function () {
        // Initialize Student Search Component
        const studentSearch = new StudentSearchComponent({
            inputId: 'student_search',
            hiddenInputId: 'student_id',
            resultsContainerId: 'student_search_results',
            detailsContainerId: 'student_details',
            onSelect: function (student) {
                $('#student_search').addClass('has-selection');
                console.log('Student selected:', student);

                // Set enrollment_id if available
                if (student.enrollment_id) {
                    $('#enrollment_id').val(student.enrollment_id);
                }


                const detailsHtml = `
                    <div class="alert alert-info mb-0">
                        <div class="row">
                            <div class="col-md-8">
                                <strong><i class="fas fa-user"></i> ${fullName}</strong><br>
                                <small class="text-muted">
                                    ${student.enrollment_no ? 'Enrollment: ' + student.enrollment_no + ' | ' : ''}
                                    Mobile: ${student.mob || 'N/A'}
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                ${student.course_name ? '<span class="badge bg-primary">' + student.course_name + '</span>' : ''}
                                ${student.group_name ? '<span class="badge bg-secondary">' + student.group_name + '</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
                $('#student_details').html(detailsHtml);
            }
        });

        // Clear selection styling when input is cleared
        $('#student_search').on('input', function () {
            if ($(this).val().trim() === '') {
                $(this).removeClass('has-selection');
                $('#student_details').html('');
                $('#enrollment_id').val('');
            }
        });

        // Initialize Select2 if available
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        // Handle test type change
        $('input[name="test_type"]').change(function () {
            if ($(this).val() === 'omr_mcq') {
                $('#omrSection').show();
                $('#descriptiveSection').hide();
            } else {
                $('#omrSection').hide();
                $('#descriptiveSection').show();
            }
            updateDisplay();
        });

        // Handle paper set selection
        $('#paper_set_id').change(function () {
            var questions = $(this).find(':selected').data('questions');
            if (questions) {
                $('#total_questions').val(questions);
            }
        });

        // Calculate on input change
        $('#desc_obtained_marks, #desc_total_marks').on('input', function () {
            calculateDescriptivePercent();
            updateDisplay();
        });

        // Form submission
        $('#testMarksForm').submit(function (e) {
            // Check if student is selected
            var studentId = $('#student_id').val();
            if (!studentId) {
                e.preventDefault();
                showToast('error', 'Student Required', 'Please select a student before submitting');
                $('#student_search').focus();
                return false;
            }

            if ($('input[name="test_type"]:checked').val() === 'descriptive') {
                collectSubjectMarks();
            }
        });
    });

    function calculateOMRMarks() {
        var total = parseInt($('#total_questions').val()) || 100;
        var correct = parseInt($('#correct_answers').val()) || 0;
        var wrong = parseInt($('#wrong_answers').val()) || 0;
        var unanswered = total - correct - wrong;

        if (unanswered < 0) unanswered = 0;
        $('#unanswered').val(unanswered);

        // Calculate obtained marks (1 mark per correct answer for now)
        var totalMarks = parseFloat($('#total_marks').val()) || 100;
        var marksPerQuestion = totalMarks / total;
        var obtained = correct * marksPerQuestion;

        updateDisplay(obtained, totalMarks);
    }

    function calculateDescriptivePercent() {
        var total = parseFloat($('#desc_total_marks').val()) || 100;
        var obtained = parseFloat($('#desc_obtained_marks').val()) || 0;
        var percent = (obtained / total * 100).toFixed(2);
        $('#desc_percentage').val(percent + '%');
    }

    function updateDisplay(obtained, total) {
        var testType = $('input[name="test_type"]:checked').val();

        if (testType === 'omr_mcq') {
            var totalMarks = parseFloat($('#total_marks').val()) || 100;
            var correct = parseInt($('#correct_answers').val()) || 0;
            var totalQ = parseInt($('#total_questions').val()) || 100;
            var obtainedMarks = (correct / totalQ) * totalMarks;

            $('#displayObtainedMarks').text(obtainedMarks.toFixed(2));
            $('#displayTotalMarks').text(totalMarks.toFixed(2));
            $('#displayPercentage').text(((obtainedMarks / totalMarks) * 100).toFixed(2) + '%');
        } else {
            var total = parseFloat($('#desc_total_marks').val()) || 100;
            var obtained = parseFloat($('#desc_obtained_marks').val()) || 0;

            $('#displayObtainedMarks').text(obtained.toFixed(2));
            $('#displayTotalMarks').text(total.toFixed(2));
            $('#displayPercentage').text(((obtained / total) * 100).toFixed(2) + '%');
        }
    }

    function addSubjectRow() {
        var row = `
        <tr>
            <td><input type="text" name="subjects[${subjectRowIndex}][name]" class="form-control" placeholder="e.g., Chemistry"></td>
            <td><input type="number" name="subjects[${subjectRowIndex}][total]" class="form-control subject-total" step="0.01" value="25" onchange="calculateSubjectPercent(this)"></td>
            <td><input type="number" name="subjects[${subjectRowIndex}][obtained]" class="form-control subject-obtained" step="0.01" value="0" min="0" onchange="calculateSubjectPercent(this)"></td>
            <td><span class="subject-percent badge bg-secondary">0%</span></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeSubjectRow(this)"><i class="fas fa-trash"></i></button></td>
        </tr>
    `;
        $('#subjectMarksTable tbody').append(row);
        subjectRowIndex++;
    }

    function removeSubjectRow(btn) {
        if ($('#subjectMarksTable tbody tr').length > 1) {
            $(btn).closest('tr').remove();
            recalculateDescriptiveTotals();
        }
    }

    function calculateSubjectPercent(input) {
        var row = $(input).closest('tr');
        var total = parseFloat(row.find('.subject-total').val()) || 0;
        var obtained = parseFloat(row.find('.subject-obtained').val()) || 0;
        var percent = total > 0 ? ((obtained / total) * 100).toFixed(1) : 0;

        var badge = 'secondary';
        if (percent >= 80) badge = 'success';
        else if (percent >= 60) badge = 'primary';
        else if (percent >= 40) badge = 'warning';
        else if (percent > 0) badge = 'danger';

        row.find('.subject-percent').text(percent + '%').attr('class', 'subject-percent badge bg-' + badge);

        recalculateDescriptiveTotals();
    }

    function recalculateDescriptiveTotals() {
        var totalMarks = 0;
        var totalObtained = 0;

        $('#subjectMarksTable tbody tr').each(function () {
            totalMarks += parseFloat($(this).find('.subject-total').val()) || 0;
            totalObtained += parseFloat($(this).find('.subject-obtained').val()) || 0;
        });

        $('#desc_total_marks').val(totalMarks);
        $('#desc_obtained_marks').val(totalObtained);
        calculateDescriptivePercent();
        updateDisplay();
    }

    function collectSubjectMarks() {
        var subjects = [];
        $('#subjectMarksTable tbody tr').each(function () {
            var name = $(this).find('input[name$="[name]"]').val();
            var total = parseFloat($(this).find('.subject-total').val()) || 0;
            var obtained = parseFloat($(this).find('.subject-obtained').val()) || 0;

            if (name && name.trim() !== '') {
                subjects.push({
                    subject: name.trim(),
                    total: total,
                    obtained: obtained
                });
            }
        });

        $('#subject_marks_json').val(JSON.stringify(subjects));
    }
</script>