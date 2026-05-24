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

$page_title = "Generate Exam | OES";
$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM tbl_oes_exam_templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("Template not found.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $duration = (int)$_POST['duration_mins'];
    $shuffle_q = isset($_POST['shuffle_questions']) ? 1 : 0;
    $shuffle_o = isset($_POST['shuffle_options']) ? 1 : 0;
    $show_res = isset($_POST['display_result_immediately']) ? 1 : 0;
    
    try {
        $conn->beginTransaction();

        // 1. Fetch rules
        $stmt_rules = $conn->prepare("SELECT * FROM tbl_oes_template_rules WHERE template_id = ?");
        $stmt_rules->execute([$template_id]);
        $rules = $stmt_rules->fetchAll(PDO::FETCH_ASSOC);

        $selected_question_ids = [];

        // 2. Select Questions based on rules
        foreach ($rules as $i => $rule) {
            $query = "SELECT id FROM tbl_oes_questions WHERE status = 1 AND standard_id = ? AND subject_id = ?";
            $params = [$template['standard_id'], $rule['subject_id']];

            if ($rule['chapter_id'] > 0) {
                $query .= " AND chapter_id = ?";
                $params[] = $rule['chapter_id'];
            }
            if ($rule['difficulty'] !== 'any') {
                $query .= " AND difficulty = ?";
                $params[] = $rule['difficulty'];
            }

            // Exclude already selected questions
            if (!empty($selected_question_ids)) {
                $placeholders = str_repeat('?,', count($selected_question_ids) - 1) . '?';
                $query .= " AND id NOT IN ($placeholders)";
                $params = array_merge($params, $selected_question_ids);
            }

            $query .= " ORDER BY RAND() LIMIT " . (int)$rule['num_questions'];

            $stmt_q = $conn->prepare($query);
            $stmt_q->execute($params);
            $found_ids = $stmt_q->fetchAll(PDO::FETCH_COLUMN);

            if (count($found_ids) < $rule['num_questions']) {
                throw new Exception("Not enough questions in the bank for Rule #" . ($i+1) . ". Required: {$rule['num_questions']}, Found: " . count($found_ids));
            }

            $selected_question_ids = array_merge($selected_question_ids, $found_ids);
        }

        // 3. Create Exam
        $stmt_exam = $conn->prepare("INSERT INTO tbl_oes_exams (title, description, standard_id, division_id, start_time, end_time, duration_mins, total_marks, passing_marks, exam_mode, shuffle_questions, shuffle_options, display_result_immediately, status, created_by) 
            VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, 'Practice', ?, ?, ?, 'Scheduled', ?)");
        
        $passing = $template['total_marks'] * 0.35; // default 35% passing

        $stmt_exam->execute([
            $title, $desc, $template['standard_id'], $start, $end, $duration, 
            $template['total_marks'], $passing, $shuffle_q, $shuffle_o, $show_res, $_SESSION['user_id']
        ]);
        
        $exam_id = $conn->lastInsertId();

        // Fetch actual question records to build Answer Key
        $placeholders = str_repeat('?,', count($selected_question_ids) - 1) . '?';
        $stmt_fetch = $conn->prepare("SELECT id, correct_option FROM tbl_oes_questions WHERE id IN ($placeholders)");
        $stmt_fetch->execute($selected_question_ids);
        $questions_data = $stmt_fetch->fetchAll(PDO::FETCH_KEY_PAIR);

        $answers_array = [];
        // 4. Attach Questions & Build OMR Answer Array
        $stmt_attach = $conn->prepare("INSERT INTO tbl_oes_exam_questions (exam_id, question_id, order_no) VALUES (?, ?, ?)");
        $order = 1;
        foreach ($selected_question_ids as $qid) {
            $stmt_attach->execute([$exam_id, $qid, $order]);
            if(isset($questions_data[$qid])) {
                $answers_array[$order] = $questions_data[$qid];
            }
            $order++;
        }

        // 5. Generate Offline Paper Set & Answer Key
        $stmt_ps = $conn->prepare("INSERT INTO tbl_paper_sets (paper_set_name, paper_code, description, total_questions, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt_ps->execute([$title, 'AUTO-'.$exam_id, 'Auto-generated from OES', count($selected_question_ids)]);
        $paper_set_id = $conn->lastInsertId();

        $stmt_ak = $conn->prepare("INSERT INTO tbl_answer_keys (paper_set_id, test_name, test_date, total_questions, answers_json, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt_ak->execute([
            $paper_set_id,
            $title,
            date('Y-m-d', strtotime($start)),
            count($selected_question_ids),
            json_encode($answers_array),
            $_SESSION['user_id']
        ]);

        $conn->commit();
        $success = "Exam '{$title}' successfully generated with " . count($selected_question_ids) . " questions! Offline OMR Answer Key also created. <a href='export-pdf-paper.php?exam_id={$exam_id}' target='_blank' class='btn btn-sm btn-light ml-3 border text-dark'><i class='fas fa-print'></i> Print PDF Paper</a>";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <?php if($error): ?>
            <div class="alert alert-danger shadow-sm border-0" style="border-radius: 12px;">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?= $error ?>
            </div>
            <?php endif; ?>
            
            <?php if($success): ?>
            <div class="alert alert-success shadow-sm border-0" style="border-radius: 12px;">
                <i class="fas fa-check-circle mr-2"></i> <?= $success ?>
                <a href="exam-templates.php" class="btn btn-sm btn-success float-right">Back to Templates</a>
            </div>
            <?php else: ?>

            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px; max-width: 800px; margin: 0 auto;">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-magic mr-2 text-primary"></i> Generate Exam</h5>
                            <small class="text-muted">Template: <strong><?= htmlspecialchars($template['template_name']) ?></strong></small>
                        </div>
                        <a href="exam-templates.php" class="btn btn-light shadow-sm" style="border-radius: 10px;">Cancel</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert bg-light border-0 mb-4" style="border-radius: 12px;">
                        <div class="row text-center">
                            <div class="col-4 border-right">
                                <h4 class="mb-0 text-primary font-weight-bold"><?= $template['total_questions'] ?></h4>
                                <small class="text-muted text-uppercase">Questions</small>
                            </div>
                            <div class="col-4 border-right">
                                <h4 class="mb-0 text-primary font-weight-bold"><?= $template['total_marks'] ?></h4>
                                <small class="text-muted text-uppercase">Total Marks</small>
                            </div>
                            <div class="col-4">
                                <h4 class="mb-0 text-primary font-weight-bold"><?= $template['duration_mins'] ?>m</h4>
                                <small class="text-muted text-uppercase">Duration</small>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-2">Exam Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($template['template_name']) ?> - <?= date('M d, Y') ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-2">Start Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="start_time" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-2">End Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="end_time" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+2 days')) ?>" required>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Duration (Mins) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_mins" class="form-control" value="<?= $template['duration_mins'] ?>" required>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Instructions</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($template['description']) ?></textarea>
                        </div>

                        <hr class="my-4">
                        <h6 class="font-weight-bold text-dark mb-3">Settings</h6>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="shuffle_questions" name="shuffle_questions" value="1" checked>
                            <label class="custom-control-label small font-weight-bold" for="shuffle_questions">Shuffle Questions</label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="shuffle_options" name="shuffle_options" value="1" checked>
                            <label class="custom-control-label small font-weight-bold" for="shuffle_options">Shuffle Options</label>
                        </div>

                        <div class="custom-control custom-switch mb-4">
                            <input type="checkbox" class="custom-control-input" id="display_result_immediately" name="display_result_immediately" value="1">
                            <label class="custom-control-label small font-weight-bold" for="display_result_immediately">Show Results Instantly</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm" style="border-radius: 12px; font-weight: 600;">
                            <i class="fas fa-magic mr-2"></i> Auto-Generate Exam
                        </button>
                    </form>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
