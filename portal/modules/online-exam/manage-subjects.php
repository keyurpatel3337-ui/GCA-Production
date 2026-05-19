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

$page_title = "Manage Subjects | OES";

// Handle Actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM tbl_subjects WHERE id = ?");
            $stmt->execute([$id]);
            $msg = "deleted";
        }
        header("Location: manage-subjects.php?msg=$msg");
        exit();
    }
}

// Pagination Logic
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

$totalItems = $conn->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Fetch Subjects with Standard Info and Pagination
$subjects = $conn->query("SELECT s.*, std.stdtext 
                         FROM tbl_subjects s 
                         LEFT JOIN standard std ON s.standard_id = std.stdid 
                         ORDER BY std.stdid ASC, s.subject_name ASC
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
                        let title = 'Subject ' + msg + ' successfully!';
                        let icon = 'success';
                        
                        if (msg.startsWith('imported_')) {
                            const count = msg.split('_')[1];
                            title = count + ' Subjects imported successfully!';
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

            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 font-weight-bold text-dark" style="font-size: 1.1rem;">Subject List</h5>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text bg-light border-0" style="border-radius: 10px 0 0 10px;">
                                    <i class="fas fa-search text-muted small"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control border-0 bg-light" placeholder="Search Subject..." style="border-radius: 0 10px 10px 0; font-size: 0.85rem; height: 38px;">
                            </div>
                            <select id="filterStandard" class="form-select form-select-sm border-0 bg-light" style="width: 180px; border-radius: 10px; font-size: 0.85rem; height: 38px;">
                                <option value="">All Standards</option>
                                <?php
                                $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC");
                                while ($std = $standards->fetch()) {
                                    echo "<option value='".htmlspecialchars($std['stdtext'])."'>".htmlspecialchars($std['stdtext'])."</option>";
                                }
                                ?>
                            </select>
                            <div class="d-flex align-items-center gap-2">
                                <a href="export-subjects.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3" style="border-radius: 12px; height: 38px; font-size: 0.85rem; font-weight: 600;" title="Export CSV">
                                    <i class="fas fa-download text-muted mr-2"></i> Export
                                </a>
                                <a href="import-subjects.php" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center px-3" style="border-radius: 12px; height: 38px; font-size: 0.85rem; font-weight: 600;" title="Import CSV">
                                    <i class="fas fa-upload text-muted mr-2"></i> Import
                                </a>
                                <a href="add-subject.php" class="btn btn-primary shadow-sm d-flex align-items-center justify-content-center px-4" style="border-radius: 12px; height: 38px; font-size: 0.85rem; font-weight: 600;">
                                    <i class="fas fa-plus mr-2" style="font-size: 0.75rem;"></i> Add Subject
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="subjectTable">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="px-4 py-3">Standard</th>
                                    <th class="py-3">Subject Name</th>
                                    <th class="py-3">Status</th>
                                    <th class="text-end px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $s): ?>
                                    <tr>
                                        <td class="px-4 align-middle">
                                            <span class="badge badge-info"><?php echo htmlspecialchars($s['stdtext'] ?? 'General / All'); ?></span>
                                        </td>
                                        <td class="align-middle font-weight-bold"><?php echo htmlspecialchars($s['subject_name']); ?></td>
                                        <td class="align-middle">
                                            <?php if ($s['activated'] == 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end px-4 align-middle">
                                            <a href="add-subject.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info mr-2">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSubject(<?php echo $s['id']; ?>)">
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
                    <?php echo renderPagination($currentPage, $totalPages, 'manage-subjects.php', 2, $totalItems, 'subjects'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>

function deleteSubject(id) {
    if (confirm('Are you sure you want to delete this subject? This will affect all chapters and questions linked to it.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Search and Filter Logic
document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterStandard').addEventListener('change', filterTable);

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const standardTerm = document.getElementById('filterStandard').value;
    const rows = document.querySelectorAll('#subjectTable tbody tr');

    rows.forEach(row => {
        const subjectName = row.cells[1].innerText.toLowerCase();
        const standardName = row.cells[0].innerText.trim();
        
        const matchesSearch = subjectName.includes(searchTerm);
        const matchesStandard = standardTerm === "" || standardName === standardTerm;

        if (matchesSearch && matchesStandard) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
