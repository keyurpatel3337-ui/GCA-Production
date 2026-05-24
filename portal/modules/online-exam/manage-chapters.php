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

$page_title = "Manage Chapters | OES";

// Handle Actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $chpid = (int)$_POST['chpid'];
            $stmt = $conn->prepare("DELETE FROM tbl_chapters WHERE chpid = ?");
            $stmt->execute([$chpid]);
            $msg = "deleted";
        }
        header("Location: manage-chapters.php?msg=$msg");
        exit();
    }
}

// Pagination Logic
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

$totalItems = $conn->query("SELECT COUNT(*) FROM tbl_chapters WHERE activated = 1 AND is_deleted = 0")->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Fetch Chapters with Pagination
$chapters = $conn->query("SELECT c.*, s.subject_name, co.course_name AS general_standard,
                         (SELECT COUNT(*) FROM tbl_oes_questions q WHERE q.chapter_id = c.chpid AND q.standard_id = 1 AND q.status = 1) AS q_11th,
                         (SELECT COUNT(*) FROM tbl_oes_questions q WHERE q.chapter_id = c.chpid AND q.standard_id = 2 AND q.status = 1) AS q_12th,
                         (SELECT COUNT(*) FROM tbl_oes_questions q WHERE q.chapter_id = c.chpid AND q.standard_id = 3 AND q.status = 1) AS q_reneet
                         FROM tbl_chapters c 
                         LEFT JOIN tbl_subjects s ON c.subid = s.id 
                         LEFT JOIN tbl_courses co ON s.standard_id = co.id
                         WHERE c.activated = 1 AND c.is_deleted = 0
                         ORDER BY co.course_name ASC, s.subject_name ASC, c.chapter_number ASC
                         LIMIT $perPage OFFSET $offset")->fetchAll();

require_once PORTAL_PATH . 'common/pagination.php';

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
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
                        let title = 'Chapter ' + msg + ' successfully!';
                        let icon = 'success';
                        
                        if (msg.startsWith('imported_')) {
                            const count = msg.split('_')[1];
                            title = count + ' Chapters imported successfully!';
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

            <div class="card shadow-sm mb-4 border-0 css-manage-chapters-dc9ce7">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 font-weight-bold text-dark css-manage-chapters-e7ec96">Chapter List</h5>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="input-group input-group-sm css-manage-chapters-d4150c">
                                <span class="input-group-text bg-light border-0 css-manage-chapters-67aaf6">
                                    <i class="fas fa-search text-muted small"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control border-0 bg-light css-manage-chapters-84dccc" placeholder="Search Chapter...">
                            </div>
                            <select id="filterStandard" class="form-select form-select-sm border-0 bg-light css-manage-chapters-267d83" onchange="updateSubjectFilter(this.value)">
                                <option value="">All Standards</option>
                                <option value="11th">11th</option>
                                <option value="12th">12th</option>
                                <option value="Reneet">Reneet</option>
                            </select>
                            <select id="filterSubject" class="form-select form-select-sm border-0 bg-light css-manage-chapters-267d83">
                                <option value="">All Subjects</option>
                            </select>
                            <div class="d-flex align-items-center gap-2">
                                <a href="export-chapters.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-manage-chapters-dbb5d6" title="Export CSV">
                                    <i class="fas fa-download text-muted mr-2"></i> Export
                                </a>
                                <a href="import-chapters.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3 css-manage-chapters-dbb5d6" title="Import CSV">
                                    <i class="fas fa-upload text-muted mr-2"></i> Import
                                </a>
                                <a href="add-chapter.php" class="btn btn-primary shadow-sm d-flex align-items-center justify-content-center px-4 css-manage-chapters-dbb5d6">
                                    <i class="fas fa-plus mr-2 css-manage-chapters-af89d6"></i> Add Chapter
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="chapterTable">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="px-4 py-3">Standard</th>
                                    <th class="py-3">Subject</th>
                                    <th class="py-3">Chapter Name</th>
                                    <th class="py-3 text-center">11th Qs</th>
                                    <th class="py-3 text-center">12th Qs</th>
                                    <th class="py-3 text-center">Re-Neet Qs</th>
                                    <th class="text-end px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chapters as $ch): ?>
                                    <tr>
                                        <td class="px-4 align-middle">
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($ch['general_standard'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($ch['subject_name'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td class="align-middle font-weight-bold"><?php echo htmlspecialchars($ch['chapter']); ?></td>
                                        <td class="align-middle text-center">
                                            <?php $qc = (int)($ch['q_11th'] ?? 0); ?>
                                            <a href="question-bank.php?chapter_id=<?php echo $ch['chpid']; ?>&standard_id=1" class="text-decoration-none">
                                                <span class="badge rounded-pill css-manage-chapters-fe1898"><?php echo $qc ?: '—'; ?></span>
                                            </a>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php $qc = (int)($ch['q_12th'] ?? 0); ?>
                                            <a href="question-bank.php?chapter_id=<?php echo $ch['chpid']; ?>&standard_id=2" class="text-decoration-none">
                                                <span class="badge rounded-pill css-manage-chapters-b1b44c"><?php echo $qc ?: '—'; ?></span>
                                            </a>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php $qc = (int)($ch['q_reneet'] ?? 0); ?>
                                            <a href="question-bank.php?chapter_id=<?php echo $ch['chpid']; ?>&standard_id=3" class="text-decoration-none">
                                                <span class="badge rounded-pill css-manage-chapters-eeaafe"><?php echo $qc ?: '—'; ?></span>
                                            </a>
                                        </td>
                                        <td class="text-end px-4 align-middle">
                                            <a href="add-chapter.php?id=<?php echo $ch['chpid']; ?>" class="btn btn-sm btn-outline-info mr-2">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteChapter(<?php echo $ch['chpid']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white border-0 py-3">
                    <?php echo renderPagination($currentPage, $totalPages, 'manage-chapters.php', 2, $totalItems, 'chapters'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Delete Form -->
<form id="deleteForm" method="POST" class="css-manage-chapters-93b8ea">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="chpid" id="delete_chpid">
</form>

<script>

function deleteChapter(id) {
    if (confirm('Are you sure you want to delete this chapter? This will affect all topics and questions linked to it.')) {
        document.getElementById('delete_chpid').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Data for filters
const allSubjects = <?php echo json_encode($conn->query("SELECT id, subject_name, standard_id FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC)); ?>;

// Search and Filter Logic
document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterStandard').addEventListener('change', filterTable);
document.getElementById('filterSubject').addEventListener('change', filterTable);

function updateSubjectFilter(stdtext) {
    const subFilter = document.getElementById('filterSubject');
    subFilter.innerHTML = '<option value="">All Subjects</option>';
    
    if (stdtext) {
        const stdId = {
            '11th': 11,
            '12th': 12,
            'Reneet': 13
        }[stdtext];

        const courseToStd = {
            1: 11,
            2: 11,
            4: 12,
            5: 12,
            6: 13
        };

        allSubjects.filter(s => courseToStd[s.standard_id] == stdId).forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.subject_name;
            opt.innerText = s.subject_name;
            subFilter.appendChild(opt);
        });
    }
    filterTable();
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const standardTerm = document.getElementById('filterStandard').value;
    const subjectTerm = document.getElementById('filterSubject').value;
    const rows = document.querySelectorAll('#chapterTable tbody tr');

    rows.forEach(row => {
        const standardName = row.cells[0].innerText.trim();
        const subjectName = row.cells[1].innerText.trim();
        const chapterName = row.cells[2].innerText.toLowerCase();
        
        const matchesSearch = chapterName.includes(searchTerm);
        const matchesStandard = standardTerm === "" || standardName === standardTerm;
        const matchesSubject = subjectTerm === "" || subjectName === subjectTerm;

        if (matchesSearch && matchesStandard && matchesSubject) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
