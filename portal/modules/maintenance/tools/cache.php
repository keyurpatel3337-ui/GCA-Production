<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Cache Manager";
$message = '';
$message_type = '';

// Cache directories
$cache_dirs = [
    'PHP Session' => session_save_path(),
    'Temp Files' => sys_get_temp_dir()
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_opcache' && function_exists('opcache_reset')) {
        opcache_reset();
        $message = 'OPcache cleared successfully!';
        $message_type = 'success';
    }
}

// Get cache stats
$opcache_enabled = function_exists('opcache_get_status');
$opcache_stats = $opcache_enabled ? @opcache_get_status(false) : null;

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/maintenance/tools/cache.css">

<div class="container-fluid py-4">

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i
                    class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> fs-4 me-3"></i>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- OPcache Section -->
        <div class="col-lg-7">
            <div class="glass-card h-100 overflow-hidden border-0">
                <div class="card-header bg-gradient-primary text-white py-3 px-4 border-0">
                    <h5 class="card-title mb-0 fw-bold text-white"><i class="fas fa-bolt me-2"></i> PHP OPcache Status
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($opcache_enabled && $opcache_stats): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="p-3 bg-light rounded-3 text-center border">
                                    <div class="text-muted small fw-bold text-uppercase mb-1">Status</div>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3">Enabled</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 bg-light rounded-3 text-center border">
                                    <div class="text-muted small fw-bold text-uppercase mb-1">Hit Rate</div>
                                    <div class="h5 fw-bold mb-0">
                                        <?php echo round($opcache_stats['opcache_statistics']['opcache_hit_rate'] ?? 0, 1); ?>%
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 bg-light rounded-3 text-center border">
                                    <div class="text-muted small fw-bold text-uppercase mb-1">Cached</div>
                                    <div class="h5 fw-bold mb-0">
                                        <?php echo $opcache_stats['opcache_statistics']['num_cached_scripts'] ?? 0; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 bg-light rounded-3 text-center border">
                                    <div class="text-muted small fw-bold text-uppercase mb-1">Memory</div>
                                    <div class="h5 fw-bold mb-0">
                                        <?php echo round(($opcache_stats['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 1); ?>MB
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle border-0">
                                <tbody>
                                    <tr>
                                        <td class="ps-0 border-0">
                                            <div class="fw-bold text-dark">Total Memory Pool</div>
                                            <small class="text-muted">Total memory allocated for OPcache</small>
                                        </td>
                                        <td class="text-end border-0 fw-medium">
                                            <?php echo round((($opcache_stats['memory_usage']['used_memory'] ?? 0) + ($opcache_stats['memory_usage']['free_memory'] ?? 0)) / 1024 / 1024, 0); ?>
                                            MB
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="ps-0 border-bottom">
                                            <div class="fw-bold text-dark">Current Hits / Misses</div>
                                            <small class="text-muted">Performance tracking since last reset</small>
                                        </td>
                                        <td class="text-end border-bottom fw-medium">
                                            <span
                                                class="text-success"><?php echo $opcache_stats['opcache_statistics']['hits'] ?? 0; ?></span>
                                            / <span
                                                class="text-danger"><?php echo $opcache_stats['opcache_statistics']['misses'] ?? 0; ?></span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form method="POST"
                            onsubmit="return confirm('Clear the PHP OPcache? This may cause a slight temporary slowdown.');">
                            <input type="hidden" name="action" value="clear_opcache">
                            <button type="submit" class="btn btn-warning w-100 py-2 fw-bold shadow-sm">
                                <i class="fas fa-trash-alt me-2"></i> Purge All Compiled Scripts
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning border-0 bg-warning-subtle p-4 mb-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark">OPcache is not active</h6>
                                    <p class="mb-0 small text-muted">Accelerate your portal performance by enabling the Zend
                                        OPcache extension in your PHP configuration.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light p-4 rounded-3 border">
                            <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i> How to enable on
                                XAMPP:</h6>
                            <ol class="small text-muted ps-3 mb-0">
                                <li class="mb-2">Click <strong>Config</strong> next to Apache in XAMPP Control Panel and
                                    select <strong>php.ini</strong>.</li>
                                <li class="mb-2">Search for <code>[opcache]</code> block (usually near the bottom).</li>
                                <li class="mb-2">Remove the <code>;</code> from <code>zend_extension=opcache</code>.</li>
                                <li class="mb-2">Set <code>opcache.enable=1</code>.</li>
                                <li><strong>Restart Apache</strong> in XAMPP to apply changes.</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cache Directories Section -->
        <div class="col-lg-5">
            <div class="glass-card h-100 border-0 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-folder-open me-2 text-primary"></i> Runtime
                        Paths
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($cache_dirs as $name => $dir): ?>
                            <div class="list-group-item p-4 border-bottom-0">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="icon-box bg-primary-subtle text-primary me-3 rounded p-2 cache-custom-1">
                                        <i class="fas fa-hdd"></i>
                                    </div>
                                    <div class="fw-bold text-dark"><?php echo $name; ?> Path</div>
                                </div>
                                <div class="bg-light p-2 rounded border small text-break">
                                    <code class="text-primary"><?php echo $dir ? $dir : 'Not Configured'; ?></code>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-4 pt-0 mt-2">
                        <div class="alert alert-info border-0 bg-info-subtle small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Sessions and temp files are managed by the operating system and PHP garbage collector.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>