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
$page_title = ($id > 0 ? "Edit" : "Add") . " Topic | OES";

$topic = ['subject_id' => 0, 'chapter_id' => 0, 'topic_name_english' => ''];
$standard_id = 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT t.*, s.standard_id FROM tbl_topics t LEFT JOIN tbl_subjects s ON t.subject_id = s.id WHERE t.id = ?");
    $stmt->execute([$id]);
    $topic_data = $stmt->fetch();
    if ($topic_data) {
        $topic = $topic_data;
        $standard_id = $topic['standard_id'];
    } else {
        header("Location: manage-topics.php?msg=error");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subid = (int)$_POST['subject_id'];
    $chapter_id = (int)$_POST['chapter_id'];
    $topic_name = $_POST['topic_name'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tbl_topics SET subject_id = ?, chapter_id = ?, topic_name_english = ? WHERE id = ?");
        $stmt->execute([$subid, $chapter_id, $topic_name, $id]);
        $msg = "updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_topics (subject_id, chapter_id, topic_name_english, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$subid, $chapter_id, $topic_name]);
        $msg = "added";
    }
    header("Location: manage-topics.php?msg=$msg");
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
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-tag mr-2 text-primary"></i> <?php echo $id > 0 ? 'Edit' : 'Add'; ?> Topic</h5>
                        <a href="manage-topics.php" class="btn btn-sm btn-light shadow-sm" style="border-radius: 10px;"><i class="fas fa-arrow-left mr-1"></i> Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Standard <span class="text-danger">*</span></label>
                            <select id="modal_standard_id" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" onchange="loadSubjects(this.value)">
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
                            <select name="subject_id" id="modal_subject_id" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" required onchange="loadChapters(this.value)">
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Chapter <span class="text-danger">*</span></label>
                            <select name="chapter_id" id="modal_chapter_id" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" required>
                                <option value="">Select Chapter</option>
                            </select>
                        </div>
                        <div class="form-group mb-5">
                            <label class="small font-weight-bold text-muted mb-2">Topic Name <span class="text-danger">*</span></label>
                            <input type="text" name="topic_name" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 45px;" value="<?php echo htmlspecialchars($topic['topic_name_english']); ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage-topics.php" class="btn btn-light shadow-sm px-4" style="border-radius: 12px; height: 45px; line-height: 33px; font-weight: 600;">Cancel</a>
                            <button type="submit" class="btn btn-primary shadow-sm px-4" style="border-radius: 12px; height: 45px; font-weight: 600;">Save Topic</button>
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
const allChapters = <?php 
    $ch_all = $conn->query("SELECT chpid, subid, chapter FROM tbl_chapters ORDER BY chapter ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($ch_all); 
?>;

function loadSubjects(stdid, selectedSubId = null) {
    const subSelect = document.getElementById('modal_subject_id');
    subSelect.innerHTML = '<option value="">Select Subject</option>';
    document.getElementById('modal_chapter_id').innerHTML = '<option value="">Select Chapter</option>';
    
    if (!stdid) return;

    allSubjects.filter(s => s.standard_id == stdid).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.innerText = s.subject_name;
        if (selectedSubId && s.id == selectedSubId) opt.selected = true;
        subSelect.appendChild(opt);
    });
}

function loadChapters(subid, selectedChapterId = null) {
    const chapterSelect = document.getElementById('modal_chapter_id');
    chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
    
    if (!subid) return;

    allChapters.filter(ch => ch.subid == subid).forEach(ch => {
        const opt = document.createElement('option');
        opt.value = ch.chpid;
        opt.innerText = ch.chapter;
        if (selectedChapterId && ch.chpid == selectedChapterId) opt.selected = true;
        chapterSelect.appendChild(opt);
    });
}

<?php if ($id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    loadSubjects(<?php echo $standard_id; ?>, <?php echo $topic['subject_id']; ?>);
    loadChapters(<?php echo $topic['subject_id']; ?>, <?php echo $topic['chapter_id']; ?>);
});
<?php endif; ?>
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
