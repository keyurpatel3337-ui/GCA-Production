<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$answer_key_id = $_POST['id'] ?? 0;

// Get answer key details
try {
    $op = new Operation();

    $answer_key = $op->readWithJoin(
        'tbl_answer_keys ak',
        ['ak.*', 'ps.paper_set_name', 'ps.paper_code'],
        [
            ['type' => 'INNER', 'table' => 'tbl_paper_sets ps', 'on' => 'ak.paper_set_id = ps.id']
        ],
        ['ak.id' => $answer_key_id]
    );

    if (!$answer_key) {
        set_flash_message('error', 'Answer key not found!');
        header('Location: answer-keys.php');
        exit;
    }

    // Parse answers JSON
    $answers = json_decode($answer_key['answers_json'], true);
    if (!is_array($answers)) {
        $answers = [];
    }

    // Get all paper sets for dropdown
    $paper_sets = $op->readAll('tbl_paper_sets', ['status' => 'active'], 'paper_set_name ASC');
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Answer Key for Edit");
    set_flash_message('error', 'Error fetching answer key details');
    header('Location: answer-keys.php');
    exit;
}

$page_title = "Edit Answer Key" ;
$page_breadcrumb = "Key -";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <form action="answer-key-update.php" method="POST" id="editAnswerKeyForm">
                    <input type="hidden" name="answer_key_id" value="<?php echo $answer_key_id; ?>">

                    <!-- Answer Key Information -->
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h3 class="card-title"><i class="fas fa-edit"></i> Edit Answer Key Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Test Name <span class="text-danger">*</span></label>
                                        <input type="text" name="test_name" class="form-control"
                                            value="<?php echo htmlspecialchars($answer_key['test_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Paper Set <span class="text-danger">*</span></label>
                                        <select name="paper_set_id" class="form-control" required>
                                            <?php foreach ($paper_sets as $ps): ?>
                                                <option value="<?php echo $ps['id']; ?>" <?php echo ($ps['id'] == $answer_key['paper_set_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ps['paper_set_name'] ?? '') . ' (' . $ps['paper_code'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Test Date</label>
                                        <input type="date" name="test_date" class="form-control"
                                            value="<?php echo $answer_key['test_date']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status <span class="text-danger">*</span></label>
                                        <select name="status" class="form-control" required>
                                            <option value="active" <?php echo ($answer_key['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($answer_key['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Answer Key Details -->
                    <div class="card">
                        <div class="card-header bg-success">
                            <h3 class="card-title"><i class="fas fa-list-ol"></i> Edit Answers
                                (<?php echo count($answers); ?> Questions)</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-light" onclick="fillSampleAnswers()">
                                    <i class="fas fa-magic"></i> Fill Sample (A,B,C,D)
                                </button>
                                <button type="button" class="btn btn-sm btn-light" onclick="clearAllAnswers()">
                                    <i class="fas fa-eraser"></i> Clear All
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Instructions:</strong> Enter the correct answer (A, B, C, or D) for each
                                question below.
                            </div>

                            <div class="row">
                                <?php
                                $total_questions = $answer_key['total_questions'];
                                $chunks = array_chunk($answers, 25); // 25 questions per column
                                
                                // If answers array is smaller than total questions, fill remaining
                                $all_questions = [];
                                for ($i = 1; $i <= $total_questions; $i++) {
                                    $existing = array_filter($answers, function ($ans) use ($i) {
                                        return $ans['q'] == $i;
                                    });
                                    if (!empty($existing)) {
                                        $all_questions[] = reset($existing);
                                    } else {
                                        $all_questions[] = ['q' => $i, 'ans' => ''];
                                    }
                                }

                                $chunks = array_chunk($all_questions, 25);
                                foreach ($chunks as $chunk):
                                    ?>
                                    <div class="col-md-3">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th width="60">Q. No.</th>
                                                    <th>Answer</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($chunk as $answer): ?>
                                                    <tr>
                                                        <td class="text-center"><strong><?php echo $answer['q']; ?></strong>
                                                        </td>
                                                        <td>
                                                            <input type="hidden" name="questions[]"
                                                                value="<?php echo $answer['q']; ?>">
                                                            <select name="answers[]"
                                                                class="form-control form-control-sm answer-input" required>
                                                                <option value="">-</option>
                                                                <option value="A" <?php echo (strtoupper($answer['ans']) == 'A') ? 'selected' : ''; ?>>A</option>
                                                                <option value="B" <?php echo (strtoupper($answer['ans']) == 'B') ? 'selected' : ''; ?>>B</option>
                                                                <option value="C" <?php echo (strtoupper($answer['ans']) == 'C') ? 'selected' : ''; ?>>C</option>
                                                                <option value="D" <?php echo (strtoupper($answer['ans']) == 'D') ? 'selected' : ''; ?>>D</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="answer-keys.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <a href="answer-key-view.php?id=<?php echo $answer_key_id; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> View Answer Key
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    function fillSampleAnswers() {
        const pattern = ['A', 'B', 'C', 'D'];
        const inputs = document.querySelectorAll('.answer-input');
        inputs.forEach((input, index) => {
            input.value = pattern[index % 4];
        });
        showToast('success', 'Done', 'Sample answers filled with A, B, C, D pattern!');
    }

    function clearAllAnswers() {
        showConfirm({
            title: 'Clear All Answers',
            message: 'Are you sure you want to clear all answers?',
            confirmText: 'Yes, Clear All',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                const inputs = document.querySelectorAll('.answer-input');
                inputs.forEach(input => { input.value = ''; });
                showToast('info', 'Cleared', 'All answers cleared!');
            }
        });
    }

    // Form validation
    document.getElementById('editAnswerKeyForm').addEventListener('submit', function (e) {
        const inputs = document.querySelectorAll('.answer-input');
        let emptyCount = 0;

        inputs.forEach(input => {
            if (input.value === '') {
                emptyCount++;
            }
        });

        if (emptyCount > 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Incomplete Answers',
                text: `${emptyCount} question(s) have no answer. Do you want to continue?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Continue',
                confirmButtonColor: '#f39c12',
                cancelButtonText: 'Go Back'
            }).then(result => {
                if (result.isConfirmed) {
                    document.getElementById('editAnswerKeyForm').submit();
                }
            });
        }
    });
</script>
