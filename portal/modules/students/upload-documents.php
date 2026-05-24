<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Check access - Principal, Establishment, Reception or Super Admin
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$student_id) {
    header('Location: students.php');
    exit;
}

$student_name = "Unknown Student";
$student_data = null;
$existing_docs = [];

try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    // Fetch student data
    $stmt = $conn->prepare("SELECT s.id, s.surname, s.student_name, s.fathers_name, s.board_id, e.enrollment_no 
                           FROM tbl_gm_std_registration s 
                           LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id 
                           WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student_data) {
        $student_name = trim(($student_data['surname'] ?? '') . ' ' . ($student_data['student_name'] ?? '') . ' ' . ($student_data['fathers_name'] ?? ''));

        // Fetch existing documents
        $stmt = $conn->prepare("SELECT id, document_type, file_path FROM tbl_student_documents WHERE student_id = ?");
        $stmt->execute([$student_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_docs[$row['document_type']] = $row;
        }
    }

    // Handle Individual Document Upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_doc']) && isset($_POST['doc_type'])) {
        $doc_type = $_POST['doc_type'];
        $file = $_FILES['student_doc'];

        if ($file['error'] === 0 && $student_data) {
            $enroll_no = $student_data['enrollment_no'] ?? ('REG-' . $student_data['id']);
            $student_folder = $enroll_no . '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $student_data['student_name']);
            $storage_root = 'D:/StudentDocuments/';
            $student_path = $storage_root . $student_folder . '/';

            if (!is_dir($student_path)) {
                mkdir($student_path, 0777, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = preg_replace('/[^A-Za-z0-9]/', '_', $doc_type) . '_' . time() . '.' . $extension;
            $target_path = $student_path . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Check if document of this type already exists to delete old record/file
                if (isset($existing_docs[$doc_type])) {
                    $old_full_path = $storage_root . $existing_docs[$doc_type]['file_path'];
                    if (file_exists($old_full_path)) {
                        unlink($old_full_path);
                    }
                    $stmt = $conn->prepare("UPDATE tbl_student_documents SET file_path = ?, uploaded_by = ?, uploaded_at = NOW() WHERE id = ?");
                    $db_path = $student_folder . '/' . $filename;
                    $stmt->execute([$db_path, $_SESSION['user_id'], $existing_docs[$doc_type]['id']]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO tbl_student_documents (student_id, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                    $db_path = $student_folder . '/' . $filename;
                    $stmt->execute([$student_id, $doc_type, $db_path, $_SESSION['user_id']]);
                }

                set_flash_message('success', $doc_type . ' uploaded successfully.');
                header("Location: upload-documents.php?id=" . $student_id);
                exit;
            } else {
                set_flash_message('error', 'Failed to save document. Please check disk permissions.');
            }
        }
    }
} catch (Exception $e) {
    logError("Document Upload Page Error: " . $e->getMessage());
}

$board_id = $student_data['board_id'] ?? 0;
$required_docs = [
    'School Leaving Certificate' => 'Original + 3 xerox copy',
];

if ($board_id == 1) { // GSEB - Gujarat Board
    $required_docs['S.S.C. Marksheet'] = '4 Xerox Copy';
} else {
    $required_docs['10th Marksheet (Other Board)'] = 'Original + 4 xerox copy (Original will be returned after verification)';
    $required_docs['Migration Certificate'] = 'Original + 2 xerox copy for other boards';
}

$required_docs += [
    'First Attempt Certificate' => '1 xerox copy',
    'Aadhar Card' => '1 xerox copy',
    'Passport Size Photo' => '1 passport size PHOTO'
];

$page_title = "Document Repository - " . $student_name;
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4 mt-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($student_name ?? ''); ?></h4>
            <p class="text-muted small mb-0">ID: #<?php echo htmlspecialchars($student_id ?? ''); ?> | Enrollment:
                <?php echo htmlspecialchars($student_data['enrollment_no'] ?? 'N/A'); ?>
            </p>
        </div>
        <div class="text-end">
            <a href="students.php?view=enrolled" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Document Checkbox List -->
        <div class="col-lg-12">
            <div class="glass-card border-0 shadow-sm overflow-hidden">
                <div
                    class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-tasks me-2 text-primary"></i> Documentation Checklist
                    </h5>
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <?php echo count($existing_docs); ?> / <?php echo count($required_docs); ?> Uploaded
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 ps-4 py-3 css-upload-documents-ae1f13">Status</th>
                                <th class="border-0 py-3">Document Type</th>
                                <th class="border-0 py-3">Description</th>
                                <th class="border-0 py-3 text-center css-upload-documents-5c2dc9">Actions</th>
                                <th class="border-0 pe-4 py-3 text-end css-upload-documents-c25159">Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($required_docs as $type => $desc):
                                $is_uploaded = isset($existing_docs[$type]);
                                ?>
                                <tr class="<?php echo $is_uploaded ? 'table-success bg-opacity-10' : ''; ?>">
                                    <td class="ps-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" disabled <?php echo $is_uploaded ? 'checked' : ''; ?>
                                                style="width: 1.5rem; height: 1.5rem; border-color: #dee2e6;">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo $type; ?></div>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?php echo $desc; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <form action="" method="POST" enctype="multipart/form-data" class="d-inline-block">
                                            <input type="hidden" name="doc_type" value="<?php echo $type; ?>">
                                            <input type="file" name="student_doc" id="file_<?php echo md5($type); ?>"
                                                class="d-none" onchange="this.form.submit()" accept=".pdf,.jpg,.jpeg,.png">

                                            <button type="button"
                                                class="btn <?php echo $is_uploaded ? 'btn-outline-primary' : 'btn-primary'; ?> btn-sm px-3 rounded-pill"
                                                onclick="window.document.getElementById('file_<?php echo md5($type); ?>').click()">
                                                <i
                                                    class="fas <?php echo $is_uploaded ? 'fa-sync-alt' : 'fa-upload'; ?> me-1"></i>
                                                <?php echo $is_uploaded ? 'Replace' : 'Upload'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <?php if ($is_uploaded): ?>
                                            <a href="../establishment/view-doc.php?file=<?php echo urlencode($existing_docs[$type]['file_path']); ?>"
                                                target="_blank" class="btn btn-info btn-sm rounded-circle shadow-sm"
                                                style="width: 32px; height: 32px; padding: 0; line-height: 32px;"
                                                title="View Document">
                                                <i class="fas fa-eye fa-xs text-white"></i>
                                            </a>
                                            <!-- Mini Preview for Images -->
                                            <?php
                                            $ext = strtolower(pathinfo($existing_docs[$type]['file_path'], PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                                                <div class="mt-1">
                                                    <img src="../establishment/view-doc.php?file=<?php echo urlencode($existing_docs[$type]['file_path']); ?>"
                                                        class="rounded shadow-sm border"
                                                        style="width: 40px; height: 40px; object-fit: cover;">
                                                </div>
                                            <?php else: ?>
                                                <div class="mt-1">
                                                    <span
                                                        class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle small px-2">PDF</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted smaller">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../../include/footer.php'; ?>