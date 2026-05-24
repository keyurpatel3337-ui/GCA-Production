<?php

/**
 * Database Content Monitor Dashboard
 * Shows all website data from database with status and preview
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check roles
if (!hasRole(ROLE_WEBSITE_ADMIN) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

/**
 * Get status and count for a database table
 */
function getTableStatus($tableName, $displayName, $description, $icon, $color)
{
    global $conn, $dbOps;

    try {
        // Check if table exists and get count
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as count FROM $tableName", []);
        $count = $result['count'];

        // Get sample data (first 3 records)
        $sampleData = $dbOps->customSelect("SELECT * FROM $tableName LIMIT 3", []);

        return [
            'table_name' => $tableName,
            'display_name' => $displayName,
            'description' => $description,
            'icon' => $icon,
            'color' => $color,
            'count' => $count,
            'status' => $count > 0 ? 'active' : 'empty',
            'sample_data' => $sampleData,
            'has_data' => $count > 0
        ];
    } catch (Exception $e) {
        return [
            'table_name' => $tableName,
            'display_name' => $displayName,
            'description' => $description,
            'icon' => $icon,
            'color' => $color,
            'count' => 0,
            'status' => 'error',
            'error' => $e->getMessage(),
            'sample_data' => [],
            'has_data' => false
        ];
    }
}

// Define all website-related database tables
$dbTables = [
    getTableStatus(
        'tbl_site_settings',
        'Site Settings',
        'Global website settings like site name, contact info, social links, SEO metadata',
        'fa-cog',
        'primary'
    ),
    getTableStatus(
        'tbl_navigation_menu',
        'Navigation Menu',
        'Header and footer navigation menu items with hierarchy',
        'fa-bars',
        'info'
    ),
    getTableStatus(
        'tbl_social_links',
        'Social Media Links',
        'Social media platform links (Facebook, Twitter, Instagram, etc.)',
        'fa-share-alt',
        'success'
    ),
    getTableStatus(
        'tbl_pages',
        'Website Pages',
        'All website pages (Home, About, Contact, etc.)',
        'fa-file-alt',
        'warning'
    ),
    getTableStatus(
        'tbl_page_sections',
        'Page Sections',
        'Sections within each page (Hero, Features, Testimonials, etc.)',
        'fa-layer-group',
        'danger'
    ),
    getTableStatus(
        'tbl_page_content',
        'Page Content',
        'Actual content fields for each section (text, images, URLs)',
        'fa-align-left',
        'secondary'
    )
];

$page_title = "Database Content Monitor";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



    <div class="container-fluid">

        <!-- Info Banner -->
        <div class="alert alert-info border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fs-3 me-3"></i>
                <div>
                    <h5 class="mb-1">Database-Driven Website Content</h5>
                    <p class="mb-0 small">All website content is fetched directly from the database. This dashboard
                        shows the status of each data source and allows you to preview the actual data being used on
                        your website.</p>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <?php
            $totalTables = count($dbTables);
            $activeTables = count(array_filter($dbTables, fn($t) => $t['has_data']));
            $emptyTables = count(array_filter($dbTables, fn($t) => !$t['has_data'] && $t['status'] !== 'error'));
            $errorTables = count(array_filter($dbTables, fn($t) => $t['status'] === 'error'));
            ?>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                <i class="fas fa-database text-primary fs-4"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold"><?php echo $totalTables; ?></h3>
                                <small class="text-muted">Total Tables</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                                <i class="fas fa-check-circle text-success fs-4"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold text-success"><?php echo $activeTables; ?></h3>
                                <small class="text-muted">Active (Has Data)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                                <i class="fas fa-exclamation-triangle text-warning fs-4"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold text-warning"><?php echo $emptyTables; ?></h3>
                                <small class="text-muted">Empty Tables</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 p-3 rounded-3 me-3">
                                <i class="fas fa-times-circle text-danger fs-4"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold text-danger"><?php echo $errorTables; ?></h3>
                                <small class="text-muted">Errors</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Tables List -->
        <div class="row">
            <?php foreach ($dbTables as $index => $table): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100"
                        style="border-radius: 16px; border-left: 4px solid var(--bs-<?php echo $table['color']; ?>) !important;">
                        <div class="card-body">
                            <!-- Header -->
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-<?php echo $table['color']; ?> bg-opacity-10 p-3 rounded-3 me-3">
                                        <i
                                            class="fas <?php echo $table['icon']; ?> text-<?php echo $table['color']; ?> fs-4"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1 fw-bold"><?php echo $table['display_name']; ?></h5>
                                        <small class="text-muted"><?php echo $table['table_name']; ?></small>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <?php if ($table['status'] === 'active'): ?>
                                    <span class="badge bg-success rounded-pill px-3">
                                        <i class="fas fa-check-circle me-1"></i> Active
                                    </span>
                                <?php elseif ($table['status'] === 'empty'): ?>
                                    <span class="badge bg-warning rounded-pill px-3">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Empty
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3">
                                        <i class="fas fa-times-circle me-1"></i> Error
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <p class="text-muted small mb-3"><?php echo $table['description']; ?></p>

                            <!-- Stats -->
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <span class="badge bg-light text-dark px-3 py-2">
                                        <i class="fas fa-database me-1"></i>
                                        <strong><?php echo $table['count']; ?></strong> Records
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary"
                                        onclick="previewData('<?php echo $table['table_name']; ?>', '<?php echo $table['display_name']; ?>')">
                                        <i class="fas fa-eye me-1"></i> Preview
                                    </button>
                                    <?php if ($table['has_data']): ?>
                                        <button class="btn btn-outline-success"
                                            onclick="viewAllData('<?php echo $table['table_name']; ?>', '<?php echo $table['display_name']; ?>')">
                                            <i class="fas fa-list me-1"></i> View All
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quick Preview (First Record) -->
                            <?php if ($table['has_data'] && !empty($table['sample_data'])): ?>
                                <div class="bg-light p-3 rounded-3">
                                    <small class="text-muted fw-bold d-block mb-2">
                                        <i class="fas fa-eye me-1"></i> Quick Preview (First Record)
                                    </small>
                                    <div class="small">
                                        <?php
                                        $firstRecord = $table['sample_data'][0];
                                        $displayCount = 0;
                                        foreach ($firstRecord as $key => $value):
                                            if ($displayCount >= 3)
                                                break; // Show only first 3 fields
                                            if (in_array($key, ['id', 'created_at', 'updated_at']))
                                                continue;
                                            $displayCount++;
                                            ?>
                                            <div class="mb-1">
                                                <strong
                                                    class="text-primary"><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong>
                                                <span class="text-dark">
                                                    <?php
                                                    $displayValue = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                                                    echo htmlspecialchars($displayValue ?: '(empty)' ?? '');
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($firstRecord) > 5): ?>
                                            <small class="text-muted fst-italic">... and <?php echo count($firstRecord) - 5; ?> more
                                                fields</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($table['status'] === 'error'): ?>
                                <div class="alert alert-danger mb-0 small">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($table['error'] ?? ''); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0 small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    No data found in this table. The website may not be using this feature yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 16px;">
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-eye me-2"></i>
                    <span id="modalTableName"></span> - Data Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function previewData(tableName, displayName) {
        $('#modalTableName').text(displayName);
        $('#previewModal').modal('show');

        $('#previewContent').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading data...</p>
        </div>
    `);

        $.ajax({
            url: 'ajax-preview-data.php',
            method: 'POST',
            data: {
                table: tableName,
                limit: 10
            },
            success: function (response) {
                $('#previewContent').html(response);
            },
            error: function () {
                $('#previewContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading data. Please try again.
                </div>
            `);
            }
        });
    }

    function viewAllData(tableName, displayName) {
        $('#modalTableName').text(displayName + ' (All Records)');
        $('#previewModal').modal('show');

        $('#previewContent').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading all records...</p>
        </div>
    `);

        $.ajax({
            url: 'ajax-preview-data.php',
            method: 'POST',
            data: {
                table: tableName,
                limit: 1000
            },
            success: function (response) {
                $('#previewContent').html(response);
            },
            error: function () {
                $('#previewContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading data. Please try again.
                </div>
            `);
            }
        });
    }
</script>

<?php include '../../include/footer.php'; ?>
