<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$response = $api->get('fees/fee-splits');

if ($response && isset($response['success']) && $response['success']) {
    $splits = $response['data']['splits'] ?? [];
    $total_percentage = $response['data']['total_percentage'] ?? 0;
} else {
    $splits = [];
    $total_percentage = 0;
    set_flash_message('error', $response['error'] ?? 'Failed to load fee splits');
}

$page_title = "Fee Split Management";
$page_breadcrumb = "Management -";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<main class="app-main">


    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo gca_safe_html($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo gca_safe_html($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Summary Card -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card <?php echo $total_percentage == 100 ? 'card-success' : 'card-warning'; ?>">
                    <div class="card-header">
                        <h3 class="card-title">Total Split Percentage</h3>
                    </div>
                    <div class="card-body">
                        <h2 class="text-center">
                            <strong><?php echo formatIndianCurrency($total_percentage); ?>%</strong>
                            <?php if ($total_percentage == 100): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                <span class="text-muted" style="font-size: 16px;">
                                    (Should total 100%)
                                </span>
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>


    </div>
    </div><!-- /.app-content -->
</main><!-- /.app-main -->

<!-- Add Split Modal -->
<div class="modal fade" id="addSplitModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Fee Split</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="fee-split-save.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Split Name <span class="text-danger">*</span></label>
                        <input type="text" name="split_name" class="form-control" required
                            placeholder="e.g., First Installment">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Percentage <span class="text-danger">*</span></label>
                        <input type="number" name="split_percentage" class="form-control" step="0.01" min="0" max="100"
                            required placeholder="e.g., 25.00">
                        <small class="text-muted">Enter value between 0 and 100</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order <span class="text-danger">*</span></label>
                        <input type="number" name="split_order" class="form-control" min="1" required
                            placeholder="e.g., 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                            placeholder="Optional description"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="is_active_add" name="is_active"
                                value="1" checked>
                            <label class="form-check-label" for="is_active_add">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Split</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Split Modal -->
<div class="modal fade" id="editSplitModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Fee Split</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="fee-split-update.php">
                <input type="hidden" name="split_id" id="edit_split_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Split Name <span class="text-danger">*</span></label>
                        <input type="text" name="split_name" id="edit_split_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Percentage <span class="text-danger">*</span></label>
                        <input type="number" name="split_percentage" id="edit_split_percentage" class="form-control"
                            step="0.01" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order <span class="text-danger">*</span></label>
                        <input type="number" name="split_order" id="edit_split_order" class="form-control" min="1"
                            required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active"
                                value="1">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Split</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        // Move modals to body to prevent z-index issues
        $(document).ready(function () {
            $('#addSplitModal').appendTo("body");
            $('#editSplitModal').appendTo("body");
        });

        function editSplit(split) {

            $('#edit_split_id').val(split.id);
            $('#edit_split_name').val(split.split_name);
            $('#edit_split_percentage').val(split.split_percentage);
            $('#edit_split_order').val(split.split_order);
            $('#edit_description').val(split.description || '');
            $('#edit_is_active').prop('checked', split.is_active == 1);
            $('#editSplitModal').modal('show');
        }

        function deleteSplit(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                window.location.href = 'fee-split-delete.php?id=' + id;
            }
        }
    </script>