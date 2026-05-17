<?php
/**
 * Bulk Send Credentials with Filtration
 */
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$dbOps = new DatabaseOperations();

$page_title = "Bulk Send Credentials";
$page_breadcrumb = "Students - Bulk Send";

// Get filters from GET
$selected_course = $_GET['course'] ?? '';
$selected_group = $_GET['group'] ?? '';
$selected_medium = $_GET['medium'] ?? '';
$selected_division = $_GET['division'] ?? '';

// Fetch Dropdowns (Mirroring bulk-student-edit logic)
$dropdowns_response = $api->get('students/enrolled', ['per_page' => 1]);
$dropdowns = [];
if ($dropdowns_response && isset($dropdowns_response['success']) && $dropdowns_response['success']) {
    $dropdowns = $dropdowns_response['data']['filters'] ?? [];
}

// Fallback for dropdowns
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
        'per_page' => 500 // Load more for bulk send
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
            <div class="col-sm-6 text-end">
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
                <h5 class="card-title mb-0"><i class="fas fa-filter me-2 text-primary"></i>Filter Students for Bulk Credential Emailing</h5>
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
                    <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Students for Credential Sending (<?php echo count($students); ?> students)</h5>
                    <a href="students.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
                <form action="bulk-send-credentials-process.php" method="POST" id="bulkSendForm">
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
                                            <th>#</th>
                                            <th>Student Name</th>
                                            <th>Aadhaar Number</th>
                                            <th>Mobile (Password)</th>
                                            <th>Email Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $index => $s): 
                                            $sid = $s['id'] ?? $s['registration_id'] ?? null;
                                            if (!$sid) continue;
                                            $has_email = !empty($s['email']);
                                        ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($s['full_name'] ?? 'Unknown'); ?></div>
                                                    <input type="hidden" name="student_ids[]" value="<?php echo $sid; ?>">
                                                </td>
                                                <td><code><?php echo htmlspecialchars($s['aadhaar'] ?? 'N/A'); ?></code></td>
                                                <td><code><?php echo htmlspecialchars($s['mob'] ?? $s['phone'] ?? 'N/A'); ?></code></td>
                                                <td>
                                                    <?php if ($has_email): ?>
                                                        <?php echo htmlspecialchars($s['email'] ?? ''); ?>
                                                    <?php else: ?>
                                                        <span class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i> Missing email</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($has_email): ?>
                                                        <span class="badge bg-success">Ready</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Incomplete</span>
                                                    <?php endif; ?>
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
                            <button type="submit" class="btn btn-primary fw-bold px-5" id="confirmSendBtn">
                                <i class="fas fa-paper-plane me-2"></i> Confirm & Send to All
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
                <p class="text-muted">Select a standard and optionally other filters to load students and send their credentials in bulk.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('bulkSendForm')?.addEventListener('submit', function(e) {
        if (!confirm('Are you sure you want to send credentials to all loaded students? This action will process <?php echo count($students); ?> emails.')) {
            e.preventDefault();
            return;
        }

        const btn = document.getElementById('confirmSendBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
    });
</script>

<?php include '../../include/footer.php'; ?>
