<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(__DIR__, 4) . '/common/helpers/format_helper.php';

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Database Tools";
$message = '';
$message_type = '';
$query_result = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'optimize') {
        $tables = $_POST['tables'] ?? [];
        if (!empty($tables)) {
            try {
                foreach ($tables as $table) {
                    $conn->exec("OPTIMIZE TABLE `" . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . "`");
                }
                $message = count($tables) . " table(s) optimized successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Optimization failed: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }

    if ($action === 'query') {
        $query = trim($_POST['query'] ?? '');
        // Only allow SELECT queries
        if (preg_match('/^\s*SELECT\s/i', $query)) {
            try {
                $stmt = $conn->query($query);
                $query_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $message = count($query_result) . " row(s) returned.";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Query error: " . $e->getMessage();
                $message_type = 'danger';
            }
        } else {
            $message = "Only SELECT queries are allowed for safety.";
            $message_type = 'warning';
        }
    }
}

// Get all tables
$tables = $conn->query("SHOW TABLE STATUS")->fetchAll(PDO::FETCH_ASSOC);

include PORTAL_PATH . 'include/header.php';
include PORTAL_PATH . 'include/sidebar.php';
?>



<div class="container-fluid">

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Table Status -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table"></i> Table Status</h3>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <form method="POST">
                        <input type="hidden" name="action" value="optimize">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Table</th>
                                    <th>Rows</th>
                                    <th>Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tables as $table): ?>
                                    <tr>
                                        <td><input type="checkbox" name="tables[]" value="<?php echo $table['Name']; ?>">
                                        </td>
                                        <td><code><?php echo $table['Name']; ?></code></td>
                                        <td>
                                            <?php echo formatIndianCurrency($table['Rows'], false); ?>
                                        </td>
                                        <td>
                                            <?php echo round(($table['Data_length'] + $table['Index_length']) / 1024, 2); ?>
                                            KB
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="p-3">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-wrench"></i> Optimize
                                Selected</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Query Runner -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-terminal"></i> Query Runner (SELECT only)</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="query">
                        <div class="mb-3">
                            <textarea name="query" class="form-control font-monospace" rows="4"
                                placeholder="SELECT * FROM tbl_roles LIMIT 10"><?php echo $_POST['query'] ?? ''; ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Run Query</button>
                    </form>

                    <?php if ($query_result && !empty($query_result)): ?>
                        <div class="mt-3" style="max-height: 300px; overflow: auto;">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($query_result[0]) as $col): ?>
                                            <th>
                                                <?php echo $col; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($query_result as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $val): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($val ?? ''); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    document.getElementById('selectAll')?.addEventListener('change', function () {
        document.querySelectorAll('input[name="tables[]"]').forEach(cb => cb.checked = this.checked);
    });
</script>

<?php include PORTAL_PATH . 'include/footer.php'; ?>