<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load results data via API - merge GET and POST, POST takes priority
$api = new APIClient();
$response = $api->get('dashboard/results', array_merge($_GET, $_POST));

// Extract data from API response
// Initialize Default Values
$total_results = 0;
$avg_percentage = 0;
$results = [];
$paper_sets = [];

// Filter Parameters for View
$search = $_POST['search'] ?? $_GET['search'] ?? '';
$paper_set_id = $_POST['paper_set_id'] ?? $_GET['paper_set_id'] ?? '';

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $total_results = $data['total_results'] ?? 0;
    $avg_percentage = $data['avg_percentage'] ?? 0;
    $results = $data['results'] ?? [];
    $paper_sets = $data['paper_sets'] ?? [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-lg-6 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php
                        echo $total_results; ?></h3>
                    <p>Total Results</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php
                        echo number_format($avg_percentage, 2); ?>%</h3>
                    <p>Average Percentage</p>
                </div>
                <div class="icon">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">All Test Results</h3>
            <div class="card-tools">
                <a href="<?php
                            echo PORTAL_URL; ?>/modules/results/results.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-list"></i> Detailed Results
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="POST" class="mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <select name="paper_set_id" class="form-control">
                            <option value="">All Paper Sets</option>
                            <?php
                            foreach ($paper_sets as $ps): ?>
                                <option value="<?php
                                                echo $ps['id']; ?>"
                                    <?php
                                    echo $paper_set_id == $ps['id'] ? 'selected' : ''; ?>>
                                    <?php
                                    echo htmlspecialchars($ps['paper_name'] . ' (' . $ps['paper_code'] . ')' ?? ''); ?>
                                </option>
                            <?php
                            endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by student name or mobile..."
                            value="<?php
                                    echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="<?php
                                    echo PORTAL_URL; ?>/modules/dashboard/results.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <?php
            if (empty($results)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No results found.
                </div>
            <?php
            else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Mobile</th>
                                <th>Paper</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($results as $result): ?>
                                <tr>
                                    <td>
                                        <strong><?php
                                                echo htmlspecialchars(($result['surname'] ?? '') . ' ' . ($result['student_name'] ?? '')); ?></strong>
                                    </td>
                                    <td><?php
                                        echo htmlspecialchars($result['mob'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars($result['paper_name'] ?? 'N/A'); ?><br>
                                        <small class="text-muted"><?php
                                                                    echo htmlspecialchars($result['paper_code'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php
                                                echo $result['marks_obtained']; ?></strong> / <?php
                                                                                                echo $result['total_marks']; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php
                                                                    echo $result['percentage'] >= 75 ? 'success' : ($result['percentage'] >= 50 ? 'warning' : 'danger'); ?>">
                                            <?php
                                            echo number_format($result['percentage'], 2); ?>%
                                        </span>
                                    </td>
                                    <td><?php
                                        echo date('d M Y', strtotime($result['created_at'])); ?></td>
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

<?php
include '../../include/footer.php'; ?>