<?php
$root = dirname(__DIR__, 3);
require_once $root . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access (Super Admin, Principle, Counsellor, Dept Head, Assistant Teacher)
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

// Force HTTPS for production to enable Voice/OCR
if (ENVIRONMENT === 'production' && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect_url");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$qData = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT *, id, difficulty, question_text, explanation, video_solution_url, solution_image, marks
        FROM tbl_oes_questions
        WHERE id = ?");
    $stmt->execute([$id]);
    $qData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the fetched question data for debugging
    $log_dir = __DIR__ . '/scratch';
    if (!is_dir($log_dir)) { mkdir($log_dir, 0777, true); }
    file_put_contents($log_dir . '/edit_save_log.txt', "[" . date('Y-m-d H:i:s') . "] INDEX LOADED FOR ID: " . $id . "\nData: " . print_r($qData, true) . "\n\n", FILE_APPEND);
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
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-dark font-weight-bold">Online Exam <span class="text-primary">System</span>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Create Question</h1>
                    <p class="text-muted small">Design high-quality questions with rich media and math support.</p>
                </div>
                <a href="question-bank.php" class="btn btn-outline-primary">
                    <i class="fas fa-list-ul mr-2"></i> Question Bank
                </a>
            </div>

            <form id="question-form" action="save-question.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="question_id" value="<?php echo $id; ?>">
                <div class="row">
                    <!-- Left Side: Main Editor -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4" id="main-editor-card">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary" id="question-body-label">Question Body
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="editor-main" class="quill-editor"></div>
                                <textarea name="question_text" id="question_text" class="d-none"></textarea>
                            </div>
                        </div>

                        <!-- MCQ Specific Section -->
                        <div id="mcq_section" style="display: none;">
                            <!-- MCQ Correct Answer -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header py-3 bg-light d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    <h6 class="m-0 font-weight-bold text-primary">Correct Answer</h6>
                                </div>
                                <div class="card-body">
                                    <select name="correct_option" class="form-control form-control-lg border-primary">
                                        <option value="A">Option A</option>
                                        <option value="B">Option B</option>
                                        <option value="C">Option C</option>
                                        <option value="D">Option D</option>
                                    </select>
                                    <small class="text-muted mt-2 d-block">Select the correct option for this MCQ
                                        question.</small>
                                </div>
                            </div>

                            <!-- MCQ Options Editors -->
                            <div class="mcq-options-grid">
                                <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                                    <div class="option-item">
                                        <div class="card shadow-sm h-100">
                                            <div
                                                class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="m-0 font-weight-bold text-secondary">Option
                                                    <?php echo strtoupper($opt); ?>
                                                </h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div id="editor-<?php echo $opt; ?>" class="quill-editor"
                                                    style="min-height: 120px;"></div>
                                                <textarea name="option_<?php echo $opt; ?>" id="option_<?php echo $opt; ?>"
                                                    class="d-none"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Bulk Descriptive Section -->
                        <div id="descriptive_bulk_section" style="display: none;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="card shadow-sm mb-4 border-left-info">
                                    <div class="card-header py-3 bg-info text-white d-flex justify-content-between">
                                        <h6 class="m-0 font-weight-bold"><?php echo $i; ?> Mark Question</h6>
                                        <span>Type: Descriptive</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label class="small font-weight-bold text-secondary">Question Text
                                                (<?php echo $i; ?> Mark)</label>
                                            <div id="editor-desc-q<?php echo $i; ?>" class="quill-editor"
                                                style="min-height: 120px;"></div>
                                            <textarea name="desc_question_<?php echo $i; ?>"
                                                id="desc_question_<?php echo $i; ?>" class="d-none"></textarea>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label class="small font-weight-bold text-secondary text-success">Solution /
                                                Explanation</label>
                                            <div id="editor-desc-s<?php echo $i; ?>" class="quill-editor"
                                                style="min-height: 100px;"></div>
                                            <textarea name="desc_solution_<?php echo $i; ?>"
                                                id="desc_solution_<?php echo $i; ?>" class="d-none"></textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-0">
                                                    <label class="small font-weight-bold text-secondary">Video Solution
                                                        Link</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="fas fa-video"></i></span>
                                                        <input type="url" name="desc_video_<?php echo $i; ?>"
                                                            class="form-control" placeholder="YouTube/Vimeo Link">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-0">
                                                    <label class="small font-weight-bold text-secondary">Solution
                                                        Image</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="fas fa-image"></i></span>
                                                        <input type="file" name="desc_image_<?php echo $i; ?>"
                                                            class="form-control" accept="image/*">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div id="standard_solution_section">
                            <div class="card shadow-sm mb-4" id="solution-card">
                                <div class="card-header py-3 bg-light">
                                    <h6 class="m-0 font-weight-bold text-primary">Question Solution</h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-group mb-4">
                                        <label class="small font-weight-bold text-secondary">Text Solution
                                            (Explanation)</label>
                                        <div id="editor-explanation" class="quill-editor" style="min-height: 150px;">
                                        </div>
                                        <textarea name="explanation" id="explanation" class="d-none"></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="small font-weight-bold text-secondary">Video Solution
                                                    (YouTube/Vimeo Link)</label>
                                                <div class="input-group shadow-sm">
                                                    <span class="input-group-text border-0 bg-white"><i
                                                            class="fas fa-video text-danger"></i></span>
                                                    <input type="url" name="video_solution_url"
                                                        class="form-control border-0"
                                                        placeholder="Paste video link here...">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="small font-weight-bold text-secondary">Solution
                                                    Image</label>
                                                <div class="input-group shadow-sm">
                                                    <span class="input-group-text border-0 bg-white"><i
                                                            class="fas fa-image text-primary"></i></span>
                                                    <input type="file" name="solution_image"
                                                        class="form-control border-0" accept="image/*">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Configuration -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm sticky-top" style="top: 20px;">
                            <div class="card-header py-3 bg-primary text-white">
                                <h6 class="m-0 font-weight-bold">Configuration</h6>
                            </div>
                            <div class="card-body">
                                <div id="main_question_type_section">
                                    <div class="form-group mb-3">
                                        <label class="small font-weight-bold">Question Type</label>
                                        <select id="question_type_select" class="form-control" required>
                                            <option value="">Select Type</option>
                                            <option value="1" data-marks="1" data-neg="0" data-type="mcq" <?php echo ($qData && $qData['question_type_id'] == 1) ? 'selected' : ''; ?>>MCQ</option>
                                            <option value="descriptive" data-type="descriptive" <?php echo ($qData && $qData['question_type_id'] != 1) ? 'selected' : ''; ?>>Descriptive</option>
                                        </select>
                                        <input type="hidden" name="question_type_id" id="real_question_type_id" value="<?php echo $qData ? htmlspecialchars($qData['question_type_id']) : ''; ?>">
                                    </div>
                                </div>


                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Difficulty Level</label>
                                    <select name="difficulty" class="form-control" required>
                                        <option value="Level A">Level A</option>
                                        <option value="Level B">Level B</option>
                                        <option value="Level C">Level C</option>
                                        <option value="Level D">Level D</option>
                                        <option value="Level E">Level E</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Standard</label>
                                    <select name="standard_id" id="standard_id_select" class="form-control">
                                        <option value="">General / All Standards</option>
                                        <?php
                                        $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC");
                                        while ($std = $standards->fetch()) {
                                            echo "<option value='{$std['stdid']}'>{$std['stdtext']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Subject</label>
                                    <select name="subject_id" id="subject_id_select" class="form-control" required>
                                        <option value="">Select Subject</option>
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
                                    <select name="chapter_id" id="chapter_id_select" class="form-control">
                                        <option value="">Select Subject First</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Topic</label>
                                    <select name="topic_id" id="topic_id_select" class="form-control">
                                        <option value="">Select Chapter First</option>
                                    </select>
                                </div>


                                <input type="hidden" name="marks" id="marks_input" value="1.0">
                                <input type="hidden" name="negative_marks" id="negative_marks_input" value="0.0">

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
            </form>

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

<script src="../../assets/js/oes-editor.js?v=<?php echo time(); ?>"></script>

<?php if ($qData): ?>
    <script>
        window.oesInitialData = {
            main: <?php echo json_encode($qData['question_text']); ?>,
            a: <?php echo json_encode($qData['option_a']); ?>,
            b: <?php echo json_encode($qData['option_b']); ?>,
            c: <?php echo json_encode($qData['option_c']); ?>,
            d: <?php echo json_encode($qData['option_d']); ?>,
            explanation: <?php echo json_encode($qData['explanation']); ?>,
            <?php for ($i = 1; $i <= 5; $i++): ?>
                'desc-q<?php echo $i; ?>': <?php echo json_encode($qData['desc_question_'.$i] ?? ''); ?>,
                'desc-s<?php echo $i; ?>': <?php echo json_encode($qData['desc_solution_'.$i] ?? ''); ?>,
            <?php endfor; ?>
            difficulty: '<?php echo $qData['difficulty']; ?>',
            marks: '<?php echo $qData['marks']; ?>',
            negative_marks: '<?php echo $qData['negative_marks']; ?>',
            video_solution_url: <?php echo json_encode($qData['video_solution_url']); ?>,
            question_type_id: '<?php echo $qData['question_type_id']; ?>',
            correct_option: '<?php echo $qData['correct_option']; ?>',
            standard_id: '<?php echo $qData['standard_id']; ?>',
            subject_id: '<?php echo $qData['subject_id']; ?>',
            chapter_id: '<?php echo $qData['chapter_id']; ?>',
            topic_id: '<?php echo $qData['topic_id']; ?>'
        };
    </script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        initOesEditor(window.oesInitialData || null);
        
        // Final sync for edit mode
        if (window.oesInitialData) {
            // Immediate sync to ensure correct question type state right away
            const typeSelect = document.getElementById('question_type_select');
            if (typeSelect) {
                typeSelect.value = (window.oesInitialData.question_type_id == '1') ? '1' : 'descriptive';
                updateQuestionTypeUI();
            }

            setTimeout(() => {
                // Cascade selects
                const stdSelect = document.getElementById('standard_id_select');
                if (stdSelect && window.oesInitialData.standard_id) {
                    stdSelect.value = window.oesInitialData.standard_id;
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

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>