<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$exam_id) {
    die("Invalid Exam ID.");
}

// Fetch exam details
$stmt = $conn->prepare("SELECT * FROM tbl_oes_exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Exam not found.");
}

// Fetch selected questions
$stmt_q = $conn->prepare("SELECT q.id, q.question_text, q.marks 
                          FROM tbl_oes_exam_questions eq 
                          JOIN tbl_oes_questions q ON eq.question_id = q.id 
                          WHERE eq.exam_id = ? 
                          ORDER BY eq.order_no ASC");
$stmt_q->execute([$exam_id]);
$selected_questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

// Fetch standards for filter
$standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdtext ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Edit Exam | OES";

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<style>
    .question-pool-item, .selected-question-item {
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .question-pool-item:hover { border-color: #4e73df !important; background: #f0f4ff; }
    .selected-question-item:hover { border-color: #dc3545 !important; background: #fff5f5; }
    .selected-question-item { border-left: 4px solid #28a745 !important; }

    .split-panel { display: flex; gap: 20px; }
    .panel-left, .panel-right { flex: 1; min-width: 0; }

    .q-pool-box, .q-selected-box {
        height: 520px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 10px;
        background: #f8f9fa;
    }
    .q-selected-box { background: #f0fff4; border-color: #c3e6cb; }

    .panel-header {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 8px 12px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .panel-header.available { background: #e8f0fe; color: #3c4fb5; }
    .panel-header.selected-h { background: #d4edda; color: #155724; }

    .q-item-card {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 8px;
        font-size: 0.85rem;
    }
    .q-item-card .q-meta { font-size: 0.75rem; color: #888; margin-top: 4px; }
    .q-item-card .add-btn, .q-item-card .remove-btn {
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 20px;
        float: right;
        margin-left: 8px;
    }
    .empty-state { text-align: center; color: #aaa; padding: 40px 20px; font-size: 0.9rem; }
    .filter-bar { display: flex; gap: 8px; margin-bottom: 12px; }
</style>

<main class="app-main">
    <div class="app-content pt-3">
        <div class="container-fluid">
            <form id="exam-form" method="POST" action="update-exam.php">
                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">

                <!-- ── Row 1: Basic Info ── -->
                <div class="card shadow-sm mb-4 border-left-primary">
                    <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-2"></i>Basic Information</h6>
                        <a href="manage-exams.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 10px;">
                            <i class="fas fa-arrow-left mr-2"></i> Back to List
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted">Exam Title</label>
                                    <input type="text" name="title" value="<?= htmlspecialchars($exam['title']) ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted">Standard</label>
                                    <select name="standard_id" id="main-standard" class="form-control" required onchange="onStandardChange(this.value)">
                                        <option value="11" <?= ($exam['standard_id'] == 11) ? 'selected' : '' ?>>11th</option>
                                        <option value="12" <?= ($exam['standard_id'] == 12) ? 'selected' : '' ?>>12th</option>
                                        <option value="13" <?= ($exam['standard_id'] == 13) ? 'selected' : '' ?>>Reneet</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted">Target Group</label>
                                    <select name="group_id" id="group-id" class="form-control">
                                        <option value="">All Groups</option>
                                        <?php
                                        $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC");
                                        while ($g = $groups->fetch()) {
                                            $selected = ($g['id'] == $exam['group_id']) ? 'selected' : '';
                                            echo "<option value='{$g['id']}' $selected>{$g['group_name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted">Subject Filter (for pooling)</label>
                                    <select id="q-subject-filter" class="form-control">
                                        <option value="">Select Subject</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-muted">Description / Instructions</label>
                            <textarea name="description" rows="2" class="form-control"><?= htmlspecialchars($exam['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ── Row 2: Question Selection (split pane) ── -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks mr-2"></i>Manage Questions</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" id="load-btn" class="btn btn-dark btn-sm" onclick="loadQuestions()">
                                <i class="fas fa-search mr-1"></i> Search Pool
                            </button>
                            <span class="badge bg-success text-white px-3 py-2 ml-2" id="selected-count"><?= count($selected_questions) ?> Selected</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="split-panel">

                            <!-- Left: Available Questions -->
                            <div class="panel-left">
                                <div class="panel-header available">
                                    <i class="fas fa-database mr-1"></i> Question Pool
                                    <span id="pool-count" class="float-right">0</span>
                                </div>
                                <div id="questions-pool" class="q-pool-box">
                                    <div class="empty-state">
                                        <i class="fas fa-search fa-2x mb-2 d-block"></i>
                                        Select a Subject, then click <strong>Search Pool</strong> to find more questions.
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Selected Questions -->
                            <div class="panel-right">
                                <div class="panel-header selected-h">
                                    <i class="fas fa-check-double mr-1"></i> Currently Included
                                    <span id="selected-panel-count" class="float-right"><?= count($selected_questions) ?></span>
                                </div>
                                <div id="selected-pool" class="q-selected-box">
                                    <?php foreach ($selected_questions as $q): ?>
                                        <div class="q-item-card selected-question-item" id="selected-item-<?= $q['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeQuestion(<?= $q['id'] ?>)"><i class="fas fa-times mr-1"></i>Remove</button>
                                            <div class="q-text"><?= strip_tags($q['question_text']) ?></div>
                                            <div class="q-meta"><i class="fas fa-star mr-1 text-warning"></i><?= $q['marks'] ?> Marks</div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($selected_questions)): ?>
                                        <div class="empty-state" id="selected-empty">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No questions selected.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ── Row 3: Scheduling ── -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 bg-warning text-dark">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-clock mr-2"></i>Scheduling & Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Start Date & Time</label>
                                    <input type="datetime-local" name="start_time" value="<?= date('Y-m-d\TH:i', strtotime($exam['start_time'])) ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">End Date & Time</label>
                                    <input type="datetime-local" name="end_time" value="<?= date('Y-m-d\TH:i', strtotime($exam['end_time'])) ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Duration (Mins)</label>
                                    <input type="number" name="duration_mins" value="<?= $exam['duration_mins'] ?>" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Exam Type</label>
                                    <select name="exam_mode" class="form-control" required>
                                        <option value="Practice" <?= ($exam['exam_mode'] === 'Practice') ? 'selected' : '' ?>>Practice Test</option>
                                        <option value="Final" <?= ($exam['exam_mode'] === 'Final') ? 'selected' : '' ?>>Final Exam</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="w-100 pl-3">
                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="shuffle_questions" name="shuffle_questions" value="1" <?= $exam['shuffle_questions'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label small font-weight-bold" for="shuffle_questions">Shuffle Questions</label>
                                    </div>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="display_result_immediately" name="display_result_immediately" value="1" <?= $exam['display_result_immediately'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label small font-weight-bold" for="display_result_immediately">Show Results Instantly</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs for selected question IDs -->
                <div id="hidden-question-inputs">
                    <?php foreach ($selected_questions as $q): ?>
                        <input type="hidden" name="question_ids[]" value="<?= $q['id'] ?>">
                    <?php endforeach; ?>
                </div>

                <div class="text-right mb-5">
                    <a href="manage-exams.php" class="btn btn-light px-4 mr-2" style="border-radius: 10px;">Discard Changes</a>
                    <button type="submit" class="btn btn-warning btn-lg px-5 font-weight-bold" style="border-radius: 10px;">
                        <i class="fas fa-save mr-2"></i> Save Updates
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
    // State: map of question_id -> question object
    const selectedQuestions = {};
    // Pre-populate with existing questions
    <?php foreach ($selected_questions as $q): ?>
    selectedQuestions[<?= $q['id'] ?>] = {
        id: <?= $q['id'] ?>,
        question_text: `<?= addslashes($q['question_text']) ?>`,
        marks: <?= $q['marks'] ?>
    };
    <?php endforeach; ?>

    // Initialize subjects for current standard
    document.addEventListener('DOMContentLoaded', () => {
        onStandardChange(document.getElementById('main-standard').value);
    });

    async function onStandardChange(standardId) {
        const subSelect = document.getElementById('q-subject-filter');
        if (!standardId) {
            subSelect.innerHTML = '<option value="">Select Subject</option>';
            return;
        }

        try {
            const res = await fetch(`ajax/get-subjects.php?standard_id=${standardId}`);
            const subjects = await res.json();
            let html = '<option value="">Select Subject</option>';
            subjects.forEach(s => html += `<option value="${s.id}">${s.subject_name}</option>`);
            subSelect.innerHTML = html;
        } catch (e) {
            subSelect.innerHTML = '<option value="">Error loading subjects</option>';
        }
    }

    async function loadQuestions() {
        const subjectId = document.getElementById('q-subject-filter').value;
        const pool = document.getElementById('questions-pool');

        if (!subjectId) {
            alert('Please select a subject to browse the pool.');
            return;
        }

        pool.innerHTML = '<div class="text-center py-5"><i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i></div>';

        try {
            const res = await fetch(`ajax/fetch-questions.php?subject_id=${subjectId}`);
            const questions = await res.json();

            // Filter out already-selected questions
            const available = questions.filter(q => !selectedQuestions[q.id]);

            if (available.length === 0) {
                pool.innerHTML = '<div class="empty-state"><i class="fas fa-check-double fa-2x mb-2 d-block text-success"></i>All pool questions are already in this exam.</div>';
                document.getElementById('pool-count').textContent = '0';
                return;
            }

            pool.innerHTML = available.map(q => buildPoolItem(q)).join('');
            document.getElementById('pool-count').textContent = available.length;
        } catch (e) {
            pool.innerHTML = '<div class="empty-state text-danger">Error fetching pool.</div>';
        }
    }

    function buildPoolItem(q) {
        const cleanText = stripHtml(q.question_text);
        const shortText = cleanText.substring(0, 100) + (cleanText.length > 100 ? '...' : '');
        return `<div class="q-item-card question-pool-item" id="pool-item-${q.id}">
            <button type="button" class="btn btn-sm btn-primary add-btn" onclick="addQuestion(${q.id}, '${escapeJson(q.question_text)}', ${q.marks})"><i class="fas fa-plus mr-1"></i>Add</button>
            <div class="q-text">${shortText}</div>
            <div class="q-meta"><i class="fas fa-star mr-1 text-warning"></i>${q.marks} Marks</div>
        </div>`;
    }

    function buildSelectedItem(q) {
        const cleanText = stripHtml(q.question_text);
        const shortText = cleanText.substring(0, 100) + (cleanText.length > 100 ? '...' : '');
        return `<div class="q-item-card selected-question-item" id="selected-item-${q.id}">
            <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeQuestion(${q.id})"><i class="fas fa-times mr-1"></i>Remove</button>
            <div class="q-text">${shortText}</div>
            <div class="q-meta"><i class="fas fa-star mr-1 text-warning"></i>${q.marks} Marks</div>
        </div>`;
    }

    function addQuestion(id, text, marks) {
        selectedQuestions[id] = { id, question_text: text, marks };
        
        // Remove from pool view if present
        document.getElementById(`pool-item-${id}`)?.remove();
        document.getElementById('pool-count').textContent = document.querySelectorAll('.question-pool-item').length;

        // Add to selected view
        const selectedPool = document.getElementById('selected-pool');
        document.getElementById('selected-empty')?.remove();
        selectedPool.insertAdjacentHTML('beforeend', buildSelectedItem(selectedQuestions[id]));

        updateSelectedState();
    }

    function removeQuestion(id) {
        delete selectedQuestions[id];
        document.getElementById(`selected-item-${id}`)?.remove();

        if (Object.keys(selectedQuestions).length === 0) {
            document.getElementById('selected-pool').innerHTML = `<div class="empty-state" id="selected-empty"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No questions selected.</div>`;
        }

        // Just reload pool to reflect changes
        if (document.getElementById('q-subject-filter').value) loadQuestions();
        updateSelectedState();
    }

    function updateSelectedState() {
        const count = Object.keys(selectedQuestions).length;
        document.getElementById('selected-count').textContent = `${count} Selected`;
        document.getElementById('selected-panel-count').textContent = count;

        const container = document.getElementById('hidden-question-inputs');
        container.innerHTML = '';
        Object.keys(selectedQuestions).forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'question_ids[]';
            input.value = id;
            container.appendChild(input);
        });
    }

    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    function escapeJson(str) {
        return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
