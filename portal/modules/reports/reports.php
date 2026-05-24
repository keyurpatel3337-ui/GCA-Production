<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Load reports list via API
$api = new APIClient();
$response = $api->get('reports/list');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $reports = $data['reports'] ?? [];
} else {
    // Fallback to default values if API fails
    $reports = [];
}

$stats = $data['stats'] ?? [];
$top_performers = $data['top_performers'] ?? [];

$page_title = "Reports & Analytics";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php
                    echo $stats['students'] ?? 0; ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php
                    echo $stats['omr_checked'] ?? 0; ?></h3>
                    <p>OMRs Checked</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php
                    echo $stats['results'] ?? 0; ?></h3>
                    <p>Total Results</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3><?php
                    echo $stats['avg_score'] ?? 0; ?>%</h3>
                    <p>Average Score</p>
                </div>
                <div class="icon">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top 10 Performers</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student Name</th>
                                <th>Roll No</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($top_performers as $performer): ?>
                                <tr>
                                    <td><?php
                                    echo $rank++; ?></td>
                                    <td><?php
                                    echo htmlspecialchars($performer['student_name'] ?? 'N/A'); ?></td>
                                    <td><?php
                                    echo htmlspecialchars($performer['roll_number'] ?? 'N/A'); ?></td>
                                    <td><?php
                                    echo $performer['score']; ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?php
                                            echo number_format($performer['percentage'], 2); ?>%
                                        </span>
                                    </td>
                                    <td><span class="badge bg-primary"><?php
                                    echo $performer['grade']; ?></span></td>
                                </tr>
                                <?php
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>