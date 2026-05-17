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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = ($id > 0 ? "Edit" : "Add") . " Chapter | OES";

$chapter = ['subid' => 0, 'chapter' => ''];
$standard_id = 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT c.*, s.standard_id FROM chapters c LEFT JOIN tbl_subjects s ON c.subid = s.id WHERE c.chpid = ?");
    $stmt->execute([$id]);
    $chapter_data = $stmt->fetch();
    if ($chapter_data) {
        $chapter = $chapter_data;
        $standard_id = $chapter['standard_id'];
    } else {
        header("Location: manage-chapters.php?msg=error");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subid = (int)$_POST['subject_id'];
    $chapter_name = $_POST['chapter_name'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE chapters SET subid = ?, chapter = ? WHERE chpid = ?");
        $stmt->execute([$subid, $chapter_name, $id]);
        $msg = "updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO chapters (subid, chapter) VALUES (?, ?)");
        $stmt->execute([$subid, $chapter_name]);
        $msg = "added";
    }
    header("Location: manage-chapters.php?msg=$msg");
    exit();
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/online-exam/add-chapter.css">

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            <div class="card shadow-sm mb-4 border-0 add-chapter-custom-1">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-bookmark mr-2 text-primary"></i> <?php echo $id > 0 ? 'Edit' : 'Add'; ?> Chapter</h5>
                        <a href="manage-chapters.php" class="btn btn-sm btn-light shadow-sm add-chapter-custom-2"><i class="fas fa-arrow-left mr-1"></i> Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Standard <span class="text-danger">*</span></label>
                            <select id="modal_standard_id" class="form-control border-0 shadow-sm add-chapter-custom-3" onchange="loadSubjects(this.value)">
                                <option value="">Select Standard</option>
                                <?php
                                $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC");
                                while ($std = $standards->fetch()) {
                                    $selected = ($standard_id == $std['stdid']) ? 'selected' : '';
                                    echo "<option value='{$std['stdid']}' $selected>{$std['stdtext']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" id="modal_subject_id" class="form-control border-0 shadow-sm add-chapter-custom-3" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div class="form-group mb-5">
                            <label class="small font-weight-bold text-muted mb-2">Chapter Name <span class="text-danger">*</span></label>
                            <input type="text" name="chapter_name" class="form-control border-0 shadow-sm add-chapter-custom-3" value="<?php echo htmlspecialchars($chapter['chapter']); ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage-chapters.php" class="btn btn-light shadow-sm px-4 add-chapter-custom-4">Cancel</a>
                            <button type="submit" class="btn btn-primary shadow-sm px-4 add-chapter-custom-5">Save Chapter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const allSubjects = <?php 
    $sub_all = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($sub_all); 
?>;

function loadSubjects(stdid, selectedSubId = null) {
    const subSelect = document.getElementById('modal_subject_id');
    subSelect.innerHTML = '<option value="">Select Subject</option>';
    
    if (!stdid) return;

    allSubjects.filter(s => s.standard_id == stdid).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.innerText = s.subject_name;
        if (selectedSubId && s.id == selectedSubId) opt.selected = true;
        subSelect.appendChild(opt);
    });
}

<?php if ($id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    loadSubjects(<?php echo $standard_id; ?>, <?php echo $chapter['subid']; ?>);
});
<?php endif; ?>
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
