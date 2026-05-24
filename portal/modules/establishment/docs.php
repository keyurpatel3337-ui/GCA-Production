<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Establishment Admin or Super Admin
if (!hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Document Manager";
$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : null;
$student_data = null;
$documents = [];

try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    if ($student_id) {
        $stmt = $conn->prepare("SELECT s.id, s.surname, s.student_name, s.fathername, s.mob, e.enrollment_no 
                               FROM tbl_gm_std_registration s 
                               LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id 
                               WHERE s.id = ?");
        $stmt->execute([$student_id]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student_data) {
            $stmt = $conn->prepare("SELECT d.*, u.name as uploader_name FROM tbl_student_documents d LEFT JOIN tbl_users u ON d.uploaded_by = u.id WHERE d.student_id = ? ORDER BY d.uploaded_at ASC");
            $stmt->execute([$student_id]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Handle File Upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document']) && !isset($_POST['document_id']) && $student_id) {
        $doc_type = $_POST['document_type'];
        $file = $_FILES['document'];

        $enroll_no = $student_data['enrollment_no'] ?? ('REG-' . $student_data['id']);
        $student_folder = $enroll_no . '-' . $student_data['student_name'];
        $storage_root = 'D:/StudentDocuments/';
        $student_path = $storage_root . $student_folder . '/';

        if (!is_dir($student_path)) {
            mkdir($student_path, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $doc_type . '_' . time() . '.' . $extension;
        $target_path = $student_path . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $db_path = $student_folder . '/' . $filename;
            $stmt = $conn->prepare("INSERT INTO tbl_student_documents (student_id, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $doc_type, $db_path, $_SESSION['user_id']]);
            set_flash_message('success', 'Document uploaded successfully to D: drive.');
            header("Location: docs.php?student_id=" . $student_id);
            exit;
        } else {
            set_flash_message('error', 'Failed to move uploaded file to D: drive.');
        }
    }

    // Handle Document Deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
        $doc_id = (int) $_POST['document_id'];

        // Fetch file path first
        $stmt = $conn->prepare("SELECT file_path FROM tbl_student_documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();

        if ($doc) {
            $storage_root = 'D:/StudentDocuments/';
            $full_path = $storage_root . $doc['file_path'];

            // Delete record from DB
            $stmt = $conn->prepare("DELETE FROM tbl_student_documents WHERE id = ?");
            if ($stmt->execute([$doc_id])) {
                // Try to delete physical file
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
                set_flash_message('success', 'Document deleted successfully.');
            }
        }
        header("Location: docs.php?student_id=" . $student_id);
        exit;
    }

    // Handle Document Update/Replace
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_document' && isset($_FILES['document'])) {
        $doc_id = (int) $_POST['document_id'];
        $doc_type = $_POST['document_type'];
        $file = $_FILES['document'];

        // Fetch old file path
        $stmt = $conn->prepare("SELECT file_path FROM tbl_student_documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        $old_doc = $stmt->fetch();

        if ($old_doc) {
            $enroll_no = $student_data['enrollment_no'] ?? ('REG-' . $student_data['id']);
            $student_folder = $enroll_no . '-' . $student_data['student_name'];
            $storage_root = 'D:/StudentDocuments/';
            $student_path = $storage_root . $student_folder . '/';

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $doc_type . '_' . time() . '.' . $extension;
            $target_path = $student_path . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Delete old file
                $old_full_path = $storage_root . $old_doc['file_path'];
                if (file_exists($old_full_path)) {
                    unlink($old_full_path);
                }

                // Update DB
                $db_path = $student_folder . '/' . $filename;
                $stmt = $conn->prepare("UPDATE tbl_student_documents SET document_type = ?, file_path = ?, uploaded_by = ?, uploaded_at = NOW() WHERE id = ?");
                $stmt->execute([$doc_type, $db_path, $_SESSION['user_id'], $doc_id]);

                set_flash_message('success', 'Document updated successfully.');
            }
        }
        header("Location: docs.php?student_id=" . $student_id);
        exit;
    }
} catch (Exception $e) {
    logError("Document Manager Error: " . $e->getMessage());
    set_flash_message('error', 'An error occurred while processing your request.');
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4 pb-5">
    <div class="mb-4 mt-2">
        <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-file-upload me-2 text-primary"></i> Student Document Manager
        </h4>
        <p class="text-muted small mb-0">Upload and verify student identification and academic records.</p>
    </div>

    <div class="row g-4">
        <!-- Student Search -->
        <div class="col-lg-4">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4">Select Student</h5>
                <form action="docs.php" method="GET">
                    <div class="input-group mb-3">
                        <input type="text" name="search" class="form-control" placeholder="Search ID or Name..."
                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search'] ?? '') : ''; ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>

                <?php if (isset($_GET['search'])): ?>
                    <div class="mt-3 overflow-auto css-docs-59cb08">
                        <?php
                        $search_term = $_GET['search'];
                        $search_wildcard = '%' . $search_term . '%';
                        $stmt = $conn->prepare("SELECT id, surname, student_name, fathers_name, CONCAT(surname, ' ', student_name, ' ', fathers_name) as full_name FROM tbl_gm_std_registration 
                                                   WHERE student_name LIKE ? 
                                                   OR surname LIKE ? 
                                                   OR fathers_name LIKE ? 
                                                   OR id LIKE ? 
                                                   OR mob LIKE ? 
                                                   OR CONCAT(surname, ' ', student_name, ' ', fathers_name) LIKE ?");
                        $stmt->execute([$search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard]);
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $is_active = ($student_id == $row['id']);
                            ?>
                            <a href="docs.php?student_id=<?php echo $row['id']; ?>&search=<?php echo urlencode($search_term); ?>"
                                class="text-decoration-none d-block py-3 border-bottom student-list-item <?php echo $is_active ? 'bg-primary bg-opacity-10 border-start border-primary border-4' : ''; ?>"
                                style="transition: all 0.2s ease;">
                                <div class="px-2">
                                    <div class="fw-bold text-dark text-uppercase small mb-1 css-docs-4237ea">
                                        <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>
                                    </div>
                                    <div class="text-muted smaller">ID: <?php echo $row['id']; ?></div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Management Area -->
        <div class="col-lg-8">
            <?php if ($student_data): ?>
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="fw-bold mb-1">
                                <?php echo htmlspecialchars($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . $student_data['fathers_name'] ?? ''); ?>
                            </h4>
                            <span class="badge bg-primary-subtle text-primary">ID:
                                <?php echo $student_data['id']; ?>
                            </span>
                        </div>
                        <button class="btn btn-warning btn-sm fw-bold rounded-pill" data-bs-toggle="modal"
                            data-bs-target="#uploadModal">
                            <i class="fas fa-plus me-1"></i> Add Document
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Document Type</th>
                                    <th>Uploaded By</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="text-muted opacity-50">
                                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                                <p>No documents uploaded yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><i class="far fa-file-pdf text-danger me-2"></i>
                                                    <?php echo htmlspecialchars($doc['document_type'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td><small class="text-muted">
                                                    <?php echo htmlspecialchars($doc['uploader_name'] ?? ''); ?>
                                                </small></td>
                                            <td><small class="text-muted">
                                                    <?php echo date('d M Y, h:i A', strtotime($doc['uploaded_at'])); ?>
                                                </small></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view-doc.php?file=<?php echo urlencode($doc['file_path']); ?>"
                                                        target="_blank" class="btn btn-light border" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-light border"
                                                        onclick="openUpdateModal(<?php echo $doc['id']; ?>, '<?php echo $doc['document_type']; ?>')"
                                                        title="Replace / Change">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-light border text-danger"
                                                        onclick="confirmDelete(<?php echo $doc['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="glass-card p-5 text-center">
                    <i class="fas fa-user-circle fa-4x text-muted opacity-25 mb-4"></i>
                    <h5 class="text-muted">Please select a student to manage their documents.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Update/Replace Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Update/Replace Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="docs.php?student_id=<?php echo $student_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_document">
                <input type="hidden" name="document_id" id="update_doc_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Document Type</label>
                        <select name="document_type" id="update_doc_type" class="form-select" required>
                            <option value="Aadhar Card">Aadhar Card</option>
                            <option value="Tenth Marksheet">10th Marksheet</option>
                            <option value="Twelfth Marksheet">12th Marksheet</option>
                            <option value="Transfer Certificate">Transfer Certificate</option>
                            <option value="Passport Photo">Passport Photo</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select New File</label>
                        <input type="file" name="document" class="form-control" required>
                        <div class="form-text mt-2 small text-danger">Replacing this will delete the old file
                            permanently.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Replace Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="POST" action="docs.php?student_id=<?php echo $student_id; ?>" style="display:none;">
    <input type="hidden" name="action" value="delete_document">
    <input type="hidden" name="document_id" id="delete_doc_id">
</form>

<script>
    function openUpdateModal(id, type) {
        document.getElementById('update_doc_id').value = id;
        document.getElementById('update_doc_type').value = type;
        var modal = new bootstrap.Modal(document.getElementById('updateModal'));
        modal.show();
    }

    function confirmDelete(id) {
        showConfirm({
            title: 'Delete Document',
            message: 'Are you sure you want to delete this document permanently? This action cannot be undone.',
            confirmText: 'Delete Document',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                document.getElementById('delete_doc_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Upload New Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="docs.php?student_id=<?php echo $student_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Document Type</label>
                        <select name="document_type" class="form-select" required>
                            <option value="Aadhar Card">Aadhar Card</option>
                            <option value="Tenth Marksheet">10th Marksheet</option>
                            <option value="Twelfth Marksheet">12th Marksheet</option>
                            <option value="Transfer Certificate">Transfer Certificate</option>
                            <option value="Passport Photo">Passport Photo</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select File</label>
                        <input type="file" name="document" class="form-control" required>
                        <div class="form-text mt-2 small">Supported: JPG, PNG, PDF (Max 5MB)</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Upload Now</button>
                </div>
            </form>
        </div>
    </div>

    
    <?php include '../../include/footer.php'; ?>

