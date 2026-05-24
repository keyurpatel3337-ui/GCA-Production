<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "System Statistics";

// Gather statistics
try {
    // Students
    $total_students = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration")->fetchColumn();
    $active_enrollments = $conn->query("SELECT COUNT(*) FROM tbl_enrolled_students WHERE is_active = 1")->fetchColumn();

    // Payments
    $today_payments = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM tbl_payments WHERE DATE(payment_date) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    $month_payments = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM tbl_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetch(PDO::FETCH_ASSOC);
    $total_payments = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM tbl_payments")->fetch(PDO::FETCH_ASSOC);

    // Database info
    $db_size = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $table_count = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();

    // Table details
    $table_stats = $conn->query("
        SELECT table_name, table_rows, 
               ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        ORDER BY (data_length + index_length) DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Users
    $users_count = $conn->query("SELECT COUNT(*) FROM tbl_users")->fetchColumn();

    // Weekly stats
    $weekly_payments = $conn->query("
        SELECT DATE(payment_date) as date, COUNT(*) as count, SUM(amount) as total
        FROM tbl_payments 
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY date
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



<div class="container-fluid">

    <!-- Main Stats -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>
                        <?php echo formatIndianCurrency($total_students ?? 0, false); ?>
                    </h3>
                    <p>Total Students</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>
                        <?php echo formatIndianCurrency($active_enrollments ?? 0, false); ?>
                    </h3>
                    <p>Active Enrollments</p>
                </div>
                <div class="icon"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>₹
                        <?php echo formatIndianCurrency($month_payments['total'] ?? 0, false); ?>
                    </h3>
                    <p>This Month (
                        <?php echo $month_payments['count'] ?? 0; ?> txn)
                    </p>
                </div>
                <div class="icon"><i class="fas fa-rupee-sign"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>
                        <?php echo $db_size ?? 0; ?> MB
                    </h3>
                    <p>Database Size</p>
                </div>
                <div class="icon"><i class="fas fa-database"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Payment Stats -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-rupee-sign"></i> Payment Statistics</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tr>
                            <td>Today's Payments</td>
                            <td class="text-end"><strong>
                                    <?php echo $today_payments['count'] ?? 0; ?>
                                </strong> (₹
                                <?php echo formatIndianCurrency($today_payments['total'] ?? 0, false); ?>)
                            </td>
                        </tr>
                        <tr>
                            <td>This Month</td>
                            <td class="text-end"><strong>
                                    <?php echo $month_payments['count'] ?? 0; ?>
                                </strong> (₹
                                <?php echo formatIndianCurrency($month_payments['total'] ?? 0, false); ?>)
                            </td>
                        </tr>
                        <tr>
                            <td>Total Ever</td>
                            <td class="text-end"><strong>
                                    <?php echo $total_payments['count'] ?? 0; ?>
                                </strong> (₹
                                <?php echo formatIndianCurrency($total_payments['total'] ?? 0, false); ?>)
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- DB Stats -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-database"></i> Database Statistics</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tr>
                            <td>Total Tables</td>
                            <td class="text-end"><strong>
                                    <?php echo $table_count ?? 0; ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <td>Database Size</td>
                            <td class="text-end"><strong>
                                    <?php echo $db_size ?? 0; ?> MB
                                </strong></td>
                        </tr>
                        <tr>
                            <td>Total Users</td>
                            <td class="text-end"><strong>
                                    <?php echo $users_count ?? 0; ?>
                                </strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Tables -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-table"></i> Top 10 Tables by Size</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Rows</th>
                            <th>Size (MB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_stats as $table): ?>
                            <tr>
                                <td><code><?php echo $table['table_name']; ?></code></td>
                                <td>
                                    <?php echo number_format($table['table_rows']); ?>
                                </td>
                                <td>
                                    <?php echo $table['size_mb']; ?> MB
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>



<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>

