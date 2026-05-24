<?php
$root = dirname(__DIR__, 3);
require_once $root . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access (Super Admin, Principle, Counsellor, Dept Head, Assistant Teacher)
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

// Session Config Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_config') {
    $_SESSION['oes_active_config'] = [
        'standard_id' => $_POST['standard_id'],
        'group_id' => !empty($_POST['group_id']) ? $_POST['group_id'] : null,
        'subject_id' => $_POST['subject_id'],
        'chapter_id' => !empty($_POST['chapter_id']) ? $_POST['chapter_id'] : null,
        'topic_id' => !empty($_POST['topic_id']) ? $_POST['topic_id'] : null,
        'question_type_id' => $_POST['question_type_id'],
        'difficulty' => $_POST['difficulty'],
        'exam_type' => isset($_POST['exam_type']) ? $_POST['exam_type'] : 'both',
        'marks' => ($_POST['question_type_id'] == '1') ? '1.0' : '0.0',
        'negative_marks' => '0.0',
        'mcq_question_count' => isset($_POST['mcq_question_count']) ? (int)$_POST['mcq_question_count'] : 1,
        'desc_count_1' => isset($_POST['desc_count_1']) ? (int)$_POST['desc_count_1'] : 1,
        'desc_count_2' => isset($_POST['desc_count_2']) ? (int)$_POST['desc_count_2'] : 1,
        'desc_count_3' => isset($_POST['desc_count_3']) ? (int)$_POST['desc_count_3'] : 1,
        'desc_count_4' => isset($_POST['desc_count_4']) ? (int)$_POST['desc_count_4'] : 1,
        'desc_count_5' => isset($_POST['desc_count_5']) ? (int)$_POST['desc_count_5'] : 1
    ];
    $_SESSION['oes_flash_saved'] = false;
    unset($_SESSION['oes_editing_config']);
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'edit_config') {
    $_SESSION['oes_editing_config'] = true;
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'reset_config') {
    unset($_SESSION['oes_active_config']);
    unset($_SESSION['oes_editing_config']);
    header("Location: index.php");
    exit();
}

// Force HTTPS for production to enable Voice/OCR
if (ENVIRONMENT === 'production' && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect_url");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_editing_config = isset($_SESSION['oes_editing_config']) && $_SESSION['oes_editing_config'];
$has_active_config = ($id === 0 && isset($_SESSION['oes_active_config']) && !$is_editing_config);
$pre = ($is_editing_config && isset($_SESSION['oes_active_config'])) ? $_SESSION['oes_active_config'] : [];

// Session flash message for saved question
$flash_saved = isset($_SESSION['oes_flash_saved']) ? $_SESSION['oes_flash_saved'] : null;
unset($_SESSION['oes_flash_saved']);

$qData = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT *, id, difficulty, question_text, explanation, video_solution_url, solution_image, marks
        FROM tbl_oes_questions
        WHERE id = ?");
    $stmt->execute([$id]);
    $qData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = ($qData ? "Edit Question" : "Create Question") . " | OES";

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<link rel="stylesheet" href="../../assets/css/oes-editor.css?v=<?php echo time(); ?>">

<!-- Hidden OCR Input -->
<input type="file" id="ocr-input" style="display: none;" accept="image/*">
<!-- Hidden Document Import Input -->
<input type="file" id="doc-import-input" style="display: none;" accept=".docx,.xlsx,.xls,.csv">

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">

            <?php if ($flash_saved === true): ?>
                <div class="alert alert-success border-success bg-white shadow-sm mb-4 alert-dismissible fade show rounded-lg" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success fa-2x mr-3"></i>
                        <div>
                            <strong class="text-success d-block" style="font-size: 1.1rem;">Question Saved Successfully!</strong>
                            <span class="text-muted small">A new blank form is ready. The locked configuration remains active.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($id === 0 && (!$has_active_config || $is_editing_config)): ?>
                <!-- Step 1: Pre-Configuration Setup Form -->
                <div class="row justify-content-center">
                    <div class="col-md-10 col-lg-8">
                        <div class="card shadow-sm border-0 rounded-lg mt-4" style="border-top: 5px solid var(--primary) !important;">
                            <div class="card-header bg-white text-center py-4 border-bottom-0 d-block">
                                <h4 class="font-weight-bold text-primary mb-1 d-block"><i class="fas fa-sliders-h mr-2"></i>Exam Question Setup</h4>
                                <span class="text-muted small d-block mt-2">Set configuration once, create multiple questions easily.</span>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" action="index.php">
                                    <input type="hidden" name="action" value="set_config">
                                    
                                    <div class="row">
                                        <!-- Row 1: Type, Difficulty, Exam Type -->
                                        <div class="col-md-4 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Question Type</label>
                                            <select name="question_type_id" id="setup_question_type_select" class="form-control" required>
                                                <option value="">Select Type</option>
                                                <option value="1" <?php echo (($pre['question_type_id'] ?? '') == '1') ? 'selected' : ''; ?>>MCQ</option>
                                                <option value="descriptive" <?php echo (($pre['question_type_id'] ?? '') == 'descriptive') ? 'selected' : ''; ?>>Descriptive</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Difficulty Level</label>
                                            <select name="difficulty" class="form-control" required>
                                                <option value="Level A" <?php echo (($pre['difficulty'] ?? 'Level A') == 'Level A') ? 'selected' : ''; ?>>Level A</option>
                                                <option value="Level B" <?php echo (($pre['difficulty'] ?? '') == 'Level B') ? 'selected' : ''; ?>>Level B</option>
                                                <option value="Level C" <?php echo (($pre['difficulty'] ?? '') == 'Level C') ? 'selected' : ''; ?>>Level C</option>
                                                <option value="Level D" <?php echo (($pre['difficulty'] ?? '') == 'Level D') ? 'selected' : ''; ?>>Level D</option>
                                                <option value="Level E" <?php echo (($pre['difficulty'] ?? '') == 'Level E') ? 'selected' : ''; ?>>Level E</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Exam Type</label>
                                            <select name="exam_type" class="form-control" required>
                                                <option value="both" <?php echo (($pre['exam_type'] ?? 'both') == 'both') ? 'selected' : ''; ?>>Practice & Final (Both)</option>
                                                <option value="practice" <?php echo (($pre['exam_type'] ?? '') == 'practice') ? 'selected' : ''; ?>>Practice Test Only</option>
                                                <option value="final" <?php echo (($pre['exam_type'] ?? '') == 'final') ? 'selected' : ''; ?>>Final Exam Only</option>
                                            </select>
                                        </div>

                                        <!-- Row 2: Standard & Group -->
                                        <div class="col-md-6 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Standard</label>
                                            <select name="standard_id" id="standard_id_select" class="form-control" required>
                                                <option value="">Select Standard</option>
                                                <option value="11" <?php echo (($pre['standard_id'] ?? '') == '11') ? 'selected' : ''; ?>>11th</option>
                                                <option value="12" <?php echo (($pre['standard_id'] ?? '') == '12') ? 'selected' : ''; ?>>12th</option>
                                                <option value="13" <?php echo (($pre['standard_id'] ?? '') == '13') ? 'selected' : ''; ?>>Reneet</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Group</label>
                                            <select name="group_id" id="group_id_select" class="form-control" required>
                                                <?php
                                                $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC");
                                                while ($g = $groups->fetch()) {
                                                    $sel = (($pre['group_id'] ?? '') == $g['id']) ? 'selected' : '';
                                                    echo "<option value='{$g['id']}' $sel>{$g['group_name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <!-- Row 3: Subject & Chapter -->
                                        <div class="col-md-6 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Subject</label>
                                            <select name="subject_id" id="subject_id_select" class="form-control" required>
                                                <option value="">Select Standard First</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-3">
                                            <label class="small font-weight-bold text-secondary mb-1">Chapter</label>
                                            <select name="chapter_id" id="chapter_id_select" class="form-control" required>
                                                <option value="">Select Subject First</option>
                                            </select>
                                        </div>

                                        <!-- Row 4: Topic (Full Width) -->
                                        <div class="col-12 form-group mb-4">
                                            <label class="small font-weight-bold text-secondary mb-1">Topic</label>
                                            <select name="topic_id" id="topic_id_select" class="form-control" required>
                                                <option value="">Select Chapter First</option>
                                            </select>
                                        </div>

                                        <!-- Row 5: Dynamic Counts based on Question Type -->
                                        <div class="col-12 form-group mb-4" id="mcq_count_wrapper" style="display: none;">
                                            <label class="small font-weight-bold text-secondary mb-1">Number of MCQ Questions to Create</label>
                                            <input type="number" name="mcq_question_count" id="setup_mcq_count" class="form-control text-primary font-weight-bold" min="1" max="50" value="<?php echo htmlspecialchars($pre['mcq_question_count'] ?? '1'); ?>" style="border-radius: 8px;">
                                        </div>

                                        <div class="col-12 form-group mb-4" id="desc_counts_wrapper" style="display: none;">
                                            <label class="small font-weight-bold text-secondary mb-2">Number of Descriptive Questions to Create per Mark Level</label>
                                            <div class="row">
                                                <?php for ($m = 1; $m <= 5; $m++): ?>
                                                    <div class="col">
                                                        <label class="small text-muted font-weight-bold mb-1"><?php echo $m; ?>-Mark</label>
                                                        <input type="number" name="desc_count_<?php echo $m; ?>" class="form-control text-info font-weight-bold text-center" min="0" max="20" value="<?php echo htmlspecialchars($pre['desc_count_'.$m] ?? '1'); ?>" style="border-radius: 8px;">
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const qTypeSelect = document.getElementById('setup_question_type_select');
                                        const mcqCountWrapper = document.getElementById('mcq_count_wrapper');
                                        const descCountsWrapper = document.getElementById('desc_counts_wrapper');

                                        function toggleSetupCounts() {
                                            if (!qTypeSelect) return;
                                            const val = qTypeSelect.value;
                                            if (val === '1') {
                                                mcqCountWrapper.style.display = 'block';
                                                descCountsWrapper.style.display = 'none';
                                            } else if (val === 'descriptive') {
                                                mcqCountWrapper.style.display = 'none';
                                                descCountsWrapper.style.display = 'block';
                                            } else {
                                                mcqCountWrapper.style.display = 'none';
                                                descCountsWrapper.style.display = 'none';
                                            }
                                        }

                                        if (qTypeSelect) {
                                            qTypeSelect.addEventListener('change', toggleSetupCounts);
                                            toggleSetupCounts(); // Initial trigger
                                        }
                                    });
                                    </script>

                                    <button type="submit" class="btn btn-primary btn-block btn-lg shadow-sm">
                                        <i class="fas fa-check-circle mr-2"></i>Lock Setup & Start Writing
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800"><?php echo $qData ? "Edit Question" : "Create Question"; ?></h1>
                    <p class="text-muted small">Design high-quality questions with rich media and math support.</p>
                </div>
                <a href="question-bank.php" class="btn btn-outline-primary">
                    <i class="fas fa-list-ul mr-2"></i> Question Bank
                </a>
            </div>

            <!-- Locked Setup Banner -->
            <?php if ($has_active_config):
                $cfg = $_SESSION['oes_active_config'];

                // Fetch Subject name
                $cfg_subject = $conn->prepare("SELECT subject_name FROM tbl_subjects WHERE id = ?");
                $cfg_subject->execute([$cfg['subject_id']]);
                $cfg_subject_name = $cfg_subject->fetchColumn() ?: 'N/A';

                // Fetch Chapter name
                $cfg_chapter_name = 'N/A';
                if (!empty($cfg['chapter_id'])) {
                    $cfg_chapter = $conn->prepare("SELECT chapter FROM tbl_chapters WHERE chpid = ?");
                    $cfg_chapter->execute([$cfg['chapter_id']]);
                    $cfg_chapter_name = $cfg_chapter->fetchColumn() ?: 'N/A';
                }

                // Fetch Topic name
                $cfg_topic_name = 'N/A';
                if (!empty($cfg['topic_id'])) {
                    $cfg_topic = $conn->prepare("SELECT topic_name_english FROM tbl_topics WHERE id = ?");
                    $cfg_topic->execute([$cfg['topic_id']]);
                    $cfg_topic_name = $cfg_topic->fetchColumn() ?: 'N/A';
                }

                // Fetch Group name
                $cfg_group_name = null;
                if (!empty($cfg['group_id'])) {
                    $cfg_group = $conn->prepare("SELECT group_name FROM tbl_group WHERE id = ?");
                    $cfg_group->execute([$cfg['group_id']]);
                    $cfg_group_name = $cfg_group->fetchColumn() ?: null;
                }

                $cfg_standard_label = match((string)$cfg['standard_id']) {
                    '11' => '11th', '12' => '12th', '13' => 'Re-Neet', default => $cfg['standard_id']
                };
            ?>
                <div class="card shadow-sm border-0 mb-4 rounded-lg" style="border: 1px solid #e3e6f0; border-left: 4px solid #1a73e8 !important; border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <!-- Column 1: Setup Locked Badge -->
                            <div class="col-md-auto mb-2 mb-md-0">
                                <span class="badge text-white px-3 py-2 font-weight-bold text-uppercase d-inline-flex align-items-center shadow-sm" style="border-radius: 8px; font-size: 0.8rem; letter-spacing: 0.5px; background-color: #1a73e8 !important; white-space: nowrap;">
                                    <i class="fas fa-lock mr-2"></i> SETUP LOCKED
                                </span>
                            </div>
                            <!-- Column 2: Active Configuration Info Table -->
                            <div class="col-md mb-2 mb-md-0 text-dark">
                                <span class="font-weight-bold text-dark d-block mb-1" style="font-size: 0.95rem;">
                                    <i class="fas fa-sliders-h mr-1 text-primary"></i> Active Configuration Details:
                                </span>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0" style="font-size: 0.85rem; line-height: 1.4; width: 100%; min-width: 500px;">
                                        <tbody>
                                            <tr>
                                                <td class="text-secondary py-1 pl-0" style="width: 30%;">Standard: <strong class="text-dark"><?php echo $cfg_standard_label; ?></strong></td>
                                                <td class="text-secondary py-1" style="width: 35%;">Subject: <strong class="text-dark"><?php echo htmlspecialchars($cfg_subject_name); ?></strong></td>
                                                <td class="text-secondary py-1" style="width: 35%;">Type: <strong class="text-dark"><?php echo ($cfg['question_type_id'] == '1') ? 'MCQ' : 'Descriptive'; ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="text-secondary py-1 pl-0"><?php if ($cfg_group_name): ?>Group: <strong class="text-dark"><?php echo htmlspecialchars($cfg_group_name); ?></strong><?php else: ?><span class="text-muted-light">-</span><?php endif; ?></td>
                                                <td class="text-secondary py-1">Chapter: <strong class="text-dark"><?php echo htmlspecialchars($cfg_chapter_name); ?></strong></td>
                                                <td class="text-secondary py-1">Level: <strong class="text-dark"><?php echo htmlspecialchars($cfg['difficulty']); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <?php
                                                $cfg_exam_type_label = match($cfg['exam_type'] ?? 'both') {
                                                    'practice' => 'Practice Test Only',
                                                    'final' => 'Final Exam Only',
                                                    default => 'Practice & Final'
                                                };
                                                ?>
                                                <td class="text-secondary py-1 pl-0" <?php echo empty($cfg['topic_id']) ? 'colspan="3"' : ''; ?>>Exam Type: <strong class="text-dark"><?php echo htmlspecialchars($cfg_exam_type_label); ?></strong></td>
                                                <?php if (!empty($cfg['topic_id'])): ?>
                                                    <td class="text-secondary py-1" colspan="2">Topic: <strong class="text-dark"><?php echo htmlspecialchars($cfg_topic_name); ?></strong></td>
                                                <?php endif; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Column 3: Action Buttons -->
                            <div class="col-md-auto text-md-right d-flex align-items-center justify-content-md-end flex-wrap gap-2">
                                <a href="index.php?action=edit_config" class="btn btn-outline-primary btn-sm px-3 py-1.5 font-weight-bold mr-2 d-inline-flex align-items-center" style="border-radius: 8px; font-size: 0.85rem; border-color: #1a73e8; color: #1a73e8; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); white-space: nowrap;">
                                    <i class="fas fa-edit mr-1"></i> Change Setup
                                </a>
                                <a href="index.php?action=reset_config" class="btn btn-outline-danger btn-sm px-3 py-1.5 font-weight-bold d-inline-flex align-items-center" style="border-radius: 8px; font-size: 0.85rem; border-color: #ea4335; color: #ea4335; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);" title="Clear configuration completely">
                                    <i class="fas fa-times mr-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


            <form id="question-form" action="save-question.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="question_id" value="<?php echo $id; ?>">
                
                <?php if ($has_active_config): ?>
                    <!-- Hidden inputs for locked configuration parameters -->
                    <input type="hidden" name="standard_id" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['standard_id']); ?>">
                    <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['group_id'] ?? ''); ?>">
                    <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['subject_id']); ?>">
                    <input type="hidden" name="chapter_id" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['chapter_id']); ?>">
                    <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['topic_id']); ?>">
                    <input type="hidden" name="question_type_id" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['question_type_id']); ?>">
                    <input type="hidden" name="difficulty" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['difficulty']); ?>">
                    <input type="hidden" name="exam_type" value="<?php echo htmlspecialchars($_SESSION['oes_active_config']['exam_type'] ?? 'both'); ?>">
                <?php endif; ?>
                <div class="row">
                    <!-- Left Side: Main Editor -->
                    <div class="<?php echo ($has_active_config && !$qData) ? 'col-lg-12' : 'col-lg-8'; ?>">
                        
                        <?php
                        $default_marks = '1.0';
                        $default_neg = '0.0';
                        if ($qData) {
                            $default_marks = htmlspecialchars($qData['marks']);
                            $default_neg = htmlspecialchars($qData['negative_marks']);
                        } elseif ($has_active_config) {
                            $default_marks = htmlspecialchars($_SESSION['oes_active_config']['marks']);
                            $default_neg = htmlspecialchars($_SESSION['oes_active_config']['negative_marks']);
                        }

                        $rendered_editors = [];

                        // ----------------------------------------------------
                        // CONDITION 1: BULK MCQ MODE
                        // ----------------------------------------------------
                        if ($has_active_config && !$qData && $_SESSION['oes_active_config']['question_type_id'] == '1'):
                            $mcq_question_count = isset($_SESSION['oes_active_config']['mcq_question_count']) ? (int)$_SESSION['oes_active_config']['mcq_question_count'] : 1;
                            for ($idx = 1; $idx <= $mcq_question_count; $idx++):
                                // Push all editor IDs to $rendered_editors
                                $rendered_editors[] = "main-$idx";
                                $rendered_editors[] = "main-guj-$idx";
                                $rendered_editors[] = "a-$idx";
                                $rendered_editors[] = "a-guj-$idx";
                                $rendered_editors[] = "b-$idx";
                                $rendered_editors[] = "b-guj-$idx";
                                $rendered_editors[] = "c-$idx";
                                $rendered_editors[] = "c-guj-$idx";
                                $rendered_editors[] = "d-$idx";
                                $rendered_editors[] = "d-guj-$idx";
                                $rendered_editors[] = "explanation-$idx";
                                $rendered_editors[] = "explanation-guj-$idx";
                        ?>
                                <div class="card shadow-sm border-0 mb-5 rounded-lg" style="border: 1px solid #e3e6f0; border-top: 5px solid var(--primary) !important; border-radius: 12px; overflow: hidden;">
                                    <div class="card-header py-3 bg-white border-bottom d-flex align-items-center justify-content-between">
                                        <h5 class="m-0 font-weight-bold text-primary"><i class="fas fa-question-circle mr-2"></i> MCQ Question #<?php echo $idx; ?></h5>
                                        <span class="badge bg-primary text-white px-3 py-2" style="font-size: 0.85rem;">MCQ</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <!-- Marks & Neg Marks for this question -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <div class="card shadow-none border" style="border-radius: 8px;">
                                                    <div class="card-body p-2 d-flex align-items-center">
                                                        <div class="bg-primary-light text-primary p-2 rounded-circle mr-3" style="background: rgba(26, 115, 232, 0.1); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-star" style="font-size: 0.9rem;"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <label class="font-weight-bold small text-muted mb-0">Question Marks</label>
                                                            <input type="number" step="0.01" min="0" name="marks_<?php echo $idx; ?>" id="marks_input_<?php echo $idx; ?>" class="form-control form-control-sm border-0 shadow-none font-weight-bold p-0 text-primary" style="font-size: 1.1rem; background: transparent; height: auto;" value="<?php echo $default_marks; ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card shadow-none border" style="border-radius: 8px;">
                                                    <div class="card-body p-2 d-flex align-items-center">
                                                        <div class="bg-danger-light text-danger p-2 rounded-circle mr-3" style="background: rgba(234, 67, 53, 0.1); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-minus-circle" style="font-size: 0.9rem;"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <label class="font-weight-bold small text-muted mb-0">Negative Marks</label>
                                                            <input type="number" step="0.01" min="0" name="negative_marks_<?php echo $idx; ?>" id="negative_marks_input_<?php echo $idx; ?>" class="form-control form-control-sm border-0 shadow-none font-weight-bold p-0 text-danger" style="font-size: 1.1rem; background: transparent; height: auto;" value="<?php echo $default_neg; ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Question Text Block -->
                                        <div class="mb-4">
                                            <label class="font-weight-bold text-dark mb-2"><i class="fas fa-pen-fancy text-primary mr-2"></i>Question Body (Bilingual Input)</label>
                                            <div class="row">
                                                <div class="col-md-6 border-right">
                                                    <div class="form-group mb-0">
                                                        <label class="font-weight-bold small text-muted mb-2">
                                                            <span class="badge bg-primary text-white mr-1">EN</span> Question Text (English)
                                                        </label>
                                                        <div id="editor-main-<?php echo $idx; ?>" class="quill-editor" style="min-height: 120px;"></div>
                                                        <textarea name="question_text_<?php echo $idx; ?>" id="question_text_<?php echo $idx; ?>" class="d-none"></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="font-weight-bold small text-muted mb-2">
                                                            <span class="badge bg-warning text-dark mr-1">GU</span> Question Text (Gujarati)
                                                        </label>
                                                        <div id="editor-main-guj-<?php echo $idx; ?>" class="quill-editor" style="min-height: 120px;"></div>
                                                        <textarea name="question_text_guj_<?php echo $idx; ?>" id="question_text_guj_<?php echo $idx; ?>" class="d-none"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- MCQ Options List -->
                                        <input type="hidden" name="correct_option_<?php echo $idx; ?>" id="correct_option_hidden_<?php echo $idx; ?>" value="A">
                                        <div class="mcq-options-list mb-4">
                                            <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                                                <?php $opt_upper = strtoupper($opt); ?>
                                                <div class="option-item mb-3">
                                                    <div class="card shadow-none border" id="option-card-<?php echo $opt_upper; ?>-<?php echo $idx; ?>" style="transition: all 0.3s ease; border-radius: 8px; overflow: hidden;">
                                                        <div class="card-header py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
                                                            <h6 class="m-0 font-weight-bold text-secondary">Option <?php echo $opt_upper; ?></h6>
                                                            <button type="button" class="btn btn-xs btn-outline-success font-weight-bold correct-opt-btn" data-opt="<?php echo $opt_upper; ?>" data-idx="<?php echo $idx; ?>" style="border-radius: 20px; font-size: 0.75rem; padding: 2px 10px;">
                                                                <i class="far fa-circle mr-1"></i> Mark Correct
                                                            </button>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="row">
                                                                <div class="col-md-6 border-right">
                                                                    <div class="form-group mb-0">
                                                                        <label class="font-weight-bold small text-muted mb-2">
                                                                            <span class="badge bg-primary text-white mr-1">EN</span> English
                                                                        </label>
                                                                        <div id="editor-<?php echo $opt; ?>-<?php echo $idx; ?>" class="quill-editor" style="min-height: 80px;"></div>
                                                                        <textarea name="option_<?php echo $opt; ?>_<?php echo $idx; ?>" id="option_<?php echo $opt; ?>_<?php echo $idx; ?>" class="d-none"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group mb-0">
                                                                        <label class="font-weight-bold small text-muted mb-2">
                                                                            <span class="badge bg-warning text-dark mr-1">GU</span> Gujarati
                                                                        </label>
                                                                        <div id="editor-<?php echo $opt; ?>-guj-<?php echo $idx; ?>" class="quill-editor" style="min-height: 80px;"></div>
                                                                        <textarea name="option_<?php echo $opt; ?>_guj_<?php echo $idx; ?>" id="option_<?php echo $opt; ?>_guj_<?php echo $idx; ?>" class="d-none"></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Solution and Explanation block for this MCQ -->
                                        <div class="card shadow-none border mb-4" style="border-radius: 8px;">
                                            <div class="card-header py-2 bg-light d-flex align-items-center">
                                                <i class="fas fa-lightbulb text-warning mr-2"></i>
                                                <h6 class="m-0 font-weight-bold text-dark">Explanation & Solution</h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="row mb-3">
                                                    <div class="col-md-6 border-right">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2">
                                                                <span class="badge bg-primary text-white mr-1">EN</span> English Solution
                                                            </label>
                                                            <div id="editor-explanation-<?php echo $idx; ?>" class="quill-editor" style="min-height: 100px;"></div>
                                                            <textarea name="explanation_<?php echo $idx; ?>" id="explanation_<?php echo $idx; ?>" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2">
                                                                <span class="badge bg-warning text-dark mr-1">GU</span> Gujarati Solution
                                                            </label>
                                                            <div id="editor-explanation-guj-<?php echo $idx; ?>" class="quill-editor" style="min-height: 100px;"></div>
                                                            <textarea name="explanation_guj_<?php echo $idx; ?>" id="explanation_guj_<?php echo $idx; ?>" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row pt-2 border-top">
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="small font-weight-bold text-secondary"><i class="fas fa-video text-danger mr-1"></i> Video Solution Link</label>
                                                            <input type="url" name="video_solution_url_<?php echo $idx; ?>" class="form-control form-control-sm" placeholder="YouTube/Vimeo Link">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="small font-weight-bold text-secondary"><i class="fas fa-image text-primary mr-1"></i> Solution Image</label>
                                                            <input type="file" name="solution_image_<?php echo $idx; ?>" class="form-control form-control-sm border" accept="image/*">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                        <?php
                            endfor;
                        // ----------------------------------------------------
                        // CONDITION 2: BULK DESCRIPTIVE MODE
                        // ----------------------------------------------------
                        elseif ($has_active_config && !$qData && $_SESSION['oes_active_config']['question_type_id'] == 'descriptive'):
                            for ($m = 1; $m <= 5; $m++):
                                $desc_count = isset($_SESSION['oes_active_config']['desc_count_' . $m]) ? (int)$_SESSION['oes_active_config']['desc_count_' . $m] : 0;
                                if ($desc_count <= 0) continue;

                                for ($idx = 1; $idx <= $desc_count; $idx++):
                                    $rendered_editors[] = "desc-q{$m}-{$idx}";
                                    $rendered_editors[] = "desc-q{$m}-{$idx}-guj";
                                    $rendered_editors[] = "desc-s{$m}-{$idx}";
                                    $rendered_editors[] = "desc-s{$m}-{$idx}-guj";
                        ?>
                                    <div class="card shadow-sm border-0 mb-5 rounded-lg" style="border: 1px solid #e3e6f0; border-top: 5px solid var(--info) !important; border-radius: 12px; overflow: hidden;">
                                        <div class="card-header py-3 bg-white border-bottom d-flex align-items-center justify-content-between">
                                            <h5 class="m-0 font-weight-bold text-info"><i class="fas fa-pen-alt mr-2"></i> <?php echo $m; ?>-Mark Question - #<?php echo $idx; ?></h5>
                                            <span class="badge bg-info text-white px-3 py-2" style="font-size: 0.85rem;">Descriptive</span>
                                        </div>
                                        <div class="card-body p-4">
                                            <!-- Marks and Negative Marks for this Descriptive Question -->
                                            <div class="row mb-3 bg-light p-2 rounded shadow-sm">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary"><i class="fas fa-star text-primary mr-1"></i> Marks for this Question</label>
                                                        <input type="number" step="0.01" min="0" name="desc_marks_<?php echo $m; ?>_<?php echo $idx; ?>" class="form-control form-control-sm font-weight-bold text-primary" value="<?php echo (float)$m; ?>" required style="border-radius: 8px;">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary"><i class="fas fa-minus-circle text-danger mr-1"></i> Negative Marks</label>
                                                        <input type="number" step="0.01" min="0" name="desc_negative_marks_<?php echo $m; ?>_<?php echo $idx; ?>" class="form-control form-control-sm font-weight-bold text-danger" value="0.0" required style="border-radius: 8px;">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Question Text: Bilingual -->
                                            <div class="mb-4">
                                                <label class="small font-weight-bold text-secondary mb-2"><i class="fas fa-question-circle mr-1 text-info"></i> Question Text — <?php echo $m; ?> Mark (Bilingual)</label>
                                                <div class="row">
                                                    <div class="col-md-6 border-right">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2"><span class="badge bg-primary text-white mr-1">EN</span> English</label>
                                                            <div id="editor-desc-q<?php echo $m; ?>-<?php echo $idx; ?>" class="quill-editor" style="min-height: 120px;"></div>
                                                            <textarea name="desc_question_<?php echo $m; ?>_<?php echo $idx; ?>" id="desc_question_<?php echo $m; ?>_<?php echo $idx; ?>" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2"><span class="badge bg-warning text-dark mr-1">GU</span> Gujarati</label>
                                                            <div id="editor-desc-q<?php echo $m; ?>-<?php echo $idx; ?>-guj" class="quill-editor" style="min-height: 120px;"></div>
                                                            <textarea name="desc_question_<?php echo $m; ?>_<?php echo $idx; ?>_guj" id="desc_question_<?php echo $m; ?>_<?php echo $idx; ?>_guj" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Solution: Bilingual -->
                                            <div class="mb-4">
                                                <label class="small font-weight-bold text-secondary mb-2"><i class="fas fa-lightbulb mr-1 text-warning"></i> Solution / Explanation (Bilingual)</label>
                                                <div class="row">
                                                    <div class="col-md-6 border-right">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2"><span class="badge bg-primary text-white mr-1">EN</span> English</label>
                                                            <div id="editor-desc-s<?php echo $m; ?>-<?php echo $idx; ?>" class="quill-editor" style="min-height: 100px;"></div>
                                                            <textarea name="desc_solution_<?php echo $m; ?>_<?php echo $idx; ?>" id="desc_solution_<?php echo $m; ?>_<?php echo $idx; ?>" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2"><span class="badge bg-warning text-dark mr-1">GU</span> Gujarati</label>
                                                            <div id="editor-desc-s<?php echo $m; ?>-<?php echo $idx; ?>-guj" class="quill-editor" style="min-height: 100px;"></div>
                                                            <textarea name="desc_solution_<?php echo $m; ?>_<?php echo $idx; ?>_guj" id="desc_solution_<?php echo $m; ?>_<?php echo $idx; ?>_guj" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Video & Image -->
                                            <div class="row pt-2 border-top">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary"><i class="fas fa-video text-danger mr-1"></i> Video Solution Link</label>
                                                        <input type="url" name="desc_video_<?php echo $m; ?>_<?php echo $idx; ?>" class="form-control form-control-sm" placeholder="YouTube/Vimeo Link">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary"><i class="fas fa-image text-primary mr-1"></i> Solution Image</label>
                                                        <input type="file" name="desc_image_<?php echo $m; ?>_<?php echo $idx; ?>" class="form-control form-control-sm border" accept="image/*">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                        <?php
                                endfor;
                            endfor;
                        // ----------------------------------------------------
                        // CONDITION 3: FALLBACK/SINGLE EDIT MODE
                        // ----------------------------------------------------
                        else:
                            // Build editor IDs for single/fallback mode
                            $rendered_editors = [
                                'main', 'main-guj', 'a', 'a-guj', 'b', 'b-guj', 'c', 'c-guj', 'd', 'd-guj', 'explanation', 'explanation-guj'
                            ];
                            for ($i = 1; $i <= 5; $i++) {
                                $rendered_editors[] = "desc-q{$i}";
                                $rendered_editors[] = "desc-q{$i}-guj";
                                $rendered_editors[] = "desc-s{$i}";
                                $rendered_editors[] = "desc-s{$i}-guj";
                            }
                        ?>
                            <!-- Marks and Negative Marks Cards -->
                            <div class="row mb-4" id="marks_neg_container">
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0" style="border-radius: 12px; border: 1px solid #e3e6f0; border-left: 4px solid #1a73e8 !important;">
                                        <div class="card-body p-3 d-flex align-items-center">
                                            <div class="bg-primary-light text-primary p-3 rounded-circle mr-3" style="background: rgba(26, 115, 232, 0.1); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-star" style="font-size: 1.25rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label class="font-weight-bold small text-muted mb-1">Question Marks</label>
                                                <input type="number" step="0.01" min="0" name="marks" id="marks_input" class="form-control form-control-lg border-0 shadow-none font-weight-bold p-0 text-primary" style="font-size: 1.4rem; background: transparent; height: auto;" value="<?php echo $default_marks; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0" style="border-radius: 12px; border: 1px solid #e3e6f0; border-left: 4px solid #ea4335 !important;">
                                        <div class="card-body p-3 d-flex align-items-center">
                                            <div class="bg-danger-light text-danger p-3 rounded-circle mr-3" style="background: rgba(234, 67, 53, 0.1); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-minus-circle" style="font-size: 1.25rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label class="font-weight-bold small text-muted mb-1">Negative Marks</label>
                                                <input type="number" step="0.01" min="0" name="negative_marks" id="negative_marks_input" class="form-control form-control-lg border-0 shadow-none font-weight-bold p-0 text-danger" style="font-size: 1.4rem; background: transparent; height: auto;" value="<?php echo $default_neg; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Question Body Card -->
                            <div class="card shadow-sm mb-4" id="main-editor-card">
                                <div class="card-header py-3 bg-white border-bottom-primary d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-pen-fancy text-primary mr-2"></i>
                                        <h6 class="m-0 font-weight-bold text-primary" id="question-body-label">Question Body (Bilingual Input)</h6>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 border-right">
                                            <div class="form-group mb-0">
                                                <label class="font-weight-bold small text-muted mb-2">
                                                    <span class="badge bg-primary text-white mr-1">EN</span> Question Text (English)
                                                </label>
                                                <div id="editor-main" class="quill-editor" style="min-height: 150px;"></div>
                                                <textarea name="question_text" id="question_text" class="d-none"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-0">
                                                <label class="font-weight-bold small text-muted mb-2">
                                                    <span class="badge bg-warning text-dark mr-1">GU</span> Question Text (Gujarati)
                                                </label>
                                                <div id="editor-main-guj" class="quill-editor" style="min-height: 150px;"></div>
                                                <textarea name="question_text_guj" id="question_text_guj" class="d-none"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MCQ Specific Section -->
                            <div id="mcq_section" style="display: none;">
                                <!-- Hidden Correct Answer Input -->
                                <input type="hidden" name="correct_option" id="correct_option_hidden" value="A">
      
                                <!-- MCQ Bilingual Option Cards List -->
                                <div class="mcq-options-list">
                                    <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                                        <?php $opt_upper = strtoupper($opt); ?>
                                        <div class="option-item mb-4">
                                            <div class="card shadow-sm" id="option-card-<?php echo $opt_upper; ?>" style="transition: all 0.3s ease; border-radius: 12px; overflow: hidden; border: 1px solid #e3e6f0;">
                                                <div class="card-header py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-dot-circle text-secondary mr-2"></i>
                                                        <h6 class="m-0 font-weight-bold text-secondary">Option <?php echo $opt_upper; ?></h6>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-success font-weight-bold correct-opt-btn" data-opt="<?php echo $opt_upper; ?>" style="border-radius: 20px; transition: all 0.2s;">
                                                        <i class="far fa-circle mr-1"></i> Mark Correct
                                                    </button>
                                                </div>
                                                <div class="card-body p-3">
                                                    <div class="row">
                                                        <div class="col-md-6 border-right">
                                                            <div class="form-group mb-0">
                                                                <label class="font-weight-bold small text-muted mb-2">
                                                                    <span class="badge bg-primary text-white mr-1">EN</span> Option <?php echo strtoupper($opt); ?> (English)
                                                                </label>
                                                                <div id="editor-<?php echo $opt; ?>" class="quill-editor" style="min-height: 100px;"></div>
                                                                <textarea name="option_<?php echo $opt; ?>" id="option_<?php echo $opt; ?>" class="d-none"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group mb-0">
                                                                <label class="font-weight-bold small text-muted mb-2">
                                                                    <span class="badge bg-warning text-dark mr-1">GU</span> Option <?php echo strtoupper($opt); ?> (Gujarati)
                                                                </label>
                                                                <div id="editor-<?php echo $opt; ?>-guj" class="quill-editor" style="min-height: 100px;"></div>
                                                                <textarea name="option_<?php echo $opt; ?>_guj" id="option_<?php echo $opt; ?>_guj" class="d-none"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Bulk Descriptive Section -->
                            <div id="descriptive_bulk_section" style="display: none;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-header py-3 bg-info text-white d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-pen-alt mr-2"></i>
                                                <h6 class="m-0 font-weight-bold"><?php echo $i; ?>-Mark Question</h6>
                                            </div>
                                            <span class="badge bg-white text-info px-2 py-1">Descriptive</span>
                                        </div>
                                        <div class="card-body">
                                            <!-- Question Text: Bilingual -->
                                            <div class="mb-1">
                                                <label class="small font-weight-bold text-secondary mb-2">
                                                    <i class="fas fa-question-circle mr-1 text-info"></i>
                                                    Question Text — <?php echo $i; ?> Mark (Bilingual)
                                                </label>
                                                <div class="row">
                                                    <div class="col-md-6 border-right">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2">
                                                                <span class="badge bg-primary text-white mr-1">EN</span> English
                                                            </label>
                                                            <div id="editor-desc-q<?php echo $i; ?>" class="quill-editor" style="min-height: 120px;"></div>
                                                            <textarea name="desc_question_<?php echo $i; ?>" id="desc_question_<?php echo $i; ?>" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2">
                                                                <span class="badge bg-warning text-dark mr-1">GU</span> Gujarati
                                                            </label>
                                                            <div id="editor-desc-q<?php echo $i; ?>-guj" class="quill-editor" style="min-height: 120px;"></div>
                                                            <textarea name="desc_question_<?php echo $i; ?>_guj" id="desc_question_<?php echo $i; ?>_guj" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr class="my-3">

                                            <!-- Solution: Bilingual -->
                                            <div class="mb-3">
                                                <label class="small font-weight-bold text-secondary mb-2">
                                                    <i class="fas fa-lightbulb mr-1 text-warning"></i>
                                                    Solution / Explanation (Bilingual)
                                                </label>
                                                <div class="row">
                                                    <div class="col-md-6 border-right">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2">
                                                                <span class="badge bg-primary text-white mr-1">EN</span> English
                                                            </label>
                                                            <div id="editor-desc-s<?php echo $i; ?>" class="quill-editor" style="min-height: 100px;"></div>
                                                            <textarea name="desc_solution_<?php echo $i; ?>" id="desc_solution_<?php echo $i; ?>" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-0">
                                                            <label class="font-weight-bold small text-muted mb-2">
                                                                <span class="badge bg-warning text-dark mr-1">GU</span> Gujarati
                                                            </label>
                                                            <div id="editor-desc-s<?php echo $i; ?>-guj" class="quill-editor" style="min-height: 100px;"></div>
                                                            <textarea name="desc_solution_<?php echo $i; ?>_guj" id="desc_solution_<?php echo $i; ?>_guj" class="d-none"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Video & Image -->
                                            <div class="row pt-2 border-top">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary">
                                                            <i class="fas fa-video text-danger mr-1"></i> Video Solution Link
                                                        </label>
                                                        <div class="input-group input-group-sm shadow-sm">
                                                            <span class="input-group-text border-0 bg-white"><i class="fas fa-video text-danger"></i></span>
                                                            <input type="url" name="desc_video_<?php echo $i; ?>" class="form-control border-0" placeholder="YouTube/Vimeo Link">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary">
                                                            <i class="fas fa-image text-primary mr-1"></i> Solution Image
                                                        </label>
                                                        <div class="input-group input-group-sm shadow-sm">
                                                            <span class="input-group-text border-0 bg-white"><i class="fas fa-image text-primary"></i></span>
                                                            <input type="file" name="desc_image_<?php echo $i; ?>" class="form-control border-0" accept="image/*">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Custom Marks & Negative Marks for this Descriptive Level -->
                                            <div class="row mt-3 pt-3 border-top bg-light p-2 rounded shadow-sm">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary">
                                                            <i class="fas fa-star text-primary mr-1"></i> Marks for this Question
                                                        </label>
                                                        <input type="number" step="0.01" min="0" name="desc_marks_<?php echo $i; ?>" class="form-control form-control-sm font-weight-bold text-primary" value="<?php echo (float)$i; ?>" required style="border-radius: 8px;">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold text-secondary">
                                                            <i class="fas fa-minus-circle text-danger mr-1"></i> Negative Marks
                                                        </label>
                                                        <input type="number" step="0.01" min="0" name="desc_negative_marks_<?php echo $i; ?>" class="form-control form-control-sm font-weight-bold text-danger" value="0.0" required style="border-radius: 8px;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- Question Solution Card -->
                            <div id="standard_solution_section">
                                <div class="card shadow-sm mb-4" id="solution-card">
                                    <div class="card-header py-3 bg-white border-bottom-primary d-flex align-items-center">
                                        <i class="fas fa-lightbulb text-warning mr-2"></i>
                                        <h6 class="m-0 font-weight-bold text-primary">Question Solution & Explanation</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6 border-right">
                                                <div class="form-group mb-0">
                                                    <label class="font-weight-bold small text-muted mb-2">
                                                        <span class="badge bg-primary text-white mr-1">EN</span> Text Solution / Explanation (English)
                                                    </label>
                                                    <div id="editor-explanation" class="quill-editor" style="min-height: 120px;"></div>
                                                    <textarea name="explanation" id="explanation" class="d-none"></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-0">
                                                    <label class="font-weight-bold small text-muted mb-2">
                                                        <span class="badge bg-warning text-dark mr-1">GU</span> Text Solution / Explanation (Gujarati)
                                                    </label>
                                                    <div id="editor-explanation-guj" class="quill-editor" style="min-height: 120px;"></div>
                                                    <textarea name="explanation_guj" id="explanation_guj" class="d-none"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row pt-3 border-top">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label class="small font-weight-bold text-secondary mb-2">
                                                        <i class="fas fa-video text-danger mr-1"></i> Video Solution (YouTube/Vimeo Link)
                                                    </label>
                                                    <div class="input-group shadow-sm">
                                                        <span class="input-group-text border-0 bg-white"><i class="fas fa-video text-danger"></i></span>
                                                        <input type="url" name="video_solution_url" class="form-control border-0" placeholder="Paste video link here...">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label class="small font-weight-bold text-secondary mb-2">
                                                        <i class="fas fa-image text-primary mr-1"></i> Solution Image
                                                    </label>
                                                    <div class="input-group shadow-sm">
                                                        <span class="input-group-text border-0 bg-white"><i class="fas fa-image text-primary"></i></span>
                                                        <input type="file" name="solution_image" class="form-control border-0" accept="image/*">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- bottom action buttons card -->
                        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; background: #fff; border: 1px solid #e3e6f0;">
                            <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <h6 class="m-0 font-weight-bold text-dark">Ready to save your question?</h6>
                                    <p class="text-muted small mb-0">Please verify all bilingual inputs, custom marks, and explanations before submitting.</p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" onclick="clearOesDraft()" class="btn btn-outline-danger px-3 font-weight-bold mr-2" style="border-radius: 8px;" title="Reset all typed fields and clear draft">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </button>
                                    <button type="button" onclick="previewQuestion()" class="btn btn-outline-info px-4 font-weight-bold mr-2" style="border-radius: 8px;">
                                        <i class="fas fa-eye mr-1"></i> Preview
                                    </button>
                                    <button type="submit" class="btn btn-primary px-5 py-2 font-weight-bold shadow-sm" style="border-radius: 8px; background: #1a73e8; border: none; transition: all 0.2s;">
                                        <i class="fas fa-save mr-2"></i> Save Question
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Configuration -->
                    <?php if (!$has_active_config || $qData): ?>
                    <div class="col-lg-4">
                        <div class="card shadow-sm sticky-top" style="top: 20px;">
                            <div class="card-header py-3 bg-primary text-white">
                                <h6 class="m-0 font-weight-bold">Configuration</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $locked = $has_active_config;
                                $disabled = $locked ? 'disabled' : '';
                                ?>
                                <div id="main_question_type_section">
                                    <div class="form-group mb-3">
                                        <label class="small font-weight-bold">Question Type</label>
                                        <select id="question_type_select" class="form-control" <?php echo $disabled; ?> required>
                                            <option value="">Select Type</option>
                                            <option value="1" data-marks="1" data-neg="0" data-type="mcq" <?php echo ($qData && $qData['question_type_id'] == 1) ? 'selected' : ''; ?>>MCQ</option>
                                            <option value="descriptive" data-type="descriptive" <?php echo ($qData && $qData['question_type_id'] != 1) ? 'selected' : ''; ?>>Descriptive</option>
                                        </select>
                                        <input type="hidden" name="<?php echo $locked ? '' : 'question_type_id'; ?>" id="real_question_type_id" value="<?php echo $qData ? htmlspecialchars($qData['question_type_id']) : ''; ?>">
                                    </div>
                                </div>


                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Difficulty Level</label>
                                    <select name="<?php echo $locked ? '' : 'difficulty'; ?>" class="form-control" <?php echo $disabled; ?> required>
                                        <option value="Level A">Level A</option>
                                        <option value="Level B" <?php echo ($qData && $qData['difficulty'] == 'Level B') ? 'selected' : ''; ?>>Level B</option>
                                        <option value="Level C" <?php echo ($qData && $qData['difficulty'] == 'Level C') ? 'selected' : ''; ?>>Level C</option>
                                        <option value="Level D" <?php echo ($qData && $qData['difficulty'] == 'Level D') ? 'selected' : ''; ?>>Level D</option>
                                        <option value="Level E" <?php echo ($qData && $qData['difficulty'] == 'Level E') ? 'selected' : ''; ?>>Level E</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Exam Type</label>
                                    <select name="<?php echo $locked ? '' : 'exam_type'; ?>" class="form-control" <?php echo $disabled; ?> required>
                                        <option value="both" <?php echo ($qData && ($qData['exam_type'] ?? 'both') == 'both') ? 'selected' : ''; ?>>Practice & Final (Both)</option>
                                        <option value="practice" <?php echo ($qData && ($qData['exam_type'] ?? '') == 'practice') ? 'selected' : ''; ?>>Practice Test Only</option>
                                        <option value="final" <?php echo ($qData && ($qData['exam_type'] ?? '') == 'final') ? 'selected' : ''; ?>>Final Exam Only</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Standard</label>
                                    <select name="<?php echo $locked ? '' : 'standard_id'; ?>" id="standard_id_select" class="form-control" <?php echo $disabled; ?>>
                                        <option value="">General / All Standards</option>
                                        <option value="11" <?php echo ($qData && $qData['standard_id'] == 11) ? 'selected' : ''; ?>>11th</option>
                                        <option value="12" <?php echo ($qData && $qData['standard_id'] == 12) ? 'selected' : ''; ?>>12th</option>
                                        <option value="13" <?php echo ($qData && $qData['standard_id'] == 13) ? 'selected' : ''; ?>>Reneet</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Group</label>
                                    <select name="<?php echo $locked ? '' : 'group_id'; ?>" id="group_id_select" class="form-control" <?php echo $disabled; ?> required>
                                        <?php
                                        $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC");
                                        while ($g = $groups->fetch()) {
                                            $selected = ($qData && $qData['group_id'] == $g['id']) ? 'selected' : '';
                                            echo "<option value='{$g['id']}' $selected>{$g['group_name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Subject</label>
                                    <select name="<?php echo $locked ? '' : 'subject_id'; ?>" id="subject_id_select" class="form-control" <?php echo $disabled; ?> required>
                                        <option value="">Select Standard First</option>
                                        <?php
                                        // Fetching from legacy tbl_subjects table
                                        $subjects = $conn->query("SELECT id as subid, subject_name as subname FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC")->fetchAll();
                                        foreach ($subjects as $sub) {
                                            echo "<option value='{$sub['subid']}'>{$sub['subname']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Chapter</label>
                                    <select name="<?php echo $locked ? '' : 'chapter_id'; ?>" id="chapter_id_select" class="form-control" <?php echo $disabled; ?>>
                                        <option value="">Select Subject First</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Topic</label>
                                    <select name="<?php echo $locked ? '' : 'topic_id'; ?>" id="topic_id_select" class="form-control" <?php echo $disabled; ?>>
                                        <option value="">Select Chapter First</option>
                                    </select>
                                </div>


                                <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">
                                    <i class="fas fa-save mr-2"></i> Save Question
                                </button>

                                <div class="row mt-3">
                                    <div class="col-6">
                                        <button type="button" onclick="previewQuestion()"
                                            class="btn btn-outline-info btn-block btn-sm">
                                            <i class="fas fa-eye mr-1"></i> Preview
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" onclick="printQuestion()"
                                            class="btn btn-outline-secondary btn-block btn-sm">
                                            <i class="fas fa-print mr-1"></i> Print / PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
            </form>
            <?php endif; ?>

        </div>
    </div>
</main>

<!-- Math Modal -->
<div id="mathModal" class="oes-modal">
    <div class="oes-modal-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="m-0 font-weight-bold text-primary">Math & Chemistry Equation</h5>
            <button type="button" onclick="closeModal('mathModal')" class="btn-close"></button>
        </div>
        <div class="alert alert-info py-2 small mb-3">
            <strong>Tip:</strong> For Chemistry, use <code>\ce{...}</code>. Example: <code>\ce{H2O}</code>
        </div>
        <math-field id="math-input" class="w-100 p-3 border rounded-lg mb-4" style="font-size: 1.5rem;"></math-field>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" onclick="closeModal('mathModal')" class="btn btn-light">Cancel</button>
            <button type="button" id="confirm-math-btn" class="btn btn-primary px-4">Insert Equation</button>
        </div>
    </div>
</div>

<!-- OCR Modal -->
<div id="ocrModal" class="oes-modal" style="background: rgba(0,0,0,0.9);">
    <div class="oes-modal-content oes-modal-full bg-dark text-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 font-weight-bold text-white">Select Text Area</h5>
            <button type="button" onclick="closeModal('ocrModal')" class="btn-close btn-close-white"></button>
        </div>
        <div
            class="flex-grow-1 overflow-hidden d-flex align-items-center justify-content-center bg-black rounded position-relative">
            <img id="ocr-preview-img" style="max-width: 100%; max-height: 100%;">
            <div id="ocr-processing-overlay">
                <div class="spinner-border text-light" role="status"></div>
                <span>Processing Image...</span>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <p class="text-muted small mb-0">Drag to select the specific text you want to extract.</p>
            <div class="d-flex gap-2">
                <button type="button" onclick="closeModal('ocrModal')" class="btn btn-outline-light">Cancel</button>
                <button type="button" id="start-ocr-btn" class="btn btn-primary">Extract Text</button>
            </div>
        </div>
    </div>
</div>

<!-- Draw Modal -->
<div id="drawModal" class="oes-modal">
    <div class="oes-modal-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 font-weight-bold text-primary">Drawing Board</h5>
            <div class="d-flex gap-2">
                <button type="button" onclick="clearCanvas()" class="btn btn-sm btn-outline-danger"><i
                        class="fas fa-trash"></i></button>
                <button type="button" onclick="closeModal('drawModal')" class="btn-close"></button>
            </div>
        </div>
        <div class="border rounded bg-white overflow-hidden mb-3">
            <canvas id="drawing-canvas"></canvas>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" onclick="closeModal('drawModal')" class="btn btn-light">Cancel</button>
            <button type="button" id="confirm-draw-btn" class="btn btn-primary px-4">Insert Drawing</button>
        </div>
    </div>
</div>

<!-- Chem Modal (Ketcher) -->
<div id="chemModal" class="oes-modal">
    <div class="oes-modal-content oes-modal-full">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 font-weight-bold text-primary">Draw Molecule (Ketcher)</h5>
            <button type="button" onclick="closeModal('chemModal')" class="btn-close"></button>
        </div>
        <iframe id="ketcher-frame" src="../../assets/ketcher/index.html" class="flex-grow-1 border rounded"></iframe>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" onclick="closeModal('chemModal')" class="btn btn-light">Cancel</button>
            <button type="button" id="confirm-chem-btn" class="btn btn-primary px-4">Insert Molecule</button>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="oes-modal">
    <div class="oes-modal-content" style="max-width: 850px; border-top: 5px solid var(--primary);">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <h5 class="m-0 font-weight-bold text-primary"><i class="fas fa-eye mr-2"></i> Question Preview</h5>
            <button type="button" onclick="closeModal('previewModal')" class="btn-close"></button>
        </div>
        <div id="preview-content" class="p-2" style="font-family: 'Inter', sans-serif;">
            <!-- Rendered content goes here -->
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" onclick="closeModal('previewModal')" class="btn btn-light">Close Preview</button>
            <button type="button" onclick="printQuestion()" class="btn btn-primary"><i class="fas fa-print mr-2"></i>
                Print This Question</button>
        </div>
    </div>
</div>

<script>
    window.oesEditorIds = <?php echo json_encode($rendered_editors); ?>;
</script>
<script src="../../assets/js/oes-editor.js?v=<?php echo time(); ?>"></script>

<?php if ($qData): ?>
    <script>
        window.oesInitialData = {
            main: <?php echo json_encode($qData['question_text']); ?>,
            'main-guj': <?php echo json_encode($qData['question_text_guj'] ?? ''); ?>,
            a: <?php echo json_encode($qData['option_a']); ?>,
            'a-guj': <?php echo json_encode($qData['option_a_guj'] ?? ''); ?>,
            b: <?php echo json_encode($qData['option_b']); ?>,
            'b-guj': <?php echo json_encode($qData['option_b_guj'] ?? ''); ?>,
            c: <?php echo json_encode($qData['option_c']); ?>,
            'c-guj': <?php echo json_encode($qData['option_c_guj'] ?? ''); ?>,
            d: <?php echo json_encode($qData['option_d']); ?>,
            'd-guj': <?php echo json_encode($qData['option_d_guj'] ?? ''); ?>,
            explanation: <?php echo json_encode($qData['explanation']); ?>,
            'explanation-guj': <?php echo json_encode($qData['explanation_guj'] ?? ''); ?>,
            <?php for ($i = 1; $i <= 5; $i++): ?>
                'desc-q<?php echo $i; ?>': <?php echo json_encode($qData['desc_question_'.$i] ?? ''); ?>,
                'desc-s<?php echo $i; ?>': <?php echo json_encode($qData['desc_solution_'.$i] ?? ''); ?>,
            <?php endfor; ?>
            difficulty: '<?php echo $qData['difficulty']; ?>',
            exam_type: '<?php echo $qData['exam_type'] ?? 'both'; ?>',
            marks: '<?php echo $qData['marks']; ?>',
            negative_marks: '<?php echo $qData['negative_marks']; ?>',
            video_solution_url: <?php echo json_encode($qData['video_solution_url']); ?>,
            question_type_id: '<?php echo $qData['question_type_id']; ?>',
            correct_option: '<?php echo $qData['correct_option']; ?>',
            standard_id: '<?php echo $qData['standard_id']; ?>',
            group_id: '<?php echo $qData['group_id'] ?? ''; ?>',
            subject_id: '<?php echo $qData['subject_id']; ?>',
            chapter_id: '<?php echo $qData['chapter_id']; ?>',
            topic_id: '<?php echo $qData['topic_id']; ?>'
        };
    </script>
<?php elseif ($has_active_config): ?>
    <script>
        window.oesInitialData = {
            <?php foreach ($rendered_editors as $editorId): ?>
                <?php echo json_encode($editorId); ?>: '',
            <?php endforeach; ?>
            difficulty: '<?php echo $_SESSION['oes_active_config']['difficulty']; ?>',
            exam_type: '<?php echo $_SESSION['oes_active_config']['exam_type'] ?? 'both'; ?>',
            marks: '<?php echo $_SESSION['oes_active_config']['marks']; ?>',
            negative_marks: '<?php echo $_SESSION['oes_active_config']['negative_marks']; ?>',
            video_solution_url: '',
            question_type_id: '<?php echo $_SESSION['oes_active_config']['question_type_id']; ?>',
            correct_option: 'A',
            standard_id: '<?php echo $_SESSION['oes_active_config']['standard_id']; ?>',
            group_id: '<?php echo $_SESSION['oes_active_config']['group_id'] ?? ''; ?>',
            subject_id: '<?php echo $_SESSION['oes_active_config']['subject_id']; ?>',
            chapter_id: '<?php echo $_SESSION['oes_active_config']['chapter_id']; ?>',
            topic_id: '<?php echo $_SESSION['oes_active_config']['topic_id']; ?>'
        };
    </script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- OES DRAFT AUTO-SAVE SYSTEM ---
        const AUTO_SAVE_KEY = 'oes_draft_data';
        const isCreateMode = <?php echo ($qData) ? 'false' : 'true'; ?>;

        if (isCreateMode) {
            const savedDraft = localStorage.getItem(AUTO_SAVE_KEY);
            if (savedDraft) {
                try {
                    const draft = JSON.parse(savedDraft);
                    // Override oesInitialData with saved draft
                    window.oesInitialData = Object.assign(window.oesInitialData || {}, draft);
                    console.log('[OES AutoSave] Restored draft data successfully.');
                } catch (e) {
                    console.error('[OES AutoSave] Failed to parse draft data:', e);
                }
            }
        }

        // Initialize Quill editors (will automatically pre-fill using restored oesInitialData!)
        initOesEditor(window.oesInitialData || null);

        // Populate non-Quill inputs from restored draft on load
        if (isCreateMode) {
            const savedDraft = localStorage.getItem(AUTO_SAVE_KEY);
            if (savedDraft) {
                try {
                    const draft = JSON.parse(savedDraft);
                    
                    Object.keys(draft).forEach(key => {
                        // Skip Quill editor keys so they are not restored as raw text in textareas
                        if (window.oesEditorIds && window.oesEditorIds.includes(key)) return;

                        // Try finding input/textarea/select by name
                        const el = document.querySelector(`[name="${key}"]`);
                        if (el) {
                            if (el.type !== 'file') {
                                el.value = draft[key];
                            }
                        }
                    });
                } catch (e) {
                    console.error('[OES AutoSave] Failed to restore non-Quill inputs:', e);
                }
            }
        }

        // Save draft function
        window.saveOesDraft = function() {
            if (!isCreateMode) return;

            const draft = {};

            // Quill Editor states - dynamically loop over window.oesEditorIds!
            if (window.oesEditorIds && typeof editors !== 'undefined') {
                window.oesEditorIds.forEach(id => {
                    draft[id] = (editors && editors[id]) ? editors[id].root.innerHTML : '';
                });
            }

            // Capture all form elements dynamically (including MCQ options, marks, descriptive)
            const formElements = document.querySelectorAll('#question-form input:not([type="file"]), #question-form select, #question-form textarea');
            formElements.forEach(el => {
                if (el.name) {
                    draft[el.name] = el.value;
                }
            });

            localStorage.setItem(AUTO_SAVE_KEY, JSON.stringify(draft));
            console.log('[OES AutoSave] Draft automatically saved.');
        };

        // Debounce helper
        let autoSaveTimeout = null;
        window.triggerAutoSave = function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(window.saveOesDraft, 1000);
        };

        // Clean draft helper
        window.clearOesDraft = function() {
            localStorage.removeItem(AUTO_SAVE_KEY);
            Swal.fire({
                icon: 'success',
                title: 'Draft Cleared',
                text: 'Form draft reset successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        };

        // Keyup/Change Listeners to capture editor content and form modifications
        const formEl = document.getElementById('question-form');
        if (formEl) {
            formEl.addEventListener('input', window.triggerAutoSave);
            formEl.addEventListener('change', window.triggerAutoSave);
            formEl.addEventListener('submit', () => {
                localStorage.removeItem(AUTO_SAVE_KEY);
            });
        }

        // Capture Quill editor changes via document listeners on focus/keyup/click
        document.addEventListener('keyup', (e) => {
            if (e.target.closest('.ql-editor')) {
                window.triggerAutoSave();
            }
        });
        document.addEventListener('click', (e) => {
            if (e.target.closest('.ql-toolbar') || e.target.closest('.ql-editor')) {
                window.triggerAutoSave();
            }
        });

        // Toggle Marks and Negative Marks display based on Question Type selection
        const toggleMarksNegUI = () => {
            const typeSelect = document.getElementById('question_type_select');
            const marksNegContainer = document.getElementById('marks_neg_container');
            if (marksNegContainer) {
                let isMcq = false;
                if (typeSelect) {
                    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
                    const typeName = selectedOption ? (selectedOption.getAttribute('data-type') || selectedOption.text.trim()).toLowerCase() : '';
                    isMcq = (typeName === 'mcq' || typeSelect.value === '1');
                } else {
                    const lockedTypeInput = document.getElementsByName('question_type_id')[0];
                    const lockedTypeVal = lockedTypeInput ? lockedTypeInput.value : (window.oesInitialData ? window.oesInitialData.question_type_id : '');
                    isMcq = (lockedTypeVal === '1' || lockedTypeVal === 'mcq');
                }

                if (isMcq) {
                    marksNegContainer.style.setProperty('display', 'flex', 'important');
                } else {
                    marksNegContainer.style.setProperty('display', 'none', 'important');
                }
            }
        };

        // Event listener for dropdown changes
        const typeSelectEl = document.getElementById('question_type_select');
        if (typeSelectEl) {
            typeSelectEl.addEventListener('change', toggleMarksNegUI);
        }

        // Initialize display state
        setTimeout(toggleMarksNegUI, 100);
        setTimeout(toggleMarksNegUI, 500);

        // Correct Option Toggle functions (Supports Multi-Correct MCQs!)
        window.setCorrectOption = function(optString, idx = null) {
            let selectedOpts = optString ? optString.split(',').map(s => s.trim().toUpperCase()).filter(Boolean) : [];
            if (selectedOpts.length === 0) {
                selectedOpts = ['A'];
            }
            selectedOpts.sort();

            const inputId = idx ? `correct_option_hidden_${idx}` : 'correct_option_hidden';
            const hiddenInput = document.getElementById(inputId);
            if (hiddenInput) {
                hiddenInput.value = selectedOpts.join(',');
            }
            ['A', 'B', 'C', 'D'].forEach(o => {
                const selector = idx 
                    ? `.correct-opt-btn[data-opt="${o}"][data-idx="${idx}"]` 
                    : `.correct-opt-btn[data-opt="${o}"]:not([data-idx])`;
                const btn = document.querySelector(selector);
                const cardId = idx ? `option-card-${o}-${idx}` : `option-card-${o}`;
                const card = document.getElementById(cardId);
                if (selectedOpts.includes(o)) {
                    if (btn) {
                        btn.classList.replace('btn-outline-success', 'btn-success');
                        btn.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Correct Answer';
                    }
                    if (card) {
                        card.style.borderColor = '#2ec4b6';
                        card.style.borderWidth = '2px';
                        card.style.boxShadow = '0 8px 24px rgba(46, 196, 182, 0.15)';
                    }
                } else {
                    if (btn) {
                        btn.classList.replace('btn-success', 'btn-outline-success');
                        btn.innerHTML = '<i class="far fa-circle mr-1"></i> Mark Correct';
                    }
                    if (card) {
                        card.style.borderColor = '';
                        card.style.borderWidth = '';
                        card.style.boxShadow = '';
                    }
                }
            });
        };

        // Initialize toggle states
        setTimeout(() => {
            const bulkInputs = document.querySelectorAll('input[id^="correct_option_hidden_"]');
            if (bulkInputs.length > 0) {
                bulkInputs.forEach(inp => {
                    const idx = inp.id.replace('correct_option_hidden_', '');
                    const initialCorrect = inp.value ? inp.value.toUpperCase() : 'A';
                    window.setCorrectOption(initialCorrect, idx);
                });
            } else {
                const initialCorrect = (window.oesInitialData && window.oesInitialData.correct_option) 
                    ? window.oesInitialData.correct_option.toUpperCase() 
                    : 'A';
                window.setCorrectOption(initialCorrect);
            }
        }, 300);

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.correct-opt-btn');
            if (btn) {
                e.preventDefault();
                const opt = btn.getAttribute('data-opt');
                const idx = btn.getAttribute('data-idx');
                const inputId = idx ? `correct_option_hidden_${idx}` : 'correct_option_hidden';
                const hiddenInput = document.getElementById(inputId);
                let selectedOpts = hiddenInput && hiddenInput.value 
                    ? hiddenInput.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean) 
                    : ['A'];

                if (selectedOpts.includes(opt)) {
                    if (selectedOpts.length > 1) {
                        selectedOpts = selectedOpts.filter(o => o !== opt);
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'At least one correct answer',
                            text: 'An MCQ must have at least one correct option selected.',
                            confirmButtonColor: '#1a73e8'
                        });
                        return;
                    }
                } else {
                    selectedOpts.push(opt);
                }
                window.setCorrectOption(selectedOpts.join(','), idx);
            }
        });
        
        // Final sync for edit mode
        if (window.oesInitialData) {
            // Immediate sync to ensure correct question type state right away
            const typeSelect = document.getElementById('question_type_select');
            if (typeSelect) {
                typeSelect.value = (window.oesInitialData.question_type_id == '1') ? '1' : 'descriptive';
            }
            updateQuestionTypeUI();

            setTimeout(() => {
                // Cascade selects
                const stdSelect = document.getElementById('standard_id_select');
                if (stdSelect && window.oesInitialData.standard_id) {
                    stdSelect.value = window.oesInitialData.standard_id;
                    
                    const groupSelect = document.getElementById('group_id_select');
                    if (groupSelect && window.oesInitialData.group_id) {
                        groupSelect.value = window.oesInitialData.group_id;
                    }

                    updateSubjects().then(() => {
                        const subSelect = document.getElementById('subject_id_select');
                        if (subSelect && window.oesInitialData.subject_id) {
                            subSelect.value = window.oesInitialData.subject_id;
                            updateChapters().then(() => {
                                const chSelect = document.getElementById('chapter_id_select');
                                if (chSelect && window.oesInitialData.chapter_id) {
                                    chSelect.value = window.oesInitialData.chapter_id;
                                    updateTopics().then(() => {
                                        const topSelect = document.getElementById('topic_id_select');
                                        if (topSelect && window.oesInitialData.topic_id) {
                                            topSelect.value = window.oesInitialData.topic_id;
                                        }
                                    });
                                }
                            });
                        }
                    });
                }
            }, 300);
        }
    });
</script>


<?php if ($is_editing_config && !empty($pre)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const preStdId    = <?php echo json_encode($pre['standard_id'] ?? ''); ?>;
    const preSubId    = <?php echo json_encode($pre['subject_id'] ?? ''); ?>;
    const preChapId   = <?php echo json_encode($pre['chapter_id'] ?? ''); ?>;
    const preTopicId  = <?php echo json_encode($pre['topic_id'] ?? ''); ?>;
    const preGroupId  = <?php echo json_encode($pre['group_id'] ?? ''); ?>;

    // Pre-select Group
    const groupSel = document.getElementById('group_id_select');
    if (groupSel && preGroupId) groupSel.value = preGroupId;

    // Pre-select Standard, then cascade
    const stdSel = document.getElementById('standard_id_select');
    if (stdSel && preStdId) {
        stdSel.value = preStdId;

        // Trigger subject load
        updateSubjects().then(() => {
            const subSel = document.getElementById('subject_id_select');
            if (subSel && preSubId) {
                subSel.value = preSubId;

                // Trigger chapter load
                updateChapters().then(() => {
                    const chapSel = document.getElementById('chapter_id_select');
                    if (chapSel && preChapId) {
                        chapSel.value = preChapId;

                        // Trigger topic load
                        updateTopics().then(() => {
                            const topSel = document.getElementById('topic_id_select');
                            if (topSel && preTopicId) {
                                topSel.value = preTopicId;
                            }
                        });
                    }
                });
            }
        });
    }
});
</script>
<?php endif; ?>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>