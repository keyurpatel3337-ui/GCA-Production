<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load students data via API - merge GET and POST, POST takes priority
$api = new APIClient();
$response = $api->get('dashboard/students-view', array_merge($_GET, $_POST));

// Initialize Default Values
$total_active = 0;
$total_registered = 0;
$total_enrolled = 0;
$assigned_students = 0;
$unassigned_students = 0;
$students = [];

// Filter Parameters for View
$search = $_POST['search'] ?? '';
$board = $_POST['board'] ?? '';

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $total_active = $data['total_active'] ?? 0;
    $total_registered = $data['total_registered'] ?? 0;
    $total_enrolled = $data['total_enrolled'] ?? 0;
    $assigned_students = $data['assigned_students'] ?? 0;
    $unassigned_students = $data['unassigned_students'] ?? 0;
    $students = $data['students'] ?? [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-lg-4 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php
                        echo $total_active; ?></h3>
                    <p>Total Active Students</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php
                        echo $assigned_students; ?></h3>
                    <p>Assigned to Counsellor</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php
                        echo $unassigned_students; ?></h3>
                    <p>Unassigned</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-times"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">All Students</h3>
            <div class="card-tools">
                <a href="../students/list.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-list"></i> Full Student List
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Search Form -->
            <form method="POST" class="mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name, mobile, aadhaar..."
                            value="<?php
                                    echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="board" class="form-control">
                            <option value="">All Boards</option>
                            <option value="GSEB" <?php
                                                    echo $board == 'GSEB' ? 'selected' : ''; ?>>GSEB</option>
                            <option value="CBSE" <?php
                                                    echo $board == 'CBSE' ? 'selected' : ''; ?>>CBSE</option>
                            <option value="ICSE" <?php
                                                    echo $board == 'ICSE' ? 'selected' : ''; ?>>ICSE</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="<?php
                                    echo PORTAL_URL; ?>/modules/dashboard/students.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <?php
            if (empty($students)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No students found.
                </div>
            <?php
            else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Board</th>
                                <th>Group</th>
                                <th>Counsellor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <strong><?php
                                                echo htmlspecialchars($student['surname'] . ' ' . $student['student_name'] ?? ''); ?></strong>
                                    </td>
                                    <td><?php
                                        echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                                    <td><?php
                                        echo htmlspecialchars($student['board_name'] ?? 'N/A'); ?></td>
                                    <td><?php
                                        echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        if ($student['counsellor_name']): ?>
                                            <span class="badge bg-success">
                                                <?php
                                                echo htmlspecialchars($student['counsellor_name'] ?? ''); ?>
                                            </span>
                                        <?php
                                        else: ?>
                                            <span class="badge bg-secondary">Not Assigned</span>
                                        <?php
                                        endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($student['status'] == 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php
                                        else: ?>
                                            <span class="badge bg-danger">Inactive</span>
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

<?php
include '../../include/footer.php'; ?>