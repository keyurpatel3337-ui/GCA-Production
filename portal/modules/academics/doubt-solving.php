<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to Super Admin, Principal, Dept Head, and Teachers
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_DEPT_HEAD, ROLE_TEACHER, ROLE_ASSISTANT_TEACHER])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$teacher_id = (int)$_SESSION['user_id'];

// Handle Solve / Reply action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_doubt') {
    $doubt_id = intval($_POST['doubt_id'] ?? 0);
    $reply_text = trim($_POST['reply_text'] ?? '');

    if ($doubt_id <= 0 || empty($reply_text)) {
        set_flash_message('error', 'Reply text is required to resolve a doubt.');
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE tbl_student_material_doubts 
                SET reply_text = ?, replied_by = ?, status = 'Resolved', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$reply_text, $teacher_id, $doubt_id]);
            set_flash_message('success', 'Doubt resolved and reply sent to the student!');
        } catch (Exception $e) {
            set_flash_message('error', 'Error resolving doubt: ' . $e->getMessage());
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch doubts for this teacher's uploaded materials
$stmt = $conn->prepare("
    SELECT d.*, u.name as student_name, m.title as material_title, m.file_path, s.subject_name
    FROM tbl_student_material_doubts d
    JOIN tbl_users u ON d.student_id = u.id
    JOIN tbl_academic_materials m ON d.material_id = m.id
    JOIN tbl_subjects s ON m.subject_id = s.id
    WHERE m.uploaded_by = ?
    ORDER BY d.status DESC, d.id DESC
");
$stmt->execute([$teacher_id]);
$doubts = $stmt->fetchAll();

$page_title = 'Interactive Academic Doubt Desk';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Doubt Solution Desk</h4>
            <p class="text-muted small mb-0">Review and resolve specialized questions flagged page-by-page by students in the Study Library.</p>
        </div>
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

    <!-- Doubts List -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold"><i class="fas fa-question-circle text-danger me-2"></i> Flagged Doubts Registry</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="20%">Student Details</th>
                            <th width="25%">Study Material (Context)</th>
                            <th width="30%">Student Doubt Query</th>
                            <th width="10%">Status</th>
                            <th width="10%" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doubts)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-double fa-3x mb-3 d-block text-success"></i>
                                    All clear! No student doubts are currently pending. Keep up the excellent work!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($doubts as $d): ?>
                                <tr>
                                    <td><?= $d['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($d['student_name']) ?></div>
                                        <div class="text-muted small">Student ID: #<?= $d['student_id'] ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($d['material_title']) ?></div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="badge bg-secondary"><?= htmlspecialchars($d['subject_name']) ?></span>
                                            <span class="badge bg-light text-dark border">Page <?= $d['page_number'] ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="p-2 border rounded bg-light small text-dark fw-bold css-doubt-solving-4f5f61"><?= htmlspecialchars($d['doubt_text']) ?></div>
                                        <?php if ($d['status'] === 'Resolved'): ?>
                                            <div class="mt-2 p-2 border rounded small bg-white text-success border-success css-doubt-solving-4f5f61">
                                                <i class="fas fa-reply me-1"></i> <strong>Your Reply:</strong> <?= htmlspecialchars($d['reply_text']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['status'] === 'Resolved'): ?>
                                            <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fas fa-check me-1"></i> Resolved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($d['status'] === 'Pending'): ?>
                                            <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" onclick="openReplyModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['student_name'])) ?>', '<?= htmlspecialchars(addslashes($d['material_title'])) ?>', <?= $d['page_number'] ?>, '<?= htmlspecialchars(addslashes($d['doubt_text'])) ?>')">
                                                <i class="fas fa-reply me-1"></i> Resolve
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>
                                                Resolved
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal: Reply and Resolve Doubt -->
<div class="modal fade" id="resolveDoubtModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-reply me-2"></i> Resolve Academic Doubt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="resolve_doubt">
                <input type="hidden" name="doubt_id" id="reply_doubt_id">
                <div class="modal-body p-4">
                    <div class="mb-3 p-2 bg-light border rounded">
                        <span class="text-muted small d-block mb-1">Context:</span>
                        <div class="small fw-bold mb-1" id="reply_context_title">Material Title - Page 1</div>
                        <span class="text-muted small d-block mb-1">Student Doubt Query from <strong id="reply_student_name">Student</strong>:</span>
                        <div class="small text-dark italic font-weight-bold" id="reply_doubt_text">"Question text query..."</div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark"><i class="fas fa-reply me-1 text-danger"></i> Enter your detailed solution / explanation <span class="text-danger">*</span></label>
                        <textarea name="reply_text" class="form-control" rows="5" placeholder="Provide a detailed, step-by-step solution to the student's academic doubt..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger text-white px-4 rounded-pill"><i class="fas fa-paper-plane me-1"></i> Send Solution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openReplyModal(doubtId, studentName, materialTitle, pageNum, doubtText) {
        $('#reply_doubt_id').val(doubtId);
        $('#reply_student_name').text(studentName);
        $('#reply_context_title').html(`<i class="fas fa-book me-1"></i> ${materialTitle} <span class="badge bg-secondary ms-1">Page ${pageNum}</span>`);
        $('#reply_doubt_text').text(`"${doubtText}"`);
        $('#resolveDoubtModal').modal('show');
    }
</script>

<?php include '../../include/footer.php'; ?>
