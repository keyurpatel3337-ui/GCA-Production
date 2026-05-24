<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to Super Admin, Principal, Dept Head, and Teachers
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_DEPT_HEAD, ROLE_TEACHER, ROLE_ASSISTANT_TEACHER])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$teacher_id = (int)$_SESSION['user_id'];

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'create_quiz') {
            $title = trim($_POST['title'] ?? '');
            $subject_id = intval($_POST['subject_id'] ?? 0);
            
            if (empty($title) || $subject_id <= 0 || empty($_POST['questions'])) {
                echo json_encode(['success' => false, 'message' => 'Title, Subject, and Questions are required.']);
                exit;
            }

            // Generate unique quiz code
            $quiz_code = strtoupper(substr(md5(uniqid()), 0, 6));

            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO tbl_live_quizzes (title, quiz_code, subject_id, teacher_id, status) VALUES (?, ?, ?, ?, 'Waiting')");
            $stmt->execute([$title, $quiz_code, $subject_id, $teacher_id]);
            $quiz_id = $conn->lastInsertId();

            // Insert questions
            $ins_q = $conn->prepare("
                INSERT INTO tbl_live_quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, order_no) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['questions'] as $index => $q) {
                $ins_q->execute([
                    $quiz_id,
                    trim($q['text'] ?? ''),
                    trim($q['a'] ?? ''),
                    trim($q['b'] ?? ''),
                    trim($q['c'] ?? ''),
                    trim($q['d'] ?? ''),
                    trim($q['correct'] ?? 'A'),
                    $index + 1
                ]);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Live Quiz created successfully!', 'quiz_id' => $quiz_id]);
            exit;
        }

        if ($action === 'start_quiz') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE tbl_live_quizzes SET status = 'Active', current_question_index = 1 WHERE id = ?");
            $stmt->execute([$quiz_id]);
            echo json_encode(['success' => true, 'message' => 'Quiz started! First question is active.']);
            exit;
        }

        if ($action === 'reveal_answer') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE tbl_live_quizzes SET status = 'Revealed' WHERE id = ?");
            $stmt->execute([$quiz_id]);
            echo json_encode(['success' => true, 'message' => 'Answer revealed.']);
            exit;
        }

        if ($action === 'next_question') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $current_idx = intval($_POST['current_index'] ?? 0);

            $stmt = $conn->prepare("UPDATE tbl_live_quizzes SET status = 'Active', current_question_index = ? WHERE id = ?");
            $stmt->execute([$current_idx + 1, $quiz_id]);
            echo json_encode(['success' => true, 'message' => 'Loaded next question.']);
            exit;
        }

        if ($action === 'complete_quiz') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE tbl_live_quizzes SET status = 'Completed' WHERE id = ?");
            $stmt->execute([$quiz_id]);
            echo json_encode(['success' => true, 'message' => 'Quiz completed!']);
            exit;
        }

        if ($action === 'get_quiz_state') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            
            // Get Quiz details
            $q_stmt = $conn->prepare("
                SELECT q.*, sub.subject_name 
                FROM tbl_live_quizzes q
                JOIN tbl_subjects sub ON q.subject_id = sub.id
                WHERE q.id = ?
            ");
            $q_stmt->execute([$quiz_id]);
            $quiz = $q_stmt->fetch();

            if (!$quiz) {
                echo json_encode(['success' => false, 'message' => 'Quiz not found.']);
                exit;
            }

            // Get active question details
            $question = null;
            if ($quiz['status'] === 'Active' || $quiz['status'] === 'Revealed') {
                $quest_stmt = $conn->prepare("SELECT * FROM tbl_live_quiz_questions WHERE quiz_id = ? AND order_no = ?");
                $quest_stmt->execute([$quiz_id, $quiz['current_question_index']]);
                $question = $quest_stmt->fetch();
            }

            // Get total count of joined students
            $stud_stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) FROM tbl_live_quiz_responses WHERE quiz_id = ?");
            $stud_stmt->execute([$quiz_id]);
            $students_count = $stud_stmt->fetchColumn();

            // Option count mapping
            $responses = [];
            $answers_stmt = $conn->prepare("SELECT selected_option, COUNT(*) as qty FROM tbl_live_quiz_responses WHERE quiz_id = ? AND question_id = ? GROUP BY selected_option");
            $answers_stmt->execute([$quiz_id, $question['id'] ?? 0]);
            while ($row = $answers_stmt->fetch()) {
                $responses[$row['selected_option']] = (int)$row['qty'];
            }

            // Leaderboard calculations (top 5 sorted by sum points)
            $lead_stmt = $conn->prepare("
                SELECT u.name, SUM(r.points) as score
                FROM tbl_live_quiz_responses r
                JOIN tbl_users u ON r.student_id = u.id
                WHERE r.quiz_id = ?
                GROUP BY r.student_id
                ORDER BY score DESC, MIN(r.response_time_ms) ASC
                LIMIT 5
            ");
            $lead_stmt->execute([$quiz_id]);
            $leaderboard = $lead_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'quiz' => $quiz,
                'question' => $question,
                'students_count' => $students_count,
                'responses' => $responses,
                'leaderboard' => $leaderboard
            ]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Read subjects for builder dropdown
$subjects = $conn->query("SELECT id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC")->fetchAll();

$page_title = 'Live Lecture Quiz Hub';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Live Quiz Hub</h4>
            <p class="text-muted small mb-0">Generate live, interactive MCQ competitive quiz sessions to run during lectures.</p>
        </div>
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createQuizModal">
            <i class="fas fa-magic"></i>
            <span>Create Live Quiz</span>
        </button>
    </div>

    <!-- Active Presentation Console -->
    <div class="row">
        <!-- Presenter Panel -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow border-0 rounded-3 css-live-quiz-6ebde4">
                <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold" id="quiz-header-title"><i class="fas fa-play me-2"></i> Presentation Monitor</h6>
                    <span class="badge bg-danger rounded-pill css-live-quiz-224b51" id="live-quiz-badge">LIVE RUNNING</span>
                </div>
                <div class="card-body bg-light d-flex flex-column align-items-center justify-content-center" id="monitor-content">
                    <!-- Idle State -->
                    <div class="text-center text-muted" id="idle-screen">
                        <i class="fas fa-tv fa-4x mb-3 text-secondary d-block"></i>
                        <h5>No quiz active at this moment.</h5>
                        <p class="small text-muted">Create a live quiz or launch an existing session from the list below.</p>
                    </div>

                    <!-- Lobby Waiting State -->
                    <div class="w-100 text-center py-4 css-live-quiz-224b51" id="lobby-screen">
                        <span class="text-muted small d-block mb-1">JOIN CODE:</span>
                        <h1 class="display-1 fw-extrabold text-primary mb-3 css-live-quiz-501590" id="lobby-code">------</h1>
                        <h4 class="fw-bold" id="lobby-title">Quiz Title</h4>
                        <div class="my-4 py-3 bg-white rounded-3 border">
                            <h2 class="fw-extrabold text-success mb-0" id="lobby-student-count">0</h2>
                            <p class="text-muted small mb-0">Students Joined the Lobby</p>
                        </div>
                        <button type="button" class="btn btn-primary btn-lg rounded-pill px-5" id="startQuizBtn" onclick="startQuiz()">
                            <i class="fas fa-play me-2"></i> Start Live Quiz
                        </button>
                    </div>

                    <!-- Active Question Presentation State -->
                    <div class="w-100 css-live-quiz-224b51" id="question-screen">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <span class="badge bg-primary" id="q-number-pill">Question 1</span>
                            <span class="fw-bold fs-4 text-danger"><i class="far fa-clock me-1"></i> <span id="quiz-timer">30</span>s</span>
                        </div>
                        
                        <h3 class="fw-bold text-dark mb-4 text-center" id="q-text-display">Question Text</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border bg-white rounded shadow-sm option-box" id="opt-a-box">
                                    <strong class="text-primary me-2">A:</strong> <span id="opt-a-text">Option A</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border bg-white rounded shadow-sm option-box" id="opt-b-box">
                                    <strong class="text-success me-2">B:</strong> <span id="opt-b-text">Option B</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border bg-white rounded shadow-sm option-box" id="opt-c-box">
                                    <strong class="text-warning me-2">C:</strong> <span id="opt-c-text">Option C</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border bg-white rounded shadow-sm option-box" id="opt-d-box">
                                    <strong class="text-danger me-2">D:</strong> <span id="opt-d-text">Option D</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bar chart count overlay -->
                        <div id="chart-overlay" class="mt-4 p-3 bg-white border rounded shadow-sm css-live-quiz-224b51">
                            <h6 class="fw-bold small text-muted mb-2">Student Response Breakdown:</h6>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex align-items-center">
                                    <span class="small fw-bold css-live-quiz-ec5c70">A:</span>
                                    <div class="progress flex-grow-1 css-live-quiz-945cc2">
                                        <div class="progress-bar bg-primary css-live-quiz-1f28c3" id="bar-a"></div>
                                    </div>
                                    <span class="small ms-2 fw-bold" id="cnt-a">0</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="small fw-bold css-live-quiz-ec5c70">B:</span>
                                    <div class="progress flex-grow-1 css-live-quiz-945cc2">
                                        <div class="progress-bar bg-success css-live-quiz-1f28c3" id="bar-b"></div>
                                    </div>
                                    <span class="small ms-2 fw-bold" id="cnt-b">0</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="small fw-bold css-live-quiz-ec5c70">C:</span>
                                    <div class="progress flex-grow-1 css-live-quiz-945cc2">
                                        <div class="progress-bar bg-warning css-live-quiz-1f28c3" id="bar-c"></div>
                                    </div>
                                    <span class="small ms-2 fw-bold" id="cnt-c">0</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="small fw-bold css-live-quiz-ec5c70">D:</span>
                                    <div class="progress flex-grow-1 css-live-quiz-945cc2">
                                        <div class="progress-bar bg-danger css-live-quiz-1f28c3" id="bar-d"></div>
                                    </div>
                                    <span class="small ms-2 fw-bold" id="cnt-d">0</span>
                                </div>
                            </div>
                            <div class="text-center mt-3 fw-bold text-success" id="reveal-correct-box">
                                <i class="fas fa-check-circle me-1"></i> CORRECT ANSWER: <span id="correct-opt-text">A</span>
                            </div>
                        </div>

                        <!-- Footer actions -->
                        <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-4" id="presenter-actions-bar">
                            <button type="button" class="btn btn-warning rounded-pill px-4" id="revealBtn" onclick="revealAnswer()"><i class="fas fa-eye me-1"></i> Reveal Answer</button>
                            <button type="button" class="btn btn-primary rounded-pill px-4 css-live-quiz-224b51" id="nextBtn" onclick="nextQuestion()"><i class="fas fa-arrow-right me-1"></i> Next Question</button>
                            <button type="button" class="btn btn-dark rounded-pill px-4 css-live-quiz-224b51" id="completeBtn" onclick="completeQuiz()"><i class="fas fa-flag-checkered me-1"></i> End Quiz</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboard / Competitive Stats -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-0 rounded-3 css-live-quiz-6ebde4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="fas fa-crown me-2"></i> Live Leaderboard</h6>
                </div>
                <div class="card-body p-3 overflow-auto" id="leaderboard-container">
                    <div class="text-center py-5 text-muted" id="lead-empty-state">
                        <i class="fas fa-users-cog fa-3x mb-3 text-secondary d-block"></i>
                        <h6>Quiz statistics will populate here once answers are submitted!</h6>
                    </div>
                    <!-- Ranking List -->
                    <div id="leaderboard-list" class="d-flex flex-column gap-3 css-live-quiz-3e2e7c">
                        <!-- JS generated items -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Live Quiz -->
<div class="modal fade" id="createQuizModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-magic me-2"></i> Create Live Quiz</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createQuizForm">
                <div class="modal-body p-4 css-live-quiz-7260ae">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Quiz Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Live Quiz: Mechanics & Force" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-list me-1"></i> Quiz Questions</h6>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="addQuestionField()">
                            <i class="fas fa-plus me-1"></i> Add Question
                        </button>
                    </div>
                    <div id="builder-questions-list" class="d-flex flex-column gap-3">
                        <!-- Initial Question Builder Card -->
                        <div class="p-3 border rounded bg-light question-build-card" data-idx="0">
                            <div class="mb-2 d-flex justify-content-between">
                                <span class="badge bg-secondary">Question 1</span>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="questions[0][text]" class="form-control" placeholder="Type MCQ question..." required>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6"><input type="text" name="questions[0][a]" class="form-control form-control-sm" placeholder="Option A" required></div>
                                <div class="col-md-6"><input type="text" name="questions[0][b]" class="form-control form-control-sm" placeholder="Option B" required></div>
                                <div class="col-md-6"><input type="text" name="questions[0][c]" class="form-control form-control-sm" placeholder="Option C" required></div>
                                <div class="col-md-6"><input type="text" name="questions[0][d]" class="form-control form-control-sm" placeholder="Option D" required></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="small fw-bold">Correct Option</label>
                                    <select name="questions[0][correct]" class="form-select form-select-sm">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill"><i class="fas fa-rocket me-1"></i> Create and Launch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let activeQuizId = null;
    let quizStateInterval = null;
    let countdownTimer = null;
    let qCount = 1;

    // Add field to modal dynamic question builder
    function addQuestionField() {
        const list = document.getElementById('builder-questions-list');
        const idx = qCount;
        const html = `
            <div class="p-3 border rounded bg-light question-build-card" data-idx="${idx}">
                <div class="mb-2 d-flex justify-content-between">
                    <span class="badge bg-secondary">Question ${idx + 1}</span>
                    <button type="button" class="btn btn-sm text-danger p-0" onclick="this.closest('.question-build-card').remove();"><i class="fas fa-trash"></i></button>
                </div>
                <div class="mb-2">
                    <input type="text" name="questions[${idx}][text]" class="form-control" placeholder="Type MCQ question..." required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><input type="text" name="questions[${idx}][a]" class="form-control form-control-sm" placeholder="Option A" required></div>
                    <div class="col-md-6"><input type="text" name="questions[${idx}][b]" class="form-control form-control-sm" placeholder="Option B" required></div>
                    <div class="col-md-6"><input type="text" name="questions[${idx}][c]" class="form-control form-control-sm" placeholder="Option C" required></div>
                    <div class="col-md-6"><input type="text" name="questions[${idx}][d]" class="form-control form-control-sm" placeholder="Option D" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label class="small fw-bold">Correct Option</label>
                        <select name="questions[${idx}][correct]" class="form-select form-select-sm">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
        list.insertAdjacentHTML('beforeend', html);
        qCount++;
    }

    // Submit Create Quiz Form
    $('#createQuizForm').on('submit', function (e) {
        e.preventDefault();
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Creating...');

        $.post(location.href, $(this).serialize() + '&action=create_quiz')
            .then(res => {
                submitBtn.prop('disabled', false).html('<i class="fas fa-rocket me-1"></i> Create and Launch');
                if (res.success) {
                    $('#createQuizModal').modal('hide');
                    showToast('success', 'Created!', res.message);
                    
                    // Activate presenting mode
                    activeQuizId = res.quiz_id;
                    startPollingState();
                } else {
                    showToast('error', 'Error!', res.message);
                }
            })
            .catch(() => {
                submitBtn.prop('disabled', false).html('<i class="fas fa-rocket me-1"></i> Create and Launch');
                showToast('error', 'Connection Error!', 'Failed to connect to API.');
            });
    });

    // Start Real-Time State Polling (Simulated with short AJAX poll)
    function startPollingState() {
        document.getElementById('idle-screen').style.display = 'none';
        document.getElementById('live-quiz-badge').style.display = 'block';

        if (quizStateInterval) clearInterval(quizStateInterval);
        
        quizStateInterval = setInterval(() => {
            $.post(location.href, { action: 'get_quiz_state', quiz_id: activeQuizId })
                .done(res => {
                    if (res.success) {
                        const q = res.quiz;
                        const quest = res.question;
                        
                        if (q.status === 'Waiting') {
                            document.getElementById('lobby-screen').style.display = 'block';
                            document.getElementById('question-screen').style.display = 'none';
                            document.getElementById('lobby-code').textContent = q.quiz_code;
                            document.getElementById('lobby-title').textContent = q.title;
                            document.getElementById('lobby-student-count').textContent = res.students_count;
                        } 
                        else if (q.status === 'Active' || q.status === 'Revealed') {
                            document.getElementById('lobby-screen').style.display = 'none';
                            document.getElementById('question-screen').style.display = 'block';
                            
                            document.getElementById('q-number-pill').textContent = `Question ${q.current_question_index}`;
                            document.getElementById('q-text-display').textContent = quest.question_text;
                            document.getElementById('opt-a-text').textContent = quest.option_a;
                            document.getElementById('opt-b-text').textContent = quest.option_b;
                            document.getElementById('opt-c-text').textContent = quest.option_c;
                            document.getElementById('opt-d-text').textContent = quest.option_d;

                            // Reset color boxes border styles
                            $('.option-box').removeClass('border-success bg-light-success text-success fw-bold');

                            if (q.status === 'Active') {
                                document.getElementById('chart-overlay').style.display = 'none';
                                document.getElementById('revealBtn').style.display = 'block';
                                document.getElementById('nextBtn').style.display = 'none';
                                document.getElementById('completeBtn').style.display = 'none';
                                startLocalTimer();
                            } 
                            else if (q.status === 'Revealed') {
                                clearInterval(countdownTimer);
                                document.getElementById('quiz-timer').textContent = '0';
                                document.getElementById('revealBtn').style.display = 'none';
                                
                                // Show next or complete button
                                if (q.current_question_index < qCount) {
                                    document.getElementById('nextBtn').style.display = 'block';
                                } else {
                                    document.getElementById('completeBtn').style.display = 'block';
                                }

                                // Show responses breakdown & reveal correct option
                                document.getElementById('chart-overlay').style.display = 'block';
                                document.getElementById('correct-opt-text').textContent = quest.correct_option;
                                
                                const total = res.students_count || 1;
                                const cntA = res.responses['A'] || 0;
                                const cntB = res.responses['B'] || 0;
                                const cntC = res.responses['C'] || 0;
                                const cntD = res.responses['D'] || 0;

                                document.getElementById('cnt-a').textContent = cntA;
                                document.getElementById('cnt-b').textContent = cntB;
                                document.getElementById('cnt-c').textContent = cntC;
                                document.getElementById('cnt-d').textContent = cntD;

                                document.getElementById('bar-a').style.width = ((cntA / total) * 100) + '%';
                                document.getElementById('bar-b').style.width = ((cntB / total) * 100) + '%';
                                document.getElementById('bar-c').style.width = ((cntC / total) * 100) + '%';
                                document.getElementById('bar-d').style.width = ((cntD / total) * 100) + '%';

                                // Color target option
                                const targetBoxId = 'opt-' + quest.correct_option.toLowerCase() + '-box';
                                document.getElementById(targetBoxId).classList.add('border-success', 'bg-light-success', 'text-success', 'fw-bold');

                                // Update Leaderboard visually
                                updateLeaderboardUI(res.leaderboard);
                            }
                        }
                    }
                });
        }, 1500);
    }

    // Start countdown timer
    let timerValue = 30;
    function startLocalTimer() {
        if (countdownTimer) return;
        timerValue = 30;
        document.getElementById('quiz-timer').textContent = timerValue;

        countdownTimer = setInterval(() => {
            timerValue--;
            document.getElementById('quiz-timer').textContent = timerValue;
            if (timerValue <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                revealAnswer();
            }
        }, 1000);
    }

    function startQuiz() {
        $.post(location.href, { action: 'start_quiz', quiz_id: activeQuizId })
            .done(res => showToast('success', 'Lobby Started!', res.message));
    }

    function revealAnswer() {
        clearInterval(countdownTimer);
        countdownTimer = null;
        $.post(location.href, { action: 'reveal_answer', quiz_id: activeQuizId })
            .done(res => showToast('success', 'Answer Revealed!', res.message));
    }

    function nextQuestion() {
        const curIdx = parseInt(document.getElementById('q-number-pill').textContent.replace('Question ', ''));
        $.post(location.href, { action: 'next_question', quiz_id: activeQuizId, current_index: curIdx })
            .done(res => showToast('success', 'Next Slide!', res.message));
    }

    function completeQuiz() {
        $.post(location.href, { action: 'complete_quiz', quiz_id: activeQuizId })
            .done(res => {
                showToast('success', 'Finished!', res.message);
                clearInterval(quizStateInterval);
                document.getElementById('question-screen').style.display = 'none';
                document.getElementById('live-quiz-badge').style.display = 'none';
                document.getElementById('idle-screen').style.display = 'block';
            });
    }

    // Update Leaderboard List
    function updateLeaderboardUI(board) {
        document.getElementById('lead-empty-state').style.setProperty('display', 'none', 'important');
        const container = document.getElementById('leaderboard-list');
        container.style.setProperty('display', 'flex', 'important');
        
        if (!board || board.length === 0) {
            container.innerHTML = '<div class="text-center py-4 small text-muted">No answers logged yet.</div>';
            return;
        }

        let html = '';
        board.forEach((b, i) => {
            const crown = (i === 0) ? '<i class="fas fa-crown text-warning me-1"></i>' : '';
            html += `
                <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-white shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center css-live-quiz-86ddee">${i + 1}</span>
                        <span class="fw-bold text-dark">${crown}${escapeHtml(b.name)}</span>
                    </div>
                    <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill">${b.score} pts</span>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
</script>

<?php include '../../include/footer.php'; ?>
