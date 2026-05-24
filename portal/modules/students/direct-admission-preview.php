<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pending = $_SESSION['pending_admission_data'] ?? null;
if (!$pending) {
    header('Location: direct-admission-upload.php');
    exit;
}

$page_title = "Review & Approve Upload";
$page_breadcrumb = "Students";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold text-dark"><i class="fas fa-search-plus me-2 text-primary"></i>Review Upload Data</h1>
            <p class="text-muted">Please review the data before committing to the database. Updates require explicit approval.</p>
        </div>
    </div>

    <form action="../../../counselling-backend/controllers/students/direct-admission-commit.php" method="POST">
        
        <!-- Summary Info -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-success text-white">
                    <div class="card-body">
                        <h6 class="text-white-50 small mb-1">NEW ENTRIES</h6>
                        <h3 class="mb-0 fw-bold"><?php echo count($pending['new']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-warning text-dark">
                    <div class="card-body">
                        <h6 class="text-dark-50 small mb-1">POTENTIAL UPDATES</h6>
                        <h3 class="mb-0 fw-bold"><?php echo count($pending['updates']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-danger text-white">
                    <div class="card-body">
                        <h6 class="text-white-50 small mb-1">ERRORS skipped</h6>
                        <h3 class="mb-0 fw-bold"><?php echo count($pending['errors']); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($pending['updates'])): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-warning text-dark py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-sync-alt me-2"></i>Comparison: Existing vs New Data</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-dark" onclick="selectAll(true)">Approve All</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="selectAll(false)">Reject All</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="css-direct-admission-preview-ae1f13">Approve</th>
                                <th>Student (Aadhar/Mob)</th>
                                <th>Field Comparison</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending['updates'] as $index => $item): 
                                $is_name_mismatch = isset($item['diff']['student_name']) || isset($item['diff']['surname']);
                                $row_class = $is_name_mismatch ? 'table-danger' : '';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="text-center">
                                    <input type="checkbox" name="approved_updates[]" value="<?php echo $index; ?>" class="form-check-input update-check" <?php echo $is_name_mismatch ? '' : 'checked'; ?>>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['current']['surname'] . ' ' . $item['current']['student_name'] ?? ''); ?></div>
                                    <div class="small text-muted">ID: <?php echo $item['id']; ?> | Aadhar: <?php echo $item['new']['aadhaar']; ?></div>
                                </td>
                                <td class="p-0">
                                    <table class="table table-sm table-borderless mb-0 small">
                                        <?php foreach ($item['diff'] as $field => $values): ?>
                                        <tr>
                                            <td class="text-uppercase text-muted fw-bold css-direct-admission-preview-b15add"><?php echo str_replace('_', ' ', $field); ?>:</td>
                                            <td class="text-danger strike-through"><?php echo htmlspecialchars($values['old'] ?? ''); ?></td>
                                            <td class="text-primary fw-bold"><i class="fas fa-arrow-right mx-2"></i> <?php echo htmlspecialchars($values['new'] ?? ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($item['diff'])): ?>
                                            <tr><td colspan="3" class="text-success"><i class="fas fa-check-circle me-1"></i> No changes in core identifying fields</td></tr>
                                        <?php endif; ?>
                                    </table>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($pending['new'])): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2"></i>New Students (Will be Inserted)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Name</th>
                                <th>DOB</th>
                                <th>Mobile</th>
                                <th>Aadhaar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending['new'] as $student): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($student['surname'] . ' ' . $student['student_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['dob'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mb-5">
            <a href="direct-admission-upload.php" class="btn btn-light px-4 btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Back to Upload
            </a>
            <button type="submit" class="btn btn-primary px-5 btn-lg shadow" onclick="return confirm('Are you sure you want to commit these changes?')">
                <i class="fas fa-check-double me-2"></i>Confirm & Save Data
            </button>
        </div>
    </form>
</div>



<script>
function selectAll(checked) {
    document.querySelectorAll('.update-check').forEach(cb => {
        cb.checked = checked;
    });
}
</script>

<?php include '../../include/footer.php'; ?>
