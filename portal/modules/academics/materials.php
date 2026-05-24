<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to Super Admin, Principal, and Dept Head, or Teacher
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_DEPT_HEAD, ROLE_TEACHER, ROLE_ASSISTANT_TEACHER])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$uploaded_by = (int)$_SESSION['user_id'];

// Handle upload action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $course_id = intval($_POST['course_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : null;
    
    if (empty($title) || $course_id <= 0 || $subject_id <= 0 || empty($_FILES['material_file']['name'])) {
        set_flash_message('error', 'All fields (Title, Standard, Subject, File) are required.');
    } else {
        $file_name = $_FILES['material_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($ext !== 'pdf' && $ext !== 'ppt' && $ext !== 'pptx') {
            set_flash_message('error', 'Only PDF and PPT/PPTX file uploads are supported.');
        } else {
            $type = ($ext === 'pdf') ? 'pdf' : 'ppt';
            
            // Create folder path
            $target_dir = __DIR__ . '/../../../uploads/materials/';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $unique_name = uniqid() . '.' . $ext;
            $target_file = $target_dir . $unique_name;
            
            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $target_file)) {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO tbl_academic_materials (title, description, file_path, file_type, course_id, subject_id, division_id, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $rel_path = 'uploads/materials/' . $unique_name;
                    $stmt->execute([$title, $description, $rel_path, $type, $course_id, $subject_id, $division_id, $uploaded_by]);
                    set_flash_message('success', 'Study Material uploaded successfully!');
                } catch (Exception $e) {
                    set_flash_message('error', 'Database upload registration failed: ' . $e->getMessage());
                }
            } else {
                set_flash_message('error', 'File upload moving failed.');
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle edit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $mat_id = intval($_POST['material_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $course_id = intval($_POST['course_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : null;
    
    if ($mat_id <= 0 || empty($title) || $course_id <= 0 || $subject_id <= 0) {
        set_flash_message('error', 'All fields except the file upload are required.');
    } else {
        try {
            // Find existing record
            $stmt = $conn->prepare("SELECT file_path, file_type FROM tbl_academic_materials WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$mat_id, $uploaded_by]);
            $mat = $stmt->fetch();
            
            if (!$mat) {
                set_flash_message('error', 'Study Material not found or access denied.');
            } else {
                $file_path = $mat['file_path'];
                $type = $mat['file_type'];
                $upload_ok = true;
                
                // Check if a new file is uploaded
                if (!empty($_FILES['material_file']['name'])) {
                    $file_name = $_FILES['material_file']['name'];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if ($ext !== 'pdf' && $ext !== 'ppt' && $ext !== 'pptx') {
                        set_flash_message('error', 'Only PDF and PPT/PPTX file uploads are supported.');
                        $upload_ok = false;
                    } else {
                        $type = ($ext === 'pdf') ? 'pdf' : 'ppt';
                        
                        $target_dir = __DIR__ . '/../../../uploads/materials/';
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        
                        $unique_name = uniqid() . '.' . $ext;
                        $target_file = $target_dir . $unique_name;
                        
                        if (move_uploaded_file($_FILES['material_file']['tmp_name'], $target_file)) {
                            // Delete old file
                            $full_old_path = __DIR__ . '/../../../' . $mat['file_path'];
                            if (file_exists($full_old_path)) {
                                unlink($full_old_path);
                            }
                            $file_path = 'uploads/materials/' . $unique_name;
                        } else {
                            set_flash_message('error', 'New file upload moving failed.');
                            $upload_ok = false;
                        }
                    }
                }
                
                if ($upload_ok) {
                    $update_stmt = $conn->prepare("
                        UPDATE tbl_academic_materials 
                        SET title = ?, description = ?, file_path = ?, file_type = ?, course_id = ?, subject_id = ?, division_id = ?
                        WHERE id = ? AND uploaded_by = ?
                    ");
                    $update_stmt->execute([$title, $description, $file_path, $type, $course_id, $subject_id, $division_id, $mat_id, $uploaded_by]);
                    set_flash_message('success', 'Study Material updated successfully!');
                }
            }
        } catch (Exception $e) {
            set_flash_message('error', 'Failed to update: ' . $e->getMessage());
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete action
if (isset($_GET['delete'])) {
    $mat_id = intval($_GET['delete']);
    try {
        // Find file path
        $stmt = $conn->prepare("SELECT file_path FROM tbl_academic_materials WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$mat_id, $uploaded_by]);
        $mat = $stmt->fetch();
        if ($mat) {
            $full_path = __DIR__ . '/../../../' . $mat['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            $del_stmt = $conn->prepare("DELETE FROM tbl_academic_materials WHERE id = ?");
            $del_stmt->execute([$mat_id]);
            set_flash_message('success', 'Study Material deleted successfully.');
        }
    } catch (Exception $e) {
        set_flash_message('error', 'Failed to delete: ' . $e->getMessage());
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Read allocations and options
$courses = $conn->query("SELECT id, course_name FROM tbl_courses WHERE is_active = 1 ORDER BY course_name ASC")->fetchAll();
$subjects = $conn->query("SELECT id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC")->fetchAll();
$divisions = $conn->query("SELECT id, division_name FROM tbl_division WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();

// Fetch teacher's uploads
$stmt = $conn->prepare("
    SELECT m.*, c.course_name, s.subject_name, d.division_name 
    FROM tbl_academic_materials m
    LEFT JOIN tbl_courses c ON m.course_id = c.id
    LEFT JOIN tbl_subjects s ON m.subject_id = s.id
    LEFT JOIN tbl_division d ON m.division_id = d.id
    WHERE m.uploaded_by = ?
    ORDER BY m.id DESC
");
$stmt->execute([$uploaded_by]);
$materials = $stmt->fetchAll();

$page_title = 'Study Materials Library Manager';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Material Manager</h4>
            <p class="text-muted small mb-0">Upload and target PDF textbooks, assignments, and presentation PPT slides for student access.</p>
        </div>
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
            <i class="fas fa-file-upload"></i>
            <span>Upload Study Material</span>
        </button>
    </div>

    <!-- Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Study Material List Grid -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fw-bold"><i class="fas fa-book-reader text-primary me-2"></i> Your Uploaded Materials</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="20%">Title</th>
                            <th width="15%">Standard / Subject</th>
                            <th width="12%">Target Division</th>
                            <th width="8%">File Type</th>
                            <th width="15%">Date Uploaded</th>
                            <th width="15%" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 d-block text-secondary"></i>
                                    No materials uploaded yet. Click <strong>Upload Study Material</strong> above to get started!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materials as $m): ?>
                                <tr>
                                    <td><?= $m['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($m['title']) ?></div>
                                        <div class="text-muted small text-truncate css-materials-d54b0a"><?= htmlspecialchars($m['description'] ?? 'No description') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($m['course_name']) ?></span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($m['subject_name']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (empty($m['division_name'])): ?>
                                            <span class="badge bg-info">Common (All)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Division <?= htmlspecialchars($m['division_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['file_type'] === 'pdf'): ?>
                                            <span class="text-danger fw-bold"><i class="fas fa-file-pdf me-1"></i> PDF</span>
                                        <?php else: ?>
                                            <span class="text-warning fw-bold"><i class="fas fa-file-powerpoint me-1"></i> PPT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></td>
                                    <td class="text-center">
                                        <a href="<?= BASE_URL . '/' . $m['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3 me-1">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" 
                                                onclick="openEditModal(<?= htmlspecialchars(json_encode($m)) ?>)">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </button>
                                        <a href="?delete=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Are you sure you want to delete this study material?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Upload Study Material -->
<div class="modal fade" id="uploadMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-upload me-2"></i> Upload Study Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Material Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 3: Chemical Bonding Guide" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description / Syllabus details</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Enter simple topic description or page range..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Course/Standard <span class="text-danger">*</span></label>
                            <select name="course_id" class="form-select" required>
                                <option value="">-- Select Standard --</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Division (Optional)</label>
                        <select name="division_id" class="form-select">
                            <option value="">-- Common to All Divisions --</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>">Division <?= htmlspecialchars($d['division_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small text-muted">Leave empty to target all divisions/classes in that standard.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select File (PDF / PPT / PPTX) <span class="text-danger">*</span></label>
                        <input type="file" name="material_file" class="form-control" accept=".pdf,.ppt,.pptx" required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill"><i class="fas fa-cloud-upload-alt me-1"></i> Start Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Study Material -->
<div class="modal fade" id="editMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Edit Study Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="material_id" id="edit_material_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Material Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control" placeholder="e.g. Chapter 3: Chemical Bonding Guide" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description / Syllabus details</label>
                        <textarea name="description" id="edit_description" rows="2" class="form-control" placeholder="Enter simple topic description or page range..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Course/Standard <span class="text-danger">*</span></label>
                            <select name="course_id" id="edit_course_id" class="form-select" required>
                                <option value="">-- Select Standard --</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" id="edit_subject_id" class="form-select" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Division (Optional)</label>
                        <select name="division_id" id="edit_division_id" class="form-select">
                            <option value="">-- Common to All Divisions --</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>">Division <?= htmlspecialchars($d['division_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Replace File (Optional)</label>
                        <input type="file" name="material_file" class="form-control" accept=".pdf,.ppt,.pptx">
                        <div class="form-text small text-muted">Leave empty to retain the current file. Only PDF / PPT / PPTX are supported.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success text-white px-4 rounded-pill"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditModal(mat) {
        $('#edit_material_id').val(mat.id);
        $('#edit_title').val(mat.title);
        $('#edit_description').val(mat.description);
        $('#edit_course_id').val(mat.course_id);
        $('#edit_subject_id').val(mat.subject_id);
        $('#edit_division_id').val(mat.division_id || '');
        $('#editMaterialModal').modal('show');
    }
</script>

<?php include '../../include/footer.php'; ?>
