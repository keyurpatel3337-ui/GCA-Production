<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to logged-in students and parents
if (!hasAnyRole([ROLE_STUDENT]) && !(isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = 0;
$division_id = 0;
$course_id = 0;
$group_id = 0;
$academic_year = '';
$enroll_data = null;

// Identify active student registration and enrollment details
if ($user_id > 0) {
    $enrolled = $conn->prepare("
        SELECT es.division_id, reg.course_id, reg.group_id, reg.academic_year_id as academic_year
        FROM tbl_enrolled_students es
        JOIN tbl_gm_std_registration reg ON es.registration_id = reg.id
        WHERE reg.id = ? AND es.is_active = 1
        LIMIT 1
    ");
    $enrolled->execute([$user_id]);
    $enroll_data = $enrolled->fetch(PDO::FETCH_ASSOC);

    if ($enroll_data) {
        $division_id = intval($enroll_data['division_id']);
        $course_id = intval($enroll_data['course_id']);
        $group_id = intval($enroll_data['group_id']);
        $academic_year = $enroll_data['academic_year'];
    }
}

$materials = [];
$subjects_list = [];

if ($course_id > 0) {
    // 1. Fetch materials assigned
    $stmt = $conn->prepare("
        SELECT m.*, s.subject_name, c.course_name, d.division_name, u.name as teacher_name,
               (SELECT COUNT(*) FROM tbl_student_material_bookmarks b WHERE b.student_id = ? AND b.material_id = m.id) as bookmarks_count,
               (SELECT COUNT(*) FROM tbl_student_material_notes n WHERE n.student_id = ? AND n.material_id = m.id) as notes_count,
               (SELECT COUNT(*) FROM tbl_student_material_doubts d WHERE d.student_id = ? AND d.material_id = m.id) as doubts_count,
               (SELECT COUNT(*) FROM tbl_student_material_doubts d WHERE d.student_id = ? AND d.material_id = m.id AND d.status = 'Resolved') as doubts_resolved_count
        FROM tbl_academic_materials m
        JOIN tbl_subjects s ON m.subject_id = s.id
        JOIN tbl_courses c ON m.course_id = c.id
        LEFT JOIN tbl_division d ON m.division_id = d.id
        LEFT JOIN tbl_users u ON m.uploaded_by = u.id
        WHERE m.course_id = ?
          AND (m.division_id = ? OR m.division_id IS NULL)
        ORDER BY m.id DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $course_id, $division_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch list of subjects matching standard to populate filters
    $sub_stmt = $conn->prepare("
        SELECT DISTINCT s.id, s.subject_name 
        FROM tbl_subjects s
        JOIN tbl_academic_materials m ON m.subject_id = s.id
        WHERE m.course_id = ? AND (m.division_id = ? OR m.division_id IS NULL)
    ");
    $sub_stmt->execute([$course_id, $division_id]);
    $subjects_list = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Study Materials Library';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(226, 232, 240, 0.8);
        --pdf-color: #ef4444;
        --ppt-color: #f59e0b;
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        --card-hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
    }

    body {
        background-color: #f8fafc;
    }

    .glass-header-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        box-shadow: var(--card-shadow);
    }

    .search-filter-row {
        background: #ffffff;
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    .material-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-column: column;
    }

    .material-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover-shadow);
        border-color: #cbd5e1;
    }

    .material-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
    }

    .card-type-pdf::before { background: var(--pdf-color); }
    .card-type-ppt::before { background: var(--ppt-color); }

    .file-icon-wrapper {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.2s ease;
    }

    .icon-pdf {
        background-color: rgba(239, 68, 68, 0.08);
        color: var(--pdf-color);
    }

    .icon-ppt {
        background-color: rgba(245, 158, 11, 0.08);
        color: var(--ppt-color);
    }

    .material-card:hover .file-icon-wrapper {
        transform: scale(1.08);
    }

    .kindle-stat-badge {
        font-size: 0.76rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .badge-bookmark {
        background-color: rgba(245, 158, 11, 0.06);
        color: #d97706;
        border-color: rgba(245, 158, 11, 0.15);
    }

    .badge-note {
        background-color: rgba(59, 130, 246, 0.06);
        color: #2563eb;
        border-color: rgba(59, 130, 246, 0.15);
    }

    .badge-doubt-unresolved {
        background-color: rgba(239, 68, 68, 0.06);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.15);
    }

    .badge-doubt-resolved {
        background-color: rgba(16, 185, 129, 0.06);
        color: #059669;
        border-color: rgba(16, 185, 129, 0.15);
    }

    .btn-kindle-open {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        border: none;
        transition: all 0.2s ease;
    }

    .btn-kindle-open:hover {
        background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
    }

    .empty-library-state {
        min-height: 380px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="card glass-header-card p-4 mb-4 border-0">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h1 class="fw-bold text-slate-800 mb-1" id="main-library-title" style="font-size: 1.75rem;">
                    <i class="fas fa-book-reader text-primary me-2"></i> Study Materials Library
                </h1>
                <p class="text-muted mb-0">
                    <?php if ($enroll_data): ?>
                        Access academic textbooks, class presentations, and worksheets targeted to your standard.
                    <?php else: ?>
                        Resolve your division enrollment status to view materials.
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($enroll_data): ?>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-primary px-3 py-2 rounded-pill shadow-xs" style="font-size: 0.85rem;">
                        <i class="fas fa-graduation-cap me-1"></i> Class: <?= htmlspecialchars($enroll_data['course_id']) ?>
                    </span>
                    <span class="badge bg-success px-3 py-2 rounded-pill shadow-xs" style="font-size: 0.85rem;">
                        <i class="fas fa-object-group me-1"></i> Division: <?= htmlspecialchars($enroll_data['division_id']) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$enroll_data): ?>
        <!-- Enrollment Warning Empty State -->
        <div class="empty-library-state d-flex flex-column align-items-center justify-content-center text-center p-5 shadow-xs">
            <div class="p-4 bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; font-size: 2.25rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h4 class="fw-bold text-slate-900">Enrollment Required</h4>
            <p class="text-muted mx-auto" style="max-width: 460px;">
                You are currently registered in the system but have not been assigned to a specific standard or division. Please contact the administrative department to activate your academic enrollment.
            </p>
        </div>
    <?php else: ?>
        <!-- Search & Filter Controls -->
        <div class="card search-filter-row p-3 mb-4 border-0">
            <div class="row g-3 align-items-center">
                <!-- Search Bar -->
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 text-muted" id="search-input-icon"><i class="fas fa-search"></i></span>
                        <input type="text" id="librarySearch" class="form-control bg-light border-0" placeholder="Search materials by title or description..." aria-label="Search materials" aria-describedby="search-input-icon">
                    </div>
                </div>
                <!-- Subject Filter -->
                <div class="col-md-4">
                    <select id="subjectFilter" class="form-select bg-light border-0 fw-semibold" aria-label="Filter by Subject">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects_list as $sub): ?>
                            <option value="<?= htmlspecialchars(strtolower($sub['subject_name'])) ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Material Type Filter -->
                <div class="col-md-2">
                    <select id="typeFilter" class="form-select bg-light border-0 fw-semibold" aria-label="Filter by Format">
                        <option value="">All Formats</option>
                        <option value="pdf">PDF E-Books</option>
                        <option value="ppt">PPT Slides</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Materials Grid -->
        <?php if (empty($materials)): ?>
            <div class="empty-library-state d-flex flex-column align-items-center justify-content-center text-center p-5 shadow-xs">
                <div class="p-4 bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; font-size: 2.25rem;">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h4 class="fw-bold text-slate-900">Library is Empty</h4>
                <p class="text-muted mx-auto" style="max-width: 460px;">
                    Your teachers have not uploaded any study materials or textbook references for your standard yet. Check back later!
                </p>
            </div>
        <?php else: ?>
            <div class="row g-4" id="materialsGrid">
                <?php foreach ($materials as $m): ?>
                    <div class="col-xl-4 col-md-6 material-item-col" 
                         data-title="<?= htmlspecialchars(strtolower($m['title'])) ?>"
                         data-desc="<?= htmlspecialchars(strtolower($m['description'] ?? '')) ?>"
                         data-subject="<?= htmlspecialchars(strtolower($m['subject_name'])) ?>"
                         data-type="<?= $m['file_type'] ?>">
                        
                        <div class="material-card card shadow-sm p-4 card-type-<?= $m['file_type'] ?>">
                            <!-- Card Header Info -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="file-icon-wrapper icon-<?= $m['file_type'] ?> shadow-xs">
                                    <?php if ($m['file_type'] === 'pdf'): ?>
                                        <i class="fas fa-file-pdf"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file-powerpoint"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold rounded-pill px-2.5 py-1 small"><?= htmlspecialchars($m['subject_name']) ?></span>
                                    <?php if (empty($m['division_name'])): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-semibold rounded-pill px-2.5 py-1 small d-block mt-1.5">Common</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success fw-semibold rounded-pill px-2.5 py-1 small d-block mt-1.5">Div <?= htmlspecialchars($m['division_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Title & Description -->
                            <div class="flex-grow-1 mb-3">
                                <h5 class="fw-bold text-slate-900 mb-1.5 text-truncate-2" style="font-size: 1.05rem; line-height: 1.35; min-height: 2.7rem;"><?= htmlspecialchars($m['title']) ?></h5>
                                <p class="text-muted small text-truncate-2 mb-0" style="min-height: 2.4rem; line-height: 1.45;"><?= htmlspecialchars($m['description'] ?: 'No topics description provided.') ?></p>
                            </div>

                            <!-- Kindle E-Reader Personal Stats Badge Grid -->
                            <div class="d-flex flex-wrap gap-1.5 mb-3.5 pt-2 border-top">
                                <?php if ($m['bookmarks_count'] > 0): ?>
                                    <span class="kindle-stat-badge badge-bookmark">
                                        <i class="fas fa-star"></i> <?= $m['bookmarks_count'] ?> Bookmark<?= $m['bookmarks_count'] > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($m['notes_count'] > 0): ?>
                                    <span class="kindle-stat-badge badge-note">
                                        <i class="fas fa-edit"></i> <?= $m['notes_count'] ?> Page Note<?= $m['notes_count'] > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($m['doubts_count'] > 0): ?>
                                    <?php 
                                    $unresolved = $m['doubts_count'] - $m['doubts_resolved_count'];
                                    if ($unresolved > 0): 
                                    ?>
                                        <span class="kindle-stat-badge badge-doubt-unresolved">
                                            <i class="fas fa-question-circle"></i> <?= $unresolved ?> Pending Doubt<?= $unresolved > 1 ? 's' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($m['doubts_resolved_count'] > 0): ?>
                                        <span class="kindle-stat-badge badge-doubt-resolved">
                                            <i class="fas fa-check-circle"></i> <?= $m['doubts_resolved_count'] ?> Doubt Answered
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($m['bookmarks_count'] == 0 && $m['notes_count'] == 0 && $m['doubts_count'] == 0): ?>
                                    <span class="kindle-stat-badge text-muted">
                                        <i class="far fa-smile"></i> Not started yet
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions Row -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top mt-auto">
                                <div class="small text-muted text-truncate" style="max-width: 60%;">
                                    <i class="far fa-user me-1"></i> <?= htmlspecialchars($m['teacher_name'] ?: 'Unknown') ?>
                                </div>
                                <a href="view-material.php?id=<?= $m['id'] ?>" class="btn btn-kindle-open rounded-pill px-4 btn-sm fw-bold shadow-xs">
                                    <i class="fas fa-book-open me-1.5"></i> Open Reader
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- JavaScript search & filters sync -->
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const searchInput = document.getElementById('librarySearch');
                    const subjectFilter = document.getElementById('subjectFilter');
                    const typeFilter = document.getElementById('typeFilter');
                    const items = document.querySelectorAll('.material-item-col');

                    function performFilter() {
                        const query = searchInput.value.toLowerCase().trim();
                        const selectedSubject = subjectFilter.value;
                        const selectedType = typeFilter.value;

                        let visibleCount = 0;

                        items.forEach(item => {
                            const title = item.getAttribute('data-title');
                            const desc = item.getAttribute('data-desc');
                            const subject = item.getAttribute('data-subject');
                            const type = item.getAttribute('data-type');

                            const matchesSearch = title.includes(query) || desc.includes(query);
                            const matchesSubject = !selectedSubject || subject === selectedSubject;
                            const matchesType = !selectedType || type === selectedType;

                            if (matchesSearch && matchesSubject && matchesType) {
                                item.style.display = 'block';
                                visibleCount++;
                            } else {
                                item.style.display = 'none';
                            }
                        });

                        // Show/hide empty state if all items filtered
                        let emptyState = document.getElementById('libraryFilteredEmptyState');
                        if (visibleCount === 0) {
                            if (!emptyState) {
                                const grid = document.getElementById('materialsGrid');
                                const col = document.createElement('div');
                                col.id = 'libraryFilteredEmptyState';
                                col.className = 'col-12 text-center py-5 text-muted';
                                col.innerHTML = `
                                    <i class="fas fa-search fa-3x mb-3 text-secondary"></i>
                                    <h5>No matching study materials found</h5>
                                    <p class="small">Try refining your search terms or subject selection criteria.</p>
                                `;
                                grid.appendChild(col);
                            }
                        } else if (emptyState) {
                            emptyState.remove();
                        }
                    }

                    searchInput.addEventListener('input', performFilter);
                    subjectFilter.addEventListener('change', performFilter);
                    typeFilter.addEventListener('change', performFilter);
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../../include/footer.php'; ?>
