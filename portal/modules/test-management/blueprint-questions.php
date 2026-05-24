<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$paper_set_id = $_POST['paper_set_id'] ?? 0;

// Get paper set details
try {
    $op = new Operation();

    $paper_set = $op->selectOne('tbl_paper_sets', ['*'], ['id' => $paper_set_id]);

    if (!$paper_set) {
        set_flash_message('error', 'Paper set not found!');
        header('Location: paper-sets.php');
        exit;
    }

    // Get blueprint topics for this paper set
    $topics = $op->readWithJoin(
        'tbl_blueprint_topics bt',
        ['bt.*', 's.subject_name', 't.topic_name_english', 't.topic_name_gujarati'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_subjects s', 'on' => 'bt.subject_id = s.id'],
            ['type' => 'LEFT', 'table' => 'tbl_topics t', 'on' => 'bt.topic_id = t.id']
        ],
        ['bt.paper_set_id' => $paper_set_id],
        'bt.sr_no ASC'
    );

    // Get all blueprint questions with topic information
    $questions = $op->readWithJoin(
        'tbl_blueprint_questions bq',
        ['bq.*', 't.topic_name_english', 's.subject_name as subject_category'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_blueprint_topics bt', 'on' => 'bq.blueprint_topic_id = bt.id'],
            ['type' => 'LEFT', 'table' => 'tbl_topics t', 'on' => 'bt.topic_id = t.id'],
            ['type' => 'LEFT', 'table' => 'tbl_subjects s', 'on' => 'bt.subject_id = s.id']
        ],
        ['bq.paper_set_id' => $paper_set_id],
        'bq.question_number ASC'
    );

    // Count questions by difficulty
    $difficulty_counts = [
        'low' => 0,
        'medium' => 0,
        'high' => 0
    ];
    foreach ($questions as $q) {
        $level = $q['difficulty_level'] ?? 'low';
        if (isset($difficulty_counts[$level])) {
            $difficulty_counts[$level]++;
        }
    }

    // Calculate next question number
    $next_question_number = 1;
    if (!empty($questions)) {
        $last_question = end($questions);
        $next_question_number = $last_question['question_number'] + 1;
    }

    // Calculate total questions from blueprint topics
    $total_blueprint_questions = 0;
    foreach ($topics as $topic) {
        $total_blueprint_questions += $topic['total_questions'];
    }

    // Count questions per topic and find next topic that needs questions
    $topic_question_counts = [];
    $default_topic_id = null;

    foreach ($topics as $topic) {
        $topic_id = $topic['id'];
        $topic_question_counts[$topic_id] = 0;

        // Count existing questions for this topic
        foreach ($questions as $q) {
            if ($q['blueprint_topic_id'] == $topic_id) {
                $topic_question_counts[$topic_id]++;
            }
        }

        // Find first topic that hasn't reached its quota
        if ($default_topic_id === null && $topic_question_counts[$topic_id] < $topic['total_questions']) {
            $default_topic_id = $topic_id;
        }
    }
} catch (PDOException $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: paper-sets.php');
    exit;
}

$page_title = "Blueprint Questions - " . $paper_set['paper_set_name'] . "";
$page_breadcrumb = "Blueprint";
?>
<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
        <?php echo $_SESSION['success_msg'];
        ?>
    </div>
<?php endif; ?>

<!-- Paper Set Info -->
<div class="row">
    <div class="col-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt"></i>
                    <?php echo htmlspecialchars($paper_set['paper_set_name'] ?? ''); ?>
                    (<?php echo htmlspecialchars($paper_set['paper_code'] ?? ''); ?>)
                </h3>
                <div class="card-tools">
                    <a href="blueprint.php?paper_set_id=<?php echo $paper_set_id; ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-edit"></i> Edit Blueprint
                    </a>
                    <a href="paper-sets.php" class="btn btn-sm btn-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?php echo count($questions); ?></h3>
                <p>Total Questions</p>
            </div>
            <div class="icon">
                <i class="fas fa-question-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?php echo $difficulty_counts['low']; ?></h3>
                <p>Low Level</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?php echo $difficulty_counts['medium']; ?></h3>
                <p>Medium Level</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?php echo $difficulty_counts['high']; ?></h3>
                <p>High Level</p>
            </div>
            <div class="icon">
                <i class="fas fa-fire"></i>
            </div>
        </div>
    </div>
</div>

<!-- Questions Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Blueprint Questions</h3>
                <div class="card-tools">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal"
                        <?php if (count($questions) >= $total_blueprint_questions): ?>disabled
                            title="Blueprint is complete" <?php endif; ?>>
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="questionsTable" class="table table-bordered table-striped table-sm">
                        <thead>
                            <tr>
                                <th width="80">Q. No.</th>
                                <th>Subject</th>
                                <th>Topic</th>
                                <th>Difficulty</th>
                                <th>Marks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($questions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No questions found. Please upload blueprint first.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($questions as $q):
                                    $diff_badge = $q['difficulty_level'] == 'low' ? 'success' : ($q['difficulty_level'] == 'medium' ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td class="text-center"><strong><?php echo $q['question_number']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($q['subject_category'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($q['topic_name_english'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $diff_badge; ?>">
                                                <?php echo ucfirst($q['difficulty_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatIndianCurrency($q['marks']); ?></td>
                                        <td>
                                            <button class="btn btn-xs btn-info"
                                                onclick="editQuestion(<?php echo $q['id']; ?>, <?php echo $q['question_number']; ?>, '<?php echo $q['difficulty_level']; ?>', <?php echo $q['marks']; ?>, <?php echo $q['blueprint_topic_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-xs btn-danger"
                                                onclick="deleteQuestion(<?php echo $q['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Topic-wise Summary -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info">
                <h3 class="card-title">Topic-wise Question Distribution</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Subject</th>
                            <th>Topic</th>
                            <th>Low</th>
                            <th>Medium</th>
                            <th>High</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($topics as $topic):
                            // Count questions for this topic
                            $topic_counts = ['low' => 0, 'medium' => 0, 'high' => 0, 'total' => 0];
                            foreach ($questions as $q) {
                                if ($q['blueprint_topic_id'] == $topic['id']) {
                                    $level = $q['difficulty_level'] ?? 'low';
                                    if (isset($topic_counts[$level])) {
                                        $topic_counts[$level]++;
                                    }
                                    $topic_counts['total']++;
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo $topic['sr_no']; ?></td>
                                <td><?php echo htmlspecialchars($topic['subject_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($topic['topic_name_english'] ?? '-'); ?></td>
                                <td class="text-center"><?php echo $topic_counts['low']; ?></td>
                                <td class="text-center"><?php echo $topic_counts['medium']; ?></td>
                                <td class="text-center"><?php echo $topic_counts['high']; ?></td>
                                <td class="text-center"><strong><?php echo $topic_counts['total']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Question to Blueprint</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addQuestionForm">
                <input type="hidden" name="paper_set_id" value="<?php echo $paper_set_id; ?>">
                <div class="modal-body">
                    <?php if (count($questions) >= $total_blueprint_questions): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Blueprint Complete:</strong> You have reached the maximum number of questions
                            (<?php echo $total_blueprint_questions; ?>) defined in the blueprint.
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Question Number *</label>
                        <input type="number" name="question_number" id="question_number" class="form-control"
                            value="<?php echo $next_question_number; ?>" min="1"
                            max="<?php echo $total_blueprint_questions; ?>" required readonly>
                        <small class="form-text text-muted">
                            Auto-generated. Current: <?php echo count($questions); ?> /
                            <?php echo $total_blueprint_questions; ?> questions
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Select Topic *</label>
                        <select name="blueprint_topic_id" class="form-control" required>
                            <option value="">Choose Topic</option>
                            <?php foreach ($topics as $topic):
                                $topic_id = $topic['id'];
                                $current_count = $topic_question_counts[$topic_id] ?? 0;
                                $topic_total = $topic['total_questions'];
                                $is_complete = $current_count >= $topic_total;
                                $is_selected = ($topic_id == $default_topic_id);
                                ?>
                                <option value="<?php echo $topic['id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>
                                    <?php echo $is_complete ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars(($topic['subject_name'] ?? 'N/A') . ' - ' . ($topic['topic_name_english'] ?? 'No Topic')); ?>
                                    (<?php echo $current_count; ?> / <?php echo $topic_total; ?>)
                                    <?php echo $is_complete ? ' - COMPLETE' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Auto-selected topic needing questions</small>
                    </div>
                    <div class="form-group">
                        <label>Difficulty Level</label>
                        <select name="difficulty_level" class="form-control">
                            <option value="">-- Not Specified --</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marks</label>
                        <input type="number" name="marks" class="form-control" value="1.00" step="0.01" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Question</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editQuestionForm">
                <input type="hidden" name="question_id" id="edit_question_id">
                <input type="hidden" name="paper_set_id" value="<?php echo $paper_set_id; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Question Number *</label>
                        <input type="number" name="question_number" id="edit_question_number" class="form-control"
                            min="1" max="100" required>
                    </div>
                    <div class="form-group">
                        <label>Select Topic *</label>
                        <select name="blueprint_topic_id" id="edit_topic_id" class="form-control" required>
                            <option value="">Choose Topic</option>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?php echo $topic['id']; ?>">
                                    <?php echo htmlspecialchars(($topic['subject_name'] ?? 'N/A') . ' - ' . ($topic['topic_name_english'] ?? 'No Topic')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Difficulty Level</label>
                        <select name="difficulty_level" id="edit_difficulty" class="form-control">
                            <option value="">-- Not Specified --</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marks</label>
                        <input type="number" name="marks" id="edit_marks" class="form-control" value="1.00" step="0.01"
                            min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Move modals to body to prevent z-index issues
        $('#addQuestionModal').appendTo("body");
        $('#editQuestionModal').appendTo("body");

        // Add Question Form Handler
        $('#addQuestionForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('test-management/blueprint-question-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        $('#addQuestionModal').modal('hide');
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to save question'));
        });

        // Edit Question Form Handler
        $('#editQuestionForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('test-management/blueprint-question-update', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        $('#editQuestionModal').modal('hide');
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to update question'));
        });
    });

    function editQuestion(id, qNumber, difficulty, marks, topicId) {
        $('#edit_question_id').val(id);
        $('#edit_question_number').val(qNumber);
        $('#edit_difficulty').val(difficulty);
        $('#edit_marks').val(marks);
        $('#edit_topic_id').val(topicId);
        $('#editQuestionModal').modal('show');
    }

    function deleteQuestion(id) {
        showConfirm({
            title: 'Delete Question?',
            message: 'Are you sure you want to delete this question?',
            confirmText: 'Yes, Delete',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                window.location.href = 'blueprint-question-delete.php?id=' + id + '&paper_set_id=<?php echo $paper_set_id; ?>';
            }
        });
    }
</script>

</body>