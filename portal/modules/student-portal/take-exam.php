<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: " . PORTAL_URL . "/student-login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify Exam Access
$sql = "SELECT e.*, 
        (SELECT id FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_id,
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE e.id = ? AND e.status = 'Live' AND e.start_time <= NOW() AND e.end_time >= NOW()";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id, $student_id, $exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not available or expired.");
}

if ($exam['attempt_status'] === 'Submitted') {
    die("You have already submitted this exam.");
}

// Initialize Attempt if not already started
if (!$exam['attempt_id']) {
    $ins_stmt = $conn->prepare("INSERT INTO tbl_oes_student_exams (exam_id, student_id, start_timestamp, status) VALUES (?, ?, NOW(), 'Ongoing')");
    $ins_stmt->execute([$exam_id, $student_id]);
    $attempt_id = $conn->lastInsertId();
} else {
    $attempt_id = $exam['attempt_id'];
}

// Fetch Questions
$q_sql = "SELECT q.* FROM tbl_oes_questions q 
          JOIN tbl_oes_exam_questions eq ON q.id = eq.question_id 
          WHERE eq.exam_id = ? 
          ORDER BY eq.order_no ASC";
$q_stmt = $conn->prepare($q_sql);
$q_stmt->execute([$exam_id]);
$questions = $q_stmt->fetchAll();

$page_title = "Exam: " . $exam['title'];

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<style>
    body { font-family: 'Outfit', sans-serif; }
    .question-palette-btn.active { border-color: #4f46e5; background-color: #e0e7ff; color: #4338ca; }
    .question-palette-btn.answered { background-color: #10b981; color: white; border-color: #10b981; }
    .question-palette-btn.marked { background-color: #8b5cf6; color: white; border-color: #8b5cf6; }
    .exam-container { height: calc(100vh - 160px); overflow: hidden; }
</style>

<main class="app-main">
    <div class="app-content-header bg-white border-bottom shadow-sm">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="h4 mb-0 font-weight-bold text-dark"><?php echo htmlspecialchars($exam['title']); ?></h1>
                    <p class="text-muted small mb-0">Total Questions: <?php echo count($questions); ?></p>
                </div>
                <div class="col-md-6 text-right d-flex align-items-center justify-content-end gap-4">
                    <div class="text-right mr-4">
                        <div class="text-xs font-weight-bold text-muted uppercase">Time Remaining</div>
                        <div id="timer" class="h4 mb-0 font-weight-bold text-primary">00:00:00</div>
                    </div>
                    <button onclick="confirmFinish()" class="btn btn-danger font-weight-bold px-4">
                        Finish Exam
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content p-0">
        <div class="container-fluid p-0">
            <div class="row no-gutters exam-container">
                <!-- Left: Question Content -->
                <div class="col-md-9 overflow-auto p-5">
                    <div id="question-container" class="max-w-3xl mx-auto">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <span id="question-number" class="badge badge-primary px-3 py-2">Question 1</span>
                            <span id="question-marks" class="small text-muted font-weight-bold">1.0 Marks</span>
                        </div>
                        
                        <div id="question-body" class="h4 text-dark mb-5 leading-relaxed">
                            <!-- Question text injected here -->
                        </div>

                        <div id="options-container" class="space-y-3">
                            <!-- Options injected here -->
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-5 pt-4 border-top">
                            <div class="d-flex gap-2">
                                <button onclick="prevQuestion()" class="btn btn-outline-secondary">Previous</button>
                                <button onclick="markForReview()" class="btn btn-outline-warning">Mark for Review</button>
                            </div>
                            <button onclick="saveAndNext()" class="btn btn-primary px-5 font-weight-bold">Save & Next</button>
                        </div>
                    </div>
                </div>

                <!-- Right: Palette -->
                <div class="col-md-3 bg-white border-left p-4 overflow-auto">
                    <h6 class="text-xs font-weight-bold text-muted text-uppercase mb-4">Question Palette</h6>
                    <div id="palette" class="row row-cols-4 g-2">
                        <!-- Palette buttons injected here -->
                    </div>

                    <div class="mt-5 pt-4 border-top">
                        <div class="small mb-2"><span class="badge bg-success me-2">&nbsp;</span> Answered</div>
                        <div class="small mb-2"><span class="badge bg-warning me-2">&nbsp;</span> Not Answered</div>
                        <div class="small mb-2"><span class="badge bg-primary me-2">&nbsp;</span> Active</div>
                        <div class="small mb-2"><span class="badge bg-secondary me-2">&nbsp;</span> Marked</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    const questions = <?php echo json_encode($questions); ?>;
    const attemptId = <?php echo $attempt_id; ?>;
    let currentIdx = 0;
    let responses = {}; 

    // Timer Logic
    let duration = <?php echo $exam['duration_mins']; ?> * 60;
    const timerDisplay = document.getElementById('timer');
    
    const countdown = setInterval(() => {
        const h = Math.floor(duration / 3600);
        const m = Math.floor((duration % 3600) / 60);
        const s = duration % 60;
        
        timerDisplay.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        
        if (duration <= 0) {
            clearInterval(countdown);
            autoSubmit();
        }
        duration--;
    }, 1000);

    function loadQuestion(idx) {
        currentIdx = idx;
        const q = questions[idx];
        
        document.getElementById('question-number').textContent = `Question ${idx + 1}`;
        document.getElementById('question-marks').textContent = `${q.marks} Marks`;
        document.getElementById('question-body').innerHTML = q.question_text;
        
        const options = ['A', 'B', 'C', 'D'];
        const container = document.getElementById('options-container');
        container.innerHTML = options.map(opt => `
            <div onclick="selectOption('${opt}')" id="opt-${opt}" class="p-4 border rounded-lg mb-3 cursor-pointer hover:bg-light transition-all ${responses[q.id]?.selected == opt ? 'border-primary bg-primary-light' : 'border-secondary-light'}">
                <span class="badge badge-light mr-3">${opt}</span>
                <span class="text-dark">${q['option_' + opt.toLowerCase()]}</span>
            </div>
        `).join('');

        updatePalette();
    }

    function selectOption(opt) {
        const qId = questions[currentIdx].id;
        responses[qId] = responses[qId] || {};
        responses[qId].selected = opt;
        
        document.querySelectorAll('[id^="opt-"]').forEach(el => el.classList.remove('border-primary', 'bg-primary-light'));
        document.getElementById(`opt-${opt}`).classList.add('border-primary', 'bg-primary-light');
    }

    async function saveAndNext() {
        const q = questions[currentIdx];
        const response = responses[q.id];
        
        if(response && response.selected) {
            await syncResponse(q.id, response.selected);
        }

        if(currentIdx < questions.length - 1) {
            loadQuestion(currentIdx + 1);
        } else {
            alert('This was the last question.');
        }
    }

    async function syncResponse(qId, opt) {
        try {
            await fetch('ajax/save-response.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: attemptId,
                    question_id: qId,
                    selected_option: opt
                })
            });
        } catch (error) { console.error('Sync failed', error); }
    }

    function updatePalette() {
        const palette = document.getElementById('palette');
        palette.innerHTML = questions.map((q, i) => {
            let statusClass = 'btn-outline-secondary';
            if(i === currentIdx) statusClass = 'btn-primary';
            else if(responses[q.id]?.selected) statusClass = 'btn-success';
            
            return `<div class="col"><button onclick="loadQuestion(${i})" class="btn btn-sm w-100 font-weight-bold ${statusClass}">${i + 1}</button></div>`;
        }).join('');
    }

    function prevQuestion() { if(currentIdx > 0) loadQuestion(currentIdx - 1); }

    function confirmFinish() {
        if(confirm('Are you sure you want to finish and submit the exam?')) {
            submitExam();
        }
    }

    async function submitExam() {
        clearInterval(countdown);
        await fetch('ajax/submit-exam.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attempt_id: attemptId })
        });
        window.location.href = 'online-exams.php?msg=submitted';
    }

    function autoSubmit() {
        alert('Time is up! Your exam is being submitted automatically.');
        submitExam();
    }

    loadQuestion(0);
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
