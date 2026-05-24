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

$pending_updates = $_SESSION['pending_bulk_updates'] ?? [];
$results = $_SESSION['direct_admission_results'] ?? null;

if (empty($pending_updates)) {
    header('Location: direct-admission-results.php');
    exit;
}

$page_title = "Review Changes";
$page_breadcrumb = "Students";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold text-dark"><i class="fas fa-search-dollar me-2 text-warning"></i>Review & Approve Changes</h1>
            <p class="text-muted">The following students already exist in the database. Please review the changes from the CSV before applying them.</p>
        </div>
    </div>

    <!-- Selected Criteria Display -->
    <?php if (isset($results['context'])):
    $ctx = $results['context']; ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-3 small text-muted">
                        <div><i class="fas fa-calendar-alt me-1 text-primary"></i> <strong>Year:</strong> <?php echo htmlspecialchars($ctx['academic_year_name'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-school me-1 text-primary"></i> <strong>School:</strong> <?php echo htmlspecialchars($ctx['school_name'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-landmark me-1 text-primary"></i> <strong>Board:</strong> <?php echo htmlspecialchars($ctx['board_name'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-language me-1 text-primary"></i> <strong>Medium:</strong> <?php echo htmlspecialchars($ctx['medium_name'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-users me-1 text-primary"></i> <strong>Group:</strong> <?php echo htmlspecialchars($ctx['group_name'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-book-open me-1 text-primary"></i> <strong>Course:</strong> <?php echo htmlspecialchars($ctx['course_name'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
endif; ?>

    <!-- Summary Statistics -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-1">Total Updates</h6>
                    <h3 class="fw-bold mb-0"><?php echo count($pending_updates); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-1">Total New Students</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $results['success_count'] ?? 0; ?> <small class="text-muted small fs-6">(Already Inserted)</small></h3>
                </div>
            </div>
        </div>
    </div>

    <form action="<?php echo BASE_URL; ?>/counselling-backend/controllers/students/direct-admission-process-final.php" method="POST">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-primary">Comparison Table</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="approveSimilar()">
                        <i class="fas fa-check-double me-1"></i> Approve All Similar Names (>90%)
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(true)">Select All</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(false)">Deselect All</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="css-direct-admission-review-ae1f13" class="text-center">Approve</th>
                                <th>Student & Match Status</th>
                                <th>Data Comparison</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_updates as $index => $update):
    $csv = $update['csv_data'];
    $db = $update['db_data'];
    $mismatches = $update['mismatches'];
    $similarity = round($update['similarity'] ?? 0, 1);

    $status_class = "secondary";
    $status_text = "Mismatch";
    if ($similarity >= 95) {
        $status_class = "success";
        $status_text = "Exact Match";
    }
    elseif ($similarity >= 85) {
        $status_class = "primary";
        $status_text = "Likely Match";
    }
    elseif ($similarity < 50) {
        $status_class = "danger";
        $status_text = "Critical Mismatch";
    }
?>
                            <tr class="<?php echo($status_class == 'danger') ? 'bg-light-danger' : ''; ?>" data-similarity="<?php echo $similarity; ?>">
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center">
                                        <input class="form-check-input update-checkbox" type="checkbox" name="approved_indices[]" value="<?php echo $index; ?>" id="check_<?php echo $index; ?>" <?php echo($similarity >= 90) ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="mb-0 fw-bold me-2"><?php echo htmlspecialchars($db['surname'] . ' ' . $db['student_name'] ?? ''); ?></h6>
                                        <span class="badge bg-<?php echo $status_class; ?> small"><?php echo $status_text; ?> (<?php echo $similarity; ?>%)</span>
                                    </div>
                                    <small class="text-muted d-block">ID: <?php echo $update['student_id']; ?> | Row: <?php echo $update['row_number']; ?></small>
                                </td>
                                <td class="p-0">
                                    <table class="table table-sm table-borderless mb-0 bg-transparent">
                                        <tr>
                                            <th class="css-direct-admission-review-b15add" class="small text-muted py-1">Field</th>
                                            <th class="small text-muted py-1">Current (Database)</th>
                                            <th class="small text-muted py-1">New (CSV)</th>
                                        </tr>
                                        <?php
    $fields = [
        'student_name' => 'First Name',
        'mob' => 'Mobile',
        'aadhaar' => 'Aadhaar'
    ];
    foreach ($fields as $key => $label):
        $diff = (strval($db[$key]) !== strval($csv[$key]));
?>
                                        <tr>
                                            <td class="small fw-bold py-0"><?php echo $label; ?></td>
                                            <td class="small py-0"><?php echo htmlspecialchars($db[$key] ?: '-' ?? ''); ?></td>
                                            <td class="small py-0 <?php echo $diff ? 'text-danger fw-bold bg-warning-light' : ''; ?>">
                                                <?php echo htmlspecialchars($csv[$key] ?? ''); ?>
                                                <?php if ($diff): ?> <i class="fas fa-caret-left ms-1"></i> <?php
        endif; ?>
                                            </td>
                                        </tr>
                                        <?php
    endforeach; ?>
                                    </table>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-3 d-flex justify-content-between">
                <a href="direct-admission-upload.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Upload
                </a>
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow">
                    Finalize & Commit Changes <i class="fas fa-save ms-1"></i>
                </button>
            </div>
        </div>
    </form>
</div>



<script>
function toggleAll(checked) {
    document.querySelectorAll('.update-checkbox').forEach(cb => cb.checked = checked);
}

function approveSimilar() {
    toggleAll(false);
    document.querySelectorAll('tr[data-similarity]').forEach(tr => {
        const sim = parseFloat(tr.getAttribute('data-similarity'));
        if (sim >= 90) {
            tr.querySelector('.update-checkbox').checked = true;
        }
    });
}
</script>

<?php include '../../include/footer.php'; ?>
