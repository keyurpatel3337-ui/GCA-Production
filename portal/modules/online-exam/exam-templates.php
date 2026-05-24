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

$page_title = "Exam Templates | OES";

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    $conn->prepare("DELETE FROM tbl_oes_exam_templates WHERE id = ?")->execute([$id]);
    header("Location: exam-templates.php?msg=deleted");
    exit();
}

// Fetch Templates
$query = "SELECT t.*, s.stdtext 
          FROM tbl_oes_exam_templates t 
          LEFT JOIN standard s ON t.standard_id = s.stdid 
          ORDER BY t.created_at ASC";
$templates = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Template deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-layer-group mr-2 text-primary"></i> Template Library</h5>
                        <a href="create-template.php" class="btn btn-primary shadow-sm px-4" style="border-radius: 12px; font-weight: 600;">
                            <i class="fas fa-plus mr-2" style="font-size: 0.75rem;"></i> Create New Template
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 border-0 rounded-start">Template Name</th>
                                    <th class="py-3 border-0">Standard</th>
                                    <th class="py-3 border-0">Questions</th>
                                    <th class="py-3 border-0">Marks</th>
                                    <th class="py-3 border-0">Duration</th>
                                    <th class="pe-4 py-3 border-0 rounded-end text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($templates)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                            <h5>No Templates Found</h5>
                                            <p class="small">Click "Create New Template" to build your first exam blueprint.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($templates as $t): ?>
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3">
                                            <div class="font-weight-bold text-dark"><?= htmlspecialchars($t['template_name']) ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($t['description']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['stdtext']) ?></span></td>
                                        <td><span class="badge bg-info text-white"><?= $t['total_questions'] ?> Qs</span></td>
                                        <td><?= $t['total_marks'] ?></td>
                                        <td><?= $t['duration_mins'] ?> mins</td>
                                        <td class="pe-4 text-end">
                                            <a href="generate-from-template.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-success shadow-sm" style="border-radius: 8px;" title="Generate Exam">
                                                <i class="fas fa-magic"></i> Generate
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this template?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
    </div>
</main>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
