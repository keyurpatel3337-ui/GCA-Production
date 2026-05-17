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

$page_title = "Edit Exam | OES";

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-dark font-weight-bold">Edit <span class="text-primary">Exam</span></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            
            <div class="mb-4 d-flex justify-content-between align-items-end">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Modify Exam Settings</h1>
                    <p class="text-muted small mb-0">Update schedule, title, and manage questions.</p>
                </div>
                <a href="manage-exams.php" class="btn btn-outline-secondary" style="border-radius: 10px;">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>

            <form id="exam-form" method="POST" action="update-exam.php" class="row">
                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                
                <!-- Left Side: Exam Details -->
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-2"></i> Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group mb-4">
                                        <label class="small font-weight-bold">Exam Title</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($exam['title']) ?>" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-4">
                                        <label class="small font-weight-bold">Target Standard</label>
                                        <select name="standard_id" class="form-control" required>
                                            <option value="">Select Standard</option>
                                            <?php
                                            $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdtext ASC");
                                            while ($std = $standards->fetch()) {
                                                $sel = ($std['stdid'] == $exam['standard_id']) ? 'selected' : '';
                                                echo "<option value='{$std['stdid']}' $sel>{$std['stdtext']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">Description / Instructions</label>
                                <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars($exam['description']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks mr-2"></i> Manage Questions</h6>
                            <span class="badge bg-primary px-3" id="selected-count"><?= count($selected_questions) ?> Selected</span>
                        </div>
                        <div class="card-body">
                            <div id="questions-pool" class="p-3 bg-light rounded border border-dashed" style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($selected_questions as $q): ?>
                                    <div class="card mb-2 hover-shadow transition-all pointer border-primary" onclick="toggleCheck(this)">
                                        <div class="card-body p-3 d-flex align-items-start">
                                            <div class="custom-control custom-checkbox mr-3">
                                                <input type="checkbox" name="question_ids[]" value="<?= $q['id'] ?>" class="custom-control-input q-check" checked onchange="updateCount()">
                                                <label class="custom-control-label"></label>
                                            </div>
                                            <div class="flex-1">
                                                <div class="small text-dark mb-1"><?= $q['question_text'] ?></div>
                                                <span class="badge bg-light text-muted border"><?= $q['marks'] ?> Marks</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <hr class="my-4">
                                <p class="text-muted small text-center mb-3">Add more questions by filtering below</p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <select id="q-standard-filter" class="form-control" onchange="loadSubjects(this.value)">
                                            <option value="">Select Standard</option>
                                            <?php
                                            $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdtext ASC");
                                            while ($std = $standards->fetch()) {
                                                echo "<option value='{$std['stdid']}'>{$std['stdtext']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <select id="q-subject-filter" class="form-control">
                                            <option value="">Select Subject</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" onclick="loadQuestions()" class="btn btn-dark w-100">Load</button>
                                    </div>
                                </div>
                                <div id="new-questions-list"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Scheduling -->
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 20px;">
                        <div class="card-header py-3 bg-warning text-dark">
                            <h6 class="m-0 font-weight-bold">Update Scheduling</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold">Start Date & Time</label>
                                <input type="datetime-local" name="start_time" value="<?= date('Y-m-d\TH:i', strtotime($exam['start_time'])) ?>" class="form-control" required>
                            </div>
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold">End Date & Time</label>
                                <input type="datetime-local" name="end_time" value="<?= date('Y-m-d\TH:i', strtotime($exam['end_time'])) ?>" class="form-control" required>
                            </div>
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold">Duration (Minutes)</label>
                                <input type="number" name="duration_mins" value="<?= $exam['duration_mins'] ?>" class="form-control">
                            </div>
                            
                            <hr>
                            
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="shuffle_questions" name="shuffle_questions" value="1" <?= $exam['shuffle_questions'] ? 'checked' : '' ?>>
                                <label class="custom-control-label small font-weight-bold" for="shuffle_questions">Shuffle Questions</label>
                            </div>

                            <div class="custom-control custom-switch mb-4">
                                <input type="checkbox" class="custom-control-input" id="display_result_immediately" name="display_result_immediately" value="1" <?= $exam['display_result_immediately'] ? 'checked' : '' ?>>
                                <label class="custom-control-label small font-weight-bold" for="display_result_immediately">Show Results Instantly</label>
                            </div>

                            <button type="submit" class="btn btn-warning btn-block btn-lg mt-4 font-weight-bold w-100">
                                <i class="fas fa-save mr-2"></i> Update Exam
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    async function loadSubjects(standardId) {
        const subSelect = document.getElementById('q-subject-filter');
        subSelect.innerHTML = '<option value="">Loading...</option>';
        if (!standardId) return;

        try {
            const response = await fetch(`ajax/get-subjects.php?standard_id=${standardId}`);
            const subjects = await response.json();
            let html = '<option value="">Select Subject</option>';
            subjects.forEach(sub => {
                html += `<option value="${sub.id}">${sub.subject_name}</option>`;
            });
            subSelect.innerHTML = html;
        } catch (error) {
            subSelect.innerHTML = '<option value="">Error</option>';
        }
    }

    async function loadQuestions() {
        const subjectId = document.getElementById('q-subject-filter').value;
        const list = document.getElementById('new-questions-list');
        if(!subjectId) {
            alert('Please select a subject first.');
            return;
        }

        list.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Fetching questions...</p></div>';

        try {
            const response = await fetch(`ajax/fetch-questions.php?subject_id=${subjectId}`);
            const data = await response.json();
            
            if(data.error) {
                list.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            const questions = data;
            if(questions.length === 0) {
                list.innerHTML = '<p class="text-center text-muted py-5">No available questions found for this subject.</p>';
                return;
            }

            // Get already selected IDs to avoid duplicates in view
            const selectedIds = Array.from(document.querySelectorAll('.q-check')).map(i => i.value);

            list.innerHTML = questions.filter(q => !selectedIds.includes(q.id.toString())).map(q => `
                <div class="card mb-2 hover-shadow transition-all pointer" onclick="toggleCheck(this)">
                    <div class="card-body p-3 d-flex align-items-start">
                        <div class="custom-control custom-checkbox mr-3">
                            <input type="checkbox" name="question_ids[]" value="${q.id}" class="custom-control-input q-check" onchange="updateCount(event)">
                            <label class="custom-control-label"></label>
                        </div>
                        <div class="flex-1">
                            <div class="small text-dark mb-1">${q.question_text}</div>
                            <span class="badge bg-light text-muted border">${q.marks} Marks</span>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Error:', error);
            list.innerHTML = '<p class="text-center text-danger py-5">Failed to load questions. Please try again.</p>';
        }
    }

    function toggleCheck(card) {
        const check = card.querySelector('.q-check');
        check.checked = !check.checked;
        if(check.checked) card.classList.add('border-primary');
        else card.classList.remove('border-primary');
        updateCount();
    }

    function updateCount(e) {
        if(e) e.stopPropagation();
        const count = document.querySelectorAll('.q-check:checked').length;
        document.getElementById('selected-count').textContent = `${count} Selected`;
    }
</script>

<style>
    .hover-shadow:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; border-color: #4e73df; }
    .pointer { cursor: pointer; }
    .transition-all { transition: all 0.2s; }
</style>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
