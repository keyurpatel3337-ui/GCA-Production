<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/website-admin');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $stats = $response['data'] ?? [];
    $total_pages = $stats['total_pages'] ?? 0;
    $total_header_menu = $stats['total_header_menu'] ?? 0;
    $total_footer_menu = $stats['total_footer_menu'] ?? 0;
    $total_settings = $stats['total_settings'] ?? 0;
} else {
    // Fallback to default values if API fails
    $stats = [];
    $total_pages = 0;
    $total_header_menu = 0;
    $total_footer_menu = 0;
    $total_settings = 0;
}
?>
<?php
$page_title = "Website Admin Dashboard";
include '../../include/header.php'; ?>
<?php
include '../../include/navbar.php'; ?>
<?php
include '../../include/sidebar.php'; ?>





<div class="container-fluid">
    <?php
    include '../../include/mfa_alert.php';
    ?>
    <?php display_flash_messages(); ?>


    <!-- Stats Cards (New) -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $total_pages ?? 0; ?></div>
                            <div class="stat-label">Total Pages</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/website/pages.php" class="stat-link text-info">
                        Manage Pages <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $total_header_menu ?? 0; ?></div>
                            <div class="stat-label">Header Items</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-bars"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/website/menus.php?type=header"
                        class="stat-link text-primary">
                        Edit Menu <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $total_footer_menu ?? 0; ?></div>
                            <div class="stat-label">Footer Items</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-shoe-prints"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/website/menus.php?type=footer"
                        class="stat-link text-success">
                        Edit Menu <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $total_settings ?? 0; ?></div>
                            <div class="stat-label">Global Settings</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-cogs"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/website/settings.php" class="stat-link text-warning">
                        Configure <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="section-title">
        <i class="fas fa-bolt text-warning"></i> Quick Actions
    </h4>

    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <div class="card card-enhanced h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1"><i class="fas fa-database text-success me-2"></i> Database
                            Monitor</h5>
                        <p class="card-text text-muted mb-0">View all database content sources enabled on the
                            website.</p>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/website/db-content-monitor.php"
                        class="btn btn-outline-success">
                        View Monitor
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-enhanced h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1"><i class="fas fa-globe text-primary me-2"></i> Website Preview
                        </h5>
                        <p class="card-text text-muted mb-0">View your changes on the live website.</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>" target="_blank" class="btn btn-outline-primary">
                        Open Website
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php
include '../../include/footer.php'; ?>