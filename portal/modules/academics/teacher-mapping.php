<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to Super Admin, Principal, and Dept Head
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_DEPT_HEAD])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$apiClient = new APIClient();
$response = $apiClient->get('academics/teacher-mappings');

$teachers = [];
$subjects = [];
$divisions = [];

if ($response && isset($response['success']) && $response['success']) {
    $teachers = $response['data']['teachers'] ?? [];
    $subjects = $response['data']['subjects'] ?? [];
    $divisions = $response['data']['divisions'] ?? [];
} else {
    set_flash_message('error', $response['message'] ?? 'Failed to load teacher mappings');
}

$page_title = 'Teacher Allocation Mappings';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Teacher Mappings</h4>
            <p class="text-muted small mb-0">Configure teacher subject specializations and division class assignments.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
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

    <!-- Search & Tabs Navigation -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-0">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center px-4 py-3 border-bottom gap-3">
                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs border-0 mb-0" id="mappingTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects-pane" type="button" role="tab">
                            <i class="fas fa-book me-2 text-primary"></i> Subject Allocation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="divisions-tab" data-bs-toggle="tab" data-bs-target="#divisions-pane" type="button" role="tab">
                            <i class="fas fa-users me-2 text-success"></i> Division Allocation
                        </button>
                    </li>
                </ul>

                <!-- Live Search Bar -->
                <div class="search-box-container css-teacher-mapping-1f44ea">
                    <i class="fas fa-search"></i>
                    <input type="text" id="liveSearchInput" class="form-control rounded-pill shadow-sm" placeholder="Search teacher by name or dept...">
                </div>
            </div>

            <!-- Tab Contents -->
            <div class="tab-content p-4" id="mappingTabContent">
                <!-- TAB 1: SUBJECT ALLOCATIONS -->
                <div class="tab-pane fade show active" id="subjects-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0" id="subjectsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="20%">Teacher Name</th>
                                    <th width="15%">Role / Designation</th>
                                    <th width="15%">Department</th>
                                    <th width="35%">Allocated Subjects</th>
                                    <th width="10%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teachers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No active teachers found in the system.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teachers as $t): ?>
                                        <tr class="teacher-row" data-name="<?= htmlspecialchars(strtolower($t['name'])) ?>" data-dept="<?= htmlspecialchars(strtolower($t['department'] ?? '')) ?>">
                                            <td><?= $t['id'] ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($t['name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($t['role_name'] ?? 'N/A') ?></span>
                                                <?php if (!empty($t['designation'])): ?>
                                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($t['designation']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($t['department'] ?? 'N/A') ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if (empty($t['subjects'])): ?>
                                                        <span class="pill-badge pill-empty"><i class="fas fa-info-circle"></i> None Mapped</span>
                                                    <?php else: ?>
                                                        <?php foreach ($t['subjects'] as $sub): ?>
                                                            <span class="pill-badge pill-subject"><i class="fas fa-book-open"></i> <?= htmlspecialchars($sub['name']) ?></span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" 
                                                        onclick="openSubjectModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name'])) ?>', <?= htmlspecialchars(json_encode(array_column($t['subjects'], 'id')) ?: '[]') ?>)">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: DIVISION ALLOCATIONS -->
                <div class="tab-pane fade" id="divisions-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0" id="divisionsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="20%">Teacher Name</th>
                                    <th width="15%">Role / Designation</th>
                                    <th width="15%">Department</th>
                                    <th width="35%">Allocated Divisions</th>
                                    <th width="10%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teachers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No active teachers found in the system.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teachers as $t): ?>
                                        <tr class="teacher-row" data-name="<?= htmlspecialchars(strtolower($t['name'])) ?>" data-dept="<?= htmlspecialchars(strtolower($t['department'] ?? '')) ?>">
                                            <td><?= $t['id'] ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($t['name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($t['role_name'] ?? 'N/A') ?></span>
                                                <?php if (!empty($t['designation'])): ?>
                                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($t['designation']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($t['department'] ?? 'N/A') ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if (empty($t['divisions'])): ?>
                                                        <span class="pill-badge pill-empty"><i class="fas fa-info-circle"></i> None Mapped</span>
                                                    <?php else: ?>
                                                        <?php foreach ($t['divisions'] as $div): ?>
                                                            <span class="pill-badge pill-division"><i class="fas fa-chalkboard"></i> <?= htmlspecialchars($div['name']) ?></span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" 
                                                        onclick="openDivisionModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name'])) ?>', <?= htmlspecialchars(json_encode(array_column($t['divisions'], 'id')) ?: '[]') ?>)">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </button>
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
</div>

<!-- Modal: Subject Mapping Sync -->
<div class="modal fade" id="subjectMappingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-book-reader me-2"></i> Edit Subject Allocations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="subjectMappingForm">
                <input type="hidden" name="teacher_id" id="subject_teacher_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted small d-block mb-1">Mapping For:</span>
                        <h5 class="fw-bold text-dark mb-0" id="subject_teacher_name">---</h5>
                    </div>
                    <hr>
                    <label class="form-label fw-bold text-dark mb-2"><i class="fas fa-list-check me-1 text-primary"></i> Select Specialized Subjects</label>
                    <div class="checkbox-grid">
                        <?php foreach ($subjects as $s): ?>
                            <label class="checkbox-item mb-0">
                                <input type="checkbox" name="subject_ids[]" value="<?= $s['id'] ?>" class="subject-checkbox form-check-input">
                                <span><?= htmlspecialchars($s['subject_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill"><i class="fas fa-save me-1"></i> Sync Allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Division Mapping Sync -->
<div class="modal fade" id="divisionMappingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-users me-2"></i> Edit Division Allocations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="divisionMappingForm">
                <input type="hidden" name="teacher_id" id="division_teacher_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted small d-block mb-1">Mapping For:</span>
                        <h5 class="fw-bold text-dark mb-0" id="division_teacher_name">---</h5>
                    </div>
                    <hr>
                    <label class="form-label fw-bold text-dark mb-2"><i class="fas fa-list-check me-1 text-success"></i> Select Classes/Divisions</label>
                    <div class="checkbox-grid">
                        <?php foreach ($divisions as $d): ?>
                            <label class="checkbox-item mb-0">
                                <input type="checkbox" name="division_ids[]" value="<?= $d['id'] ?>" class="division-checkbox form-check-input">
                                <span><?= htmlspecialchars($d['division_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success text-white px-4 rounded-pill"><i class="fas fa-save me-1"></i> Sync Allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Live Search Logic
        const liveSearchInput = document.getElementById('liveSearchInput');
        
        liveSearchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.teacher-row');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const dept = row.getAttribute('data-dept');
                if (name.includes(query) || dept.includes(query)) {
                    row.style.setProperty('display', '', 'important');
                } else {
                    row.style.setProperty('display', 'none', 'important');
                }
            });
        });

        // 2. Subject Mapping Sync Form Submission
        $('#subjectMappingForm').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.api.post('academics/teacher-subject-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Allocated!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Failed!', response.message || 'Saving mapping failed.');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Sync Allocation');
                    }
                })
                .catch(() => {
                    showToast('error', 'Error!', 'Network/API connection error.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Sync Allocation');
                });
        });

        // 3. Division Mapping Sync Form Submission
        $('#divisionMappingForm').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.api.post('academics/teacher-division-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Allocated!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Failed!', response.message || 'Saving mapping failed.');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Sync Allocation');
                    }
                })
                .catch(() => {
                    showToast('error', 'Error!', 'Network/API connection error.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Sync Allocation');
                });
        });
    });

    // 4. Modal Triggers
    function openSubjectModal(teacherId, teacherName, currentSubjectIds) {
        $('#subject_teacher_id').val(teacherId);
        $('#subject_teacher_name').text(teacherName);
        
        // Reset checkboxes
        $('.subject-checkbox').prop('checked', false);
        
        // Check current allocations
        if (currentSubjectIds && Array.isArray(currentSubjectIds)) {
            currentSubjectIds.forEach(id => {
                $(`.subject-checkbox[value="${id}"]`).prop('checked', true);
            });
        }
        
        $('#subjectMappingModal').modal('show');
    }

    function openDivisionModal(teacherId, teacherName, currentDivisionIds) {
        $('#division_teacher_id').val(teacherId);
        $('#division_teacher_name').text(teacherName);
        
        // Reset checkboxes
        $('.division-checkbox').prop('checked', false);
        
        // Check current allocations
        if (currentDivisionIds && Array.isArray(currentDivisionIds)) {
            currentDivisionIds.forEach(id => {
                $(`.division-checkbox[value="${id}"]`).prop('checked', true);
            });
        }
        
        $('#divisionMappingModal').modal('show');
    }
</script>

<?php include '../../include/footer.php'; ?>
