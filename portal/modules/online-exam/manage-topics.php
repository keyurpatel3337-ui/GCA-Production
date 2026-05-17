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

$page_title = "Manage Topics | OES";

// Handle Actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $topic_id = (int)$_POST['topic_id'];
            $stmt = $conn->prepare("DELETE FROM tbl_topics WHERE id = ?");
            $stmt->execute([$topic_id]);
            $msg = "deleted";
        }
        header("Location: manage-topics.php?msg=$msg");
        exit();
    }
}

// Pagination Logic
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

$totalItems = $conn->query("SELECT COUNT(*) FROM tbl_topics")->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Fetch Topics with Subject, Chapter, and Standard Info with Pagination
$topics = $conn->query("SELECT t.*, s.subject_name, c.chapter as chapter_name, std.stdtext 
                      FROM tbl_topics t 
                      LEFT JOIN tbl_subjects s ON t.subject_id = s.id 
                      LEFT JOIN chapters c ON t.chapter_id = c.chpid 
                      LEFT JOIN standard std ON t.standard_id = std.stdid
                      ORDER BY std.stdid ASC, s.subject_name ASC, c.chapter ASC, t.topic_name_english ASC
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
                        let title = 'Topic ' + msg + ' successfully!';
                        let icon = 'success';
                        
                        if (msg.startsWith('imported_')) {
                            const count = msg.split('_')[1];
                            title = count + ' Topics imported successfully!';
                        }
                        
                        Toast.fire({
                            icon: icon,
                            title: title
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 font-weight-bold text-dark" style="font-size: 1.1rem;">Topic List</h5>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <span class="input-group-text bg-light border-0" style="border-radius: 10px 0 0 10px;">
                                    <i class="fas fa-search text-muted small"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control border-0 bg-light" placeholder="Search Topic..." style="border-radius: 0 10px 10px 0; font-size: 0.85rem; height: 38px;">
                            </div>
                            <select id="filterStandard" class="form-select form-select-sm border-0 bg-light" style="width: 140px; border-radius: 10px; font-size: 0.85rem; height: 38px;" onchange="updateSubjectFilter(this.value)">
                                <option value="">All Standards</option>
                                <?php
                                $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC");
                                while ($std = $standards->fetch()) {
                                    echo "<option value='".htmlspecialchars($std['stdtext'])."'>".htmlspecialchars($std['stdtext'])."</option>";
                                }
                                ?>
                            </select>
                            <select id="filterSubject" class="form-select form-select-sm border-0 bg-light" style="width: 140px; border-radius: 10px; font-size: 0.85rem; height: 38px;" onchange="updateChapterFilter(this.value)">
                                <option value="">All Subjects</option>
                            </select>
                            <select id="filterChapter" class="form-select form-select-sm border-0 bg-light" style="width: 140px; border-radius: 10px; font-size: 0.85rem; height: 38px;">
                                <option value="">All Chapters</option>
                            </select>
                            <div class="d-flex align-items-center gap-2">
                                <a href="export-topics.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3" style="border-radius: 12px; height: 38px; font-size: 0.85rem; font-weight: 600;" title="Export CSV">
                                    <i class="fas fa-download text-muted mr-2"></i> Export
                                </a>
                                <a href="import-topics.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3" style="border-radius: 12px; height: 38px; font-size: 0.85rem; font-weight: 600;" title="Import CSV">
                                    <i class="fas fa-upload text-muted mr-2"></i> Import
                                </a>
                                <a href="add-topic.php" class="btn btn-primary shadow-sm d-flex align-items-center justify-content-center px-4" style="border-radius: 12px; height: 38px; font-size: 0.85rem; font-weight: 600;">
                                    <i class="fas fa-plus mr-2" style="font-size: 0.75rem;"></i> Add Topic
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="topicTable">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="px-4 py-3">Standard</th>
                                    <th class="py-3">Subject</th>
                                    <th class="py-3">Chapter</th>
                                    <th class="py-3">Topic Name</th>
                                    <th class="text-end px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topics as $tp): ?>
                                    <tr>
                                        <td class="px-4 align-middle">
                                            <span class="badge badge-info"><?php echo htmlspecialchars($tp['stdtext'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($tp['subject_name'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge badge-success"><?php echo htmlspecialchars($tp['chapter_name'] ?? 'General'); ?></span>
                                        </td>
                                        <td class="align-middle font-weight-bold"><?php echo htmlspecialchars($tp['topic_name_english']); ?></td>
                                        <td class="text-end px-4 align-middle">
                                            <a href="add-topic.php?id=<?php echo $tp['id']; ?>" class="btn btn-sm btn-outline-info mr-2">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTopic(<?php echo $tp['id']; ?>)">
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
                    <?php echo renderPagination($currentPage, $totalPages, 'manage-topics.php', 2, $totalItems, 'topics'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>

function deleteTopic(id) {
    if (confirm('Are you sure you want to delete this topic? This will affect all questions linked to it.')) {
        document.getElementById('delete_topic_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Data for filters
const allSubjects = <?php echo json_encode($conn->query("SELECT id, subject_name, standard_id FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC)); ?>;
const allChapters = <?php echo json_encode($conn->query("SELECT chpid, chapter, subid FROM chapters WHERE activated = 1 AND is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC)); ?>;

// Search and Filter Logic
document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterStandard').addEventListener('change', filterTable);
document.getElementById('filterSubject').addEventListener('change', filterTable);
document.getElementById('filterChapter').addEventListener('change', filterTable);

function updateSubjectFilter(stdtext) {
    const subFilter = document.getElementById('filterSubject');
    subFilter.innerHTML = '<option value="">All Subjects</option>';
    document.getElementById('filterChapter').innerHTML = '<option value="">All Chapters</option>';
    
    if (stdtext) {
        const stdId = <?php 
            $std_mapping = $conn->query("SELECT stdid, stdtext FROM standard")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(array_flip($std_mapping));
        ?>[stdtext];

        allSubjects.filter(s => s.standard_id == stdId).forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.subject_name;
            opt.innerText = s.subject_name;
            subFilter.appendChild(opt);
        });
    }
    filterTable();
}

function updateChapterFilter(subName) {
    const chpFilter = document.getElementById('filterChapter');
    chpFilter.innerHTML = '<option value="">All Chapters</option>';
    
    if (subName) {
        const subId = allSubjects.find(s => s.subject_name === subName)?.id;
        if (subId) {
            allChapters.filter(c => c.subid == subId).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.chapter;
                opt.innerText = c.chapter;
                chpFilter.appendChild(opt);
            });
        }
    }
    filterTable();
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const standardTerm = document.getElementById('filterStandard').value;
    const subjectTerm = document.getElementById('filterSubject').value;
    const chapterTerm = document.getElementById('filterChapter').value;
    const rows = document.querySelectorAll('#topicTable tbody tr');

    rows.forEach(row => {
        const standardName = row.cells[0].innerText.trim();
        const subjectName = row.cells[1].innerText.trim();
        const chapterName = row.cells[2].innerText.trim();
        const topicName = row.cells[3].innerText.toLowerCase();
        
        const matchesSearch = topicName.includes(searchTerm);
        const matchesStandard = standardTerm === "" || standardName === standardTerm;
        const matchesSubject = subjectTerm === "" || subjectName === subjectTerm;
        const matchesChapter = chapterTerm === "" || (chapterName === chapterTerm || (chapterTerm === "" && chapterName === "General"));

        // Special handling for "General" chapters when filtering by subject but not chapter
        const finalChapterMatch = chapterTerm === "" || chapterName === chapterTerm;

        if (matchesSearch && matchesStandard && matchesSubject && finalChapterMatch) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
