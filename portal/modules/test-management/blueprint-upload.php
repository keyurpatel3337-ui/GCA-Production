<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Upload Blueprint" ;
$page_breadcrumb = "Blueprint";

// Get pre-selected paper set ID from URL if available
$selected_paper_set_id = $_POST['paper_set_id'] ?? 0;

// Get all paper sets
try {
    $paper_sets = $dbOps->select('tbl_paper_sets', ['*'], ['status' => 'active'], 'paper_set_name');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Paper Sets for Blueprint Upload");
    $paper_sets = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary">
                        <h3 class="card-title">Step 1: Upload CSV File</h3>
                    </div>
                    <form action="blueprint-process.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Select Paper Set <span class="text-danger">*</span></label>
                                <select name="paper_set_id" class="form-control select2" required>
                                    <option value="">Choose Paper Set</option>
                                    <?php foreach ($paper_sets as $ps): ?>
                                        <option value="<?php echo $ps['id']; ?>" <?php echo ($ps['id'] == $selected_paper_set_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ps['paper_set_name'] ?? '') . ' (' . $ps['paper_code'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Upload Blueprint CSV File <span class="text-danger">*</span></label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="blueprint_file"
                                        id="blueprint_file" accept=".csv" required>
                                    <label class="custom-file-label" for="blueprint_file">Choose CSV file</label>
                                </div>
                                <small class="text-muted">Supported formats: .csv (Max: 5MB)</small>
                            </div>

                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> CSV Format Requirements:</h5>
                                <ul class="mb-0">
                                    <li>Column 1: Sr.No</li>
                                    <li>Column 2: Topic (English)</li>
                                    <li>Columns 3 onwards: Question numbers grouped by difficulty (Low, Medium, High)
                                    </li>
                                    <li>Row 1: Headers with subject categories</li>
                                    <li>Row 2: Difficulty level labels</li>
                                    <li>Data starts from Row 3</li>
                                    <li>Use comma (,) as delimiter</li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Preview Blueprint
                            </button>
                            <a href="paper-sets.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">Sample Format</h3>
                    </div>
                    <div class="card-body">
                        <img src="../assets/img/blueprint-sample.png" alt="Blueprint Sample" class="img-fluid"
                            onerror="this.style.display='none'">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Sr.No</th>
                                    <th>Topic</th>
                                    <th colspan="2">Low</th>
                                    <th colspan="2">Medium</th>
                                    <th colspan="2">High</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>01</td>
                                    <td>Real Numbers</td>
                                    <td>01</td>
                                    <td>02</td>
                                    <td>03</td>
                                    <td>04</td>
                                    <td>05</td>
                                    <td>06</td>
                                </tr>
                            </tbody>
                        </table>
                        <a href="../downloads/blueprint-template.php" class="btn btn-sm btn-success btn-block">
                            <i class="fas fa-download"></i> Download CSV Template
                        </a>
                    </div>
                </div>

                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">Important Notes</h3>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>CSV file will be parsed automatically</li>
                            <li>You can preview before saving</li>
                            <li>Edit any mistakes in preview</li>
                            <li>100 questions must be mapped</li>
                            <li>Total: Low(48) + Medium(26) + High(26) = 100</li>
                            <li>Ensure proper comma separation</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        </div>

<script>
    $(function () {
        // Update file label on selection
        $('#blueprint_file').change(function (e) {
            var fileName = e.target.files[0].name;
            $('.custom-file-label').text(fileName);
        });
    });
</script>

<?php include '../../include/footer.php'; ?>
