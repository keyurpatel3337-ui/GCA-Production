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

$page_title = "Create Template | OES";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['template_name']);
    $desc = trim($_POST['description']);
    $std = (int)$_POST['standard_id'];
    $duration = (int)$_POST['duration_mins'];

    // Rule arrays
    $subjects = $_POST['subject_id'] ?? [];
    $chapters = $_POST['chapter_id'] ?? [];
    $topics = $_POST['topic_id'] ?? [];
    $types = $_POST['question_type_id'] ?? [];
    $difficulties = $_POST['difficulty'] ?? [];
    $nums = $_POST['num_questions'] ?? [];
    $marks = $_POST['marks_per_question'] ?? [];

    $total_q = 0;
    $total_m = 0;
    
    // Calculate totals
    for($i=0; $i<count($subjects); $i++) {
        $n = (int)$nums[$i];
        $m = (float)$marks[$i];
        $total_q += $n;
        $total_m += ($n * $m);
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO tbl_oes_exam_templates (template_name, description, standard_id, total_marks, total_questions, duration_mins, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $std, $total_m, $total_q, $duration, $_SESSION['user_id']]);
        $template_id = $conn->lastInsertId();

        $stmt_rule = $conn->prepare("INSERT INTO tbl_oes_template_rules (template_id, subject_id, chapter_id, topic_id, question_type_id, difficulty, num_questions, marks_per_question) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        for($i=0; $i<count($subjects); $i++) {
            if(!empty($subjects[$i]) && (int)$nums[$i] > 0) {
                $stmt_rule->execute([
                    $template_id,
                    (int)$subjects[$i],
                    empty($chapters[$i]) ? 0 : (int)$chapters[$i],
                    empty($topics[$i]) ? 0 : (int)$topics[$i],
                    empty($types[$i]) ? 1 : (int)$types[$i],
                    empty($difficulties[$i]) ? 'any' : $difficulties[$i],
                    (int)$nums[$i],
                    (float)$marks[$i]
                ]);
            }
        }

        $conn->commit();
        header("Location: exam-templates.php?msg=success");
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $error = "Failed to save template: " . $e->getMessage();
    }
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" id="templateForm">
                <div class="row">
                    <!-- Basic Info -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                            <div class="card-header bg-white border-0 py-3">
                                <h6 class="m-0 font-weight-bold text-primary">1. Basic Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted mb-2">Template Name <span class="text-danger">*</span></label>
                                    <input type="text" name="template_name" class="form-control" placeholder="e.g. Weekly PCB Mock Test" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted mb-2">Standard <span class="text-danger">*</span></label>
                                    <select name="standard_id" id="standard_id" class="form-control" required>
                                        <option value="">Select Standard</option>
                                        <?php
                                        $stds = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC");
                                        while ($s = $stds->fetch()) {
                                            echo "<option value='{$s['stdid']}'>{$s['stdtext']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted mb-2">Duration (Mins) <span class="text-danger">*</span></label>
                                    <input type="number" name="duration_mins" class="form-control" value="60" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold text-muted mb-2">Description</label>
                                    <textarea name="description" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Card -->
                        <div class="card shadow-sm border-0 bg-primary text-white" style="border-radius: 15px;">
                            <div class="card-body">
                                <h6 class="font-weight-bold mb-3">Blueprint Summary</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Questions:</span>
                                    <strong id="summary-q">0</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Marks:</span>
                                    <strong id="summary-m">0</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rules Engine -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">2. Question Rules Engine</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRuleRow()" style="border-radius: 8px;">
                                    <i class="fas fa-plus"></i> Add Rule
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-borderless align-middle mb-0" id="rulesTable">
                                        <thead class="bg-light text-muted small text-uppercase">
                                            <tr>
                                                <th class="ps-4">Subject</th>
                                                <th>Chapter</th>
                                                <th>Difficulty</th>
                                                <th>Qs</th>
                                                <th>Marks/Q</th>
                                                <th class="pe-4 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="rulesBody">
                                            <!-- Rows added dynamically -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-4 bg-light rounded-bottom text-end">
                                    <a href="exam-templates.php" class="btn btn-light px-4 me-2" style="border-radius: 12px; font-weight: 600;">Cancel</a>
                                    <button type="submit" class="btn btn-primary px-5 shadow-sm" style="border-radius: 12px; font-weight: 600;">Save Template</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Template for new row -->
<script type="text/template" id="rowTemplate">
<tr class="rule-row border-bottom">
    <td class="ps-4">
        <select name="subject_id[]" class="form-control form-control-sm subject-select" required onchange="loadChapters(this)">
            <option value="">Subject</option>
        </select>
    </td>
    <td>
        <select name="chapter_id[]" class="form-control form-control-sm chapter-select">
            <option value="">Any Chapter</option>
        </select>
    </td>
    <td>
        <select name="difficulty[]" class="form-control form-control-sm">
            <option value="any">Any</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
        </select>
    </td>
    <td style="width: 80px;">
        <input type="number" name="num_questions[]" class="form-control form-control-sm text-center num-q" min="1" value="1" required onchange="updateSummary()">
    </td>
    <td style="width: 80px;">
        <input type="number" name="marks_per_question[]" class="form-control form-control-sm text-center marks-q" min="0.5" step="0.5" value="1" required onchange="updateSummary()">
    </td>
    <td class="pe-4 text-end">
        <button type="button" class="btn btn-sm btn-light text-danger" onclick="removeRuleRow(this)">
            <i class="fas fa-times"></i>
        </button>
    </td>
</tr>
</script>

<script>
let subjectsCache = {};

document.getElementById('standard_id').addEventListener('change', async function() {
    const stdId = this.value;
    document.getElementById('rulesBody').innerHTML = ''; // Clear rules on std change
    updateSummary();

    if(!stdId) return;

    if(!subjectsCache[stdId]) {
        const res = await fetch(`ajax/get-subjects.php?standard_id=${stdId}`);
        subjectsCache[stdId] = await res.json();
    }
    
    // Add first row automatically
    addRuleRow();
});

function addRuleRow() {
    const stdId = document.getElementById('standard_id').value;
    if(!stdId) {
        alert('Please select a Standard first.');
        return;
    }

    const template = document.getElementById('rowTemplate').innerHTML;
    const tbody = document.getElementById('rulesBody');
    tbody.insertAdjacentHTML('beforeend', template);

    const newRow = tbody.lastElementChild;
    const subjectSelect = newRow.querySelector('.subject-select');
    
    subjectsCache[stdId].forEach(sub => {
        subjectSelect.add(new Option(sub.subject_name, sub.id));
    });

    updateSummary();
}

function removeRuleRow(btn) {
    btn.closest('tr').remove();
    updateSummary();
}

async function loadChapters(selectElem) {
    const subId = selectElem.value;
    const row = selectElem.closest('tr');
    const chapterSelect = row.querySelector('.chapter-select');
    
    chapterSelect.innerHTML = '<option value="">Any Chapter</option>';
    if(!subId) return;

    const res = await fetch(`ajax/get-chapters.php?subject_id=${subId}`);
    const chapters = await res.json();
    
    chapters.forEach(ch => {
        chapterSelect.add(new Option(ch.chapter, ch.chpid));
    });
}

function updateSummary() {
    let totalQ = 0;
    let totalM = 0;

    document.querySelectorAll('.rule-row').forEach(row => {
        const q = parseFloat(row.querySelector('.num-q').value) || 0;
        const m = parseFloat(row.querySelector('.marks-q').value) || 0;
        totalQ += q;
        totalM += (q * m);
    });

    document.getElementById('summary-q').textContent = totalQ;
    document.getElementById('summary-m').textContent = totalM;
}

// Initial dummy endpoints need to exist: ajax/get-subjects.php and ajax/get-chapters.php
// I will verify if they exist or create them.
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
