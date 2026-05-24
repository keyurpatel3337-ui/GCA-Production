<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE; // Added pagination support
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['admission_confirm_filters']);
    } else {
        // Start with existing filters or defaults
        $currentFilters = $_SESSION['admission_confirm_filters'] ?? [
            'search' => '',
            'per_page' => 25,
            'page' => 1
        ];

        if (isset($_POST['page'])) {
            // Pagination request: Only update page (and per_page if present)
            $currentFilters['page'] = $_POST['page'];
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
        } else {
            // Filter request: Update filters and reset page
            $currentFilters['search'] = $_POST['search'] ?? '';
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
            $currentFilters['page'] = 1;
        }

        $_SESSION['admission_confirm_filters'] = $currentFilters;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['admission_confirm_filters'] ?? [
    'search' => '',
    'per_page' => 25,
    'page' => 1
];

$search = $filters['search'];
$page = $filters['page'];
$perPage = $filters['per_page'];

$requestParams = [
    'search' => $search,
    'page' => $page,
    'per_page' => $perPage
];

// Load admission confirmations via API
$api = new APIClient();
$response = $api->get('students/admission-confirm-list', $requestParams);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $students = $data['students'] ?? [];

    // Pagination data
    $pagination = $data['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 25;
    $totalRecords = $pagination['total_records'] ?? ($data['total'] ?? count($students));
    $totalPages = $pagination['total_pages'] ?? 1;
} else {
    // Fallback to default values if API fails
    $students = [];
    $totalRecords = 0;
    $page = 1;
    $perPage = 25;
    $totalPages = 1;
}

$page_title = 'Confirm Admissions';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <!-- Alert Messages -->
    <?php
    if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php
            echo htmlspecialchars($_SESSION['success_msg'] ?? '');
            unset($_SESSION['success_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php
            echo htmlspecialchars($_SESSION['error_msg'] ?? '');
            unset($_SESSION['error_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['info_msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle"></i> <?php
            echo htmlspecialchars($_SESSION['info_msg'] ?? '');
            unset($_SESSION['info_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
    endif; ?>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search"
                            placeholder="Search by name, mobile, Aadhaar..." value="<?php
                            echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button type="submit" name="clear_filters" value="1" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i> Students Pending Admission Confirmation
                <span class="badge bg-primary ms-2"><?php
                echo $totalRecords; ?> Student(s)</span>
            </h3>
        </div>
        <div class="card-body">
            <?php
            if (empty($students)): ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No students pending admission confirmation.
                </div>
                <?php
            else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="admissionTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student Name</th>
                                <th>Mobile</th>
                                <th>Aadhaar</th>
                                <th>Standard</th>
                                <th>Fee</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($students as $student): ?>
                                <tr>
                                    <td><?php
                                    echo $student['id']; ?></td>
                                    <td>
                                        <strong><?php
                                        echo htmlspecialchars(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? '')); ?></strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-phone"></i>
                                        <?php
                                        echo htmlspecialchars($student['mob'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-id-card"></i>
                                        <?php
                                        echo htmlspecialchars($student['aadhaar'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php
                                        echo htmlspecialchars($student['board_name'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($student['course_fee']): ?>
                                            <strong>₹<?php
                                            echo formatIndianCurrency($student['course_fee']); ?></strong>
                                            <?php
                                        else: ?>
                                            <span class="text-muted">Not configured</span>
                                            <?php
                                        endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" action="details.php" class="css-admission-confirm-list-f8e39d">
                                                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" class="btn btn-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="admission-confirm.php" class="css-admission-confirm-list-f8e39d">
                                                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Confirm Admission">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalRecords > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <?php
                            echo renderPaginationPost($page, $totalPages);
                            ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
            endif; ?>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card mt-3">
        <div class="card-body">
            <h6 class="card-title"><i class="fas fa-info-circle"></i> Instructions</h6>
            <ul class="mb-0">
                <li>Review student details and test results before confirming admission</li>
                <li>Apply scholarship if student qualifies (percentage or fixed amount)</li>
                <li>System will generate a unique admission letter number</li>
                <li>Admission letter can be printed and given to student</li>
                <li>Student will bring the letter to accounts department for token fee payment</li>
            </ul>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>