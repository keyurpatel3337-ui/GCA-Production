<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$paper_set_id = $_POST['id'] ?? 0;

// Get paper set details
try {
    $op = new Operation();

    $paper_set = $op->selectOne('tbl_paper_sets', ['*'], ['id' => $paper_set_id]);

    if (!$paper_set) {
        set_flash_message('error', 'Paper set not found!');
        header('Location: paper-sets.php');
        exit;
    }
} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: paper-sets.php');
    exit;
}

$page_title = "Edit Paper Set" ;
$page_breadcrumb = "Set -";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Paper Set Details</h3>
                    </div>
                    <form action="paper-set-update.php" method="POST">
                        <input type="hidden" name="paper_set_id" value="<?php echo $paper_set_id; ?>">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Paper Set Name *</label>
                                <input type="text" name="paper_set_name" class="form-control"
                                    value="<?php echo htmlspecialchars($paper_set['paper_set_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Paper Code *</label>
                                <input type="text" name="paper_code" class="form-control"
                                    value="<?php echo htmlspecialchars($paper_set['paper_code'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3"><?php echo htmlspecialchars($paper_set['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Total Questions</label>
                                <input type="number" name="total_questions" class="form-control"
                                    value="<?php echo $paper_set['total_questions']; ?>" readonly>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Low Level Count</label>
                                        <input type="number" name="low_level_count" class="form-control"
                                            value="<?php echo $paper_set['low_level_count']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Medium Level Count</label>
                                        <input type="number" name="medium_level_count" class="form-control"
                                            value="<?php echo $paper_set['medium_level_count']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>High Level Count</label>
                                        <input type="number" name="high_level_count" class="form-control"
                                            value="<?php echo $paper_set['high_level_count']; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $paper_set['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $paper_set['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="draft" <?php echo $paper_set['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update Paper Set</button>
                            <a href="paper-sets.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Info</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Created By:</strong> Admin</p>
                        <p><strong>Created At:</strong>
                            <?php echo date('d M Y H:i', strtotime($paper_set['created_at'])); ?></p>
                        <p><strong>Last Updated:</strong>
                            <?php echo date('d M Y H:i', strtotime($paper_set['updated_at'])); ?></p>
                        <hr>
                        <a href="blueprint.php?paper_set_id=<?php echo $paper_set_id; ?>"
                            class="btn btn-info btn-block">
                            <i class="fas fa-edit"></i> Edit Blueprint
                        </a>
                        <a href="answer-keys.php?paper_set_id=<?php echo $paper_set_id; ?>"
                            class="btn btn-success btn-block">
                            <i class="fas fa-key"></i> Manage Answer Keys
                        </a>
                    </div>
                </div>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>
