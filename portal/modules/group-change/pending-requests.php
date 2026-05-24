<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filter'])) {
        unset($_SESSION['pending_requests_filter']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $_SESSION['pending_requests_filter'] = $_POST['status'] ?? 'all';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get status filter from session
$status_filter = $_SESSION['pending_requests_filter'] ?? 'all';

// Load pending group change requests via API
$api = new APIClient();
$response = $api->get('group-change/pending-requests');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $pending_requests = $data['requests'] ?? [];
} else {
    // Fallback to default values if API fails
    $pending_requests = [];
}

$page_title = "Pending Group Change Requests";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




    <div class="container-fluid">
        <!-- Status Filter Tabs -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <form method="POST" class="css-pending-requests-f8e39d">
                            <input type="hidden" name="status" value="all">
                            <button type="submit" class="nav-link <?php echo $status_filter === 'all' ? 'active' : ''; ?>" style="border:none;background:none;cursor:pointer;">
                                All <span class="badge bg-secondary ms-1"><?php echo $total_count; ?></span>
                            </button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <form method="POST" class="css-pending-requests-f8e39d">
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" style="border:none;background:none;cursor:pointer;">
                                Pending <span class="badge bg-warning text-dark ms-1"><?php echo $status_counts['pending'] ?? 0; ?></span>
                            </button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <form method="POST" class="css-pending-requests-f8e39d">
                            <input type="hidden" name="status" value="under_review">
                            <button type="submit" class="nav-link <?php echo $status_filter === 'under_review' ? 'active' : ''; ?>" style="border:none;background:none;cursor:pointer;">
                                Under Review <span class="badge bg-info ms-1"><?php echo $status_counts['under_review'] ?? 0; ?></span>
                            </button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <form method="POST" class="css-pending-requests-f8e39d">
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" style="border:none;background:none;cursor:pointer;">
                                Approved <span class="badge bg-success ms-1"><?php echo $status_counts['approved'] ?? 0; ?></span>
                            </button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <form method="POST" class="css-pending-requests-f8e39d">
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" style="border:none;background:none;cursor:pointer;">
                                Rejected <span class="badge bg-danger ms-1"><?php echo $status_counts['rejected'] ?? 0; ?></span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Search and Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Group Change Requests</h3>
                <div class="card-tools">
                    <form method="GET" class="d-inline-block">
                        <input type="hidden" name="status" value="<?php
                                                                    echo htmlspecialchars($status_filter ?? ''); ?>">
                        <div class="input-group input-group-sm css-pending-requests-88ed85">
                            <input type="text" name="search" class="form-control"
                                placeholder="Search by name, request no..."
                                value="<?php
                                        echo htmlspecialchars($search ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-body">
                <?php
                if (empty($requests)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No requests found matching your criteria.
                    </div>
                <?php
                else: ?>
                    <div class="table-responsive">
                        <table id="requestsTable" class="table table-bordered table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Request No</th>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Current Group</th>
                                    <th>Requested Group</th>
                                    <th>Fee Impact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($requests as $req): ?>
                                    <?php

                                    // Status badge mapping
                                    $status_badges = [
                                        'pending' => 'bg-warning text-dark',
                                        'under_review' => 'bg-info',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'cancelled' => 'bg-secondary'
                                    ];
                                    $status_badge = $status_badges[$req['status']] ?? 'bg-secondary';
                                    ?>
                                    <tr>
                                        <td><strong>REQ-<?php
                                                        echo htmlspecialchars($req['id'] ?? ''); ?></strong></td>
                                        <td><?php
                                            echo date('d M Y', strtotime($req['request_date'])); ?></td>
                                        <td>
                                            <strong><?php
                                                    echo htmlspecialchars($req['student_name'] ?? ''); ?></strong>
                                            <br><small class="text-muted">ID: <?php
                                                                                echo htmlspecialchars($req['student_id'] ?? ''); ?></small>
                                        </td>
                                        <td><?php
                                            echo htmlspecialchars($req['current_group_name'] ?? 'N/A'); ?></td>
                                        <td><?php
                                            echo htmlspecialchars($req['requested_group_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="text-muted">
                                                <i class="fas fa-info-circle"></i> View Details
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php
                                                                echo $status_badge; ?>">
                                                <?php
                                                echo ucfirst(str_replace('_', ' ', $req['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-details"
                                                data-id="<?php
                                                            echo $req['id']; ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewRequestModal"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php
                                            if ($req['status'] === 'pending' || $req['status'] === 'under_review'): ?>
                                                <a href="review-group-change-request.php?id=<?php
                                                                                            echo $req['id']; ?>"
                                                    class="btn btn-sm btn-primary" title="Review Request">
                                                    <i class="fas fa-tasks"></i> Review
                                                </a>
                                            <?php
                                            endif; ?>
                                        </td>
                                    </tr>
                                <?php
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php
                endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- View Request Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewRequestModalLabel">
                    <i class="fas fa-file-alt"></i> Request Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading request details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
        </div>

<?php
include '../../include/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Move modal to body to prevent z-index issues
        $('#viewRequestModal').appendTo("body");

        // Load request details in modal

        $('.view-details').click(function() {
            var requestId = $(this).data('id');
            $('#requestDetailsContent').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading request details...</p>
                </div>
            `);

            $.ajax({
                url: '../student-portal/get-request-details.php',
                method: 'POST',
                data: {
                    request_id: requestId
                },
                success: function(response) {
                    $('#requestDetailsContent').html(response);
                },
                error: function() {
                    $('#requestDetailsContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Error loading request details. Please try again.
                        </div>
                    `);
                }
            });
        });
    });
</script>


