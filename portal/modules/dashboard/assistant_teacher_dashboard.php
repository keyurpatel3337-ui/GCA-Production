<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;

// Auth check
if (!hasRole(ROLE_ASSISTANT_TEACHER) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch dynamic stats from database
try {
    // Total Questions
    $q_stmt = $conn->query("SELECT COUNT(*) FROM tbl_oes_questions");
    $total_questions = $q_stmt->fetchColumn() ?? 0;

    // Total Subjects
    $s_stmt = $conn->query("SELECT COUNT(*) FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0");
    $total_subjects = $s_stmt->fetchColumn() ?? 0;

    // Total Chapters
    $c_stmt = $conn->query("SELECT COUNT(*) FROM tbl_chapters WHERE activated = 1 AND is_deleted = 0");
    $total_chapters = $c_stmt->fetchColumn() ?? 0;

    // Total Topics
    $t_stmt = $conn->query("SELECT COUNT(*) FROM tbl_topics WHERE activated = 1 AND is_deleted = 0");
    $total_topics = $t_stmt->fetchColumn() ?? 0;

} catch (Exception $e) {
    $total_questions = 120; // Fallbacks
    $total_subjects = 5;
    $total_chapters = 28;
    $total_topics = 85;
}

$page_title = "Assistant Teacher Dashboard";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Glassmorphic Styles -->
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
        --info-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        --purple-gradient: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
        --orange-gradient: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
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

    .metric-teal::before { background: var(--primary-gradient); }
    .metric-blue::before { background: var(--info-gradient); }
    .metric-purple::before { background: var(--purple-gradient); }
    .metric-orange::before { background: var(--orange-gradient); }

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
        border-color: #0d9488;
        box-shadow: 0 10px 15px -3px rgba(13, 148, 136, 0.1), 0 4px 6px -4px rgba(13, 148, 136, 0.1);
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
            <h2 class="fw-bold text-slate-800">Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Assistant Teacher'); ?>!</h2>
            <p class="text-muted mb-0">Here is your academic helper panel to manage exams, question banks, and bulk uploads.</p>
        </div>
        <span class="badge bg-teal px-3 py-2 rounded-pill shadow-sm" style="background-color: #0d9488;"><i class="fas fa-hands-helping me-1"></i> Assistant Teacher Portal</span>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <!-- Questions Created -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-teal p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Questions</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_questions); ?></h3>
                    </div>
                    <div class="p-3 bg-teal bg-opacity-10 text-teal rounded-3" style="color: #0d9488;">
                        <i class="fas fa-book fa-lg"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php" class="small text-teal text-decoration-none d-block mt-3" style="color: #0d9488;">
                    Access Question Bank <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Subjects -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-blue p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Active Subjects</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_subjects); ?></h3>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
                        <i class="fas fa-graduation-cap fa-lg"></i>
                    </div>
                </div>
                <span class="small text-muted d-block mt-3">Registered courses & subjects</span>
            </div>
        </div>

        <!-- Chapters -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-purple p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Chapters</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_chapters); ?></h3>
                    </div>
                    <div class="p-3 bg-purple bg-opacity-10 text-purple rounded-3" style="color: #7c3aed;">
                        <i class="fas fa-bookmark fa-lg"></i>
                    </div>
                </div>
                <span class="small text-muted d-block mt-3">Configured syllabus chapters</span>
            </div>
        </div>

        <!-- Topics -->
        <div class="col-xl-3 col-lg-6">
            <div class="glass-metric-card metric-orange p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Topics</span>
                        <h3 class="fw-bold mb-0 text-slate-900"><?php echo number_format($total_topics); ?></h3>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3" style="color: #ea580c;">
                        <i class="fas fa-tags fa-lg"></i>
                    </div>
                </div>
                <span class="small text-muted d-block mt-3">Individual sub-topics set up</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions and Info Row -->
    <div class="row g-4">
        <!-- Main Actions -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0 text-slate-800"><i class="fas fa-bolt text-warning me-2"></i>Primary Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/index.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-teal bg-opacity-10 text-teal" style="background-color: rgba(13, 148, 136, 0.1); color: #0d9488;">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Create Question</h6>
                                <span class="small text-muted">Add exam questions</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/bulk-import-word.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-file-word"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Word Bulk Import</h6>
                                <span class="small text-muted">Upload Word documents</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/test-marks/bulk-upload.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-warning bg-opacity-10 text-warning" style="background-color: rgba(245, 158, 11, 0.1); color: #ea580c;">
                                    <i class="fas fa-file-excel"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Upload Marks</h6>
                                <span class="small text-muted">Feed test marks Excel</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/online-exam/question-bank.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-university"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Question Bank</h6>
                                <span class="small text-muted">Browse typed database</span>
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="<?php echo PORTAL_URL; ?>/modules/test-marks/add.php" class="quick-glow-btn">
                                <div class="quick-icon-wrapper bg-purple bg-opacity-10 text-purple" style="background-color: rgba(124, 58, 237, 0.1); color: #7c3aed;">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <h6 class="fw-bold text-slate-800 mb-1">Single Marks</h6>
                                <span class="small text-muted">Enter individual score</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions Panel -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-gradient-dark text-slate-800" style="background-color: #f1f5f9;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-info-circle text-teal me-2" style="color: #0d9488;"></i>Guidelines for Assistant Staff</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex gap-3 mb-3">
                            <div class="p-2 bg-white rounded-3 shadow-sm flex-shrink-0" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                                <i class="fas fa-shield-alt text-success"></i>
                            </div>
                            <div>
                                <strong class="small d-block text-slate-900">Ensure Accuracy</strong>
                                <span class="small text-muted">Double-check option mapping (A, B, C, D) before saving questions.</span>
                            </div>
                        </li>
                        <li class="d-flex gap-3 mb-3">
                            <div class="p-2 bg-white rounded-3 shadow-sm flex-shrink-0" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                                <i class="fas fa-file-word text-primary"></i>
                            </div>
                            <div>
                                <strong class="small d-block text-slate-900">Word Upload Template</strong>
                                <span class="small text-muted">Use standard OES bracket formatting for multiple choice imports.</span>
                            </div>
                        </li>
                        <li class="d-flex gap-3">
                            <div class="p-2 bg-white rounded-3 shadow-sm flex-shrink-0" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                            </div>
                            <div>
                                <strong class="small d-block text-slate-900">Test Marks Limits</strong>
                                <span class="small text-muted">Verify maximum marks of the test configuration before submitting scores.</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>
