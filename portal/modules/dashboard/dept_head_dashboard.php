<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;

// Auth check
if (!hasRole(ROLE_DEPT_HEAD) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch dynamic stats from database
try {
    // Total Questions
    $q_stmt = $conn->query("SELECT COUNT(*) FROM tbl_oes_questions");
    $total_questions = $q_stmt->fetchColumn() ?? 0;

    // Total Exams
    $e_stmt = $conn->query("SELECT COUNT(*) FROM tbl_oes_exams");
    $total_exams = $e_stmt->fetchColumn() ?? 0;

    // Total Subjects
    $s_stmt = $conn->query("SELECT COUNT(*) FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0");
    $total_subjects = $s_stmt->fetchColumn() ?? 0;

    // Total Staff (simulated or joined with roles)
    $st_stmt = $conn->query("SELECT COUNT(*) FROM tbl_users WHERE role_id IN (12, 27, 29)");
    $total_dept_staff = $st_stmt->fetchColumn() ?? 0;

    // Fetch Syllabus Coverage (dynamic from subjects & chapters)
    $syllabus_stmt = $conn->query("
        SELECT s.subject_name, COUNT(c.chpid) as chapter_count 
        FROM tbl_subjects s 
        LEFT JOIN tbl_chapters c ON s.id = c.subid AND c.activated = 1 AND c.is_deleted = 0
        WHERE s.activated = 1 AND s.is_deleted = 0
        GROUP BY s.id 
        ORDER BY chapter_count DESC 
        LIMIT 5
    ");
    $syllabus_progress = $syllabus_stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

} catch (Exception $e) {
    $total_questions = 120;
    $total_exams = 8;
    $total_subjects = 5;
    $total_dept_staff = 4;
    $syllabus_progress = [];
}

$page_title = "Department Head Dashboard";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Glassmorphic Styles -->
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
        --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
        --info-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }

    body {
        background-color: #f8fafc;
    }

    .glass-metric-card {
        background: white;
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .glass-metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }

    .glass-metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
    }

    .metric-purple::before { background: var(--primary-gradient); }
    .metric-green::before { background: var(--success-gradient); }
    .metric-orange::before { background: var(--warning-gradient); }
    .metric-blue::before { background: var(--info-gradient); }

    .quick-glow-btn {
        background: white;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 12px;
        transition: all 0.2s ease;
        text-decoration: none;
        display: block;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .quick-glow-btn:hover {
        transform: translateY(-2px);
        border-color: #7c3aed;
        box-shadow: 0 10px 15px -3px rgba(124, 58, 237, 0.1), 0 4px 6px -4px rgba(124, 58, 237, 0.1);
    }

    .quick-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.25rem;
        transition: all 0.2s ease;
    }

    .quick-glow-btn:hover .quick-icon-wrapper {
        transform: scale(1.1);
    }
</style>

<div class="container-fluid py-4">
    <?php include '../../include/mfa_alert.php'; ?>

    <!-- Welcome Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-slate-800">Welcome Back, HOD <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Department Head'); ?>!</h2>
            <p class="text-muted mb-0">Here is the academic and examination management dashboard for your department.</p>
        </div>
        <span class="badge bg-purple px-3 py-2 rounded-pill shadow-sm" style="background-color: #7c3aed;"><i class="fas fa-crown me-1"></i> Department Lead Portal</span>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <!-- Faculty Count -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-purple p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Faculty</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_dept_staff); ?></h3>
                    </div>
                    <div class="p-3 bg-purple bg-opacity-10 text-purple rounded-3" style="color: #7c3aed;">
                        <i class="fas fa-users-cog fa-lg"></i>
                    </div>
                </div>
                <span class="small text-muted d-block mt-3">Active HODs, Teachers, & Assistants</span>
            </div>
        </div>

        <!-- Subjects -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-blue p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Department Subjects</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_subjects); ?></h3>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
                        <i class="fas fa-graduation-cap fa-lg"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-subjects.php" class="small text-primary text-decoration-none d-block mt-3">
                    Configure Subjects <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Exams -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-green p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Active Exams</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_exams); ?></h3>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
                        <i class="fas fa-laptop-code fa-lg"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-exams.php" class="small text-success text-decoration-none d-block mt-3">
                    View Exam Setup <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Questions -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-orange p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Questions</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_questions); ?></h3>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3" style="color: #ea580c;">
                        <i class="fas fa-book fa-lg"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php" class="small text-warning text-decoration-none d-block mt-3" style="color: #ea580c;">
                    Inspect Question Bank <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Syllabus Coverage and Actions -->
    <div class="row g-4 mb-5">
        <!-- Syllabus Progress Chart -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0 text-slate-800"><i class="fas fa-chart-bar text-muted me-2"></i>Syllabus Progress (Chapters per Subject)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($syllabus_progress)): ?>
                        <?php foreach ($syllabus_progress as $prog): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold text-slate-700"><?php echo htmlspecialchars($prog['subject_name']); ?></span>
                                    <span class="badge bg-purple-subtle text-purple rounded-pill"><?php echo $prog['chapter_count']; ?> Chapters</span>
                                </div>
                                <div class="progress rounded-pill" style="height: 10px;">
                                    <?php 
                                        $percent = min(100, max(10, $prog['chapter_count'] * 12));
                                    ?>
                                    <div class="progress-bar rounded-pill bg-gradient-purple" role="progressbar" style="width: <?php echo $percent; ?>%; background: var(--primary-gradient);" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-book-open fs-2 mb-3 text-slate-300"></i>
                            <p class="mb-0">No active subjects found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0 text-slate-800"><i class="fas fa-bolt text-warning me-2"></i>Primary Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/exam-templates.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-purple bg-opacity-10 text-purple" style="background-color: rgba(124, 58, 237, 0.1); color: #7c3aed;">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <span class="fw-semibold small text-slate-800 d-block">Templates</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/exam-setup.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <span class="fw-semibold small text-slate-800 d-block">Setup Exam</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/manage-subjects.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-warning bg-opacity-10 text-warning" style="background-color: rgba(245, 158, 11, 0.1); color: #ea580c;">
                                    <i class="fas fa-book"></i>
                                </div>
                                <span class="fw-semibold small text-slate-800 d-block">Subjects</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?php echo PORTAL_URL; ?>/modules/test-marks/index.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <span class="fw-semibold small text-slate-800 d-block">All Marks</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>
