<?php
$page_title = "Manage Modules";
require_once dirname(dirname(__DIR__)) . '/session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'permission_helper.php';

// Access Check
// if (!hasRole(ROLE_SUPER_ADMIN)) {
//     header('Location: ' . PORTAL_URL . '/index.php?error=access_denied');
//     exit;
// }

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $key = trim($_POST['module_key']);
            $name = trim($_POST['module_name']);
            $group = trim($_POST['group_name']);
            $desc = trim($_POST['description']);

            if (!empty($key) && !empty($name)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO tbl_modules (module_key, module_name, group_name, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$key, $name, $group, $desc]);
                    $message = "Module added successfully.";
                } catch (PDOException $e) {
                    $error = "Error adding module: " . $e->getMessage();
                }
            } else {
                $error = "Module Key and Name are required.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['module_id'];
            try {
                $stmt = $conn->prepare("DELETE FROM tbl_modules WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Module deleted successfully.";
            } catch (PDOException $e) {
                $error = "Error deleting module: " . $e->getMessage();
            }
        }
    }
}

// Fetch Modules
$modules = $conn->query("SELECT * FROM tbl_modules ORDER BY group_name, module_name")->fetchAll(PDO::FETCH_ASSOC);

include PORTAL_PATH . 'include/header.php';
include PORTAL_PATH . 'include/navbar.php';
include PORTAL_PATH . 'include/sidebar.php';
?>



    <div class="container-fluid">

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add New Module Form -->
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Add New Module</h3>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="module_key" class="form-label">Module Key <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="module_key" name="module_key"
                                    placeholder="e.g. fees.collection" required>
                                <small class="text-muted">Unique identifier used in code</small>
                            </div>
                            <div class="mb-3">
                                <label for="module_name" class="form-label">Module Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="module_name" name="module_name"
                                    placeholder="e.g. Fee Collection" required>
                            </div>
                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name</label>
                                <input type="text" class="form-control" id="group_name" name="group_name"
                                    placeholder="e.g. Fees">
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary w-100">Add Module</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modules List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Existing Modules</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Group</th>
                                    <th>Name</th>
                                    <th>Key</th>
                                    <th>Description</th>
                                    <th style="width: 50px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($module['group_name'] ?? ''); ?>
                                            </span></td>
                                        <td><strong>
                                                <?php echo htmlspecialchars($module['module_name'] ?? ''); ?>
                                            </strong></td>
                                        <td><code><?php echo htmlspecialchars($module['module_key'] ?? ''); ?></code></td>
                                        <td>
                                            <?php echo htmlspecialchars($module['description'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <form method="POST"
                                                onsubmit="return confirm('Are you sure? This will remove all permissions associated with this module.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i
                                                        class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>

<?php include PORTAL_PATH . 'include/footer.php'; ?>
