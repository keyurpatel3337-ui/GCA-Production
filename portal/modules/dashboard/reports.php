<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load reports data via API - merge GET and POST, POST takes priority
$api = new APIClient();
$response = $api->get('dashboard/reports', array_merge($_GET, $_POST));

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    // Extract any report stats if needed
} else {
    // Fallback to default values if API fails
    $data = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Student Reports</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=students_by_board" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-users"></i> Students by Board
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=students_by_counsellor" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-user-tie"></i> Students by Counsellor
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=students_by_group" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-layer-group"></i> Students by Group
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="../students/list.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-list"></i> Complete Student List
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">Test Reports</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/results/results.php" class="btn btn-outline-success btn-block">
                                <i class="fas fa-chart-line"></i> All Test Results
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=performance_by_paper" class="btn btn-outline-success btn-block">
                                <i class="fas fa-file-alt"></i> Performance by Paper Set
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=top_performers" class="btn btn-outline-success btn-block">
                                <i class="fas fa-trophy"></i> Top Performers
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/omr/omr-sheets.php" class="btn btn-outline-success btn-block">
                                <i class="fas fa-clipboard-check"></i> OMR Status
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Counselling Reports</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=appointment_summary" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-calendar"></i> Appointment Summary
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=counsellor_workload" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-tasks"></i> Counsellor Workload
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/reports/reports.php?type=session_attendance" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-check-circle"></i> Session Attendance
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php
                                        echo PORTAL_URL; ?>/modules/dashboard/counsellors.php" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-user-friends"></i> Counsellor Overview
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Custom Report Generator</h3>
                </div>
                <div class="card-body">
                    <form action="../modules/reports/reports.php" method="GET">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="type" class="form-control" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="students_by_board">Students by Board</option>
                                        <option value="students_by_counsellor">Students by Counsellor</option>
                                        <option value="performance_by_paper">Performance by Paper</option>
                                        <option value="appointment_summary">Appointment Summary</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-file-alt"></i> Generate Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Reports can be exported to Excel or PDF format. Click on the export button in each report page.
    </div>
</div>
</div>

<?php
include '../../include/footer.php'; ?>