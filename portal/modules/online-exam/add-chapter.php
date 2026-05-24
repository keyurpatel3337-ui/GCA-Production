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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = ($id > 0 ? "Edit" : "Add") . " Chapter | OES";

$chapter = ['subid' => 0, 'chapter' => ''];
$standard_id = 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT c.*, s.standard_id, co.standard AS general_standard 
                            FROM tbl_chapters c 
                            LEFT JOIN tbl_subjects s ON c.subid = s.id 
                            LEFT JOIN tbl_courses co ON s.standard_id = co.id
                            WHERE c.chpid = ?");
    $stmt->execute([$id]);
    $chapter_data = $stmt->fetch();
    if ($chapter_data) {
        $chapter = $chapter_data;
        $standard_id = $chapter['general_standard'];
    } else {
        header("Location: manage-chapters.php?msg=error");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subid = (int)$_POST['subject_id'];
    $chapter_name = $_POST['chapter_name'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tbl_chapters SET subid = ?, chapter = ? WHERE chpid = ?");
        $stmt->execute([$subid, $chapter_name, $id]);
        $msg = "updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_chapters (subid, chapter) VALUES (?, ?)");
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

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px; max-width: 600px; margin: 0 auto;">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-bookmark mr-2 text-primary"></i> <?php echo $id > 0 ? 'Edit' : 'Add'; ?> Chapter</h5>
                        <a href="manage-chapters.php" class="btn btn-sm btn-light shadow-sm" style="border-radius: 10px;"><i class="fas fa-arrow-left mr-1"></i> Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Standard <span class="text-danger">*</span></label>
                            <select id="modal_standard_id" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" onchange="loadSubjects(this.value)">
                                <option value="">Select Standard</option>
                                <option value="11" <?php echo ($standard_id == 11) ? 'selected' : ''; ?>>11th</option>
                                <option value="12" <?php echo ($standard_id == 12) ? 'selected' : ''; ?>>12th</option>
                                <option value="13" <?php echo ($standard_id == 13) ? 'selected' : ''; ?>>Reneet</option>
                            </select>
                        </div>
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" id="modal_subject_id" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" required>
                                <option value="">Select Standard First</option>
                            </select>
                        </div>
                        <div class="form-group mb-5">
                            <label class="small font-weight-bold text-muted mb-2">Chapter Name <span class="text-danger">*</span></label>
                            <input type="text" name="chapter_name" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" value="<?php echo htmlspecialchars($chapter['chapter']); ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage-chapters.php" class="btn btn-light shadow-sm px-4" style="border-radius: 12px; height: 45px; line-height: 33px; font-weight: 600;">Cancel</a>
                            <button type="submit" class="btn btn-primary shadow-sm px-4" style="border-radius: 12px; height: 45px; font-weight: 600;">Save Chapter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
async function loadSubjects(stdid, selectedSubId = null) {
    const subSelect = document.getElementById('modal_subject_id');
    subSelect.innerHTML = '<option value="">Loading Subjects...</option>';
    
    if (!stdid) {
        subSelect.innerHTML = '<option value="">Select Standard First</option>';
        return;
    }

    try {
        const response = await fetch(`ajax/get-subjects.php?standard_id=${stdid}`);
        const data = await response.json();
        subSelect.innerHTML = '<option value="">Select Subject</option>';
        data.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.innerText = s.subject_name;
            if (selectedSubId && s.id == selectedSubId) opt.selected = true;
            subSelect.appendChild(opt);
        });
    } catch (err) {
        console.error('Error fetching subjects:', err);
        subSelect.innerHTML = '<option value="">Error loading subjects</option>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($id > 0 && $standard_id > 0): ?>
        loadSubjects(<?php echo $standard_id; ?>, <?php echo $chapter['subid']; ?>);
    <?php endif; ?>
});
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
