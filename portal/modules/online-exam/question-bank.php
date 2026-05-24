<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

// Filters
$standard_filter = isset($_GET['standard_id']) ? (int)$_GET['standard_id'] : '';
$group_filter = isset($_GET['group_id']) ? (int)$_GET['group_id'] : '';
$subject_filter = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : '';
$chapter_filter = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : '';
$topic_filter = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : '';
$type_filter = isset($_GET['question_type_id']) ? (int)$_GET['question_type_id'] : '';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$exam_type_filter = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

$where_clauses = ["1=1"];
$params = [];

if ($standard_filter) {
    $where_clauses[] = "q.standard_id = :standard_id";
    $params[':standard_id'] = $standard_filter;
}
if ($group_filter) {
    $where_clauses[] = "q.group_id = :group_id";
    $params[':group_id'] = $group_filter;
}
if ($subject_filter) {
    $where_clauses[] = "q.subject_id = :subject_id";
    $params[':subject_id'] = $subject_filter;
}
if ($chapter_filter) {
    $where_clauses[] = "q.chapter_id = :chapter_id";
    $params[':chapter_id'] = $chapter_filter;
}
if ($topic_filter) {
    $where_clauses[] = "q.topic_id = :topic_id";
    $params[':topic_id'] = $topic_filter;
}
if ($type_filter) {
    $where_clauses[] = "q.question_type_id = :type_id";
    $params[':type_id'] = $type_filter;
}
if ($difficulty_filter) {
    $where_clauses[] = "q.difficulty = :difficulty";
    $params[':difficulty'] = $difficulty_filter;
}
if ($exam_type_filter) {
    $where_clauses[] = "q.exam_type = :exam_type";
    $params[':exam_type'] = $exam_type_filter;
}
if ($search_query) {
    $where_clauses[] = "q.question_text LIKE :search";
    $params[':search'] = "%$search_query%";
}

// Add standard active filter
$where_clauses[] = "q.status = 1";

$where_sql = implode(" AND ", $where_clauses);

$sql = "SELECT q.id, q.marks, q.difficulty, q.group_id, q.exam_type,
               q.question_text, q.question_text_guj,
               q.option_a, q.option_a_guj,
               q.option_b, q.option_b_guj,
               q.option_c, q.option_c_guj,
               q.option_d, q.option_d_guj,
               q.correct_option,
               s.subject_name as subname, qt.type_name, c.chapter as chapter_name, t.topic_name_english as topic_name,
               g.group_name,
               (SELECT COUNT(*) FROM tbl_oes_exam_questions WHERE question_id = q.id) as exam_count,
               (SELECT GROUP_CONCAT(CONCAT(e.title, ' (', DATE_FORMAT(e.start_time, '%d-%m-%Y'), ')') SEPARATOR '||') FROM tbl_oes_exam_questions eq JOIN tbl_oes_exams e ON eq.exam_id = e.id WHERE eq.question_id = q.id) as exams_list
        FROM tbl_oes_questions q
        LEFT JOIN tbl_subjects s ON q.subject_id = s.id 
        LEFT JOIN tbl_oes_question_types qt ON q.question_type_id = qt.id
        LEFT JOIN tbl_chapters c ON q.chapter_id = c.chpid
        LEFT JOIN tbl_topics t ON q.topic_id = t.id
        LEFT JOIN tbl_group g ON q.group_id = g.id
        WHERE $where_sql 
        ORDER BY q.id ASC";

// Pagination Logic
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

// Count total items with current filters
$count_sql = "SELECT COUNT(*) FROM tbl_oes_questions q WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$totalItems = $count_stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Final SQL with pagination
$sql_paginated = $sql . " LIMIT $perPage OFFSET $offset";

$stmt = $conn->prepare($sql_paginated);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Build Base URL for pagination (preserving existing filters)
$url_params = $_GET;
unset($url_params['page']);
$baseUrl = 'question-bank.php' . (!empty($url_params) ? '?' . http_build_query($url_params) : '');

require_once PORTAL_PATH . 'common/pagination.php';

include PORTAL_INCLUDE_PATH . 'header.php';
?>
<!-- KaTeX for math rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/mhchem.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: false}, {left: '$', right: '$', display: false}, {left: '\\(', right: '\\)', display: false}, {left: '\\[', right: '\\]', display: false}]});"></script>


<?php
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <p class="text-muted small">View and filter questions for your exams.</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="export-questions.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-question-bank-dbb5d6" title="Export CSV">
                        <i class="fas fa-download text-muted mr-2"></i> Export
                    </a>
                    <!-- Hidden file inputs for bulk import -->
                    <input type="file" id="bulk-csv-input" accept=".csv" class="css-question-bank-93b8ea">
                    <input type="file" id="bulk-word-input" accept=".docx" class="css-question-bank-93b8ea">
                    <!-- Hiding Bulk CSV as requested -->
                    <!--
                    <button onclick="document.getElementById('bulk-csv-input').click()" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-question-bank-dbb5d6" title="Bulk Import via CSV">
                        <i class="fas fa-file-csv text-success mr-2"></i> Bulk CSV
                    </button>
                    -->
                    <a href="bulk-import-word.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-question-bank-dbb5d6" title="Bulk Import via Word (.docx)">
                        <i class="fas fa-file-word text-primary mr-2"></i> Bulk Word
                    </a>
                    <!-- Hiding CSV Template as requested -->
                    <!--
                    <a href="sample_questions_template.csv" download class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-question-bank-dbb5d6" title="Download CSV Template">
                        <i class="fas fa-table text-warning mr-2"></i> CSV Template
                    </a>
                    -->
                    <a href="download-word-template.php?type=simple" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-question-bank-dbb5d6" title="Download Simplified Word Template (11 Columns)">
                        <i class="fas fa-file-word text-success mr-2"></i> Word Template (Simple)
                    </a>
                    <a href="download-word-template.php?type=full" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-question-bank-dbb5d6" title="Download Full Word Template (22 Columns)">
                        <i class="fas fa-file-word text-info mr-2"></i> Word Template (Full)
                    </a>
                    <a href="index.php" class="btn btn-primary shadow-sm d-flex align-items-center justify-content-center px-4 css-question-bank-dbb5d6">
                        <i class="fas fa-plus mr-2 css-question-bank-af89d6"></i> Create New Question
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                        let msg = '<?php echo htmlspecialchars($_GET['msg']); ?>';
                        let title = 'Question ' + msg + ' successfully!';
                        let icon = 'success';
                        
                        if (msg.startsWith('imported_')) {
                            const count = msg.split('_')[1];
                            title = count + ' Questions imported successfully!';
                        }
                        
                        Toast.fire({
                            icon: icon,
                            title: title
                        });

                        // Instantly clean msg parameter from URL address bar
                        if (window.history.replaceState) {
                            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace(/[?&]msg=[^&]+/, '').replace(/^&/, '?');
                            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
                        }
                    });
                </script>
            <?php endif; ?>

            <!-- Smart Filter -->
            <div class="card shadow-sm mb-4 border-0 css-question-bank-f1ccc3">
                <div class="card-body p-4">
                    <form method="GET" id="filter-form" class="row g-3 align-items-end">
                        <!-- Step 1: Standard -->
                        <div class="col-md-2">
                            <label class="small font-weight-bold text-uppercase text-muted mb-2"><i class="fas fa-graduation-cap mr-1"></i> 1. Standard</label>
                            <select name="standard_id" id="filter_standard" class="form-control border-0 shadow-sm css-question-bank-1033dd">
                                <option value="">Select Standard</option>
                                <option value="11" <?php echo ($standard_filter == 11) ? 'selected' : ''; ?>>11th</option>
                                <option value="12" <?php echo ($standard_filter == 12) ? 'selected' : ''; ?>>12th</option>
                                <option value="13" <?php echo ($standard_filter == 13) ? 'selected' : ''; ?>>Reneet</option>
                            </select>
                        </div>

                        <!-- Step 1b: Group -->
                        <div class="col-md-2">
                            <label class="small font-weight-bold text-uppercase text-muted mb-2"><i class="fas fa-users mr-1"></i> Group</label>
                            <select name="group_id" id="filter_group" class="form-control border-0 shadow-sm css-question-bank-1033dd">
                                <option value="">Select Group</option>
                                <?php
                                $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC");
                                while ($g = $groups->fetch()) {
                                    $selected = ($group_filter == $g['id']) ? 'selected' : '';
                                    echo "<option value='{$g['id']}' $selected>{$g['group_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Step 2: Subject -->
                        <div class="col-md-2 filter-step" id="step_subject" <?php echo !$standard_filter ? 'style="display:none;"' : ''; ?>>
                            <label class="small font-weight-bold text-uppercase text-muted mb-2"><i class="fas fa-book mr-1"></i> 2. Subject</label>
                            <select name="subject_id" id="filter_subject" class="form-control border-0 shadow-sm css-question-bank-1033dd">
                                <option value="">Select Subject</option>
                                <?php
                                if ($standard_filter) {
                                    $stmt = $conn->prepare("SELECT id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 AND standard_id = ? ORDER BY subject_name ASC");
                                    $stmt->execute([$standard_filter]);
                                    while ($sub = $stmt->fetch()) {
                                        $selected = ($subject_filter == $sub['id']) ? 'selected' : '';
                                        echo "<option value='{$sub['id']}' $selected>{$sub['subject_name']}</option>";
                                    }
                                } else {
                                    $subjects = $conn->query("SELECT id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC");
                                    while ($sub = $subjects->fetch()) {
                                        $selected = ($subject_filter == $sub['id']) ? 'selected' : '';
                                        echo "<option value='{$sub['id']}' $selected>{$sub['subject_name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Step 3: Chapter -->
                        <div class="col-md-2 filter-step" id="step_chapter" <?php echo !$subject_filter ? 'style="display:none;"' : ''; ?>>
                            <label class="small font-weight-bold text-uppercase text-muted mb-2"><i class="fas fa-bookmark mr-1"></i> 3. Chapter</label>
                            <select name="chapter_id" id="filter_chapter" class="form-control border-0 shadow-sm css-question-bank-1033dd">
                                <option value="">Select Chapter</option>
                                <?php
                                if ($subject_filter) {
                                    $stmt = $conn->prepare("SELECT chpid, chapter FROM tbl_chapters WHERE subid = ? AND activated = 1 AND is_deleted = 0 ORDER BY chapter ASC");
                                    $stmt->execute([$subject_filter]);
                                    while ($ch = $stmt->fetch()) {
                                        $selected = ($chapter_filter == $ch['chpid']) ? 'selected' : '';
                                        echo "<option value='{$ch['chpid']}' $selected>{$ch['chapter']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Step 4: Topic -->
                        <div class="col-md-2 filter-step" id="step_topic" <?php echo !$chapter_filter ? 'style="display:none;"' : ''; ?>>
                            <label class="small font-weight-bold text-uppercase text-muted mb-2"><i class="fas fa-tag mr-1"></i> 4. Topic</label>
                            <select name="topic_id" id="filter_topic" class="form-control border-0 shadow-sm css-question-bank-1033dd">
                                <option value="">Select Topic</option>
                                <?php
                                if ($subject_filter) {
                                    $stmt = $conn->prepare("SELECT id, topic_name_english as topic_name FROM tbl_topics WHERE subject_id = ? AND activated = 1 AND is_deleted = 0 ORDER BY topic_name_english ASC");
                                    $stmt->execute([$subject_filter]);
                                    while ($tp = $stmt->fetch()) {
                                        $selected = ($topic_filter == $tp['id']) ? 'selected' : '';
                                        echo "<option value='{$tp['id']}' $selected>{$tp['topic_name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Step 5: Question Type -->
                        <div class="col-md-1 filter-step" id="step_type" <?php echo !$topic_filter ? 'style="display:none;"' : ''; ?>>
                            <label class="small font-weight-bold text-uppercase text-muted mb-2">5. Type</label>
                            <select name="question_type_id" id="filter_type" class="form-control border-0 shadow-sm css-question-bank-ef6051">
                                <option value="">Type</option>
                                <?php
                                $q_types = $conn->query("SELECT id, type_name FROM tbl_oes_question_types WHERE status = 1 ORDER BY type_name ASC");
                                while ($qt = $q_types->fetch()) {
                                    $selected = ($type_filter == $qt['id']) ? 'selected' : '';
                                    echo "<option value='{$qt['id']}' $selected>{$qt['type_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Step 6: Difficulty -->
                        <div class="col-md-1 filter-step" id="step_difficulty" <?php echo !$type_filter ? 'style="display:none;"' : ''; ?>>
                            <label class="small font-weight-bold text-uppercase text-muted mb-2">6. Level</label>
                            <select name="difficulty" id="filter_difficulty" class="form-control border-0 shadow-sm css-question-bank-ef6051">
                                <option value="">Lvl</option>
                                <?php foreach(['Level A', 'Level B', 'Level C', 'Level D', 'Level E'] as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>" <?php echo $difficulty_filter == $lvl ? 'selected' : ''; ?>><?php echo str_replace('Level ', '', $lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Step 7: Exam Type -->
                        <div class="col-md-2">
                            <label class="small font-weight-bold text-uppercase text-muted mb-2"><i class="fas fa-clipboard-list mr-1"></i> Exam Type</label>
                            <select name="exam_type" id="filter_exam_type" class="form-control border-0 shadow-sm css-question-bank-1033dd">
                                <option value="">All Exam Types</option>
                                <option value="both" <?php echo $exam_type_filter == 'both' ? 'selected' : ''; ?>>Practice & Final</option>
                                <option value="practice" <?php echo $exam_type_filter == 'practice' ? 'selected' : ''; ?>>Practice Test Only</option>
                                <option value="final" <?php echo $exam_type_filter == 'final' ? 'selected' : ''; ?>>Final Exam Only</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 shadow-sm css-question-bank-ec1b6d">
                                <i class="fas fa-search mr-1"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Question List -->
            <div class="row">
                <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $q): ?>
                        <div class="col-12 mb-4">
                            <div class="card shadow-sm border-left-primary h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1 d-flex align-items-center flex-wrap gap-1">
                                                <span class="badge badge-dark">ID: <?php echo $q['id']; ?></span>
                                                <span class="badge badge-primary"><?php echo htmlspecialchars($q['subname'] ?? 'General'); ?></span>
                                                <?php if (!empty($q['group_name'])): ?>
                                                    <span class="badge badge-danger"><i class="fas fa-users mr-1"></i><?php echo htmlspecialchars($q['group_name']); ?></span>
                                                <?php endif; ?>
                                                <span class="badge badge-success"><?php echo htmlspecialchars($q['chapter_name'] ?? 'General'); ?></span>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($q['topic_name'] ?? 'General Topic'); ?></span>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($q['type_name'] ?? 'N/A'); ?></span>
                                                <span class="badge badge-warning text-dark"><?php echo htmlspecialchars($q['difficulty'] ?? 'N/A'); ?></span>
                                                <?php 
                                                $exam_type_label = match($q['exam_type'] ?? 'both') {
                                                    'practice' => 'Practice Test',
                                                    'final' => 'Final Exam',
                                                    default => 'Practice & Final'
                                                };
                                                $exam_type_class = match($q['exam_type'] ?? 'both') {
                                                    'practice' => 'border-success text-success bg-white',
                                                    'final' => 'border-danger text-danger bg-white',
                                                    default => 'border-secondary text-secondary bg-white'
                                                };
                                                ?>
                                                <span class="badge badge-pill border <?php echo $exam_type_class; ?>"><i class="fas fa-clipboard-list mr-1"></i><?php echo htmlspecialchars($exam_type_label); ?></span>
                                                <span class="badge badge-light text-dark border"><i class="fas fa-chart-bar text-info mr-1"></i> Asked: <strong><?php echo $q['exam_count']; ?></strong> time<?php echo $q['exam_count'] == 1 ? '' : 's'; ?></span>
                                                <span class="text-muted ml-2"><?php echo $q['marks']; ?> Marks</span>
                                            </div>
                                            <?php if ($q['exam_count'] > 0): ?>
                                                <div class="mt-1 mb-2 text-xs text-muted css-question-bank-d05093">
                                                    <i class="fas fa-calendar-alt text-info mr-1"></i> Asked in: 
                                                    <?php 
                                                    $ex_arr = explode('||', $q['exams_list']);
                                                    foreach ($ex_arr as $e_idx => $ex_item) {
                                                        echo ($e_idx > 0 ? ', ' : '') . '<span class="badge badge-pill bg-light text-dark font-weight-bold border">' . htmlspecialchars($ex_item) . '</span>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Language Selector Switch for Bilingual Questions -->
                                            <div class="mb-3 d-flex justify-content-end">
                                                <ul class="nav nav-pills" id="langTab-<?php echo $q['id']; ?>" role="tablist" style="font-size: 0.75rem; background: #f1f5f9; padding: 3px; border-radius: 20px;">
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link active py-1 px-3 border-0" id="en-tab-<?php echo $q['id']; ?>" data-toggle="pill" data-bs-toggle="pill" data-bs-target="#en-content-<?php echo $q['id']; ?>" href="#en-content-<?php echo $q['id']; ?>" role="tab" style="border-radius: 18px; font-weight: 600;">English</a>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link py-1 px-3 border-0" id="guj-tab-<?php echo $q['id']; ?>" data-toggle="pill" data-bs-toggle="pill" data-bs-target="#guj-content-<?php echo $q['id']; ?>" href="#guj-content-<?php echo $q['id']; ?>" role="tab" style="border-radius: 18px; font-weight: 600;">ગુજરાતી</a>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="tab-content" id="langTabContent-<?php echo $q['id']; ?>">
                                                <!-- English Version Content -->
                                                <div class="tab-pane fade show active" id="en-content-<?php echo $q['id']; ?>" role="tabpanel">
                                                    <div class="h5 mb-3 font-weight-bold text-gray-800 question-content css-question-bank-8172fa">
                                                        <?php echo $q['question_text']; ?>
                                                    </div>
                                                    
                                                    <?php if (($q['type_name'] ?? '') === 'MCQ'): ?>
                                                    <div class="row">
                                                        <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                                                            <?php $is_correct = (strtoupper($opt) == $q['correct_option']); ?>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="p-2 border rounded <?php echo $is_correct ? 'bg-success text-white border-success' : 'bg-light'; ?>">
                                                                    <strong><?php echo strtoupper($opt); ?>:</strong> <?php echo $q['option_'.$opt]; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Gujarati Version Content -->
                                                <div class="tab-pane fade" id="guj-content-<?php echo $q['id']; ?>" role="tabpanel">
                                                    <div class="h5 mb-3 font-weight-bold text-gray-800 question-content css-question-bank-8172fa">
                                                        <?php echo !empty($q['question_text_guj']) ? $q['question_text_guj'] : '<span class="text-muted italic small">No Gujarati text provided.</span>'; ?>
                                                    </div>
                                                    
                                                    <?php if (($q['type_name'] ?? '') === 'MCQ'): ?>
                                                    <div class="row">
                                                        <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                                                            <?php $is_correct = (strtoupper($opt) == $q['correct_option']); ?>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="p-2 border rounded <?php echo $is_correct ? 'bg-success text-white border-success' : 'bg-light'; ?>">
                                                                    <strong><?php echo strtoupper($opt); ?>:</strong> <?php echo !empty($q['option_'.$opt.'_guj']) ? $q['option_'.$opt.'_guj'] : '<span class="text-muted italic small">No Gujarati text</span>'; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="d-flex gap-2">
                                                <a href="index.php?id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm" style="border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="deleteQuestion(<?php echo $q['id']; ?>)" class="btn btn-sm btn-outline-danger shadow-sm" style="border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                        <h4 class="text-gray-500">No questions found</h4>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-4 mb-5">
                <div class="card shadow-sm border-0 css-question-bank-dc9ce7">
                    <div class="card-body py-3">
                        <?php echo renderPagination($currentPage, $totalPages, $baseUrl, 2, $totalItems, 'questions'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>



<script>
const allSubjects = <?php 
    $sub_all = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($sub_all); 
?>;
const allChapters = <?php 
    $ch_all = $conn->query("SELECT chpid, subid, chapter FROM tbl_chapters WHERE activated = 1 AND is_deleted = 0 ORDER BY chapter ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($ch_all); 
?>;
const allTopics = <?php 
    $tp_all = $conn->query("SELECT id, subject_id, chapter_id, topic_name_english FROM tbl_topics WHERE activated = 1 AND is_deleted = 0 ORDER BY topic_name_english ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($tp_all); 
?>;

document.addEventListener('DOMContentLoaded', function() {
    const filterStandard = document.getElementById('filter_standard');
    const filterSubject = document.getElementById('filter_subject');
    const filterChapter = document.getElementById('filter_chapter');
    const filterTopic = document.getElementById('filter_topic');
    const filterType = document.getElementById('filter_type');
    const filterDifficulty = document.getElementById('filter_difficulty');

    const stepSubject = document.getElementById('step_subject');
    const stepChapter = document.getElementById('step_chapter');
    const stepTopic = document.getElementById('step_topic');
    const stepType = document.getElementById('step_type');
    const stepDifficulty = document.getElementById('step_difficulty');

    const stdMap = {
        11: [1, 2],
        12: [4, 5],
        13: [6]
    };

    filterStandard.addEventListener('change', function() {
        if (this.value) {
            const selectedStd = this.value;
            const allowedStds = stdMap[selectedStd] || [parseInt(selectedStd)];
            filterSubject.innerHTML = '<option value="">Select Subject</option>';
            
            const addedNames = new Set();
            allSubjects.filter(s => allowedStds.includes(parseInt(s.standard_id))).forEach(s => {
                if (!addedNames.has(s.subject_name)) {
                    addedNames.add(s.subject_name);
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.innerText = s.subject_name;
                    filterSubject.appendChild(opt);
                }
            });
            $(stepSubject).fadeIn();
        } else {
            $('.filter-step').fadeOut();
            filterSubject.value = '';
            filterChapter.value = '';
            filterTopic.value = '';
            filterType.value = '';
            filterDifficulty.value = '';
        }
    });

    filterSubject.addEventListener('change', function() {
        if (this.value) {
            const selectedSub = this.value;
            filterChapter.innerHTML = '<option value="">Select Chapter</option>';
            allChapters.filter(c => c.subid == selectedSub).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.chpid;
                opt.innerText = c.chapter;
                filterChapter.appendChild(opt);
            });
            $(stepChapter).fadeIn();
        } else {
            $('#step_chapter, #step_topic, #step_type, #step_difficulty').fadeOut();
            filterChapter.value = '';
            filterTopic.value = '';
            filterType.value = '';
            filterDifficulty.value = '';
        }
    });

    filterChapter.addEventListener('change', function() {
        if (this.value) {
            const selectedChp = this.value;
            const selectedSub = filterSubject.value;
            filterTopic.innerHTML = '<option value="">Select Topic</option>';
            allTopics.filter(t => t.chapter_id == selectedChp || (t.subject_id == selectedSub && !t.chapter_id)).forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.innerText = t.topic_name_english;
                filterTopic.appendChild(opt);
            });
            $(stepTopic).fadeIn();
        } else {
            $('#step_topic, #step_type, #step_difficulty').fadeOut();
            filterTopic.value = '';
            filterType.value = '';
            filterDifficulty.value = '';
        }
    });

    filterTopic.addEventListener('change', function() {
        if (this.value) {
            $(stepType).fadeIn();
        } else {
            $('#step_type, #step_difficulty').fadeOut();
            filterType.value = '';
            filterDifficulty.value = '';
        }
    });

    filterType.addEventListener('change', function() {
        if (this.value) {
            $(stepDifficulty).fadeIn();
        } else {
            $('#step_difficulty').fadeOut();
            filterDifficulty.value = '';
        }
    });
});

function deleteQuestion(id) {
    $.ajax({
        url: 'delete-question.php',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // We can still show a tiny toast or just reload
                location.reload();
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error!', 'Something went wrong.', 'error');
        }
    });
}

// Auto-render KaTeX formulas in the list
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ql-formula').forEach(function(el) {
        const formula = el.getAttribute('data-value');
        if (formula) {
            try {
                katex.render(formula, el, {
                    throwOnError: false,
                    displayMode: false
                });
            } catch (e) {
                console.error("KaTeX error:", e);
                el.textContent = formula; // Fallback to raw text
            }
        }
    });
});
</script>

<!-- ============================
     BULK IMPORT MODAL
     ============================ -->
<div id="bulkImportModal" class="css-question-bank-af13cf">
  <div class="css-question-bank-122042">

    <!-- Modal Header -->
    <div class="css-question-bank-7fc4bc">
      <div class="css-question-bank-b59271">
        <h5 class="css-question-bank-89496a"><i class="fas fa-layer-group mr-2"></i> Bulk Question Import</h5>
        <small id="bulk-source-label" class="css-question-bank-19416b">Preparing...</small>
      </div>
      <button onclick="closeBulkModal()" class="css-question-bank-ab2050">&times;</button>
    </div>

    <!-- Progress Bar -->
    <div id="bulk-progress-wrap" class="css-question-bank-bf9203">
      <div class="css-question-bank-d523cf">
        <div id="bulk-progress-bar" class="css-question-bank-f840f4"></div>
      </div>
      <p id="bulk-progress-text" class="css-question-bank-b61c88">Importing...</p>
    </div>

    <!-- Modal Body -->
    <div class="css-question-bank-991ddc">

      <!-- Stats Row -->
      <div id="bulk-stats" class="css-question-bank-eaf2d1">
        <div class="css-question-bank-781735">
          <div class="css-question-bank-990318">
            <div id="stat-total" class="css-question-bank-ded405">0</div>
            <div class="css-question-bank-7009ca">Total Rows</div>
          </div>
          <div class="css-question-bank-8c411c">
            <div id="stat-valid" class="css-question-bank-20d97e">0</div>
            <div class="css-question-bank-7009ca">Valid</div>
          </div>
          <div class="css-question-bank-9a4a0f">
            <div id="stat-skip" class="css-question-bank-6aaca3">0</div>
            <div class="css-question-bank-7009ca">Skipped</div>
          </div>
        </div>
      </div>

      <!-- Validation Errors -->
      <div id="bulk-errors" class="css-question-bank-16bd73">
        <strong class="css-question-bank-9a4c60"><i class="fas fa-exclamation-triangle mr-1"></i> Validation Issues (skipped rows):</strong>
        <ul id="bulk-error-list" class="css-question-bank-2e40a9"></ul>
      </div>

      <!-- Global Metadata Selection -->
      <div id="bulk-metadata-wrap" class="css-question-bank-d34ebf">
        <p class="css-question-bank-837479"><i class="fas fa-cog mr-1 text-primary"></i> Apply to all questions in this file:</p>
        <div class="css-question-bank-cafc78">
            <div class="css-question-bank-a5ad82">
                <label class="css-question-bank-9279ec">Standard</label>
                <select id="bulk_std" class="form-control form-control-sm css-question-bank-8dd799">
                    <option value="">Select Standard</option>
                    <?php
                    $stds = $conn->query("SELECT stdid, stdtext FROM standard WHERE stdtext NOT LIKE '%Merged%' ORDER BY stdtext ASC");
                    while($s = $stds->fetch()) echo "<option value='{$s['stdid']}'>{$s['stdtext']}</option>";
                    ?>
                </select>
            </div>
            <div class="css-question-bank-a5ad82">
                <label class="css-question-bank-9279ec">Subject</label>
                <select id="bulk_sub" class="form-control form-control-sm css-question-bank-8dd799">
                    <option value="">Select Subject</option>
                </select>
            </div>
            <div class="css-question-bank-a5ad82">
                <label class="css-question-bank-9279ec">Chapter</label>
                <select id="bulk_ch" class="form-control form-control-sm css-question-bank-8dd799">
                    <option value="">Select Chapter</option>
                </select>
            </div>
            <div class="css-question-bank-a5ad82">
                <label class="css-question-bank-9279ec">Topic</label>
                <select id="bulk_tp" class="form-control form-control-sm css-question-bank-8dd799">
                    <option value="">Select Topic</option>
                </select>
            </div>
        </div>
      </div>

      <!-- Preview Table -->
      <div class="css-question-bank-491ef4">
        <table id="bulk-preview-table" class="css-question-bank-fe5fec">
          <thead id="bulk-preview-head" class="css-question-bank-e7a1b8"></thead>
          <tbody id="bulk-preview-body"></tbody>
        </table>
        <div id="bulk-empty-state" class="css-question-bank-49f536">
          <i class="fas fa-file-import css-question-bank-803b30"></i>
          No file selected. Upload a CSV or Word (.docx) file to preview.
        </div>
      </div>
    </div>

    <!-- Modal Footer -->
    <div class="css-question-bank-c2d8f5">
      <span id="bulk-footer-hint" class="css-question-bank-e1a0a5">Review the preview above before importing.</span>
      <div class="css-question-bank-618605">
        <button onclick="closeBulkModal()" class="btn btn-light css-question-bank-52e27d">Cancel</button>
        <button id="bulk-import-btn" onclick="submitBulkImport()" class="btn btn-primary css-question-bank-f68a5a" disabled>
          <i class="fas fa-cloud-upload-alt mr-2"></i> Import Questions
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================
     BULK IMPORT SCRIPTS
     ============================ -->
<script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
<script>
(function() {
  // ---- Column map: CSV header -> internal key ----
  const CSV_COL_MAP = {
    'standard':       'standard',
    'subject':        'subject',
    'chapter':        'chapter',
    'topic':          'topic',
    'group':          'group_name',
    'questiontype':   'question_type',
    'question type':  'question_type',
    'type':           'question_type',
    'difficulty':     'difficulty',
    'difficulty level': 'difficulty',
    'difficultylevel': 'difficulty',
    'question':       'question_text',
    'questionbody':   'question_text',
    'question text':  'question_text',
    'questiontext':   'question_text',
    'optiona':        'option_a',
    'option a':       'option_a',
    'optionb':        'option_b',
    'option b':       'option_b',
    'optionc':        'option_c',
    'option c':       'option_c',
    'optiond':        'option_d',
    'option d':       'option_d',
    'correctanswer':  'correct_option',
    'correct answer': 'correct_option',
    'correct':        'correct_option',
    'correctoption':  'correct_option',
    'correct option': 'correct_option',
    'marks':          'marks',
    'mark':           'marks',
    'negativemarks':  'negative_marks',
    'negative marks': 'negative_marks',
    'negmarks':       'negative_marks',
    'explanation':    'explanation',
    'solution':       'explanation',
    'videolink':      'video_solution_url',
    'video':          'video_solution_url',
    'solution text':  'explanation',
    'solution video link': 'video_solution_url',
    'solution image': 'solution_image',
  };

  // ---- Word table column order (Strict Positional) ----
  const WORD_COLS = [
    'standard', 'group_name', 'subject', 'chapter', 'topic', 'question_type', 'difficulty',
    'question_text', 'option_a', 'option_b', 'option_c',
    'option_d', 'correct_option', 'explanation', 'video_solution_url', 'solution_image',
    'question_text_guj', 'option_a_guj', 'option_b_guj', 'option_c_guj', 'option_d_guj', 'explanation_guj'
  ];

  let parsedQuestions = []; // Stores only valid questions for final submit
  let allParsedQuestions = []; // Stores all parsed questions (including invalid ones) for re-validation

  // ---- Helpers ----
  function autoFillMetadata(questions) {
    if (!questions || !questions.length) return;
    
    // Find the first valid row that has at least some metadata
    const firstQ = questions.find(q => q.standard || q.subject || q.chapter || q.topic) || questions[0];
    
    let msgFound = [];
    let msgNotFound = [];
    
    // Helper to find and select option by text (forgiving match)
    function selectOptionByText(selectEl, text) {
        if (!text) return false;
        
        let norm = t => t.replace(/[^a-z0-9]/gi, '').toLowerCase();
        let textNorm = norm(text);
        
        let matched = Array.from(selectEl.options).find(opt => {
            if (!opt.value) return false; // skip placeholder
            return norm(opt.text) === textNorm || norm(opt.value) === textNorm;
        });
        
        if (matched && matched.value) {
            selectEl.value = matched.value;
            selectEl.dispatchEvent(new Event('change'));
            return true;
        }
        return false;
    }
    
    // Standard
    if (firstQ.standard) {
        if (selectOptionByText(document.getElementById('bulk_std'), firstQ.standard)) {
            msgFound.push('Standard');
        } else {
            msgNotFound.push('Standard');
        }
    }
    
    // Subject
    if (firstQ.subject) {
        if (selectOptionByText(document.getElementById('bulk_sub'), firstQ.subject)) {
            msgFound.push('Subject');
        } else {
            msgNotFound.push('Subject');
        }
    }
    
    // Chapter
    if (firstQ.chapter) {
        if (selectOptionByText(document.getElementById('bulk_ch'), firstQ.chapter)) {
            msgFound.push('Chapter');
        } else {
            msgNotFound.push('Chapter');
        }
    }
    
    // Topic
    if (firstQ.topic) {
        if (selectOptionByText(document.getElementById('bulk_tp'), firstQ.topic)) {
            msgFound.push('Topic');
        } else {
            msgNotFound.push('Topic');
        }
    }
    
    // Display feedback message
    let hintWrap = document.getElementById('bulk-auto-fill-msg');
    if (!hintWrap) {
        hintWrap = document.createElement('div');
        hintWrap.id = 'bulk-auto-fill-msg';
        hintWrap.style.fontSize = '0.85rem';
        hintWrap.style.marginTop = '12px';
        hintWrap.style.padding = '8px 12px';
        hintWrap.style.borderRadius = '8px';
        document.getElementById('bulk-metadata-wrap').appendChild(hintWrap);
    }
    
    if (msgFound.length > 0 || msgNotFound.length > 0) {
        let html = '';
        if (msgFound.length > 0) {
            html += `<div class="css-question-bank-66544a"><i class="fas fa-magic mr-1"></i> Auto-filled from file: ${msgFound.join(', ')}</div>`;
        }
        if (msgNotFound.length > 0) {
            html += `<div class="css-question-bank-86701e"><i class="fas fa-exclamation-triangle mr-1"></i> Could not map: ${msgNotFound.join(', ')}. Please select manually.</div>`;
        }
        hintWrap.innerHTML = html;
        hintWrap.style.display = 'block';
        hintWrap.style.background = msgNotFound.length > 0 ? '#fff3e0' : '#e3f2fd';
        hintWrap.style.border = '1px solid ' + (msgNotFound.length > 0 ? '#ffcc80' : '#bbdefb');
    } else {
        hintWrap.style.display = 'none';
    }
  }

  function closeBulkModal() {
    document.getElementById('bulkImportModal').style.display = 'none';
    parsedQuestions = [];
    allParsedQuestions = [];
    document.getElementById('bulk-csv-input').value = '';
    document.getElementById('bulk-word-input').value = '';
  }
  window.closeBulkModal = closeBulkModal;

  function openBulkModal(source) {
    document.getElementById('bulk-source-label').textContent = 'Source: ' + source;
    document.getElementById('bulk-import-btn').disabled = true;
    document.getElementById('bulk-stats').style.display = 'none';
    document.getElementById('bulk-errors').style.display = 'none';
    document.getElementById('bulk-metadata-wrap').style.display = 'block'; // Always show for better control
    document.getElementById('bulk-progress-wrap').style.display = 'none';
    document.getElementById('bulk-empty-state').style.display = 'block';
    document.getElementById('bulk-preview-head').innerHTML = '';
    document.getElementById('bulk-preview-body').innerHTML = '';
    document.getElementById('bulkImportModal').style.display = 'block';
    
    let hintWrap = document.getElementById('bulk-auto-fill-msg');
    if (hintWrap) hintWrap.style.display = 'none';
    
    // Sync modal dropdowns with main search filters if they are selected
    const filterStd = document.getElementById('filter_standard').value;
    if (filterStd) {
        const modalStd = document.getElementById('bulk_std');
        modalStd.value = filterStd;
        modalStd.dispatchEvent(new Event('change'));
        
        // Also try to sync subject if selected
        setTimeout(() => {
            const filterSub = document.getElementById('filter_subject').value;
            if (filterSub) {
                const modalSub = document.getElementById('bulk_sub');
                modalSub.value = filterSub;
                modalSub.dispatchEvent(new Event('change'));
            }
        }, 300);
    }
  }

  // ---- Global Metadata Cascading Logic ----
  document.getElementById('bulk_std').addEventListener('change', function() {
    const sub = document.getElementById('bulk_sub');
    sub.innerHTML = '<option value="">Select Subject</option>';
    if (this.value) {
        allSubjects.filter(s => s.standard_id == this.value).forEach(s => {
            sub.innerHTML += `<option value="${s.id}">${s.subject_name}</option>`;
        });
    }
    sub.dispatchEvent(new Event('change'));
  });

  document.getElementById('bulk_sub').addEventListener('change', function() {
    const ch = document.getElementById('bulk_ch');
    ch.innerHTML = '<option value="">Select Chapter</option>';
    if (this.value) {
        allChapters.filter(c => c.subid == this.value).forEach(c => {
            ch.innerHTML += `<option value="${c.chpid}">${c.chapter}</option>`;
        });
    }
    ch.dispatchEvent(new Event('change'));
  });

  document.getElementById('bulk_sub').addEventListener('change', function() {
    const tp = document.getElementById('bulk_tp');
    tp.innerHTML = '<option value="">Select Topic</option>';
    if (this.value) {
        allTopics.filter(t => t.subject_id == this.value).forEach(t => {
            tp.innerHTML += `<option value="${t.id}">${t.topic_name_english}</option>`;
        });
    }
  });

  function normalizeHeader(h) {
    return h.toLowerCase().replace(/[^a-z0-9\s]/g, '').trim();
  }

  function mapCsvRow(rawRow, headers) {
    const q = {};
    headers.forEach((h, i) => {
      const key = CSV_COL_MAP[normalizeHeader(h)];
      if (key) q[key] = (rawRow[h] || '').toString().trim();
    });
    return q;
  }

  function validateQuestion(q, idx) {
    const errors = [];
    const type = (q.question_type || 'MCQ').toUpperCase();
    
    // If global subject is selected, individual subject is not mandatory
    const globalSub = document.getElementById('bulk_sub').value;
    if (!q.subject && !globalSub) errors.push('Subject missing (select in dropdown or include in file)');
    
    if (!q.question_text)  errors.push('Question text missing');
    
    // Only validate correct option for MCQ types (Optional as per user request)
    if (type === 'MCQ' && q.correct_option) {
      if (!['A','B','C','D'].includes(q.correct_option.toUpperCase())) {
        errors.push('Correct answer must be A/B/C/D if provided');
      }
    }
    
    return errors;
  }

  function renderPreview(questions, errors) {
    const head = document.getElementById('bulk-preview-head');
    const body = document.getElementById('bulk-preview-body');
    const empty = document.getElementById('bulk-empty-state');
    const stats = document.getElementById('bulk-stats');
    const errBox = document.getElementById('bulk-errors');
    const errList = document.getElementById('bulk-error-list');
    const btn = document.getElementById('bulk-import-btn');

    if (!questions.length) { empty.style.display = 'block'; return; }
    empty.style.display = 'none';

    const cols = ['#','Subject','Chapter','Type','Difficulty','Question (preview)','Options','Answer','Marks'];
    head.innerHTML = '<tr>' + cols.map(c =>
      `<th class="css-question-bank-1bc383">${c}</th>`
    ).join('') + '</tr>';

    const validRows = [];
    const skippedRows = [];

    body.innerHTML = questions.map((q, i) => {
      const rowErrors = validateQuestion(q, i);
      const isValid = rowErrors.length === 0;
      if (isValid) validRows.push(q); else skippedRows.push({ row: i+1, errs: rowErrors });
      const bg = isValid ? '' : 'background:#fff8e1;';
      
      // Keep full content in preview to avoid cutting off images, but use a scrollable container
      let previewHtml = (q.question_text || '');
      // Strip other tags except img
      let filteredHtml = previewHtml.replace(/<(?!\/?img\s*\/?)[^>]*>/g, '');

      // Format options for preview
      const optsHtml = [
        { label: 'A', text: q.option_a },
        { label: 'B', text: q.option_b },
        { label: 'C', text: q.option_c },
        { label: 'D', text: q.option_d }
      ].filter(o => o.text && o.text.trim()).map(o => 
        `<div class="css-question-bank-3b02f3">
            <b class="css-question-bank-ab88e8">${o.label}:</b> 
            <div class="css-question-bank-9c69a0">${o.text}</div>
         </div>`
      ).join('');

      return `<tr class="css-question-bank-8a51a5">
        <td class="css-question-bank-087b8c">${i+1}${isValid ? '' : ' <span class="css-question-bank-ecfcff" title="'+rowErrors.join(', ')+'">⚠</span>'}</td>
        <td class="css-question-bank-3b0395">${q.subject||document.getElementById('bulk_sub').options[document.getElementById('bulk_sub').selectedIndex]?.text||'-'}</td>
        <td class="css-question-bank-28d3c6">${q.chapter||document.getElementById('bulk_ch').options[document.getElementById('bulk_ch').selectedIndex]?.text||'-'}</td>
        <td class="css-question-bank-3b0395">${q.question_type||'MCQ'}</td>
        <td class="css-question-bank-3b0395">${q.difficulty||'Level A'}</td>
        <td class="css-question-bank-18bff6">
            <div class="css-question-bank-4a3db9">${filteredHtml}</div>
        </td>
        <td class="css-question-bank-9942be">
            <div class="css-question-bank-bc3747">${optsHtml || '<span class="css-question-bank-e0a2d1">No options</span>'}</div>
        </td>
        <td class="css-question-bank-2582cb">${q.correct_option||'-'}</td>
        <td class="css-question-bank-210c5d">${q.marks||1}</td>
      </tr>`;
    }).join('');

    // Update stats
    document.getElementById('stat-total').textContent = questions.length;
    document.getElementById('stat-valid').textContent = validRows.length;
    document.getElementById('stat-skip').textContent = skippedRows.length;
    stats.style.display = 'block';

    // Store all questions for re-validation on dropdown changes
    allParsedQuestions = questions;
    
    // Store only valid questions for submission
    parsedQuestions = validRows;

    // Show errors
    if (skippedRows.length) {
      errList.innerHTML = skippedRows.map(e => `<li>Row ${e.row}: ${e.errs.join(', ')}</li>`).join('');
      errBox.style.display = 'block';
    } else {
      errBox.style.display = 'none';
    }

    btn.disabled = validRows.length === 0;
    document.getElementById('bulk-footer-hint').textContent =
      `${validRows.length} valid question(s) ready to import.`;
  }

  // Re-validate when dropdowns change (use allParsedQuestions to restore skipped rows)
  document.getElementById('bulk_sub').addEventListener('change', () => {
    if (allParsedQuestions.length) renderPreview(allParsedQuestions, []);
  });

  // ---- CSV Parsing ----
  document.getElementById('bulk-csv-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    openBulkModal('CSV: ' + file.name);
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      encoding: 'UTF-8',
      complete: function(results) {
        const headers = results.meta.fields || [];
        const rows = results.data.map(row => mapCsvRow(row, headers));
        autoFillMetadata(rows);
        renderPreview(rows, []);
      },
      error: function(err) {
        alert('CSV parse error: ' + err.message);
        closeBulkModal();
      }
    });
    this.value = '';
  });

  // ---- Word (.docx) Parsing ----
    document.getElementById('bulk-word-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Validate file type
    if (!file.name.toLowerCase().endsWith('.docx')) {
      alert('Error: Only .docx files are supported. Please save your file as Word Document (.docx) and try again.');
      this.value = '';
      return;
    }

    openBulkModal('Word: ' + file.name);
    
    // Server-side parsing using PHPWord
    const formData = new FormData();
    formData.append('word_file', file);

    const progressWrap = document.getElementById('bulk-progress-wrap');
    const progressBar  = document.getElementById('bulk-progress-bar');
    const progressText = document.getElementById('bulk-progress-text');

    progressWrap.style.display = 'block';
    progressBar.style.width = '50%';
    progressText.textContent = 'Uploading and parsing Word document on server...';

    fetch('ajax/import-word-server.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      progressBar.style.width = '100%';
      progressText.textContent = 'Parsing complete!';
      
      if (data.success) {
        autoFillMetadata(data.questions);
        renderPreview(data.questions, []);
      } else {
        alert('Word parse error: ' + data.message);
        closeBulkModal();
      }
    })
    .catch(err => {
      console.error('[OES] Word Parse Error:', err);
      alert('Network error while parsing Word file. Please check your connection.');
      closeBulkModal();
    });

    this.value = '';
  });

  // ---- Submit to backend ----
  window.submitBulkImport = async function() {
    if (!parsedQuestions.length) return;

    const btn = document.getElementById('bulk-import-btn');
    const progressWrap = document.getElementById('bulk-progress-wrap');
    const progressBar  = document.getElementById('bulk-progress-bar');
    const progressText = document.getElementById('bulk-progress-text');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Importing...';
    progressWrap.style.display = 'block';
    progressBar.style.width = '30%';
    
    // Get global metadata values
    const globalData = {
        standard_id: document.getElementById('bulk_std').value,
        subject_id:  document.getElementById('bulk_sub').value,
        chapter_id:  document.getElementById('bulk_ch').value,
        topic_id:    document.getElementById('bulk_tp').value
    };

    progressText.textContent = `Sending ${parsedQuestions.length} questions to server...`;

    try {
      const resp = await fetch('save_bulk_questions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            questions: parsedQuestions,
            global_metadata: globalData
        })
      });

      progressBar.style.width = '80%';
      const data = await resp.json();
      progressBar.style.width = '100%';
      progressText.textContent = 'Done!';

      if (data.success) {
        closeBulkModal();
        const msg = `✅ Successfully imported <strong>${data.imported}</strong> question(s)` +
          (data.failed > 0 ? `, <strong>${data.failed}</strong> skipped.` : '.');

        // Show SweetAlert2 result
        let detailHtml = '';
        if (data.fail_reasons && data.fail_reasons.length) {
          detailHtml = '<ul class="css-question-bank-ffb8d8">' +
            data.fail_reasons.map(r => `<li>${r}</li>`).join('') + '</ul>';
        }

        Swal.fire({
          title: 'Import Complete',
          html: msg + detailHtml,
          icon: data.failed > 0 ? 'warning' : 'success',
          confirmButtonText: 'View Question Bank',
          timer: data.failed > 0 ? null : 2500,
          timerProgressBar: data.failed > 0 ? false : true,
          allowOutsideClick: false
        }).then(() => { 
          location.reload(); 
        });
      } else {
        Swal.fire('Import Failed', data.message || 'Unknown error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt mr-2"></i> Import Questions';
      }
    } catch (err) {
      console.error('Import Error:', err);
      progressBar.style.width = '0%';
      Swal.fire('Import Error', 'The server could not process the request. This might be due to a large file or connection timeout.', 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-cloud-upload-alt mr-2"></i> Import Questions';
    }
  };
})();
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
