<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

// Check if user is Super Admin or Principle
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if (!isset($_SESSION['blueprint_preview'])) {
    set_flash_message('error', 'No blueprint data to preview!');
    header('Location: blueprint-upload.php');
    exit;
}

$preview = $_SESSION['blueprint_preview'];
$page_title = "Preview Blueprint - " . $preview['paper_set_name'] . "";
$page_breadcrumb = "Blueprint";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="alert alert-success">
            <h5><i class="icon fas fa-check"></i> File Parsed Successfully!</h5>
            <strong>Paper Set:</strong> <?php echo htmlspecialchars($preview['paper_set_name'] ?? ''); ?><br>
            <strong>Total Topics:</strong> <?php echo count($preview['data']); ?>
        </div>

        <form id="blueprintForm">
            <input type="hidden" name="paper_set_id" value="<?php echo $preview['paper_set_id']; ?>">

            <div class="card">
                <div class="card-header bg-primary">
                    <h3 class="card-title">Blueprint Topics & Question Mapping</h3>
                    <div class="card-tools">
                        <span class="badge bg-light text-dark">Review and edit if needed</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th width="50">Sr.No</th>
                                    <th width="100">Subject</th>
                                    <th width="300">Topic Name</th>
                                    <th width="150">Low Level Questions</th>
                                    <th width="150">Medium Level Questions</th>
                                    <th width="150">High Level Questions</th>
                                    <th width="80">Total</th>
                                    <th width="80">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="blueprintTable">
                                <?php foreach ($preview['data'] as $index => $topic): ?>
                                    <tr data-index="<?php echo $index; ?>">
                                        <td>
                                            <input type="number" name="topics[<?php echo $index; ?>][sr_no]"
                                                class="form-control form-control-sm"
                                                value="<?php echo htmlspecialchars($topic['sr_no'] ?? ''); ?>" required>
                                        </td>
                                        <td>
                                            <select name="topics[<?php echo $index; ?>][subject_category]"
                                                class="form-control form-control-sm" required>
                                                <option value="Maths" <?php echo $topic['subject_category'] == 'Maths' ? 'selected' : ''; ?>>Maths</option>
                                                <option value="Science" <?php echo $topic['subject_category'] == 'Science' ? 'selected' : ''; ?>>Science</option>
                                                <option value="Physics" <?php echo $topic['subject_category'] == 'Physics' ? 'selected' : ''; ?>>Physics</option>
                                                <option value="Chemistry" <?php echo $topic['subject_category'] == 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                                                <option value="Biology" <?php echo $topic['subject_category'] == 'Biology' ? 'selected' : ''; ?>>Biology</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="topics[<?php echo $index; ?>][topic_name_english]"
                                                class="form-control form-control-sm"
                                                value="<?php echo htmlspecialchars($topic['topic_name_english'] ?? ''); ?>"
                                                required>
                                        </td>
                                        <td>
                                            <input type="text" name="topics[<?php echo $index; ?>][low_questions]"
                                                class="form-control form-control-sm question-input"
                                                value="<?php echo implode(',', $topic['low_questions']); ?>"
                                                placeholder="e.g., 1,2,3">
                                            <small class="text-muted"><?php echo count($topic['low_questions']); ?>
                                                questions</small>
                                        </td>
                                        <td>
                                            <input type="text" name="topics[<?php echo $index; ?>][medium_questions]"
                                                class="form-control form-control-sm question-input"
                                                value="<?php echo implode(',', $topic['medium_questions']); ?>"
                                                placeholder="e.g., 4,5">
                                            <small class="text-muted"><?php echo count($topic['medium_questions']); ?>
                                                questions</small>
                                        </td>
                                        <td>
                                            <input type="text" name="topics[<?php echo $index; ?>][high_questions]"
                                                class="form-control form-control-sm question-input"
                                                value="<?php echo implode(',', $topic['high_questions']); ?>"
                                                placeholder="e.g., 6,7">
                                            <small class="text-muted"><?php echo count($topic['high_questions']); ?>
                                                questions</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary total-questions">
                                                <?php echo $topic['total_questions']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger remove-row" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total Questions:</strong></td>
                                    <td class="text-center"><strong id="totalLow">0</strong></td>
                                    <td class="text-center"><strong id="totalMedium">0</strong></td>
                                    <td class="text-center"><strong id="totalHigh">0</strong></td>
                                    <td class="text-center"><strong id="grandTotal">0</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-success" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add Topic
                    </button>
                    <button type="submit" class="btn btn-primary float-end" id="saveBtn">
                        <i class="fas fa-save"></i> Save Blueprint to Database
                    </button>
                    <a href="blueprint-upload.php" class="btn btn-secondary float-end me-2">
                        <i class="fas fa-arrow-left"></i> Back to Upload
                    </a>
                </div>
            </div>
        </form>
        </div>

<script>
    $(function () {
        // Calculate totals on page load
        calculateTotals();

        // Remove row
        $(document).on('click', '.remove-row', function () {
            const row = $(this).closest('tr');
            showConfirm({
                title: 'Remove Topic?',
                message: 'Are you sure you want to remove this topic?',
                confirmText: 'Yes, Remove',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    row.remove();
                    calculateTotals();
                }
            });
        });

        // Add new row
        $('#addRowBtn').click(function () {
            const index = $('#blueprintTable tr').length;
            const newRow = `
            <tr data-index="${index}">
                <td><input type="number" name="topics[${index}][sr_no]" class="form-control form-control-sm" required></td>
                <td>
                    <select name="topics[${index}][subject_category]" class="form-control form-control-sm" required>
                        <option value="Maths">Maths</option>
                        <option value="Science">Science</option>
                        <option value="Physics">Physics</option>
                        <option value="Chemistry">Chemistry</option>
                        <option value="Biology">Biology</option>
                    </select>
                </td>
                <td><input type="text" name="topics[${index}][topic_name_english]" class="form-control form-control-sm" required></td>
                <td><input type="text" name="topics[${index}][low_questions]" class="form-control form-control-sm question-input" placeholder="e.g., 1,2,3"></td>
                <td><input type="text" name="topics[${index}][medium_questions]" class="form-control form-control-sm question-input" placeholder="e.g., 4,5"></td>
                <td><input type="text" name="topics[${index}][high_questions]" class="form-control form-control-sm question-input" placeholder="e.g., 6,7"></td>
                <td class="text-center"><span class="badge bg-primary total-questions">0</span></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
            $('#blueprintTable').append(newRow);
        });

        // Recalculate totals when question inputs change
        $(document).on('input', '.question-input', function () {
            const row = $(this).closest('tr');
            updateRowTotal(row);
            calculateTotals();
        });

        function updateRowTotal(row) {
            const low = countQuestions(row.find('input[name*="[low_questions]"]').val());
            const medium = countQuestions(row.find('input[name*="[medium_questions]"]').val());
            const high = countQuestions(row.find('input[name*="[high_questions]"]').val());
            const total = low + medium + high;
            row.find('.total-questions').text(total);
        }

        function countQuestions(value) {
            if (!value || value.trim() === '') return 0;
            return value.split(',').filter(q => q.trim() !== '').length;
        }

        function calculateTotals() {
            let totalLow = 0,
                totalMedium = 0,
                totalHigh = 0;

            $('#blueprintTable tr').each(function () {
                totalLow += countQuestions($(this).find('input[name*="[low_questions]"]').val());
                totalMedium += countQuestions($(this).find('input[name*="[medium_questions]"]').val());
                totalHigh += countQuestions($(this).find('input[name*="[high_questions]"]').val());
            });

            $('#totalLow').text(totalLow);
            $('#totalMedium').text(totalMedium);
            $('#totalHigh').text(totalHigh);
            $('#grandTotal').text(totalLow + totalMedium + totalHigh);

            // Validate total should be 100
            const grandTotal = totalLow + totalMedium + totalHigh;
            if (grandTotal !== 100 && grandTotal > 0) {
                $('#grandTotal').removeClass('text-success').addClass('text-danger');
                $('#saveBtn').prop('disabled', true).html('<i class="fas fa-exclamation-triangle"></i> Total must be 100 questions');
            } else if (grandTotal === 100) {
                $('#grandTotal').removeClass('text-danger').addClass('text-success');
                $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save"></i> Save Blueprint to Database');
            }
        }

        // Blueprint Form Submission Handler
        $('#blueprintForm').on('submit', function (e) {
            e.preventDefault();

            const grandTotal = parseInt($('#grandTotal').text());
            if (grandTotal !== 100) {
                showToast('warning', 'Warning', 'Total questions must be exactly 100');
                return false;
            }

            $.api.post('test-management/blueprint-save-final', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => {
                            window.location.href = 'paper-sets.php';
                        }, 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to save blueprint'));
        });
    });
</script>

<?php include '../../include/footer.php'; ?>