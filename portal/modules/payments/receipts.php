<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Check if user has appropriate role
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle POST filters and store in session
// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['receipts_filters']);
    } else {
        // Start with existing filters or defaults
        $currentFilters = $_SESSION['receipts_filters'] ?? [
            'search' => '',
            'from_date' => '',
            'to_date' => '',
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
            $currentFilters['from_date'] = $_POST['from_date'] ?? '';
            $currentFilters['to_date'] = $_POST['to_date'] ?? '';
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
            $currentFilters['page'] = 1;
        }

        $_SESSION['receipts_filters'] = $currentFilters;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['receipts_filters'] ?? [
    'search' => '',
    'from_date' => '',
    'to_date' => '',
    'per_page' => 25,
    'page' => 1
];

// Fetch data from API with pagination
$api = new APIClient();
$requestParams = $filters;

$response = $api->get('payments/receipts', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $receipts = $response['data']['receipts'] ?? [];
    $pagination = $response['data']['pagination'] ?? [];

    // Sync local variables with response or session
    $page = $pagination['current_page'] ?? $filters['page'];
    $perPage = $pagination['per_page'] ?? $filters['per_page'];
    $totalRecords = $pagination['total_records'] ?? count($receipts);
    $totalPages = $pagination['total_pages'] ?? 1;

    // Use filters from session/local variables for display
    $search = $filters['search'];
    $from_date = $filters['from_date'];
    $to_date = $filters['to_date'];
} else {
    $receipts = [];
    $page = 1;
    $perPage = 25;
    $totalRecords = 0;
    $totalPages = 1;
    $search = $from_date = $to_date = '';

    if (!empty($response['error'])) {
        set_flash_message('error', $response['error'] ?? 'Failed to load receipts');
    }
}

$page_title = "All Receipts";
$page_breadcrumb = "Receipts -";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Receipt Records</h3>
            <div class="card-tools">
                <a href="generate-receipt.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Generate Receipt
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="POST" class="mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name or receipt no..."
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="from_date" class="form-control" placeholder="From Date"
                            value="<?php echo $from_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="to_date" class="form-control" placeholder="To Date"
                            value="<?php echo $to_date; ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button type="submit" name="clear_filters" value="1" class="btn btn-secondary flex-grow-1">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </form>

            <!-- Receipts Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Student Name</th>
                            <th>Amount</th>
                            <th>Payment For</th>
                            <th>Payment Mode</th>
                            <th>Printed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No receipts found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($receipt['receipt_no'] ?? ''); ?></strong></td>
                                    <td><?php echo date('d-M-Y', strtotime($receipt['issued_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['student_name'] ?? ''); ?></td>
                                    <td><strong>₹<?php echo formatIndianCurrency($receipt['amount']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($receipt['payment_for'] ?? ''); ?></td>
                                    <td><span
                                            class="badge bg-info text-dark"><?php echo strtoupper($receipt['payment_mode']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($receipt['is_printed']): ?>
                                            <span class="badge bg-success">
                                                Yes (<?php echo $receipt['print_count']; ?>x)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="receipt-print-pdf.php?id=<?php echo $receipt['id']; ?>"
                                            class="btn btn-sm btn-info" title="View Receipt" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button
                                            onclick="generateSecurePDF('receipt-print-pdf.php', { id: <?php echo $receipt['id']; ?> })"
                                            class="btn btn-sm btn-success" title="Print Receipt (PDF)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <!-- <button onclick="downloadReceipt(<?php echo $receipt['id']; ?>)"
                                                class="btn btn-sm btn-danger" title="Download PDF Receipt" type="button">
                                                <i class="fas fa-file-pdf"></i>
                                            </button> -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
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
        </div>
    </div>
</div>

<script>
    function downloadReceipt(receiptId) {
        showToast('info', 'Generating Receipt...', 'Please wait.');

        // Use fetch to post data silently (Hides params from URL)
        const formData = new FormData();
        formData.append('id', receiptId);

        fetch('receipt-print-pdf.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.blob();
            })
            .then(blob => {
                // Create object URL for the blob (Clean URL like blob:http://...)
                const url = URL.createObjectURL(blob);
                window.open(url, '_blank');
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'Failed to generate receipt');
            });
    }
</script>

<?php include '../../include/footer.php'; ?>