<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE; // Load frontend globalvariable first
require_once __DIR__ . '/../../common/api_client.php';

// Load paper sets via API - merge GET and POST, POST takes priority
$api = new APIClient();
$response = $api->get('test-management/paper-sets', array_merge($_GET, $_POST));

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $paper_sets = $data['paper_sets'] ?? [];
} else {
    // Fallback to default values if API fails
    $paper_sets = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
            <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-file-alt me-2"></i>Paper Sets Management</h5>
            <div class="d-flex align-items-center">
                <div class="me-3 css-paper-sets-fa3505">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="liveSearch" class="form-control border-start-0 ps-0"
                            placeholder="Live search paper sets...">
                    </div>
                </div>
                <button type="button" class="btn btn-success-custom btn-sm fw-bold px-3" data-bs-toggle="modal"
                    data-bs-target="#addPaperSetModal">
                    <i class="fas fa-plus me-1"></i> Add New Paper Set
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="paperSetsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Paper Set Name</th>
                            <th>Code</th>
                            <th>Questions</th>
                            <th>Complexity (L/M/H)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paper_sets)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No paper sets found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paper_sets as $index => $ps): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ps['paper_set_name'] ?? ''); ?></strong></td>
                                    <td><span
                                            class="badge bg-info text-dark"><?php echo htmlspecialchars($ps['paper_code'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo $ps['total_questions']; ?></td>
                                    <td>
                                        <span class="text-success"><?php echo $ps['low_level_count']; ?></span> /
                                        <span class="text-warning"><?php echo $ps['medium_level_count']; ?></span> /
                                        <span class="text-danger"><?php echo $ps['high_level_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClasses = [
                                            'active' => 'success',
                                            'draft' => 'warning',
                                            'inactive' => 'secondary'
                                        ];
                                        $cls = $statusClasses[$ps['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $cls; ?>"><?php echo ucfirst($ps['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="blueprint.php?id=<?php echo $ps['id']; ?>" class="btn btn-sm btn-primary"
                                            title="Blueprint">
                                            <i class="fas fa-project-diagram"></i>
                                        </a>
                                        <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $ps['id']; ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="paper-set-delete.php?id=<?php echo $ps['id']; ?>" class="btn btn-sm btn-danger"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
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

    <!-- Add Paper Set Modal -->
    <div class="modal fade" id="addPaperSetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Paper Set</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addPaperSetForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Paper Set Name <span class="text-danger">*</span></label>
                                    <input type="text" name="paper_set_name" class="form-control"
                                        placeholder="e.g., Paper Set-01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Paper Code <span class="text-danger">*</span></label>
                                    <input type="text" name="paper_code" class="form-control" placeholder="e.g., PS-01"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Optional description"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Total Questions <span class="text-danger">*</span></label>
                                    <input type="number" name="total_questions" class="form-control" value="100"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Low Level Count</label>
                                    <input type="number" name="low_level_count" class="form-control" value="48">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Medium Level Count</label>
                                    <input type="number" name="medium_level_count" class="form-control" value="26">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>High Level Count</label>
                                    <input type="number" name="high_level_count" class="form-control" value="26">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Paper Set</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    include '../../include/footer.php'; ?>
    <script>
        $(document).ready(function () {
            // Move modal to body to prevent z-index issues
            $('#addPaperSetModal').appendTo("body");

            // Add Paper Set Form Handler
            $('#addPaperSetForm').on('submit', function (e) {
                e.preventDefault();
                $.api.post('test-management/paper-set-save', $(this).serialize())
                    .then(response => {
                        if (response.success) {
                            $('#addPaperSetModal').modal('hide');
                            showToast('success', 'Success!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.error || response.message);
                        }
                    }).catch(error => showToast('error', 'Error!', error.message || 'Failed to save paper set'));
            
        // Live Search Filtering
        $('#liveSearch').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $("#paperSetsTable tbody tr").filter(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(value) > -1);
            });
        });
    });
</script>