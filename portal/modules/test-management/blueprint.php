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

// Get paper set details
try {
    $op = new Operation();

    $paper_set = $op->selectOne('tbl_paper_sets', ['*'], ['id' => $paper_set_id]);

    if (!$paper_set) {
        set_flash_message('error', 'Paper set not found!');
        header('Location: paper-sets.php');
        exit;
    }

    // Get blueprint topics
    $topics = $op->readWithJoin(
        'tbl_blueprint_topics bt',
        ['bt.*', 's.subject_name', 't.topic_name_english as master_topic_name', 't.topic_name_gujarati as master_topic_gujarati'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_subjects s', 'on' => 'bt.subject_id = s.id'],
            ['type' => 'LEFT', 'table' => 'tbl_topics t', 'on' => 'bt.topic_id = t.id']
        ],
        ['bt.paper_set_id' => $paper_set_id],
        'bt.sr_no ASC'
    );

    // Get blueprint questions
    $questions = $op->readAll('tbl_blueprint_questions', ['paper_set_id' => $paper_set_id], 'question_number ASC');

    // Fetch active subjects for dropdown
    $subjects = $op->readAll('tbl_subjects', ['status' => 'active'], 'subject_name ASC', ['id', 'subject_name']);
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Blueprint Questions for Admin");
}

$page_title = "Blueprint - " . $paper_set['paper_set_name'] . "";
$page_breadcrumb = "Blueprint";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Paper Set Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Paper Code:</strong>
                            <span
                                class="badge bg-primary"><?php echo htmlspecialchars($paper_set['paper_code'] ?? ''); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Total Questions:</strong> <?php echo $paper_set['total_questions']; ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Low Level:</strong>
                            <span class="badge bg-success"><?php echo $paper_set['low_level_count']; ?></span>
                        </div>
                        <div class="col-md-2">
                            <strong>Medium Level:</strong>
                            <span
                                class="badge bg-warning text-dark"><?php echo $paper_set['medium_level_count']; ?></span>
                        </div>
                        <div class="col-md-2">
                            <strong>High Level:</strong>
                            <span class="badge bg-danger"><?php echo $paper_set['high_level_count']; ?></span>
                        </div>
                    </div>
                </div>
            </div>



            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Blueprint Management</h3>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-success-custom px-4 py-2" data-bs-toggle="modal"
                            data-bs-target="#addTopicModal">
                            <i class="fas fa-plus me-2"></i> Add Topic
                        </button>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="blueprintSearch" class="form-control border-start-0 ps-0"
                                    placeholder="Search topics...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($topics)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No topics added yet. Click "Add Topic" to create the
                            blueprint.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="blueprintTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Sr.No</th>
                                        <th>Subject</th>
                                        <th>Topic (English)</th>
                                        <th>Topic (Gujarati)</th>
                                        <th>Total Q</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topics as $topic): ?>
                                        <tr>
                                            <td><?php echo $topic['sr_no']; ?></td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo htmlspecialchars($topic['subject_name'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($topic['master_topic_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($topic['master_topic_gujarati'] ?? '-'); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $topic['total_questions']; ?></span>
                                            </td>
                                            <td>
                                                <a href="blueprint-questions.php?paper_set_id=<?php echo $paper_set_id; ?>&topic_id=<?php echo $topic['id']; ?>"
                                                    class="btn btn-xs btn-info">
                                                    <i class="fas fa-list"></i> Questions
                                                </a>
                                                <a href="blueprint-topic-delete.php?id=<?php echo $topic['id']; ?>&paper_set_id=<?php echo $paper_set_id; ?>"
                                                    class="btn btn-xs btn-danger btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($questions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Question Distribution (Total: <?php echo count($questions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <?php
                                $low = array_filter($questions, fn($q) => $q['difficulty_level'] == 'low');
                                $medium = array_filter($questions, fn($q) => $q['difficulty_level'] == 'medium');
                                $high = array_filter($questions, fn($q) => $q['difficulty_level'] == 'high');
                                ?>
                                <p>
                                    <span class="badge bg-success">Low: <?php echo count($low); ?></span>
                                    <span class="badge bg-warning text-dark">Medium:
                                        <?php echo count($medium); ?></span>
                                    <span class="badge bg-danger">High: <?php echo count($high); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>

<!-- Add Topic Modal -->
<div class="modal fade" id="addTopicModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success-custom">
                <h5 class="modal-title font-weight-bold text-white">Add Blueprint Topic</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="addBlueprintTopicForm">
                <input type="hidden" name="paper_set_id" value="<?php echo $paper_set_id; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Sr. No <span class="text-danger">*</span></label>
                                <input type="number" name="sr_no" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Subject <span class="text-danger">*</span></label>
                                <select name="subject_id" id="subject_id" class="form-control" required
                                    onchange="loadTopics(this.value)">
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Topic</label>
                        <select name="topic_id" id="topic_id" class="form-control">
                            <option value="">Select Subject First</option>
                        </select>
                        <small class="form-text text-muted">Select a subject to load available topics (optional)</small>
                    </div>

                    <div class="form-group">
                        <label>Total Questions <span class="text-danger">*</span></label>
                        <input type="number" name="total_questions" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success-custom px-4">Save Topic</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        $(document).ready(function () {
            // Move modal to body to prevent z-index issues
            $('#addTopicModal').appendTo("body");

            // Add Blueprint Topic Form Handler
            $('#addBlueprintTopicForm').on('submit', function (e) {
                e.preventDefault();
                $.api.post('test-management/blueprint-topic-save', $(this).serialize())
                    .then(response => {
                        if (response.success) {
                            $('#addTopicModal').modal('hide');
                            showToast('success', 'Success!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.error || response.message);
                        }
                    }).catch(error => showToast('error', 'Error!', error.message || 'Failed to save topic'));
            });

            // Live Search Filtering
            $('#blueprintSearch').on('keyup', function () {
                const value = $(this).val().toLowerCase();
                $("#blueprintTable tbody tr").filter(function () {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(value) > -1);
                });
            });
        });

        function loadTopics(subjectId) {
            const topicSelect = document.getElementById('topic_id');

            if (!subjectId) {
                topicSelect.innerHTML = '<option value="">Select Subject First</option>';
                return;
            }

            topicSelect.innerHTML = '<option value="">Loading...</option>';

            fetch('get-topics-by-subject.php?subject_id=' + subjectId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let options = '<option value="">Select Topic</option>';
                        data.topics.forEach(topic => {
                            options += `<option value="${topic.id}">${topic.topic_name_english}${topic.topic_name_gujarati ? ' (' + topic.topic_name_gujarati + ')' : ''}</option>`;
                        });
                        topicSelect.innerHTML = options;
                    } else {
                        topicSelect.innerHTML = '<option value="">No topics available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error loading topics:', error);
                    topicSelect.innerHTML = '<option value="">Error loading topics</option>';
                });
        }
    </script>