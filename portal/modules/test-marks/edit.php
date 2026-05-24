<?php

/**
 * Test Marks Module - Edit Test Marks
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once __DIR__ . '/data-helpers.php';

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = $_POST['id'] ?? 0;

// Get test mark details
try {
    $op = new Operation();
    $mark = $op->readWithJoin(
        'tbl_test_marks tm',
        ['tm.*', "CONCAT(r.surname, ' ', r.student_name) AS student_name", 'r.mob AS student_mobile', 'en.enrollment_no'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_gm_std_registration r', 'on' => 'tm.student_id = r.id'],
            ['type' => 'LEFT', 'table' => 'tbl_enrolled_students en', 'on' => 'tm.enrollment_id = en.enrollment_id']
        ],
        ['tm.id' => $id]
    );

    if (!$mark) {
        set_flash_message('error', 'Test mark record not found.');
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Get dropdown data using helper functions
$students = getStudentsForDropdown();
$paper_sets = getPaperSetsForDropdown();

// Decode subject marks for descriptive tests
$subjects = json_decode($mark['subject_marks_json'] ?? '[]', true) ?: [];

$page_title = "Edit Test Marks" ;
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



    <div class="container-fluid">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-success-custom text-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0 font-weight-bold"><i class="fas fa-edit me-2"></i> Edit Test Marks</h3>
            </div>
            <form action="process.php" method="POST" id="testMarksForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $mark['id']; ?>">

                <div class="card-body">
                    <?php display_flash_messages(); ?>

                    <!-- Basic Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Student</strong> <span
                                    class="text-danger">*</span></label>
                            <select name="student_id" id="student_id" class="form-select select2" required>
                                <option value="">-- Select Student --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>"
                                        data-enrollment="<?php echo $student['enrollment_id'] ?? ''; ?>" <?php echo $student['id'] == $mark['student_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['display_name'] ?? ''); ?>
                                        <?php if ($student['enrollment_no']): ?>
                                            [<?php echo htmlspecialchars($student['enrollment_no'] ?? ''); ?>]
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="enrollment_id" id="enrollment_id"
                                value="<?php echo $mark['enrollment_id'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Test Type</strong> <span
                                    class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="test_type" id="typeOMR" value="omr_mcq"
                                    <?php echo $mark['test_type'] === 'omr_mcq' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="typeOMR">
                                    <i class="fas fa-qrcode"></i> OMR MCQ Based
                                </label>
                                <input type="radio" class="btn-check" name="test_type" id="typeDescriptive"
                                    value="descriptive" <?php echo $mark['test_type'] === 'descriptive' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-success" for="typeDescriptive">
                                    <i class="fas fa-pencil-alt"></i> Descriptive Based
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label"><strong>Test Name</strong> <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="test_name" id="test_name" class="form-control" required
                                value="<?php echo htmlspecialchars($mark['test_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Test Date</strong> <span
                                    class="text-danger">*</span></label>
                            <input type="date" name="test_date" id="test_date" class="form-control" required
                                value="<?php echo $mark['test_date']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>Status</strong></label>
                            <select name="status" class="form-select">
                                <option value="evaluated" <?php echo $mark['status'] === 'evaluated' ? 'selected' : ''; ?>>Evaluated</option>
                                <option value="pending" <?php echo $mark['status'] === 'pending' ? 'selected' : ''; ?>>
                                    Pending</option>
                                <option value="verified" <?php echo $mark['status'] === 'verified' ? 'selected' : ''; ?>>
                                    Verified</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <!-- OMR MCQ Section -->
                    <div id="omrSection" class="css-edit-3d7736">
                        <h5 class="mb-3"><i class="fas fa-qrcode text-primary"></i> OMR MCQ Test Details</h5>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Paper Set</label>
                                <select name="paper_set_id" id="paper_set_id" class="form-select">
                                    <option value="">-- Select Paper Set --</option>
                                    <?php foreach ($paper_sets as $ps): ?>
                                        <option value="<?php echo $ps['id']; ?>"
                                            data-questions="<?php echo $ps['total_questions']; ?>" <?php echo $ps['id'] == $mark['paper_set_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ps['paper_set_name'] ?? ''); ?>
                                            (<?php echo $ps['paper_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Questions</label>
                                <input type="number" name="total_questions" id="total_questions" class="form-control"
                                    value="<?php echo $mark['total_questions'] ?? 100; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Marks</label>
                                <input type="number" name="total_marks" id="total_marks" class="form-control"
                                    step="0.01" value="<?php echo $mark['total_marks']; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label text-success"><i class="fas fa-check"></i> Correct
                                    Answers</label>
                                <input type="number" name="correct_answers" id="correct_answers" class="form-control"
                                    min="0" value="<?php echo $mark['correct_answers'] ?? 0; ?>"
                                    onchange="calculateOMRMarks()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-danger"><i class="fas fa-times"></i> Wrong Answers</label>
                                <input type="number" name="wrong_answers" id="wrong_answers" class="form-control"
                                    min="0" value="<?php echo $mark['wrong_answers'] ?? 0; ?>"
                                    onchange="calculateOMRMarks()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary"><i class="fas fa-minus"></i> Unanswered</label>
                                <input type="number" name="unanswered" id="unanswered" class="form-control" min="0"
                                    readonly value="<?php echo $mark['unanswered'] ?? 0; ?>">
                            </div>
                        </div>

                        <div class="card bg-light mb-3">
                            <div class="card-header"><strong>Difficulty Level Breakdown</strong></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="text-success">Low Level</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label small">Correct</label>
                                                <input type="number" name="low_level_correct"
                                                    class="form-control form-control-sm" min="0"
                                                    value="<?php echo $mark['low_level_correct'] ?? 0; ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Wrong</label>
                                                <input type="number" name="low_level_wrong"
                                                    class="form-control form-control-sm" min="0"
                                                    value="<?php echo $mark['low_level_wrong'] ?? 0; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-warning">Medium Level</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label small">Correct</label>
                                                <input type="number" name="medium_level_correct"
                                                    class="form-control form-control-sm" min="0"
                                                    value="<?php echo $mark['medium_level_correct'] ?? 0; ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Wrong</label>
                                                <input type="number" name="medium_level_wrong"
                                                    class="form-control form-control-sm" min="0"
                                                    value="<?php echo $mark['medium_level_wrong'] ?? 0; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-danger">High Level</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label small">Correct</label>
                                                <input type="number" name="high_level_correct"
                                                    class="form-control form-control-sm" min="0"
                                                    value="<?php echo $mark['high_level_correct'] ?? 0; ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Wrong</label>
                                                <input type="number" name="high_level_wrong"
                                                    class="form-control form-control-sm" min="0"
                                                    value="<?php echo $mark['high_level_wrong'] ?? 0; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descriptive Section -->
                    <div id="descriptiveSection" class="css-edit-53c152">
                        <h5 class="mb-3"><i class="fas fa-pencil-alt text-success"></i> Descriptive Test Details</h5>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Total Marks</label>
                                <input type="number" name="desc_total_marks" id="desc_total_marks" class="form-control"
                                    step="0.01" value="<?php echo $mark['total_marks']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Obtained Marks</label>
                                <input type="number" name="desc_obtained_marks" id="desc_obtained_marks"
                                    class="form-control" step="0.01" value="<?php echo $mark['obtained_marks']; ?>"
                                    min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Calculated Percentage</label>
                                <input type="text" id="desc_percentage" class="form-control" readonly
                                    value="<?php echo formatIndianCurrency($mark['percentage']); ?>%">
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
                                        <?php if (count($subjects) > 0): ?>
                                            <?php foreach ($subjects as $i => $subject): ?>
                                                <tr>
                                                    <td><input type="text" name="subjects[<?php echo $i; ?>][name]"
                                                            class="form-control"
                                                            value="<?php echo htmlspecialchars($subject['subject'] ?? ''); ?>"></td>
                                                    <td><input type="number" name="subjects[<?php echo $i; ?>][total]"
                                                            class="form-control subject-total" step="0.01"
                                                            value="<?php echo $subject['total']; ?>"
                                                            onchange="calculateSubjectPercent(this)"></td>
                                                    <td><input type="number" name="subjects[<?php echo $i; ?>][obtained]"
                                                            class="form-control subject-obtained" step="0.01"
                                                            value="<?php echo $subject['obtained']; ?>" min="0"
                                                            onchange="calculateSubjectPercent(this)"></td>
                                                    <td>
                                                        <?php $s_pct = $subject['total'] > 0 ? ($subject['obtained'] / $subject['total'] * 100) : 0; ?>
                                                        <span
                                                            class="subject-percent badge bg-secondary"><?php echo formatIndianCurrency($s_pct); ?>%</span>
                                                    </td>
                                                    <td><button type="button" class="btn btn-sm btn-danger"
                                                            onclick="removeSubjectRow(this)"><i
                                                                class="fas fa-trash"></i></button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
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
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <input type="hidden" name="subject_marks_json" id="subject_marks_json"
                                    value='<?php echo htmlspecialchars($mark['subject_marks_json'] ?? '[]'); ?>'>
                            </div>
                        </div>
                    </div>

                    <!-- Obtained Marks Display -->
                    <div class="card bg-success-custom text-white mb-3 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <h4 class="mb-0 font-weight-bold">
                                Obtained Marks: <span
                                    id="displayObtainedMarks"><?php echo formatIndianCurrency($mark['obtained_marks']); ?></span>
                                <small class="text-white-50 mx-2">/</small>
                                <span
                                    id="displayTotalMarks"><?php echo formatIndianCurrency($mark['total_marks']); ?></span>
                                <span class="mx-3 opacity-25">|</span>
                                Percentage: <span
                                    id="displayPercentage" class="bg-white text-dark px-2 py-1 rounded small"><?php echo formatIndianCurrency($mark['percentage']); ?>%</span>
                            </h4>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label"><strong>Remarks</strong></label>
                            <textarea name="remarks" class="form-control"
                                rows="3"><?php echo htmlspecialchars($mark['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-4">
                    <button type="submit" class="btn btn-success-custom btn-lg px-5 shadow-sm">
                        <i class="fas fa-save me-2"></i> Update Test Marks
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg px-4 ms-2">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    let subjectRowIndex = <?php echo count($subjects) > 0 ? count($subjects) : 1; ?>;

    $(function () {
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

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

        $('#student_id').change(function () {
            var enrollment = $(this).find(':selected').data('enrollment');
            $('#enrollment_id').val(enrollment || '');
        });

        $('#desc_obtained_marks, #desc_total_marks').on('input', function () {
            calculateDescriptivePercent();
            updateDisplay();
        });

        $('#testMarksForm').submit(function (e) {
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
        var row = `<tr>
        <td><input type="text" name="subjects[${subjectRowIndex}][name]" class="form-control" placeholder="e.g., Chemistry"></td>
        <td><input type="number" name="subjects[${subjectRowIndex}][total]" class="form-control subject-total" step="0.01" value="25" onchange="calculateSubjectPercent(this)"></td>
        <td><input type="number" name="subjects[${subjectRowIndex}][obtained]" class="form-control subject-obtained" step="0.01" value="0" min="0" onchange="calculateSubjectPercent(this)"></td>
        <td><span class="subject-percent badge bg-secondary">0%</span></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeSubjectRow(this)"><i class="fas fa-trash"></i></button></td>
    </tr>`;
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
        var totalMarks = 0,
            totalObtained = 0;
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
