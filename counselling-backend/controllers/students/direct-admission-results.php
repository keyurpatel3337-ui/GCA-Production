<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user has appropriate role
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$results = $_SESSION['direct_admission_results'] ?? null;

// If no results in session, redirect back to upload page
if (!$results) {
    header('Location: direct-admission-upload.php');
    exit;
}

// Clear results from session after fetching so they don't persist on refresh
// unset($_SESSION['direct_admission_results']); 
// Actually, let's keep it for one more refresh in case of accidental reload? 
// Or better, let the user manually go back.

$page_title = "Direct Admission Results";
$page_breadcrumb = "Students";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-bold text-dark"><i class="fas fa-clipboard-check me-2 text-success"></i>Upload Results</h1>
                <p class="text-muted">Summary of the bulk admission process.</p>
            </div>
            <a href="direct-admission-upload.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Upload
            </a>
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

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <!-- Hidden Success/Update Cards as per request -->
        <?php /* ?>
  <div class="col-md-4">
  <div class="card border-0 shadow-sm bg-success text-white">
  <div class="card-body py-4">
  <div class="d-flex justify-content-between">
  <div>
  <h6 class="text-white-50 text-uppercase mb-2">New Students</h6>
  <h2 class="mb-0 fw-bold"><?php echo $results['success_count']; ?></h2>
  </div>
  <i class="fas fa-user-plus fa-3x opacity-25"></i>
  </div>
  </div>
  </div>
  </div>
  <div class="col-md-4">
  <div class="card border-0 shadow-sm bg-info text-white">
  <div class="card-body py-4">
  <div class="d-flex justify-content-between">
  <div>
  <h6 class="text-white-50 text-uppercase mb-2">Updated Students</h6>
  <h2 class="mb-0 fw-bold"><?php echo $results['update_count']; ?></h2>
  </div>
  <i class="fas fa-user-edit fa-3x opacity-25"></i>
  </div>
  </div>
  </div>
  </div>
  <?php */?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body py-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50 text-uppercase mb-2">Failed Rows</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $results['error_count']; ?></h2>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Hidden Success/Update Tables as per request -->
        <?php /* ?>
  <?php if (!empty($results['added'])): ?>
  <div class="col-lg-6 mb-4">
  <div class="card shadow-sm border-0 h-100">
  <div class="card-header bg-white py-3">
  <h5 class="mb-0 text-success fw-bold"><i class="fas fa-check-circle me-2"></i>Newly Added Students</h5>
  </div>
  <div class="card-body p-0">
  <div class="table-responsive">
  <table class="table table-hover mb-0">
  <thead class="bg-light">
  <tr>
  <th>#</th>
  <th>Student Name</th>
  <th>Mobile</th>
  <th>Aadhaar</th>
  </tr>
  </thead>
  <tbody>
  <?php foreach ($results['added'] as $index => $student): ?>
  <tr>
  <td><?php echo $index + 1; ?></td>
  <td class="fw-bold"><?php echo htmlspecialchars($student['name'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
  </div>
  </div>
  </div>
  </div>
  <?php endif; ?>
  <?php if (!empty($results['updated'])): ?>
  <div class="col-lg-6 mb-4">
  <div class="card shadow-sm border-0 h-100">
  <div class="card-header bg-white py-3">
  <h5 class="mb-0 text-info fw-bold"><i class="fas fa-sync-alt me-2"></i>Updated Students</h5>
  </div>
  <div class="card-body p-0">
  <div class="table-responsive">
  <table class="table table-hover mb-0">
  <thead class="bg-light">
  <tr>
  <th>#</th>
  <th>Student Name</th>
  <th>Mobile</th>
  <th>Aadhaar</th>
  </tr>
  </thead>
  <tbody>
  <?php foreach ($results['updated'] as $index => $student): ?>
  <tr>
  <td><?php echo $index + 1; ?></td>
  <td class="fw-bold"><?php echo htmlspecialchars($student['name'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
  </div>
  </div>
  </div>
  </div>
  <?php endif; ?>
  <?php */?>

        <?php if (!empty($results['errors'])): ?>
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-danger fw-bold"><i class="fas fa-times-circle me-2"></i>Errors & Failures</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger bg-light-danger border-0">
                        <ul class="mb-0">
                            <?php foreach ($results['errors'] as $error): ?>
                            <li class="mb-1"><?php echo nl2br(htmlspecialchars($error ?? '')); ?></li>
                            <?php
    endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
endif; ?>
    </div>

    <div class="row mt-2">
        <div class="col-12 text-center">
            <a href="students.php" class="btn btn-secondary px-5 py-2 fw-bold shadow-sm">
                <i class="fas fa-users me-2"></i>Back to Student List
            </a>
        </div>
    </div>
</div>

<style>
    .bg-light-danger {
        background-color: #fff5f5;
        color: #dc3545;
    }
    .card {
        border-radius: 12px;
        overflow: hidden;
    }
    .table thead th {
        border-top: none;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>

<?php include '../../include/footer.php'; ?>
