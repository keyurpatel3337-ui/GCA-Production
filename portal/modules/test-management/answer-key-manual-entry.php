<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$paper_set_id = $_POST['paper_set_id'] ?? 0;
$answer_key_id = $_POST['id'] ?? 0;

// Get paper set details
try {
    $op = new Operation();

    $paper_set = $op->selectOne('tbl_paper_sets', ['*'], ['id' => $paper_set_id]);

    if (!$paper_set) {
        set_flash_message('error', 'Paper set not found!');
        header('Location: answer-keys.php');
        exit;
    }

    // If editing, get existing answer key
    $answer_key = null;
    $existing_answers = [];
    if ($answer_key_id > 0) {
        $answer_key = $op->selectOne('tbl_answer_keys', ['*'], ['id' => $answer_key_id]);

        if ($answer_key && $answer_key['answers_json']) {
            $existing_answers = json_decode($answer_key['answers_json'], true);
            // Convert to associative array with question number as key
            $temp = [];
            foreach ($existing_answers as $ans) {
                $temp[$ans['q']] = $ans['ans'];
            }
            $existing_answers = $temp;
        }
    }

    // Get blueprint questions for this paper set
    $questions = $op->readWithJoin(
        'tbl_blueprint_questions bq',
        ['bq.question_number', 't.topic_name_english', 's.subject_name as subject_category', 'bq.difficulty_level'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_blueprint_topics bt', 'on' => 'bq.blueprint_topic_id = bt.id'],
            ['type' => 'LEFT', 'table' => 'tbl_topics t', 'on' => 'bt.topic_id = t.id'],
            ['type' => 'LEFT', 'table' => 'tbl_subjects s', 'on' => 'bt.subject_id = s.id']
        ],
        ['bq.paper_set_id' => $paper_set_id],
        'bq.question_number ASC'
    );
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Answer Key Manual Entry");
    set_flash_message('error', 'Database error occurred!');
    header('Location: answer-keys.php');
    exit;
}

$page_title = ($answer_key_id > 0 ? "Edit" : "Add") . " Answer Key - " ;
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <form action="answer-key-manual-save.php" method="POST" id="answerKeyForm">
            <input type="hidden" name="paper_set_id" value="<?php echo $paper_set_id; ?>">
            <input type="hidden" name="answer_key_id" value="<?php echo $answer_key_id; ?>">

            <!-- Basic Information Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Basic Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Paper Set</label>
                                <input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($paper_set['paper_set_name'] . ' (' . $paper_set['paper_code'] . ')' ?? ''); ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total Questions</label>
                                <input type="text" class="form-control"
                                    value="<?php echo $paper_set['total_questions']; ?>" readonly>
                            </div>
                        </div>
                    </div>
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
                                <label>Test Date</label>
                                <input type="date" name="test_date" class="form-control"
                                    value="<?php echo $answer_key['test_date'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Answer Entry Card -->
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list-ol"></i> Enter Answers
                        (<?php echo count($questions); ?> Questions)</h3>
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
                    <?php if (empty($questions)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No questions found in the blueprint. Please add questions to the blueprint first.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instructions:</strong> Enter the correct answer (A, B, C, or D) for each question below.
                        </div>

                        <div class="row">
                            <?php
                            // Build array of all questions with existing answers
                            $all_questions = [];
                            foreach ($questions as $q) {
                                $all_questions[] = [
                                    'q' => $q['question_number'],
                                    'ans' => $existing_answers[$q['question_number']] ?? ''
                                ];
                            }

                            $chunks = array_chunk($all_questions, 25); // 25 questions per column
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
                                                    <td class="text-center"><strong><?php echo $answer['q']; ?></strong></td>
                                                    <td>
                                                        <input type="hidden" name="questions[]" value="<?php echo $answer['q']; ?>">
                                                        <select name="answers[]" class="form-control form-control-sm answer-input"
                                                            required>
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
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="answer-keys.php?paper_set_id=<?php echo $paper_set_id; ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="submit" class="btn btn-success" <?php echo empty($questions) ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i> Save Answer Key
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    function fillSampleAnswers() {
        const pattern = ['A', 'B', 'C', 'D'];
        const inputs = document.querySelectorAll('.answer-input');
        inputs.forEach((input, index) => {
            input.value = pattern[index % 4];
        });
        alert('Sample answers filled with A, B, C, D pattern!');
    }

    function clearAllAnswers() {
        if (confirm('Are you sure you want to clear all answers?')) {
            const inputs = document.querySelectorAll('.answer-input');
            inputs.forEach(input => {
                input.value = '';
            });
            alert('All answers cleared!');
        }
    }

    // Form validation
    document.getElementById('answerKeyForm').addEventListener('submit', function (e) {
        const inputs = document.querySelectorAll('.answer-input');
        let emptyCount = 0;

        inputs.forEach(input => {
            if (input.value === '') {
                emptyCount++;
            }
        });

        if (emptyCount > 0) {
            if (!confirm(`${emptyCount} question(s) have no answer. Do you want to continue?`)) {
                e.preventDefault();
                return false;
            }
        }
    });
</script>
