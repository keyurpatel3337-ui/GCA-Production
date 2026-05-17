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
$page_title = ($id > 0 ? "Edit" : "Add") . " Subject | OES";

$subject = ['standard_id' => 0, 'subject_name' => '', 'status' => 'active'];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM tbl_subjects WHERE id = ?");
    $stmt->execute([$id]);
    $subject_data = $stmt->fetch();
    if ($subject_data) {
        $subject = $subject_data;
    } else {
        header("Location: manage-subjects.php?msg=error");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stdid = (int)$_POST['standard_id'];
    $subject_name = $_POST['subject_name'];
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tbl_subjects SET standard_id = ?, subject_name = ?, status = ? WHERE id = ?");
        $stmt->execute([$stdid, $subject_name, $status, $id]);
        $msg = "updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_subjects (standard_id, subject_name, status) VALUES (?, ?, ?)");
        $stmt->execute([$stdid, $subject_name, $status]);
        $msg = "added";
    }
    header("Location: manage-subjects.php?msg=$msg");
    exit();
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/online-exam/add-subject.css">

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            <div class="card shadow-sm mb-4 border-0 add-subject-custom-1">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-book mr-2 text-primary"></i> <?php echo $id > 0 ? 'Edit' : 'Add'; ?> Subject</h5>
                        <a href="manage-subjects.php" class="btn btn-sm btn-light shadow-sm add-subject-custom-2"><i class="fas fa-arrow-left mr-1"></i> Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Select Standard <span class="text-danger">*</span></label>
                            <select name="standard_id" class="form-control border-0 shadow-sm add-subject-custom-3">
                                <option value="0">General / All Standards</option>
                                <?php
                                $standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC");
                                while ($std = $standards->fetch()) {
                                    $selected = ($subject['standard_id'] == $std['stdid']) ? 'selected' : '';
                                    echo "<option value='{$std['stdid']}' $selected>{$std['stdtext']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-muted mb-2">Subject Name <span class="text-danger">*</span></label>
                            <input type="text" name="subject_name" class="form-control border-0 shadow-sm add-subject-custom-3" value="<?php echo htmlspecialchars($subject['subject_name']); ?>" required>
                        </div>
                        <div class="form-group mb-5">
                            <label class="small font-weight-bold text-muted mb-2">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control border-0 shadow-sm add-subject-custom-3" required>
                                <option value="active" <?php echo $subject['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $subject['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage-subjects.php" class="btn btn-light shadow-sm px-4 add-subject-custom-4">Cancel</a>
                            <button type="submit" class="btn btn-primary shadow-sm px-4 add-subject-custom-5">Save Subject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
