<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "PHP Info";

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Server Configuration</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <tr>
                            <td width="30%"><strong>PHP Version</strong></td>
                            <td>
                                <?php echo phpversion(); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Server Software</strong></td>
                            <td>
                                <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Operating System</strong></td>
                            <td>
                                <?php echo PHP_OS; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Document Root</strong></td>
                            <td>
                                <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Memory Limit</strong></td>
                            <td>
                                <?php echo ini_get('memory_limit'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Max Execution Time</strong></td>
                            <td>
                                <?php echo ini_get('max_execution_time'); ?>s
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Upload Max Filesize</strong></td>
                            <td>
                                <?php echo ini_get('upload_max_filesize'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Post Max Size</strong></td>
                            <td>
                                <?php echo ini_get('post_max_size'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Max Input Vars</strong></td>
                            <td>
                                <?php echo ini_get('max_input_vars'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Session Save Path</strong></td>
                            <td>
                                <?php echo session_save_path(); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Timezone</strong></td>
                            <td>
                                <?php echo date_default_timezone_get(); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title">Loaded Extensions</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach (get_loaded_extensions() as $ext): ?>
                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                            <span class="badge bg-secondary">
                                <?php echo $ext; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
