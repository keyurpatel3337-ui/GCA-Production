<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_RECEPTION])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$apiClient = new APIClient();
$response = $apiClient->get('settings/academic-years');

// Debug: Check response
$academic_years = [];
$error_message = '';

if ($response && isset($response['success'])) {
    if ($response['success']) {
        $academic_years = $response['data']['academic_years'] ?? [];
    } else {
        $error_message = $response['message'] ?? 'Failed to fetch academic years';
    }
} else {
    $error_message = 'API response is invalid or empty';
}

// Debug output (remove after testing)
// echo "<!-- Response: " . htmlspecialchars(json_encode($response) ?? '') . " -->";

$page_title = 'Academic Years';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <!-- Header Banner -->

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible shadow-sm rounded-3 border-0 fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-lg me-3"></i>
                <div>
                    <strong>System Error:</strong> <?= htmlspecialchars($error_message ?? '') ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="glass-card stat-card p-4 h-100 border-0 shadow-sm border-start border-primary border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small mb-1 uppercase tracking-wider">Total Cycles</h6>
                        <h2 class="fw-bold mb-0"><?= count($academic_years) ?></h2>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                        <i class="fas fa-history fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card stat-card p-4 h-100 border-0 shadow-sm border-start border-success border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <?php
                    $active_count = count(array_filter($academic_years, function ($y) {
                        return $y['is_active'];
                    }));
                    ?>
                    <div>
                        <h6 class="text-muted small mb-1 uppercase tracking-wider">Active Cycle</h6>
                        <h2 class="fw-bold mb-0"><?= $active_count ?></h2>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success p-3 rounded-3">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card stat-card p-4 h-100 border-0 shadow-sm border-start border-info border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small mb-1 uppercase tracking-wider">Next Cycle</h6>
                        <h2 class="fw-bold mb-0">Scheduled</h2>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info p-3 rounded-3">
                        <i class="fas fa-forward fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="glass-card border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-list-ul me-2 text-primary"></i> Year List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                    onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                    data-bs-target="#addModal">
                    <i class="fas fa-plus me-1"></i> Add New Year
                </button>
                <div id="deleteSelectedBtn" style="display: none;">
                    <button class="btn btn-danger btn-sm rounded-pill px-3" onclick="deleteSelected()">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="academicYearsTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="40">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                        </th>
                        <th>Academic Cycle</th>
                        <th>Duration Period</th>
                        <th>Current Status</th>
                        <th>Author</th>
                        <th class="text-end pe-4">Toolbox</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($academic_years)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-state py-4">
                                    <div class="bg-light p-4 rounded-circle d-inline-block mb-3">
                                        <i class="fas fa-calendar-times fa-3x text-muted"></i>
                                    </div>
                                    <h5 class="text-dark fw-bold">No Records Found</h5>
                                    <p class="text-muted mx-auto" style="max-width: 300px;">Begin by setting up your first
                                        academic cycle using the "Add" button above.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($academic_years as $year): ?>
                            <tr>
                                <td class="ps-4">
                                    <input type="checkbox" class="row-checkbox form-check-input" value="<?= $year['id'] ?>">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="year-icon bg-primary bg-opacity-10 text-primary p-2 rounded me-3">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($year['year_name'] ?? '') ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-muted">From:</span>
                                        <?= $year['start_date'] ? date('d M Y', strtotime($year['start_date'])) : '<span class="text-black-50 italic">N/A</span>' ?>
                                        <br>
                                        <span class="text-muted">To:</span>
                                        &nbsp;&nbsp;&nbsp;<?= $year['end_date'] ? date('d M Y', strtotime($year['end_date'])) : '<span class="text-black-50 italic">N/A</span>' ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($year['is_active']): ?>
                                        <span
                                            class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2">
                                            <i class="fas fa-check-circle me-1 small"></i> Active Cycle
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-3 py-2">
                                            <i class="fas fa-pause-circle me-1 small"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-500"><?= htmlspecialchars($year['created_by_name'] ?? 'System') ?>
                                    </div>
                                    <div class="text-muted smaller"><?= date('d M Y', strtotime($year['created_at'])) ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="action-btns">
                                        <button class="btn btn-icon btn-light text-primary border-0 rounded-circle me-1"
                                            onclick="editItem(<?= htmlspecialchars(json_encode($year) ?? '') ?>)"
                                            title="Manage Cycle">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>
                                        <button class="btn btn-icon btn-light text-danger border-0 rounded-circle"
                                            onclick="deleteItem(<?= $year['id'] ?>)" title="Remove Cycle">
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
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pt-4 px-4 bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus me-2 shadow-sm"></i>Configure Cycle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm" method="POST" action="javascript:void(0);">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-600">Cycle Identity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i
                                        class="fas fa-tag text-muted"></i></span>
                                <input type="text" name="year_name" class="form-control border-start-0"
                                    placeholder="e.g. 2024-2025" required>
                            </div>
                            <div class="form-text smaller mt-1">Naming standard typically follows
                                <strong>YYYY-YYYY</strong> format.
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-600">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-600">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-0 fw-bold small">Current Status</h6>
                                    <div class="text-muted smaller">Enable this cycle for new registrations</div>
                                </div>
                                <div class="form-check form-switch p-0 m-0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input ms-0"
                                        id="add_active" checked style="width: 3rem; height: 1.5rem;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Initialize Cycle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pt-4 px-4 bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 shadow-sm"></i>Modify Cycle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" method="POST" action="javascript:void(0);">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-600">Cycle Identity <span class="text-danger">*</span></label>
                            <div class="input-group drop-shadow-sm">
                                <span class="input-group-text bg-light border-end-0"><i
                                        class="fas fa-tag text-muted"></i></span>
                                <input type="text" name="year_name" id="edit_year_name"
                                    class="form-control border-start-0" required placeholder="Academic period name">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-600">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-600">End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <div
                                class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between border border-warning-subtle">
                                <div>
                                    <h6 class="mb-0 fw-bold small">Cycle Deployment</h6>
                                    <div class="text-muted smaller">Update active status for this year</div>
                                </div>
                                <div class="form-check form-switch p-0 m-0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input ms-0"
                                        id="edit_active" style="width: 3rem; height: 1.5rem;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 shadow-sm fw-bold">Commit
                        Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 20px;
    }

    .welcome-banner {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .stat-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1) !important;
    }

    .year-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .btn-icon:hover {
        transform: scale(1.1);
        filter: brightness(0.9);
    }

    .smaller {
        font-size: 0.75rem;
    }

    .fw-600 {
        font-weight: 600;
    }

    .fw-500 {
        font-weight: 500;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(79, 70, 229, 0.02) !important;
    }

    .text-primary-light {
        color: #60a5fa;
    }
</style>

<!-- Include SheetJS for modern Excel exports -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>
<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Modal management - Move to body to avoid backdrop issues
        $('#addModal').appendTo("body");
        $('#editModal').appendTo("body");

        // UI Interactions
        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            toggleDeleteButton();
        });

        $(document).on('change', '.row-checkbox', function () {
            $('#selectAll').prop('checked', $('.row-checkbox:checked').length === $('.row-checkbox').length);
            toggleDeleteButton();
        });
    });

    function toggleDeleteButton() {
        const count = $('.row-checkbox:checked').length;
        if (count > 0) {
            $('#deleteSelectedBtn').fadeIn(200);
        } else {
            $('#deleteSelectedBtn').fadeOut(200);
        }
    }

    function exportToExcel() {
        TableUtils.exportToExcel('academicYearsTable', 'Academic_Cycles_export');
    }

    function deleteSelected() {
        let selectedIds = [];
        $('.row-checkbox:checked').each(function () {
            selectedIds.push($(this).val());
        });

        showConfirm({
            title: 'Bulk Deletion',
            message: `Are you sure you want to remove ${selectedIds.length} academic cycles? This action cannot be undone.`,
            confirmText: 'Confirm Removal',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                $.api.post('settings/academic-years-delete-multiple', { ids: selectedIds }).then(response => {
                    if (response.success) {
                        showToast('success', 'Cycles Removed', response.message);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast('error', 'Execution Failed', response.message);
                    }
                }).catch(error => {
                    showToast('error', 'System Error', error.message || 'Verification failed');
                });
            }
        });
    }

    $('#addForm').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.api.post('settings/academic-year-save', formData).then(response => {
            if (response.success) {
                showToast('success', 'Cycle Activated', response.message);
                $('#addModal').modal('hide');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('error', 'Configuration Blocked', response.message);
            }
        }).catch(error => {
            showToast('error', 'Submission Fault', error.message || 'API link failure');
        });
    });

    function editItem(data) {
        $('#edit_id').val(data.id);
        $('#edit_year_name').val(data.year_name);
        $('#edit_start_date').val(data.start_date);
        $('#edit_end_date').val(data.end_date);
        $('#edit_active').prop('checked', data.is_active == 1);
        $('#editModal').modal('show');
    }

    $('#editForm').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.api.post('settings/academic-year-update', formData).then(response => {
            if (response.success) {
                showToast('success', 'Cycle Updated', response.message);
                $('#editModal').modal('hide');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('error', 'Update Restrained', response.message);
            }
        }).catch(error => {
            showToast('error', 'Patch Error', error.message || 'Data sync failed');
        });
    });

    function deleteItem(id) {
        showConfirm({
            title: 'Remove Cycle?',
            message: "Deleting this academic cycle may affect historical registration data. Proceed with caution.",
            confirmText: 'Remove Cycle',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                $.api.post('settings/academic-year-delete', { id: id }).then(response => {
                    if (response.success) {
                        showToast('success', 'Cycle Removed', response.message);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast('error', 'Action Aborted', response.message);
                    }
                }).catch(error => {
                    showToast('error', 'API Failure', error.message || 'Request timed out');
                });
            }
        });
    }
</script>