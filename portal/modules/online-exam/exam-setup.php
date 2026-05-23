<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$page_title = "Exam Setup | OES";

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';

// Fetch standards once
$standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdtext ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        height: 480px;
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
    .filter-bar select { flex: 1; font-size: 0.85rem; }
    .filter-bar button { white-space: nowrap; font-size: 0.85rem; }
</style>

<main class="app-main">
    <div class="app-content pt-3">
        <div class="container-fluid">
            <form id="exam-form" method="POST" action="save-exam.php">

                <!-- ── Row 1: Basic Info ── -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-2"></i>Basic Information</h6>
                        <a href="exam-templates.php" class="btn btn-outline-primary btn-sm" style="border-radius: 10px;">
                            <i class="fas fa-magic mr-2"></i> Auto-Generate via Template
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Exam Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" placeholder="e.g. Weekly Assessment – Physics" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Target Standard <span class="text-danger">*</span></label>
                                    <select name="standard_id" id="main-standard" class="form-control" required onchange="onStandardChange(this.value)">
                                        <option value="">Select Standard</option>
                                        <option value="11">11th</option>
                                        <option value="12">12th</option>
                                        <option value="13">Reneet</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Target Group</label>
                                    <select name="group_id" id="group-id" class="form-control">
                                        <option value="">All Groups</option>
                                        <?php
                                        $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC");
                                        while ($g = $groups->fetch()) {
                                            echo "<option value='{$g['id']}'>{$g['group_name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Subject Filter</label>
                                    <select id="q-subject-filter" class="form-control">
                                        <option value="">— Select Standard first —</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Description / Instructions</label>
                            <textarea name="description" rows="2" placeholder="Enter exam instructions..." class="form-control"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ── Row 2: Question Selection (split pane) ── -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks mr-2"></i>Question Selection</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" id="load-btn" class="btn btn-dark btn-sm" onclick="loadQuestions()">
                                <i class="fas fa-sync mr-1"></i> Load Questions
                            </button>
                            <span class="badge bg-success px-3 py-2 ml-2" id="selected-count">0 Selected</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="split-panel">

                            <!-- Left: Available Questions -->
                            <div class="panel-left">
                                <div class="panel-header available">
                                    <i class="fas fa-list mr-1"></i> Available Questions
                                    <span id="pool-count" class="float-right">0</span>
                                </div>
                                <div id="questions-pool" class="q-pool-box">
                                    <div class="empty-state">
                                        <i class="fas fa-arrow-up fa-2x mb-2 d-block"></i>
                                        Select a Standard &amp; Subject, then click <strong>Load Questions</strong>.
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Selected Questions -->
                            <div class="panel-right">
                                <div class="panel-header selected-h">
                                    <i class="fas fa-check-circle mr-1"></i> Selected for this Exam
                                    <span id="selected-panel-count" class="float-right">0</span>
                                </div>
                                <div id="selected-pool" class="q-selected-box">
                                    <div class="empty-state" id="selected-empty">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        Click <strong>+ Add</strong> on questions to include them here.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ── Row 3: Scheduling ── -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 bg-success text-white d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Scheduling &amp; Rules</h6>
                        <span class="badge bg-white text-success font-weight-bold px-3 py-2"><i class="fas fa-clock mr-1"></i> Server Time: <?= date('d M Y, h:i A') ?> (<?= date_default_timezone_get() ?>)</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Start Date &amp; Time</label>
                                    <input type="datetime-local" name="start_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">End Date &amp; Time</label>
                                    <input type="datetime-local" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Duration (Mins)</label>
                                    <input type="number" name="duration_mins" value="60" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Exam Type</label>
                                    <select name="exam_mode" class="form-control" required>
                                        <option value="Practice" selected>Practice Test</option>
                                        <option value="Final">Final Exam</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-center">
                                <div>
                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="shuffle_questions" name="shuffle_questions" value="1" checked>
                                        <label class="custom-control-label small font-weight-bold" for="shuffle_questions">Shuffle Questions</label>
                                    </div>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="display_result_immediately" name="display_result_immediately" value="1">
                                        <label class="custom-control-label small font-weight-bold" for="display_result_immediately">Show Results Instantly</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs for selected question IDs -->
                <div id="hidden-question-inputs"></div>

                <div class="text-right mb-5">
                    <a href="manage-exams.php" class="btn btn-light px-4 mr-2" style="border-radius: 10px;">Cancel</a>
                    <button type="submit" class="btn btn-success btn-lg px-5" style="border-radius: 10px;">
                        <i class="fas fa-rocket mr-2"></i> Launch Exam
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
    // State: map of question_id -> question object
    const selectedQuestions = {};

    // When standard changes: load subjects automatically
    async function onStandardChange(standardId) {
        const subSelect = document.getElementById('q-subject-filter');
        subSelect.innerHTML = '<option value="">Loading subjects...</option>';

        if (!standardId) {
            subSelect.innerHTML = '<option value="">— Select Standard first —</option>';
            return;
        }

        try {
            const res = await fetch(`ajax/get-subjects.php?standard_id=${standardId}`);
            const subjects = await res.json();
            let html = '<option value="">Select Subject</option>';
            subjects.forEach(s => html += `<option value="${s.id}">${s.subject_name}</option>`);
            subSelect.innerHTML = html;

            // Clear question pool when standard changes
            document.getElementById('questions-pool').innerHTML = `<div class="empty-state"><i class="fas fa-arrow-up fa-2x mb-2 d-block"></i>Select a Subject, then click <strong>Load Questions</strong>.</div>`;
            document.getElementById('pool-count').textContent = '0';
        } catch (e) {
            subSelect.innerHTML = '<option value="">Error loading subjects</option>';
        }
    }

    async function loadQuestions() {
        const subjectId = document.getElementById('q-subject-filter').value;
        const pool = document.getElementById('questions-pool');

        if (!subjectId) {
            alert('Please select a subject first.');
            return;
        }

        pool.innerHTML = '<div class="text-center py-5"><i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i></div>';

        try {
            const res = await fetch(`ajax/fetch-questions.php?subject_id=${subjectId}`);
            const questions = await res.json();

            // Filter out already-selected questions
            const available = questions.filter(q => !selectedQuestions[q.id]);

            if (available.length === 0) {
                pool.innerHTML = '<div class="empty-state"><i class="fas fa-check-double fa-2x mb-2 d-block text-success"></i>All questions from this subject are already selected, or no questions found.</div>';
                document.getElementById('pool-count').textContent = '0';
                return;
            }

            pool.innerHTML = available.map(q => buildPoolItem(q)).join('');
            document.getElementById('pool-count').textContent = available.length;
        } catch (e) {
            pool.innerHTML = '<div class="empty-state text-danger">Error loading questions. Please try again.</div>';
        }
    }

    function buildPoolItem(q) {
        const shortText = stripHtml(q.question_text).substring(0, 120) + (q.question_text.length > 120 ? '...' : '');
        return `<div class="q-item-card question-pool-item" id="pool-item-${q.id}" data-id="${q.id}" data-text="${escapeAttr(q.question_text)}" data-marks="${q.marks}">
            <button type="button" class="btn btn-sm btn-success add-btn" onclick="addQuestion(${q.id})"><i class="fas fa-plus mr-1"></i>Add</button>
            <div class="q-text">${shortText}</div>
            <div class="q-meta"><i class="fas fa-star mr-1 text-warning"></i>${q.marks} Marks</div>
        </div>`;
    }

    function buildSelectedItem(q) {
        const shortText = stripHtml(q.question_text).substring(0, 120) + (q.question_text.length > 120 ? '...' : '');
        return `<div class="q-item-card selected-question-item" id="selected-item-${q.id}">
            <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeQuestion(${q.id})"><i class="fas fa-times mr-1"></i>Remove</button>
            <div class="q-text">${shortText}</div>
            <div class="q-meta"><i class="fas fa-star mr-1 text-warning"></i>${q.marks} Marks</div>
        </div>`;
    }

    function addQuestion(id) {
        const poolItem = document.getElementById(`pool-item-${id}`);
        if (!poolItem) return;

        const q = {
            id: id,
            question_text: poolItem.dataset.text,
            marks: poolItem.dataset.marks
        };

        selectedQuestions[id] = q;

        // Remove from pool
        poolItem.remove();
        document.getElementById('pool-count').textContent = document.querySelectorAll('.question-pool-item').length;

        // Add to selected panel
        const selectedPool = document.getElementById('selected-pool');
        document.getElementById('selected-empty')?.remove();
        selectedPool.insertAdjacentHTML('beforeend', buildSelectedItem(q));

        updateSelectedState();
    }

    function removeQuestion(id) {
        delete selectedQuestions[id];

        // Remove from selected panel
        document.getElementById(`selected-item-${id}`)?.remove();

        if (Object.keys(selectedQuestions).length === 0) {
            document.getElementById('selected-pool').innerHTML = `<div class="empty-state" id="selected-empty"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Click <strong>+ Add</strong> on questions to include them here.</div>`;
        }

        // Re-add to pool if current subject matches (just reload pool to be safe)
        // Re-add to pool visually if subject still loaded
        const pool = document.getElementById('questions-pool');
        const subjectId = document.getElementById('q-subject-filter').value;
        // We'll just reload the pool
        if (subjectId) loadQuestions();

        updateSelectedState();
    }

    function updateSelectedState() {
        const count = Object.keys(selectedQuestions).length;
        document.getElementById('selected-count').textContent = `${count} Selected`;
        document.getElementById('selected-panel-count').textContent = count;

        // Sync hidden inputs for form submission
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

    function escapeAttr(str) {
        return stripHtml(str).replace(/"/g, '&quot;');
    }
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
