<?php
/**
 * Dedicated Bulk Student Edit Page
 */
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$dbOps = new DatabaseOperations();

$page_title = "Bulk Student Edit";
$page_breadcrumb = "Students - Bulk Edit";

// Get filters from GET
$selected_course = $_GET['course'] ?? '';
$selected_group = $_GET['group'] ?? '';
$selected_medium = $_GET['medium'] ?? '';
$selected_division = $_GET['division'] ?? '';

// Fetch Dropdowns (Using API if possible for consistency, otherwise direct DB)
$dropdowns_response = $api->get('students/enrolled', ['per_page' => 1]); // Quick way to get dropdown values if the API provides them
$dropdowns = [];
if ($dropdowns_response && isset($dropdowns_response['success']) && $dropdowns_response['success']) {
    $dropdowns = $dropdowns_response['data']['filters'] ?? [];
}

// Fallback for dropdowns if API doesn't provide all needed or fails
if (empty($dropdowns['courses'])) {
    $dropdowns['courses'] = $dbOps->customSelect("SELECT id, course_name FROM tbl_courses WHERE is_active = 1 ORDER BY course_name");
}
if (empty($dropdowns['groups'])) {
    $dropdowns['groups'] = $dbOps->customSelect("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name");
}
if (empty($dropdowns['mediums'])) {
    $dropdowns['mediums'] = $dbOps->customSelect("SELECT id, medium_name FROM tbl_medium WHERE is_active = 1 ORDER BY medium_name");
}
if (empty($dropdowns['divisions'])) {
    $dropdowns['divisions'] = $dbOps->customSelect("SELECT id, division_name FROM tbl_division WHERE is_active = 1 ORDER BY division_name");
}

$students = [];
if ($selected_course) {
    // Build filter for loading students
    $load_filters = [
        'course' => $selected_course,
        'group' => $selected_group,
        'medium' => $selected_medium,
        'division' => $selected_division,
        'per_page' => 100 // Load up to 100 for bulk edit
    ];
    
    $students_response = $api->get('students/enrolled', $load_filters);
    if ($students_response && isset($students_response['success']) && $students_response['success']) {
        $students = $students_response['data']['students'] ?? [];
    }
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h3 class="mb-0"><?php echo $page_title; ?></h3>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                    <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <!-- Filters Card -->
        <div class="card card-outline card-primary mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-filter me-2 text-primary"></i>Select Students to Edit</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Standard <span class="text-danger">*</span></label>
                        <select name="course" required class="form-select">
                            <option value="">-- Select Standard --</option>
                            <option value="11th" <?php echo $selected_course == '11th' ? 'selected' : ''; ?>>11th</option>
                            <option value="12th" <?php echo $selected_course == '12th' ? 'selected' : ''; ?>>12th</option>
                            <option value="Reneet" <?php echo $selected_course == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Group</label>
                        <select name="group" class="form-select">
                            <option value="">-- All Groups --</option>
                            <?php foreach ($dropdowns['groups'] as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $selected_group == $g['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['group_name'] ?? $g['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Medium</label>
                        <select name="medium" class="form-select">
                            <option value="">-- All Mediums --</option>
                            <?php foreach ($dropdowns['mediums'] as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $selected_medium == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['medium_name'] ?? $m['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Division</label>
                        <select name="division" class="form-select">
                            <option value="">-- All Divisions --</option>
                            <?php foreach ($dropdowns['divisions'] as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $selected_division == $d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['division_name'] ?? $d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fas fa-sync-alt me-2"></i> Load Students
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_course): ?>
            <div class="card card-warning card-outline">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Found <?php echo count($students); ?> Students</h5>
                    <a href="students.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
                <form action="bulk-edit-save.php" method="POST" id="bulkEditForm">
                    <!-- Hidden fields to preserve filters after redirect -->
                    <input type="hidden" name="redirect_course" value="<?php echo $selected_course; ?>">
                    <input type="hidden" name="redirect_group" value="<?php echo $selected_group; ?>">
                    <input type="hidden" name="redirect_medium" value="<?php echo $selected_medium; ?>">
                    <input type="hidden" name="redirect_division" value="<?php echo $selected_division; ?>">

                    <div class="card-body p-0">
                        <?php if (empty($students)): ?>
                            <div class="p-5 text-center">
                                <i class="fas fa-users-slash text-muted fa-3x mb-3"></i>
                                <p class="text-muted fw-bold">No students found matching current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0">
                                    <thead class="table-dark small">
                                        <tr>
                                            <th style="min-width: 150px;">Student Name</th>
                                            <th>GR No</th>
                                            <th>Roll No</th>
                                            <th style="min-width: 150px;">Division</th>
                                            <th>Mobile No</th>
                                            <th>Alt Mobile</th>
                                            <th>Email</th>
                                            <th>Parent Mobile</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $s): 
                                            $sid = $s['id'] ?? $s['registration_id'] ?? null;
                                            if (!$sid) continue;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($s['full_name'] ?? 'Unknown'); ?></div>
                                                    <small class="text-muted">ID: <?php echo $sid; ?></small>
                                                    <input type="hidden" name="students[<?php echo $sid; ?>][id]" value="<?php echo $sid; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" name="students[<?php echo $sid; ?>][gr_no]" 
                                                        value="<?php echo htmlspecialchars($s['gr_no'] ?? ''); ?>" 
                                                        class="form-control form-control-sm" placeholder="GR No">
                                                </td>
                                                <td>
                                                    <input type="number" name="students[<?php echo $sid; ?>][roll_no]" 
                                                        value="<?php echo htmlspecialchars($s['roll_no'] ?? ''); ?>" 
                                                        class="form-control form-control-sm" placeholder="Roll">
                                                </td>
                                                <td>
                                                    <select name="students[<?php echo $sid; ?>][division_id]" class="form-select form-control-sm">
                                                        <option value="">-- No Division --</option>
                                                        <?php foreach ($dropdowns['divisions'] as $d): ?>
                                                            <option value="<?php echo $d['id']; ?>" <?php echo (isset($s['division_id']) && $s['division_id'] == $d['id']) || (isset($s['division']) && $s['division'] == $d['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($d['division_name'] ?? $d['name'] ?? ''); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="students[<?php echo $sid; ?>][mob]" 
                                                        value="<?php echo htmlspecialchars($s['mob'] ?? $s['phone'] ?? ''); ?>" 
                                                        class="form-control form-control-sm" placeholder="Mobile">
                                                </td>
                                                <td>
                                                    <input type="text" name="students[<?php echo $sid; ?>][amob]" 
                                                        value="<?php echo htmlspecialchars($s['amob'] ?? ''); ?>" 
                                                        class="form-control form-control-sm" placeholder="Alt Mobile">
                                                </td>
                                                <td>
                                                    <input type="email" name="students[<?php echo $sid; ?>][email]" 
                                                        value="<?php echo htmlspecialchars($s['email'] ?? ''); ?>" 
                                                        class="form-control form-control-sm" placeholder="Email">
                                                </td>
                                                <td>
                                                    <input type="text" name="students[<?php echo $sid; ?>][parent_mob]" 
                                                        value="<?php echo htmlspecialchars($s['parent_mob'] ?? ''); ?>" 
                                                        class="form-control form-control-sm" placeholder="Parent Mob">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($students)): ?>
                        <div class="card-footer bg-light text-end p-3">
                            <a href="students.php" class="btn btn-secondary me-2">Discard Changes</a>
                            <button type="submit" class="btn btn-warning fw-bold px-5">
                                <i class="fas fa-save me-2"></i> Save All Changes
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-white rounded p-5 text-center shadow-sm border">
                <div class="w-16 h-16 bg-blue-50 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px;">
                    <i class="fas fa-filter text-primary fa-3x"></i>
                </div>
                <h4 class="fw-bold">Apply Filters to Load Students</h4>
                <p class="text-muted">Select a course and optionally other filters to start editing student records in bulk.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('bulkEditForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        showConfirm({
            title: 'Save All Changes',
            message: 'Are you sure you want to save all changes? This will update records for all students listed below.',
            confirmText: 'Yes, Save Changes',
            confirmButtonClass: 'btn-warning',
            onConfirm: function () {
                form.submit();
            }
        });
    });
</script>

<?php include '../../include/footer.php'; ?>

