<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;

// Auth check
if (!hasRole(ROLE_OES_DATA_ENTRY_OPERATOR) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch dynamic stats from database
try {
    // Total Questions in Database
    $q_stmt = $conn->query("SELECT COUNT(*) FROM tbl_oes_questions WHERE status = 1");
    $total_questions = $q_stmt->fetchColumn() ?? 0;

    // Questions entered by THIS Operator
    $my_q_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_oes_questions WHERE created_by = ? AND status = 1");
    $my_q_stmt->execute([$_SESSION['user_id']]);
    $my_questions = $my_q_stmt->fetchColumn() ?? 0;

    // Total Active Subjects
    $s_stmt = $conn->query("SELECT COUNT(*) FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0");
    $total_subjects = $s_stmt->fetchColumn() ?? 0;

    // Total Chapters
    $c_stmt = $conn->query("SELECT COUNT(*) FROM tbl_chapters WHERE activated = 1 AND is_deleted = 0");
    $total_chapters = $c_stmt->fetchColumn() ?? 0;

    // Course-wise breakdown
    $course_stats = $conn->query("
        SELECT
            co.id,
            co.course_name,
            (SELECT COUNT(*) FROM tbl_subjects s WHERE s.standard_id = co.id AND s.activated = 1 AND s.is_deleted = 0) AS subject_count,
            (SELECT COUNT(*) FROM tbl_chapters c
             INNER JOIN tbl_subjects s2 ON c.subid = s2.id
             WHERE s2.standard_id = co.id AND c.activated = 1 AND c.is_deleted = 0) AS chapter_count,
            (SELECT COUNT(*) FROM tbl_topics t WHERE t.standard_id = co.id AND t.activated = 1 AND t.is_deleted = 0) AS topic_count,
            (SELECT COUNT(*) FROM tbl_oes_questions q
             WHERE q.standard_id = co.id AND q.status = 1) AS question_count
        FROM tbl_courses co
        WHERE co.is_active = 1
        ORDER BY co.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $total_questions = 0;
    $my_questions = 0;
    $total_subjects = 0;
    $total_chapters = 0;
    $course_stats = [];
}

$page_title = "OES Operator Dashboard";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Glassmorphic Styles -->


<div class="container-fluid py-4">
    <?php include '../../include/mfa_alert.php'; ?>

    <!-- Welcome Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-slate-800">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'OES Operator'); ?>!</h2>
            <p class="text-muted mb-0">Here is your digital question entry panel to build GCA's premium online exam database.</p>
        </div>
        <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm css-oes_data_entry_dashboard-132209"><i class="fas fa-keyboard me-1"></i> OES Data Entry Operator</span>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <!-- My Questions Entered -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-emerald p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">My Typed Questions</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($my_questions); ?></h3>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 css-oes_data_entry_dashboard-20bb59">
                        <i class="fas fa-keyboard fa-lg"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php?search=<?php echo urlencode($_SESSION['user_name'] ?? ''); ?>" class="small text-success text-decoration-none d-block mt-3" style="color: #059669;">
                    View My Questions <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Total Questions in Bank -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-blue p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Exam Bank</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_questions); ?></h3>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 css-oes_data_entry_dashboard-b2d203">
                        <i class="fas fa-university fa-lg"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php" class="small text-primary text-decoration-none d-block mt-3" style="color: #1e3a8a;">
                    Browse Question Bank <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Active Subjects -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-cyan p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Active Subjects</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_subjects); ?></h3>
                    </div>
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 css-oes_data_entry_dashboard-0c8f3e">
                        <i class="fas fa-book fa-lg"></i>
                    </div>
                </div>
                <span class="small text-muted d-block mt-3">Syllabus subject options</span>
            </div>
        </div>

        <!-- Total Chapters -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-purple p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Chapters</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_chapters); ?></h3>
                    </div>
                    <div class="p-3 bg-purple bg-opacity-10 text-purple rounded-3 css-oes_data_entry_dashboard-e33b5b">
                        <i class="fas fa-bookmark fa-lg"></i>
                    </div>
                </div>
                <span class="small text-muted d-block mt-3">Configured course chapters</span>
            </div>
        </div>
    </div>

    <!-- Course / Standard-wise Breakdown -->
    <?php
    $course_colors = [
        ['bg' => '#3b82f6', 'light' => 'rgba(59,130,246,0.08)', 'icon' => 'fa-graduation-cap'],
        ['bg' => '#10b981', 'light' => 'rgba(16,185,129,0.08)', 'icon' => 'fa-star'],
        ['bg' => '#8b5cf6', 'light' => 'rgba(139,92,246,0.08)', 'icon' => 'fa-refresh'],
    ];
    ?>
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h5 class="fw-bold mb-0 text-slate-800"><i class="fas fa-layer-group text-primary me-2"></i>Standard-wise Content Breakdown</h5>
            <span class="badge rounded-pill css-oes_data_entry_dashboard-c9fb21">Live</span>
        </div>
        <div class="row g-4">
            <?php foreach ($course_stats as $idx => $cs):
                $col = $course_colors[$idx % count($course_colors)];
            ?>
            <div class="col-xl-4 col-md-6">
                <div class="course-stat-card h-100">
                    <!-- Header -->
                    <div class="course-header d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center css-oes_data_entry_dashboard-f0f3df">
                            <i class="fas <?php echo $col['icon']; ?>" style="color:<?php echo $col['bg']; ?>;font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 css-oes_data_entry_dashboard-6e61d4"><?php echo htmlspecialchars($cs['course_name']); ?></h6>
                            <span class="text-muted css-oes_data_entry_dashboard-c4ad98">Standard / Course</span>
                        </div>
                        <span class="ms-auto badge rounded-pill css-oes_data_entry_dashboard-7f3b18"><?php echo number_format($cs['question_count']); ?> Qs</span>
                    </div>
                    <!-- Stats Grid -->
                    <div class="p-3">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="stat-pill css-oes_data_entry_dashboard-b166df">
                                    <i class="fas fa-book css-oes_data_entry_dashboard-af633e"></i>
                                    <div>
                                        <div class="fw-bold css-oes_data_entry_dashboard-c73bc1"><?php echo $cs['subject_count']; ?></div>
                                        <div class="css-oes_data_entry_dashboard-efcf06">Subjects</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-pill css-oes_data_entry_dashboard-84c7f7">
                                    <i class="fas fa-bookmark css-oes_data_entry_dashboard-d8c2db"></i>
                                    <div>
                                        <div class="fw-bold css-oes_data_entry_dashboard-c73bc1"><?php echo $cs['chapter_count']; ?></div>
                                        <div class="css-oes_data_entry_dashboard-efcf06">Chapters</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-pill css-oes_data_entry_dashboard-902fde">
                                    <i class="fas fa-tag css-oes_data_entry_dashboard-fd8aea"></i>
                                    <div>
                                        <div class="fw-bold css-oes_data_entry_dashboard-c73bc1"><?php echo $cs['topic_count']; ?></div>
                                        <div class="css-oes_data_entry_dashboard-efcf06">Topics</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Progress bar for questions vs total -->
                        <?php
                        $total_q = array_sum(array_column($course_stats, 'question_count'));
                        $pct = $total_q > 0 ? round(($cs['question_count'] / $total_q) * 100) : 0;
                        ?>
                        <div class="mt-3 px-1">
                            <div class="d-flex justify-content-between mb-1 css-oes_data_entry_dashboard-cfbb43">
                                <span class="text-muted">Question share</span>
                                <span class="fw-bold css-oes_data_entry_dashboard-6e61d4"><?php echo $pct; ?>%</span>
                            </div>
                            <div class="css-oes_data_entry_dashboard-53746d">
                                <div class="css-oes_data_entry_dashboard-c09283"></div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-subjects.php" class="btn btn-sm flex-fill" style="background:<?php echo $col['light']; ?>;color:<?php echo $col['bg']; ?>;border-radius:8px;font-size:0.78rem;font-weight:600;"><i class="fas fa-book me-1"></i>Subjects</a>
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-chapters.php" class="btn btn-sm flex-fill" style="background:<?php echo $col['light']; ?>;color:<?php echo $col['bg']; ?>;border-radius:8px;font-size:0.78rem;font-weight:600;"><i class="fas fa-bookmark me-1"></i>Chapters</a>
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php" class="btn btn-sm flex-fill" style="background:<?php echo $col['light']; ?>;color:<?php echo $col['bg']; ?>;border-radius:8px;font-size:0.78rem;font-weight:600;"><i class="fas fa-database me-1"></i>Bank</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions and Info Row -->
    <div class="row g-4">
        <!-- Main Actions -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0 text-slate-800"><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Create Question -->
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/index.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-primary bg-opacity-10 text-primary css-oes_data_entry_dashboard-06b74c">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Create Question</h6>
                                <span class="small text-muted">Add MCQ or Descriptive</span>
                            </a>
                        </div>
                        <!-- Question Bank -->
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-success bg-opacity-10 text-success css-oes_data_entry_dashboard-1d5289">
                                    <i class="fas fa-university"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Question Bank</h6>
                                <span class="small text-muted">View/Edit entered data</span>
                            </a>
                        </div>
                        <!-- Word Document Upload -->
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/bulk-import-word.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-purple bg-opacity-10 text-purple css-oes_data_entry_dashboard-e18e39">
                                    <i class="fas fa-file-word"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Word Bulk Upload</h6>
                                <span class="small text-muted">Import standard .docx</span>
                            </a>
                        </div>
                        <!-- Manage Chapters -->
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-chapters.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-info bg-opacity-10 text-info css-oes_data_entry_dashboard-74c77b">
                                    <i class="fas fa-bookmark"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Manage Chapters</h6>
                                <span class="small text-muted">Configure subjects & chapters</span>
                            </a>
                        </div>
                        <!-- Manage Topics -->
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-topics.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-warning bg-opacity-10 text-warning css-oes_data_entry_dashboard-ae7153">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Manage Topics</h6>
                                <span class="small text-muted">Configure chapter-wise topics</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions Panel -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 css-oes_data_entry_dashboard-5e277e">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-white"><i class="fas fa-info-circle text-info me-2 css-oes_data_entry_dashboard-9092a8"></i>OES Entry Guidelines</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex gap-3 mb-3">
                            <div class="p-2 bg-white bg-opacity-10 rounded-3 shadow-sm flex-shrink-0 css-oes_data_entry_dashboard-129b2f">
                                <i class="fas fa-superscript"></i>
                            </div>
                            <div>
                                <strong class="small d-block text-white mb-1">KaTeX Equations Formatting</strong>
                                <span class="small css-oes_data_entry_dashboard-4478db">Use <code class="css-oes_data_entry_dashboard-59ab28">$...$</code> for inline and <code class="css-oes_data_entry_dashboard-59ab28">$$...$$</code> for centered math formulas (e.g. <code class="css-oes_data_entry_dashboard-59ab28">$x^2 + y^2 = r^2$</code>).</span>
                            </div>
                        </li>
                        <li class="d-flex gap-3 mb-3">
                            <div class="p-2 bg-white bg-opacity-10 rounded-3 shadow-sm flex-shrink-0 css-oes_data_entry_dashboard-51ab65">
                                <i class="fas fa-language"></i>
                            </div>
                            <div>
                                <strong class="small d-block text-white mb-1">Bilingual Entry (English & Gujarati)</strong>
                                <span class="small css-oes_data_entry_dashboard-4478db">Always type/paste both translations so students can toggle exam language.</span>
                            </div>
                        </li>
                        <li class="d-flex gap-3">
                            <div class="p-2 bg-white bg-opacity-10 rounded-3 shadow-sm flex-shrink-0 css-oes_data_entry_dashboard-04fd3c">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <div>
                                <strong class="small d-block text-white mb-1">Double Check Options</strong>
                                <span class="small css-oes_data_entry_dashboard-4478db">Ensure option details are completely filled and the correct option key is mapped accurately.</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>
