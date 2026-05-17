<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if (!$exam_id)
    die("Invalid Exam ID");

try {
    $stmt = $conn->prepare("SELECT e.*, s.stdtext FROM tbl_oes_exams e LEFT JOIN standard s ON e.standard_id = s.stdid WHERE e.id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam)
        die("Exam not found.");

    $stmt_q = $conn->prepare("SELECT eq.order_no, q.*, sub.subject_name FROM tbl_oes_exam_questions eq JOIN tbl_oes_questions q ON eq.question_id = q.id LEFT JOIN tbl_subjects sub ON q.subject_id = sub.id WHERE eq.exam_id = ? ORDER BY eq.order_no ASC");
    $stmt_q->execute([$exam_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = "Print Exam Paper | OES";

include PORTAL_INCLUDE_PATH . 'header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.4/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.4/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.4/dist/contrib/auto-render.min.js"></script>

<style>
    :root { --paper-font-size: 14px; }
    
    /* Portal UI Override for Print */
    @media print {
        .app-sidebar, .app-header, .print-settings, .main-footer, .nav-tabs, .btn, .breadcrumb {
            display: none !important;
        }
        .app-main, .app-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .paper-container {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
        body { background: #fff !important; }
    }

    /* Paper Styling */
    .paper-container {
        width: 210mm; /* A4 width */
        min-height: 297mm;
        margin: 20px auto;
        background: #fff;
        padding: 15mm;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        font-family: 'Times New Roman', serif;
        font-size: var(--paper-font-size);
        color: #000;
        position: relative;
    }

    .paper-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
    .paper-header h1 { font-size: 24px; margin: 0; font-weight: bold; }
    .paper-header p { margin: 5px 0; font-size: 16px; }

    .paper-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #000;
    }
    .meta-item { width: 33.33%; margin-bottom: 5px; }

    .instructions {
        margin-bottom: 25px;
        padding: 10px;
        border: 1px solid #eee;
        background: #fdfdfd;
        font-style: italic;
    }
    .instructions h4 { margin-top: 0; font-size: 14px; font-weight: bold; }

    .question-block { margin-bottom: 25px; page-break-inside: avoid; break-inside: avoid; }
    .q-main { display: flex; width: 100%; margin-bottom: 8px; }
    .q-num-cell { width: 40px; font-weight: bold; flex-shrink: 0; }
    .q-text-cell { flex-grow: 1; position: relative; }
    .q-marks { float: right; font-weight: normal; font-size: 0.85em; font-style: italic; color: #444; margin-left: 10px; }

    .options-grid {
        display: flex;
        flex-wrap: wrap;
        margin-left: 40px;
        width: calc(100% - 40px);
    }
    .option { display: flex; width: 50%; margin-bottom: 8px; align-items: flex-start; }
    .opt-label { font-weight: bold; width: 28px; flex-shrink: 0; }
    .opt-text { flex-grow: 1; padding-right: 10px; }

    .q-img { max-width: 100%; max-height: 180px; margin: 10px 0; display: block; border-radius: 5px; }

    /* Option Styles */
    .options-grid.list-view .option { width: 100% !important; }
    .options-grid.inline-view .option { width: 25%; }
    @media (max-width: 800px) { .options-grid.inline-view .option { width: 50%; } }

    /* Spacing Variations */
    .question-block.compact { margin-bottom: 10px; }
    .question-block.wide { margin-bottom: 70px; }

    /* Footer / Signatures */
    .paper-footer {
        margin-top: 50px;
        display: none;
        justify-content: space-between;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }
    .signature-box { text-align: center; width: 200px; }
    .signature-line { border-top: 1px solid #000; margin-bottom: 5px; }

    /* Control Panel UI */
    .print-settings {
        background: #fff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
        margin-bottom: 25px;
    }
    .setting-group { display: flex; flex-direction: column; gap: 5px; }
    .setting-group label { font-size: 11px; text-transform: uppercase; font-weight: bold; color: #666; }
    .setting-group select { padding: 8px 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 13px; outline: none; cursor: pointer; background: #f9f9f9; }
</style>

<?php include PORTAL_INCLUDE_PATH . 'navbar.php'; ?>
<?php include PORTAL_INCLUDE_PATH . 'sidebar.php'; ?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-dark font-weight-bold">Print <span class="text-primary">Question Paper</span></h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="manage-exams.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                        <i class="fas fa-arrow-left me-1"></i> Back to Exams
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <!-- Configuration Panel -->
            <div class="print-settings no-print">
                <div class="setting-group">
                    <label>Header</label>
                    <select id="setting-header" onchange="toggleHeader(this.value); updateLinks();">
                        <option value="show">Show School Header</option>
                        <option value="hide">Hide (For Letterhead)</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label>Font Size</label>
                    <select id="setting-font" onchange="updateFontSize(this.value); updateLinks();">
                        <option value="12px">Small</option>
                        <option value="14px" selected>Medium</option>
                        <option value="16px">Large</option>
                        <option value="18px">Extra Large</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label>Option Style</label>
                    <select id="setting-options" onchange="updateOptionLayout(this.value); updateLinks();">
                        <option value="grid">2-Column Grid</option>
                        <option value="list">Vertical List</option>
                        <option value="inline">4-Column Inline</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label>Spacing</label>
                    <select id="setting-spacing" onchange="updateSpacing(this.value); updateLinks();">
                        <option value="normal">Normal</option>
                        <option value="compact">Compact</option>
                        <option value="wide">Wide (Rough Work)</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label>Signatures</label>
                    <select id="setting-footer" onchange="toggleFooter(this.value); updateLinks();">
                        <option value="hide">Hide</option>
                        <option value="show">Show</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label>Marks</label>
                    <select id="setting-marks" onchange="toggleMarks(this.value); updateLinks();">
                        <option value="show">Show</option>
                        <option value="hide">Hide</option>
                    </select>
                </div>

                <div class="ms-auto d-flex gap-2">
                    <a href="generate-pdf-paper.php?exam_id=<?= $exam_id ?>" id="btn-pdf-download" class="btn btn-dark" style="border-radius:10px;" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Print PDF
                    </a>
                    <a href="export-word-paper.php?exam_id=<?= $exam_id ?>" id="btn-word-download" class="btn btn-primary" style="background:#2b5797; border:none; border-radius:10px;">
                        <i class="fas fa-file-word me-1"></i> Word
                    </a>
                </div>
            </div>

            <!-- Paper Preview Area -->
            <div class="paper-container" id="printable-paper">
                <div id="paper-header-block" class="paper-header">
                    <h1>GYANMANJARI CAREER ACADEMY</h1>
                    <p style="font-weight: bold; font-size: 1.2em;"><?= htmlspecialchars((string)$exam['title']) ?></p>
                </div>

                <div class="paper-meta">
                    <div class="meta-item"><strong>Standard:</strong> <?= htmlspecialchars((string)$exam['stdtext'] ?: 'N/A') ?></div>
                    <div class="meta-item" style="text-align:center;"><strong>Subject:</strong> <?= htmlspecialchars((string)$questions[0]['subject_name'] ?: 'N/A') ?></div>
                    <div class="meta-item" style="text-align:right;"><strong>Max Marks:</strong> <?= (float)$exam['total_marks'] ?></div>
                    
                    <div class="meta-item"><strong>Date:</strong> <?= date('d-m-Y', strtotime($exam['start_time'])) ?></div>
                    <div class="meta-item" style="text-align:center;"><strong>Time:</strong> <?= date('h:i A', strtotime($exam['start_time'])) ?> - <?= date('h:i A', strtotime($exam['end_time'])) ?></div>
                    <div class="meta-item" style="text-align:right;"><strong>Duration:</strong> <?= (int)$exam['duration_mins'] ?> Mins</div>
                </div>

                <?php if (!empty($exam['description'])): ?>
                    <div id="instruction-block" class="instructions">
                        <h4>General Instructions:</h4>
                        <?= nl2br(htmlspecialchars((string)$exam['description'])) ?>
                    </div>
                <?php endif; ?>

                <div id="questions-wrapper" class="questions">
                    <?php foreach ($questions as $q): ?>
                        <div class="question-block">
                            <div class="q-main">
                                <div class="q-num-cell">Q.<?= htmlspecialchars((string)$q['order_no']) ?></div>
                                <div class="q-text-cell">
                                    <div class="rich-content"><?= $q['question_text'] ?></div>
                                    <span class="q-marks">(<?= $q['marks'] ?> Marks)</span>
                                    <?php if (!empty($q['question_image'])): ?>
                                        <img src="<?= PORTAL_URL ?>/uploads/questions/<?= htmlspecialchars((string)$q['question_image']) ?>"
                                            class="q-img" alt="Question Image">
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="options-grid">
                                <div class="option">
                                    <span class="opt-label">(A)</span>
                                    <div class="opt-text"><?= $q['option_a'] ?></div>
                                </div>
                                <div class="option">
                                    <span class="opt-label">(B)</span>
                                    <div class="opt-text"><?= $q['option_b'] ?></div>
                                </div>
                                <div class="option">
                                    <span class="opt-label">(C)</span>
                                    <div class="opt-text"><?= $q['option_c'] ?></div>
                                </div>
                                <div class="option">
                                    <span class="opt-label">(D)</span>
                                    <div class="opt-text"><?= $q['option_d'] ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="paper-footer-block" class="paper-footer">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <p>Student Signature</p>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <p>Supervisor Signature</p>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <p>Principal Signature</p>
                    </div>
                </div>

                <div style="text-align:center; margin-top:50px; font-weight:bold; border-top:1px solid #eee; padding-top:20px;">
                    *** END OF PAPER ***
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function updateLinks() {
        const header = document.getElementById('setting-header').value;
        const fontSize = document.getElementById('setting-font').value;
        const options = document.getElementById('setting-options').value;
        const spacing = document.getElementById('setting-spacing').value;
        const footer = document.getElementById('setting-footer').value;
        const marks = document.getElementById('setting-marks').value;
        
        const btnWord = document.getElementById('btn-word-download');
        const btnPdf = document.getElementById('btn-pdf-download');
        
        let params = `&header=${header}&cols=1&font_size=${fontSize}&opt_style=${options}&spacing=${spacing}&footer=${footer}&marks=${marks}`;
        
        btnWord.href = `export-word-paper.php?exam_id=<?= $exam_id ?>` + params;
        btnPdf.href = `generate-pdf-paper.php?exam_id=<?= $exam_id ?>` + params;
    }

    function toggleHeader(status) {
        const block = document.getElementById('paper-header-block');
        block.style.display = (status === 'hide' ? 'none' : 'block');
        updateLinks();
    }

    function updateFontSize(size) {
        document.documentElement.style.setProperty('--paper-font-size', size);
        updateLinks();
    }

    function updateOptionLayout(style) {
        const grids = document.querySelectorAll('.options-grid');
        grids.forEach(g => {
            g.classList.remove('list-view', 'inline-view');
            if (style === 'list') g.classList.add('list-view');
            if (style === 'inline') g.classList.add('inline-view');
        });
        updateLinks();
    }

    function updateSpacing(spacing) {
        const blocks = document.querySelectorAll('.question-block');
        blocks.forEach(b => {
            b.classList.remove('compact', 'wide');
            if (spacing === 'compact') b.classList.add('compact');
            if (spacing === 'wide') b.classList.add('wide');
        });
        updateLinks();
    }

    function toggleFooter(status) {
        const block = document.getElementById('paper-footer-block');
        block.style.display = (status === 'show' ? 'flex' : 'none');
        updateLinks();
    }

    function toggleMarks(status) {
        const marks = document.querySelectorAll('.q-marks');
        marks.forEach(m => m.style.display = (status === 'hide' ? 'none' : 'inline'));
        updateLinks();
    }

    document.addEventListener("DOMContentLoaded", function () {
        renderMathInElement(document.getElementById('printable-paper'), {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$', right: '$', display: false },
                { left: '\\(', right: '\\)', display: false },
                { left: '\\[', right: '\\]', display: true }
            ],
            throwOnError: false
        });
        updateLinks();
    });
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>