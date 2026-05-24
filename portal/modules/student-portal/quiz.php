<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to logged-in students
if (!hasAnyRole([ROLE_STUDENT])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = (int)$_SESSION['user_id'];

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'join_quiz') {
            $code = strtoupper(trim($_POST['quiz_code'] ?? ''));
            
            $stmt = $conn->prepare("SELECT * FROM tbl_live_quizzes WHERE quiz_code = ? AND status != 'Completed'");
            $stmt->execute([$code]);
            $quiz = $stmt->fetch();

            if (!$quiz) {
                echo json_encode(['success' => false, 'message' => 'Invalid or completed Quiz PIN Code.']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Lobby Joined!', 'quiz_id' => $quiz['id']]);
            exit;
        }

        if ($action === 'submit_response') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $question_id = intval($_POST['question_id'] ?? 0);
            $selected = trim($_POST['selected_option'] ?? '');
            $time_ms = intval($_POST['response_time_ms'] ?? 0);

            if ($quiz_id <= 0 || $question_id <= 0 || empty($selected)) {
                echo json_encode(['success' => false, 'message' => 'Incomplete submission parameters.']);
                exit;
            }

            // Verify correct answer
            $stmt = $conn->prepare("SELECT correct_option FROM tbl_live_quiz_questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $correct = $stmt->fetchColumn();

            $is_correct = ($selected === $correct) ? 1 : 0;
            
            // Calculate points: Kahoot-style speed score (Max 1000, drops linearly over 30s)
            $points = 0;
            if ($is_correct) {
                $max_time_ms = 30000;
                $points = max(500, ceil(1000 - (($time_ms / $max_time_ms) * 500)));
            }

            $stmt_ins = $conn->prepare("
                INSERT INTO tbl_live_quiz_responses (quiz_id, question_id, student_id, selected_option, response_time_ms, is_correct, points) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE selected_option = ?, response_time_ms = ?, is_correct = ?, points = ?
            ");
            $stmt_ins->execute([
                $quiz_id, $question_id, $student_id, $selected, $time_ms, $is_correct, $points,
                $selected, $time_ms, $is_correct, $points
            ]);

            echo json_encode(['success' => true, 'message' => 'Response logged!', 'is_correct' => $is_correct, 'points' => $points]);
            exit;
        }

        if ($action === 'poll_quiz') {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);

            $stmt = $conn->prepare("SELECT * FROM tbl_live_quizzes WHERE id = ?");
            $stmt->execute([$quiz_id]);
            $quiz = $stmt->fetch();

            if (!$quiz) {
                echo json_encode(['success' => false, 'message' => 'Quiz not found.']);
                exit;
            }

            $question = null;
            $has_responded = false;
            $points_earned = 0;
            $selected_opt = null;

            if ($quiz['status'] === 'Active' || $quiz['status'] === 'Revealed') {
                $quest_stmt = $conn->prepare("SELECT * FROM tbl_live_quiz_questions WHERE quiz_id = ? AND order_no = ?");
                $quest_stmt->execute([$quiz_id, $quiz['current_question_index']]);
                $question = $quest_stmt->fetch();

                if ($question) {
                    // Check if student has responded
                    $resp_stmt = $conn->prepare("SELECT selected_option, points FROM tbl_live_quiz_responses WHERE quiz_id = ? AND question_id = ? AND student_id = ?");
                    $resp_stmt->execute([$quiz_id, $question['id'], $student_id]);
                    $resp = $resp_stmt->fetch();
                    if ($resp) {
                        $has_responded = true;
                        $points_earned = (int)$resp['points'];
                        $selected_opt = $resp['selected_option'];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'status' => $quiz['status'],
                'current_question_index' => $quiz['current_question_index'],
                'question' => $question,
                'has_responded' => $has_responded,
                'points_earned' => $points_earned,
                'selected_option' => $selected_opt
            ]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$page_title = 'Join Live Lecture Quiz';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<style>
    .join-container {
        max-width: 450px;
        margin: 60px auto;
    }
    .pin-input {
        letter-spacing: 0.2em;
        font-size: 2rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
        border-radius: 12px;
        border: 2px solid #ced4da;
    }
    .pin-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 10px rgba(13,110,253,0.15);
    }
    .btn-join {
        border-radius: 50px;
        padding: 12px 25px;
        font-weight: 700;
    }
    .quiz-option-btn {
        min-height: 90px;
        font-size: 1.25rem;
        font-weight: 700;
        border-radius: 12px;
        transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .quiz-option-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .quiz-option-btn:active {
        transform: scale(0.98);
    }
    .btn-opt-a { background: #0d6efd; color: #ffffff; }
    .btn-opt-b { background: #198754; color: #ffffff; }
    .btn-opt-c { background: #ffc107; color: #000000; }
    .btn-opt-d { background: #dc3545; color: #ffffff; }
</style>

<div class="container-fluid py-4">
    <!-- 1. PIN JOIN BOX -->
    <div class="join-container" id="join-box">
        <div class="card shadow border-0 rounded-3 text-center p-4">
            <i class="fas fa-gamepad fa-4x text-primary mb-3"></i>
            <h4 class="fw-extrabold text-dark mb-1">Enter Live Quiz PIN</h4>
            <p class="text-muted small mb-4">Type the code displayed on your lecturer's presentation board.</p>
            
            <form id="joinForm">
                <div class="mb-4">
                    <input type="text" name="quiz_code" id="quiz_code" class="form-control pin-input shadow-sm" maxlength="6" autocomplete="off" placeholder="------" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-join fs-5 shadow-sm">
                    <i class="fas fa-sign-in-alt me-1"></i> Enter Arena
                </button>
            </form>
        </div>
    </div>

    <!-- 2. ACTIVE ARENA SCREEN -->
    <div class="card shadow border-0 rounded-3 max-w-2xl mx-auto p-4" id="active-arena" style="display: none; max-width: 600px;">
        <!-- Lobby State -->
        <div class="text-center py-5" id="arena-lobby">
            <i class="fas fa-circle-notch fa-spin fa-4x text-success mb-3 d-block"></i>
            <h4 class="fw-bold text-dark">You are in!</h4>
            <p class="text-muted">Waiting in the lobby for the teacher to launch the quiz questions...</p>
        </div>

        <!-- Question Answering State -->
        <div id="arena-question" style="display: none;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <span class="badge bg-secondary px-3 py-2 rounded-pill fs-6" id="q-counter">Question --</span>
                <span class="fw-bold text-muted small" id="live-timer-tracker">Answering...</span>
            </div>

            <h4 class="fw-bold text-dark text-center mb-4" id="q-text">Loading question...</h4>

            <div class="row g-3" id="options-btn-container">
                <div class="col-6">
                    <button class="btn btn-opt-a w-100 quiz-option-btn" onclick="submitOption('A')">A</button>
                </div>
                <div class="col-6">
                    <button class="btn btn-opt-b w-100 quiz-option-btn" onclick="submitOption('B')">B</button>
                </div>
                <div class="col-6">
                    <button class="btn btn-opt-c w-100 quiz-option-btn" onclick="submitOption('C')">C</button>
                </div>
                <div class="col-6">
                    <button class="btn btn-opt-d w-100 quiz-option-btn" onclick="submitOption('D')">D</button>
                </div>
            </div>

            <!-- Block overlay when already submitted -->
            <div class="text-center py-5 rounded-3 bg-light border" id="submitted-overlay" style="display: none;">
                <i class="fas fa-check-double fa-3x text-success mb-2 d-block"></i>
                <h5 class="fw-bold text-dark mb-1">Response Received!</h5>
                <p class="text-muted small mb-0">Waiting for other players and correct answer reveal...</p>
            </div>
        </div>

        <!-- Result / Scoring Revealed State -->
        <div class="text-center py-5" id="arena-result" style="display: none;">
            <div id="result-icon-box">
                <i class="fas fa-check-circle fa-5x text-success mb-3 d-block"></i>
            </div>
            <h3 class="fw-bold text-dark" id="result-text">CORRECT!</h3>
            
            <div class="my-4 py-3 bg-light rounded-3 border">
                <h1 class="fw-extrabold text-primary mb-0" id="result-points">+0</h1>
                <p class="text-muted small mb-0">Points Earned</p>
            </div>
            
            <p class="text-muted small mb-0" id="result-status-msg">Waiting for the teacher to show the next slide...</p>
        </div>
    </div>
</div>

<script>
    let activeQuizId = null;
    let activeQuestionId = null;
    let pollInterval = null;
    let startSlideTime = null;

    // Handle join submit
    $('#joinForm').on('submit', function (e) {
        e.preventDefault();
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Joining...');

        $.post(location.href, $(this).serialize() + '&action=join_quiz')
            .then(res => {
                submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-1"></i> Enter Arena');
                if (res.success) {
                    activeQuizId = res.quiz_id;
                    document.getElementById('join-box').style.display = 'none';
                    document.getElementById('active-arena').style.display = 'block';
                    startLobbyPolling();
                } else {
                    showToast('error', 'Error PIN!', res.message);
                }
            })
            .catch(() => {
                submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-1"></i> Enter Arena');
                showToast('error', 'Error', 'Failed to connect.');
            });
    });

    // Lobby / Game-state Polling
    let currentSlide = 0;
    let slideStatus = '';

    function startLobbyPolling() {
        if (pollInterval) clearInterval(pollInterval);

        pollInterval = setInterval(() => {
            $.post(location.href, { action: 'poll_quiz', quiz_id: activeQuizId })
                .done(res => {
                    if (res.success) {
                        const status = res.status;
                        const idx = res.current_question_index;
                        const q = res.question;

                        if (status === 'Waiting') {
                            document.getElementById('arena-lobby').style.display = 'block';
                            document.getElementById('arena-question').style.display = 'none';
                            document.getElementById('arena-result').style.display = 'none';
                        }
                        else if (status === 'Active') {
                            document.getElementById('arena-lobby').style.display = 'none';
                            document.getElementById('arena-result').style.display = 'none';
                            
                            // Check if loaded new slide
                            if (currentSlide !== idx || slideStatus !== 'Active') {
                                currentSlide = idx;
                                slideStatus = 'Active';
                                activeQuestionId = q.id;
                                startSlideTime = new Date().getTime(); // Mark timestamp for time score!
                                
                                document.getElementById('arena-question').style.display = 'block';
                                document.getElementById('options-btn-container').style.display = 'flex';
                                document.getElementById('submitted-overlay').style.display = 'none';
                                
                                document.getElementById('q-counter').textContent = `Question ${idx}`;
                                document.getElementById('q-text').textContent = q.question_text;
                            }

                            // If already submitted in active slide
                            if (res.has_responded) {
                                document.getElementById('options-btn-container').style.display = 'none';
                                document.getElementById('submitted-overlay').style.display = 'block';
                            }
                        }
                        else if (status === 'Revealed') {
                            if (slideStatus !== 'Revealed') {
                                slideStatus = 'Revealed';
                                document.getElementById('arena-question').style.display = 'none';
                                document.getElementById('arena-result').style.display = 'block';

                                const earn = res.points_earned;
                                const is_corr = (earn > 0);

                                const iconBox = document.getElementById('result-icon-box');
                                const textBox = document.getElementById('result-text');
                                const ptsBox = document.getElementById('result-points');

                                if (is_corr) {
                                    iconBox.innerHTML = '<i class="fas fa-check-circle fa-5x text-success mb-3 d-block animate__animated animate__bounceIn"></i>';
                                    textBox.className = 'fw-bold text-success';
                                    textBox.textContent = 'CORRECT!';
                                    ptsBox.textContent = `+${earn}`;
                                } else {
                                    iconBox.innerHTML = '<i class="fas fa-times-circle fa-5x text-danger mb-3 d-block animate__animated animate__shakeX"></i>';
                                    textBox.className = 'fw-bold text-danger';
                                    textBox.textContent = 'INCORRECT';
                                    ptsBox.textContent = '+0';
                                }
                            }
                        }
                        else if (status === 'Completed') {
                            clearInterval(pollInterval);
                            showToast('info', 'Done!', 'Quiz session completed.');
                            setTimeout(() => location.reload(), 2000);
                        }
                    }
                });
        }, 1200);
    }

    // Submit player option tap
    function submitOption(opt) {
        const timeSpent = new Date().getTime() - startSlideTime;
        
        // Block actions visually
        document.getElementById('options-btn-container').style.display = 'none';
        document.getElementById('submitted-overlay').style.display = 'block';

        $.post(location.href, {
            action: 'submit_response',
            quiz_id: activeQuizId,
            question_id: activeQuestionId,
            selected_option: opt,
            response_time_ms: timeSpent
        }).done(res => {
            if (res.success) {
                showToast('success', 'Logged!', res.message);
            } else {
                showToast('error', 'Error!', res.message);
                document.getElementById('options-btn-container').style.display = 'flex';
                document.getElementById('submitted-overlay').style.display = 'none';
            }
        });
    }
</script>

<?php include '../../include/footer.php'; ?>
