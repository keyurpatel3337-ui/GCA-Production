<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if student is logged in via final exam isolated terminal
if (!isset($_SESSION['final_student_id'])) {
    header("Location: final-exam-login.php");
    exit();
}

$student_id = $_SESSION['final_student_id'];
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify Exam Access
$sql = "SELECT e.*, 
        (SELECT id FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_id,
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE e.id = ? AND e.status IN ('Scheduled', 'Live') AND e.start_time <= NOW() AND e.end_time >= NOW() AND e.exam_mode = 'Final'";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id, $student_id, $exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Final Exam not available or expired.");
}

if ($exam['attempt_status'] === 'Submitted') {
    die("You have already submitted this exam.");
}

// Initialize Attempt if not already started
if (!$exam['attempt_id']) {
    $ins_stmt = $conn->prepare("INSERT INTO tbl_oes_student_exams (exam_id, student_id, start_timestamp, status) VALUES (?, ?, NOW(), 'Ongoing')");
    $ins_stmt->execute([$exam_id, $student_id]);
    $attempt_id = $conn->lastInsertId();
    $start_timestamp = time();
} else {
    $attempt_id = $exam['attempt_id'];
    
    // Check 30-minute inactivity grace period
    $time_sql = "SELECT start_timestamp, last_active_timestamp, TIMESTAMPDIFF(MINUTE, last_active_timestamp, NOW()) as inactive_mins FROM tbl_oes_student_exams WHERE id = ?";
    $time_stmt = $conn->prepare($time_sql);
    $time_stmt->execute([$attempt_id]);
    $attempt_info = $time_stmt->fetch();
    
    if ($attempt_info) {
        $inactive_mins = $attempt_info['inactive_mins'];
        if ($attempt_info['last_active_timestamp'] !== null && $inactive_mins >= 30) {
            // Over 30 minutes limit: auto-finalize and submit
            $sub_stmt = $conn->prepare("UPDATE tbl_oes_student_exams SET status = 'Submitted', submit_timestamp = NOW() WHERE id = ?");
            $sub_stmt->execute([$attempt_id]);
            die("<div style='font-family: sans-serif; text-align: center; margin-top: 100px;'><h1 style='color: #ef4444;'>Session Expired</h1><p style='color: #64748b;'>This exam session has been automatically finalized due to exceeding the 30-minute inactivity limit.</p><a href='final-exams.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #0d9488; color: white; text-decoration: none; border-radius: 5px;'>Back to Dashboard</a></div>");
        }
        $start_timestamp = strtotime($attempt_info['start_timestamp']);
    } else {
        $start_timestamp = time();
    }
}

// Calculate Correct Remaining Time
$elapsed_seconds = time() - $start_timestamp;
$total_duration_seconds = $exam['duration_mins'] * 60;
$remaining_seconds = $total_duration_seconds - $elapsed_seconds;

if ($remaining_seconds <= 0) {
    $sub_stmt = $conn->prepare("UPDATE tbl_oes_student_exams SET status = 'Submitted', submit_timestamp = NOW() WHERE id = ?");
    $sub_stmt->execute([$attempt_id]);
    die("<div style='font-family: sans-serif; text-align: center; margin-top: 100px;'><h1 style='color: #ef4444;'>Exam Time Over</h1><p style='color: #64748b;'>The total allowed duration for this exam has already elapsed.</p><a href='final-exams.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #0d9488; color: white; text-decoration: none; border-radius: 5px;'>Back to Dashboard</a></div>");
}

// Fetch previously saved responses
$resp_stmt = $conn->prepare("SELECT question_id, selected_option, marked_for_review FROM tbl_oes_responses WHERE student_exam_id = ?");
$resp_stmt->execute([$attempt_id]);
$saved_responses = $resp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Questions
$q_sql = "SELECT q.* FROM tbl_oes_questions q 
          JOIN tbl_oes_exam_questions eq ON q.id = eq.question_id 
          WHERE eq.exam_id = ? 
          ORDER BY eq.order_no ASC";
$q_stmt = $conn->prepare($q_sql);
$q_stmt->execute([$exam_id]);
$questions = $q_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Exam Terminal | GCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- KaTeX for math rendering -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/mhchem.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="../../assets/css/online-exam.css">
</head>
<body class="exam-body-secure">
    <!-- Offline HUD warning -->
    <div id="offline-toast" style="position: fixed; bottom: 20px; left: 20px; background: #ea580c; color: white; padding: 1rem 1.5rem; border-radius: 10px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 99999; display: none; align-items: center; gap: 12px; font-weight: 600; font-family: 'Outfit', sans-serif;">
        <i class="fas fa-wifi-slash fa-lg animate-pulse"></i>
        <span>Working Offline: Internet disconnected. Answers are saved locally.</span>
    </div>

    <!-- Proctoring Modal Overlay -->
    <div id="proctor-modal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.95); z-index: 10000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
        <div style="background: white; padding: 3rem; border-radius: 20px; max-width: 500px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="width: 80px; height: 80px; background: rgba(13, 148, 136, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; color: #0d9488;">
                <i class="fas fa-shield-alt fa-3x"></i>
            </div>
            <h2 style="font-weight: 800; color: #0f172a; font-size: 1.5rem; margin-bottom: 1rem;">Proctored Examination Mode</h2>
            <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 2rem;">
                This test requires full screen mode. Window switching, copying, and tab switching are strictly blocked and monitored.
            </p>
            <button onclick="enterProctoredMode()" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); color: white; border: none; padding: 0.9rem 2rem; border-radius: 10px; font-weight: 700; font-size: 1rem; width: 100%; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); cursor: pointer; outline: none;">
                Start Test in Fullscreen
            </button>
        </div>
    </div>

    <!-- Secure Exam Header -->
    <header class="exam-header">
        <div class="d-flex align-items-center">
            <h1 class="h5 mb-0 font-weight-bold text-dark"><?php echo htmlspecialchars($exam['title']); ?></h1>
            <span class="badge badge-secondary ml-3 py-1 px-2" style="font-size: 0.75rem;">Terminal Mode</span>
        </div>
        
        <div class="d-flex align-items-center">
            <div class="timer-box mr-4">
                <i class="far fa-clock"></i>
                <span id="timer">00:00:00</span>
            </div>
            <button onclick="confirmFinish()" class="btn btn-danger font-weight-bold px-4" style="border-radius: 8px; height: 42px;">
                Submit Exam
            </button>
        </div>
    </header>

    <!-- Main Content Row -->
    <div class="row no-gutters exam-main">
        <!-- Left: Question Body -->
        <div class="col-md-9 question-panel">
            <div class="max-w-4xl mx-auto" id="question-container">
                <div class="question-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span id="question-number" class="question-badge">Question 1</span>
                        <span id="question-marks" class="marks-badge">1.0 Marks</span>
                    </div>
                    
                    <div id="question-body" class="question-body">
                        <!-- Injected question HTML -->
                    </div>

                    <div id="options-container">
                        <!-- Injected multiple choice options -->
                    </div>
                </div>

                <!-- Navigation Controls -->
                <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap" style="gap: 10px;">
                    <button onclick="prevQuestion()" class="btn btn-outline-secondary px-4 py-2 font-weight-bold" style="border-radius: 10px;">
                        <i class="fas fa-chevron-left mr-2"></i> Previous
                    </button>
                    <div class="d-flex align-items-center" style="gap: 8px;">
                        <button onclick="clearResponse()" class="btn btn-outline-warning px-3 py-2 font-weight-bold" style="border-radius: 10px;">
                            <i class="fas fa-eraser mr-1"></i> Clear Response
                        </button>
                        <button onclick="markForReview()" class="btn btn-outline-primary px-3 py-2 font-weight-bold" style="border-radius: 10px; color: #8b5cf6; border-color: #8b5cf6;">
                            <i class="fas fa-bookmark mr-1"></i> Mark for Review
                        </button>
                    </div>
                    <button onclick="saveAndNext()" class="btn btn-teal px-5 py-2 font-weight-bold text-white" style="border-radius: 10px; background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); border: none;">
                        Save & Next <i class="fas fa-chevron-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Control Panel & Palette -->
        <div class="col-md-3 sidebar-panel">
            <div>
                <div class="palette-title">Examination Map</div>
                <div id="palette" class="palette-grid">
                    <!-- Dynamic palette numeric buttons -->
                </div>

                <div class="status-legends">
                    <div class="legend-item">
                        <div class="legend-color bg-success"></div>
                        <span>Answered</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #8b5cf6; border: 1px solid #7c3aed;"></div>
                        <span>Marked for Review</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color rgba-teal" style="background: rgba(13, 148, 136, 0.08); border: 1px solid #0d9488;"></div>
                        <span>Active</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f8fafc; border: 1px solid #cbd5e1;"></div>
                        <span>Not Visited</span>
                    </div>
                </div>
            </div>
            
            <div class="text-center text-muted small mt-4 pt-3" style="border-top: 1px solid #e2e8f0;">
                <i class="fas fa-user-shield text-success mr-1"></i> Lockout Proctored Session
            </div>
        </div>
    </div>

    <script>
        const questions = <?php echo json_encode($questions); ?>;
        const attemptId = <?php echo $attempt_id; ?>;
        let currentIdx = 0;
        
        // Pre-populate with server-saved responses
        const savedResponses = <?php echo json_encode($saved_responses); ?>;
        let responses = {};
        savedResponses.forEach(r => {
            responses[r.question_id] = { 
                selected: r.selected_option, 
                marked_for_review: parseInt(r.marked_for_review) === 1 
            };
        });

        // Merge with any newer localStorage offline cached responses
        const localBackup = localStorage.getItem(`oes_attempt_${attemptId}`);
        if (localBackup) {
            try {
                const parsed = JSON.parse(localBackup);
                if (parsed && parsed.answers) {
                    responses = { ...responses, ...parsed.answers };
                }
            } catch(e) {}
        }

        // Proctoring & Timer Configuration
        let duration = <?php echo $remaining_seconds; ?>;
        const timerDisplay = document.getElementById('timer');
        let countdown; // Deferred until fullscreen triggers
        let tabSwitchCount = 0;
        const MAX_TAB_SWITCHES = 3;
        let lastViolationTime = 0;
        let isSubmitting = false;

        let pendingSyncQueue = JSON.parse(localStorage.getItem(`oes_pending_sync_${attemptId}`)) || [];

        function enterProctoredMode() {
            const elem = document.documentElement;
            const requestMethod = elem.requestFullscreen || 
                                  elem.webkitRequestFullscreen || 
                                  elem.mozRequestFullScreen || 
                                  elem.msRequestFullscreen;

            if (requestMethod) {
                try {
                    const promise = requestMethod.call(elem);
                    if (promise !== undefined && typeof promise.then === 'function') {
                        promise.then(() => {
                            document.getElementById('proctor-modal').style.display = 'none';
                            initProctoredListeners();
                            startCountdown();
                            startHeartbeat();
                            handleNetworkChange();
                        }).catch(err => {
                            document.getElementById('proctor-modal').style.display = 'none';
                            initProctoredListeners();
                            startCountdown();
                            startHeartbeat();
                            handleNetworkChange();
                        });
                    } else {
                        document.getElementById('proctor-modal').style.display = 'none';
                        initProctoredListeners();
                        startCountdown();
                        startHeartbeat();
                        handleNetworkChange();
                    }
                } catch (e) {
                    document.getElementById('proctor-modal').style.display = 'none';
                    initProctoredListeners();
                    startCountdown();
                    startHeartbeat();
                    handleNetworkChange();
                }
            } else {
                document.getElementById('proctor-modal').style.display = 'none';
                initProctoredListeners();
                startCountdown();
                startHeartbeat();
                handleNetworkChange();
            }
        }

        function startCountdown() {
            if (countdown) clearInterval(countdown);
            countdown = setInterval(() => {
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
        }

        function startHeartbeat() {
            setInterval(() => {
                if (navigator.onLine) {
                    fetch('ajax/heartbeat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ attempt_id: attemptId })
                    }).catch(e => {});
                }
            }, 10000);
        }

        function logViolation(message) {
            if (isSubmitting) return;
            const now = Date.now();
            if (now - lastViolationTime < 1500) return; // Debounce repeated rapid events
            lastViolationTime = now;

            tabSwitchCount++;
            const remaining = MAX_TAB_SWITCHES - tabSwitchCount;
            if (remaining <= 0) {
                isSubmitting = true;
                Swal.fire({
                    icon: 'error',
                    title: 'Exam Terminated',
                    text: 'CRITICAL SECURITY VIOLATION: Maximum window/tab switching limit exceeded! Your exam is being submitted automatically.',
                    confirmButtonColor: '#ef4444',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    submitExam();
                });
            } else {
                isSubmitting = true;
                Swal.fire({
                    icon: 'warning',
                    title: 'Security Warning',
                    html: `${message}<br><br><span style="color: #ef4444; font-weight: bold; font-size: 1.1rem;">Warnings remaining: ${remaining}</span>`,
                    confirmButtonColor: '#0d9488',
                    confirmButtonText: 'I Understand',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    isSubmitting = false;
                    forceFullscreen();
                });
            }
        }

        function initProctoredListeners() {
            // 1. Detect tab switching (visibilitychange)
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    logViolation("WARNING: Tab switching is strictly prohibited! Switching tabs will auto-submit your exam.");
                }
            });

            // 2. Detect window blur (window focus loss)
            window.addEventListener('blur', () => {
                logViolation("WARNING: Window focus loss detected! Switching windows or minimizing is strictly prohibited.");
            });

            // 3. Detect when student exits fullscreen
            const fsEvents = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];
            fsEvents.forEach(evt => {
                document.addEventListener(evt, () => {
                    const fsElement = document.fullscreenElement || 
                                      document.webkitFullscreenElement || 
                                      document.mozFullScreenElement || 
                                      document.msFullscreenElement;
                    if (!fsElement) {
                        logViolation("WARNING: Fullscreen mode exited! Taking the exam in windowed mode is prohibited.");
                    }
                });
            });

            // 4. Prevent keyboard shortcuts (developer tools, copies, new tabs, etc.)
            window.addEventListener('keydown', (e) => {
                if (
                    e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C' || e.key === 'T' || e.key === 't')) ||
                    (e.ctrlKey && (e.key === 'u' || e.key === 'U' || e.key === 'c' || e.key === 'C' || e.key === 'v' || e.key === 'V' || e.key === 'a' || e.key === 'A' || e.key === 's' || e.key === 'S' || e.key === 't' || e.key === 'T' || e.key === 'n' || e.key === 'N' || e.key === 'w' || e.key === 'W' || e.key === 'f4' || e.key === 'F4')) ||
                    e.key === 'Meta' || e.key === 'Alt'
                ) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Shortcut Deactivated',
                        text: 'Security Alert: Direct tab/window shortcuts, right-clicks, and developer tools are deactivated under Proctored Mode!',
                        confirmButtonColor: '#0d9488'
                    });
                    return false;
                }
            });

            // 5. Block middle-click and multi-key click to prevent opening links in new tabs/windows
            window.addEventListener('auxclick', (e) => {
                if (e.button === 1) { // Middle click
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Action Prohibited',
                        text: 'Security Alert: Middle-clicking to open items in a new tab is disabled!',
                        confirmButtonColor: '#0d9488'
                    });
                }
            });

            window.addEventListener('click', (e) => {
                if (e.ctrlKey || e.shiftKey) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Action Prohibited',
                        text: 'Security Alert: Ctrl/Shift clicks to open items in a new tab are disabled!',
                        confirmButtonColor: '#0d9488'
                    });
                }
            });

            // 5. Prevent right-click context menu
            document.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Right-Click Disabled',
                    text: 'Security Alert: Right-click is disabled in proctored mode!',
                    confirmButtonColor: '#0d9488'
                });
            });
        }

        function forceFullscreen() {
            const elem = document.documentElement;
            const fsElement = document.fullscreenElement || 
                              document.webkitFullscreenElement || 
                              document.mozFullScreenElement || 
                              document.msFullscreenElement;

            if (!fsElement) {
                const requestMethod = elem.requestFullscreen || 
                                      elem.webkitRequestFullscreen || 
                                      elem.mozRequestFullScreen || 
                                      elem.msRequestFullscreen;
                if (requestMethod) {
                    try {
                        requestMethod.call(elem).catch(() => {});
                    } catch (e) {}
                }
            }
        }

        function loadQuestion(idx) {
            currentIdx = idx;
            const q = questions[idx];
            
            document.getElementById('question-number').textContent = `Question ${idx + 1}`;
            document.getElementById('question-marks').textContent = `${q.marks} Marks`;
            document.getElementById('question-body').innerHTML = q.question_text;
            
            const options = ['A', 'B', 'C', 'D'];
            const container = document.getElementById('options-container');
            container.innerHTML = `<div class="row">` + options.map(opt => `
                <div class="col-md-6 mb-3">
                    <div onclick="selectOption('${opt}')" id="opt-${opt}" class="option-item ${responses[q.id]?.selected == opt ? 'selected' : ''}" style="cursor: pointer; margin-bottom: 0;">
                        <div class="option-letter">${opt}</div>
                        <div class="option-text">${q['option_' + opt.toLowerCase()]}</div>
                    </div>
                </div>
            `).join('') + `</div>`;

            updatePalette();
            
            // Render Math equations
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(document.getElementById('question-container'), {
                    delimiters: [
                        {left: '$$', right: '$$', display: false},
                        {left: '$', right: '$', display: false},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '\\[', right: '\\]', display: false}
                    ],
                    throwOnError: false
                });
            }


            // Quill formulas specifically
            document.querySelectorAll('.ql-formula').forEach(function(el) {
                const formula = el.getAttribute('data-value');
                if (formula && typeof katex !== 'undefined') {
                    try {
                        katex.render(formula, el, {
                            throwOnError: false,
                            displayMode: false
                        });
                    } catch (e) { console.error("KaTeX rendering error:", e); }
                }
            });
        }

        async function selectOption(opt) {
            const qId = questions[currentIdx].id;
            responses[qId] = responses[qId] || {};
            responses[qId].selected = opt;
            
            document.querySelectorAll('.option-item').forEach(el => el.classList.remove('selected'));
            document.getElementById(`opt-${opt}`).classList.add('selected');

            // 1. Cache to local storage immediately
            localStorage.setItem(`oes_attempt_${attemptId}`, JSON.stringify({ answers: responses }));

            // 2. Sync to server
            const isMarked = !!responses[qId].marked_for_review;
            if (navigator.onLine) {
                try {
                    await syncResponse(qId, opt, isMarked);
                } catch (err) {
                    queueOfflineSync(qId, opt, isMarked);
                }
            } else {
                queueOfflineSync(qId, opt, isMarked);
            }
            updatePalette();
        }

        function queueOfflineSync(qId, opt, markedForReview = false) {
            pendingSyncQueue = pendingSyncQueue.filter(item => item.qId !== qId);
            pendingSyncQueue.push({ qId, opt, markedForReview });
            localStorage.setItem(`oes_pending_sync_${attemptId}`, JSON.stringify(pendingSyncQueue));
        }

        async function flushOfflineQueue() {
            if (pendingSyncQueue.length === 0) return;
            const itemsToSync = [...pendingSyncQueue];
            for (const item of itemsToSync) {
                try {
                    await syncResponse(item.qId, item.opt, item.markedForReview);
                    pendingSyncQueue = pendingSyncQueue.filter(qItem => qItem.qId !== item.qId);
                    localStorage.setItem(`oes_pending_sync_${attemptId}`, JSON.stringify(pendingSyncQueue));
                } catch (e) {
                    break;
                }
            }
        }

        function handleNetworkChange() {
            const toast = document.getElementById('offline-toast');
            if (toast) {
                if (navigator.onLine) {
                    toast.style.display = 'none';
                    flushOfflineQueue();
                } else {
                    toast.style.display = 'flex';
                }
            }
        }
        window.addEventListener('online', handleNetworkChange);
        window.addEventListener('offline', handleNetworkChange);

        async function saveAndNext() {
            const q = questions[currentIdx];
            const response = responses[q.id];
            
            if (response && response.selected) {
                const isMarked = !!response.marked_for_review;
                await syncResponse(q.id, response.selected, isMarked);
            }

            if (currentIdx < questions.length - 1) {
                loadQuestion(currentIdx + 1);
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Last Question Reached',
                    text: 'You have reached the final question. Review your choices or click Submit Exam.',
                    confirmButtonColor: '#0d9488'
                });
            }
        }

        async function clearResponse() {
            const qId = questions[currentIdx].id;
            
            if (responses[qId]) {
                responses[qId].selected = null;
                responses[qId].marked_for_review = false;
            }

            // Remove option selections in UI
            document.querySelectorAll('.option-item').forEach(el => el.classList.remove('selected'));

            // Cache to local storage immediately
            localStorage.setItem(`oes_attempt_${attemptId}`, JSON.stringify({ answers: responses }));

            // Sync to server
            if (navigator.onLine) {
                try {
                    await syncResponse(qId, null, false);
                } catch (err) {
                    queueOfflineSync(qId, null, false);
                }
            } else {
                queueOfflineSync(qId, null, false);
            }

            updatePalette();

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'info',
                title: 'Response cleared successfully'
            });
        }

        async function markForReview() {
            const qId = questions[currentIdx].id;
            responses[qId] = responses[qId] || {};
            responses[qId].marked_for_review = true;

            // Cache to local storage immediately
            localStorage.setItem(`oes_attempt_${attemptId}`, JSON.stringify({ answers: responses }));

            const opt = responses[qId].selected || null;

            // Sync to server
            if (navigator.onLine) {
                try {
                    await syncResponse(qId, opt, true);
                } catch (err) {
                    queueOfflineSync(qId, opt, true);
                }
            } else {
                queueOfflineSync(qId, opt, true);
            }

            updatePalette();

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Marked for Review successfully'
            });
        }

        async function syncResponse(qId, opt, markedForReview = false) {
            try {
                await fetch('ajax/save-response-final.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        attempt_id: attemptId,
                        question_id: qId,
                        selected_option: opt,
                        marked_for_review: markedForReview ? 1 : 0
                    })
                });
            } catch (error) { throw error; }
        }

        function updatePalette() {
            const palette = document.getElementById('palette');
            palette.innerHTML = questions.map((q, i) => {
                let statusClass = '';
                if (i === currentIdx) statusClass = 'active';
                else if (responses[q.id]?.marked_for_review) statusClass = 'marked';
                else if (responses[q.id]?.selected) statusClass = 'answered';
                
                return `<button onclick="loadQuestion(${i})" class="palette-btn ${statusClass}">${i + 1}</button>`;
            }).join('');
        }

        function prevQuestion() {
            if (currentIdx > 0) loadQuestion(currentIdx - 1);
        }

        function confirmFinish() {
            isSubmitting = true;
            Swal.fire({
                title: 'Finalize & Submit?',
                text: 'Are you absolutely sure you want to finalize and submit your exam? You cannot modify your answers after this.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d9488',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Submit Paper',
                cancelButtonText: 'Cancel',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    submitExam();
                } else {
                    isSubmitting = false;
                }
            });
        }

        async function submitExam() {
            clearInterval(countdown);
            await fetch('ajax/submit-exam-final.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attempt_id: attemptId })
            });
            localStorage.removeItem(`oes_attempt_${attemptId}`);
            localStorage.removeItem(`oes_pending_sync_${attemptId}`);
            window.location.href = 'final-exams.php';
        }

        function autoSubmit() {
            Swal.fire({
                icon: 'warning',
                title: 'Time Is Up!',
                text: 'Time is up! Your answers are being submitted automatically.',
                confirmButtonColor: '#0d9488',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                submitExam();
            });
        }

        // Initialize first question
        loadQuestion(0);
    </script>
</body>
</html>
