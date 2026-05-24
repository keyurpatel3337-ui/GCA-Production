<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Backup History";
$backup_dirs = [
    'Database' => [
        'daily' => 'D:/portal_backups/database/daily',
        'monthly' => 'D:/portal_backups/database/monthly',
        'yearly' => 'D:/portal_backups/database/yearly'
    ],
    'Files' => [
        'daily' => 'D:/portal_backups/files/daily',
        'monthly' => 'D:/portal_backups/files/monthly',
        'yearly' => 'D:/portal_backups/files/yearly'
    ],
    'Receipt Reports' => [
        'daily' => 'D:/portal_backups/receipt_reports/daily',
        'monthly' => 'D:/portal_backups/receipt_reports/monthly',
        'yearly' => 'D:/portal_backups/receipt_reports/yearly'
    ]
];

// Collect all backups
$all_backups = [];
$stats = ['total_files' => 0, 'total_size' => 0, 'db_count' => 0, 'files_count' => 0, 'reports_count' => 0];

foreach ($backup_dirs as $category => $types) {
    foreach ($types as $type => $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*.*');
            foreach ($files as $file) {
                $size = filesize($file);
                $stats['total_files']++;
                $stats['total_size'] += $size;

                if ($category === 'Database')
                    $stats['db_count']++;
                elseif ($category === 'Files')
                    $stats['files_count']++;
                else
                    $stats['reports_count']++;

                $all_backups[] = [
                    'category' => $category,
                    'type' => ucfirst($type),
                    'name' => basename($file),
                    'size' => round($size / 1024 / 1024, 2),
                    'date' => filemtime($file),
                    'date_formatted' => date('d-M-Y H:i', filemtime($file))
                ];
            }
        }
    }
}

// Sort by date descending
usort($all_backups, function ($a, $b) {
    return $b['date'] - $a['date'];
});

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>
                            <?php echo $stats['total_files']; ?>
                        </h3>
                        <p>Total Backups</p>
                    </div>
                    <div class="icon"><i class="fas fa-archive"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>
                            <?php echo round($stats['total_size'] / 1024 / 1024, 2); ?> MB
                        </h3>
                        <p>Total Size</p>
                    </div>
                    <div class="icon"><i class="fas fa-hdd"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>
                            <?php echo $stats['db_count']; ?>
                        </h3>
                        <p>Database Backups</p>
                    </div>
                    <div class="icon"><i class="fas fa-database"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>
                            <?php echo $stats['files_count']; ?>
                        </h3>
                        <p>Files Backups</p>
                    </div>
                    <div class="icon"><i class="fas fa-folder"></i></div>
                </div>
            </div>
        </div>

        <!-- All Backups Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> All Backups</h3>
            </div>
            <div class="card-body">
                <?php if (empty($all_backups)): ?>
                    <div class="alert alert-info">No backups found. Create your first backup from Database or Files backup
                        page.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="backupsTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_backups as $backup): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $icon = 'fa-archive';
                                            $color = 'secondary';
                                            if ($backup['category'] === 'Database') {
                                                $icon = 'fa-database';
                                                $color = 'primary';
                                            } elseif ($backup['category'] === 'Files') {
                                                $icon = 'fa-folder';
                                                $color = 'warning';
                                            } elseif ($backup['category'] === 'Receipt Reports') {
                                                $icon = 'fa-file-excel';
                                                $color = 'success';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                                <?php echo $backup['category']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $backup['type']; ?>
                                        </td>
                                        <td>
                                            <?php echo $backup['name']; ?>
                                        </td>
                                        <td>
                                            <?php echo $backup['size']; ?> MB
                                        </td>
                                        <td>
                                            <?php echo $backup['date_formatted']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </div>

<style>
    .small-box {
        border-radius: 0.5rem;
        box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
        position: relative;
        padding: 20px;
        margin-bottom: 0;
    }

    .small-box .inner h3 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }

    .small-box .inner p {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }

    .small-box .icon {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 3rem;
        color: rgba(255, 255, 255, 0.2);
    }
</style>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
