<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Restrict strictly to Super Admin and Principal
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Fetch Attempt Details
$att_stmt = $conn->prepare("SELECT se.*, s.student_name, s.surname, s.fathers_name, s.gr_no, e.title as exam_title, e.total_marks as exam_total_marks, e.exam_mode
                            FROM tbl_oes_student_exams se
                            JOIN tbl_gm_std_registration s ON se.student_id = s.id
                            JOIN tbl_oes_exams e ON se.exam_id = e.id
                            WHERE se.id = ?");
$att_stmt->execute([$attempt_id]);
$attempt = $att_stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die("<div style='font-family: sans-serif; text-align: center; margin-top: 100px;'><h1 style='color: #ef4444;'>Attempt Not Found</h1><p>The specified student attempt does not exist.</p><a href='manage-exams.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px;'>Back to Manage Exams</a></div>");
}

$student_name = trim(($attempt['surname'] ?? '') . ' ' . ($attempt['student_name'] ?? '') . ' ' . ($attempt['fathers_name'] ?? ''));
$page_title = "Response Sheet - " . htmlspecialchars($student_name) . " | OES";

// Fetch Questions
$q_stmt = $conn->prepare("SELECT q.*, eq.order_no 
                          FROM tbl_oes_questions q 
                          JOIN tbl_oes_exam_questions eq ON q.id = eq.question_id 
                          WHERE eq.exam_id = ? 
                          ORDER BY eq.order_no ASC");
$q_stmt->execute([$attempt['exam_id']]);
$questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Student Responses
$r_stmt = $conn->prepare("SELECT * FROM tbl_oes_responses WHERE student_exam_id = ?");
$r_stmt->execute([$attempt_id]);
$responses = [];
while ($row = $r_stmt->fetch(PDO::FETCH_ASSOC)) {
    $responses[$row['question_id']] = $row;
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<!-- KaTeX Style and Script (Synchronous to ensure equations render instantly) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>

<link rel="stylesheet" href="../../assets/css/online-exam.css">


<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <!-- Result Overview Card -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center flex-wrap" style="gap: 15px;">
                    <div>
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-invoice mr-2 text-primary"></i> Detailed Response Sheet</h5>
                        <p class="text-muted small mb-0 mt-1">Student: <b><?= htmlspecialchars($attempt['surname'] . ' ' . $attempt['student_name'] . ' ' . $attempt['fathers_name']) ?></b> (Reg No: <?= htmlspecialchars($attempt['gr_no'] ?: 'N/A') ?>)</p>
                    </div>
                    <a href="view-student-attempts.php?exam_id=<?= $attempt['exam_id'] ?>" class="btn btn-outline-secondary btn-sm px-3" style="border-radius: 10px; font-weight: 600;">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Attempt List
                    </a>
                </div>
                <div class="card-body p-4">
                    <div class="row text-center align-items-center">
                        <div class="col-md-3 border-right">
                            <div class="text-muted small font-weight-bold uppercase">EXAM TITLE</div>
                            <h5 class="font-weight-bold text-dark mt-2 mb-0"><?= htmlspecialchars($attempt['exam_title']) ?></h5>
                        </div>
                        <div class="col-md-3 border-right">
                            <div class="text-muted small font-weight-bold uppercase">OBTAINED SCORE</div>
                            <div class="h3 font-weight-bold text-primary mt-1 mb-0"><?= $attempt['total_score'] ?> <span class="small text-muted" style="font-size: 1rem;">/ <?= $attempt['exam_total_marks'] ?></span></div>
                        </div>
                        <div class="col-md-3 border-right">
                            <div class="text-muted small font-weight-bold uppercase">ACCURACY INDEX</div>
                            <div class="mt-2">
                                <span class="badge bg-success py-2 px-3 rounded-pill" title="Correct"><i class="fas fa-check-circle"></i> <?= $attempt['correct_answers'] ?> Correct</span>
                                <span class="badge bg-danger py-2 px-3 rounded-pill ml-1" title="Wrong"><i class="fas fa-times-circle"></i> <?= $attempt['wrong_answers'] ?> Wrong</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small font-weight-bold uppercase">SUBMISSION STATUS</div>
                            <div class="mt-2">
                                <?php if($attempt['status'] === 'Submitted'): ?>
                                    <span class="badge bg-success py-2 px-3 rounded-pill"><i class="fas fa-check-circle"></i> Submitted</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark py-2 px-3 rounded-pill"><i class="fas fa-spinner fa-spin"></i> Ongoing</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Loop -->
            <div class="row">
                <div class="col-lg-12">
                    <h5 class="mb-3 font-weight-bold text-dark"><i class="fas fa-list-ol mr-2 text-primary"></i> Detailed Question Breakdown</h5>
                    
                    <?php $q_num = 1; foreach ($questions as $q): ?>
                        <?php 
                        $response = $responses[$q['id']] ?? null;
                        $selected_option = $response ? $response['selected_option'] : null;
                        $correct_option = $q['correct_option'];
                        
                        $is_correct = ($selected_option === $correct_option);
                        $is_skipped = ($selected_option === null || $selected_option === '');
                        ?>
                        
                        <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
                            <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-dark border px-3 py-2 font-weight-bold" style="border-radius: 8px;">Question <?= $q_num++ ?></span>
                                <div class="text-end">
                                    <?php if ($is_skipped): ?>
                                        <span class="badge bg-secondary py-2 px-3 rounded-pill"><i class="fas fa-exclamation-triangle mr-1"></i> Skipped / Not Answered</span>
                                    <?php elseif ($is_correct): ?>
                                        <span class="badge bg-success py-2 px-3 rounded-pill"><i class="fas fa-check mr-1"></i> Correct Choice (+<?= $q['marks'] ?> Marks)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger py-2 px-3 rounded-pill"><i class="fas fa-times mr-1"></i> Incorrect Choice (-<?= $q['negative_marks'] ?> Marks)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body px-4 pb-4 pt-3">
                                <!-- Question Text -->
                                <div class="h5 text-dark font-weight-bold mb-4" style="line-height: 1.6;">
                                    <?= $q['question_text'] ?>
                                </div>

                                <!-- Choices -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <?php 
                                        $class_a = '';
                                        if ($correct_option === 'A') $class_a = 'choice-correct';
                                        if ($selected_option === 'A' && !$is_correct) $class_a = 'choice-wrong';
                                        if ($selected_option === 'A' && $is_correct) $class_a = 'choice-selected-correct';
                                        ?>
                                        <div class="choice-card <?= $class_a ?>">
                                            <span class="choice-prefix">A</span>
                                            <div><?= $q['option_a'] ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php 
                                        $class_b = '';
                                        if ($correct_option === 'B') $class_b = 'choice-correct';
                                        if ($selected_option === 'B' && !$is_correct) $class_b = 'choice-wrong';
                                        if ($selected_option === 'B' && $is_correct) $class_b = 'choice-selected-correct';
                                        ?>
                                        <div class="choice-card <?= $class_b ?>">
                                            <span class="choice-prefix">B</span>
                                            <div><?= $q['option_b'] ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php 
                                        $class_c = '';
                                        if ($correct_option === 'C') $class_c = 'choice-correct';
                                        if ($selected_option === 'C' && !$is_correct) $class_c = 'choice-wrong';
                                        if ($selected_option === 'C' && $is_correct) $class_c = 'choice-selected-correct';
                                        ?>
                                        <div class="choice-card <?= $class_c ?>">
                                            <span class="choice-prefix">C</span>
                                            <div><?= $q['option_c'] ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php 
                                        $class_d = '';
                                        if ($correct_option === 'D') $class_d = 'choice-correct';
                                        if ($selected_option === 'D' && !$is_correct) $class_d = 'choice-wrong';
                                        if ($selected_option === 'D' && $is_correct) $class_d = 'choice-selected-correct';
                                        ?>
                                        <div class="choice-card <?= $class_d ?>">
                                            <span class="choice-prefix">D</span>
                                            <div><?= $q['option_d'] ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        renderMathInElement(document.body, {
            delimiters: [
                {left: "$$", right: "$$", display: false},
                {left: "$", right: "$", display: false},
                {left: "\\(", right: "\\)", display: false},
                {left: "\\[", right: "\\]", display: false}
            ],
            throwOnError : false
        });
    });
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
