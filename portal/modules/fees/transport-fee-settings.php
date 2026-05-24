<?php
header('Content-Type: text/html; charset=utf-8');
$page_title = 'Transport Fee Settings';
$page_breadcrumb = 'Fees';
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once '../../include/header.php';
require_once '../../include/navbar.php';
require_once '../../include/sidebar.php';

// Fetch Active Academic Year
$current_year_id = '';
$current_year_name = '';
try {
    $stmt = $conn->prepare('SELECT id, year_name FROM tbl_academic_years WHERE is_active = 1 LIMIT 1');
    $stmt->execute();
    $year = $stmt->fetch();
    if ($year) {
        $current_year_id = $year['id'];
        $current_year_name = $year['year_name'];
    }
} catch (Exception $e) {
    echo 'Error fetching academic year: ' . $e->getMessage();
}

// Fetch Existing Settings
$settings = [];
if ($current_year_id) {
    try {
        $stmt = $conn->prepare('SELECT * FROM tbl_transport_fee_settings WHERE academic_year_id = ? LIMIT 1');
        $stmt->execute([$current_year_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silent fail
    }
}
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <?php
            if (isset($_SESSION['success_msg'])):
                ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg'];
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <?php
            if (isset($_SESSION['error_msg'])):
                ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg'];
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bus"></i> Configure for Academic Year:
                        <strong><?php echo htmlspecialchars($current_year_name ?? ''); ?></strong>
                    </h3>
                </div>

                <form action="transport-fee-save.php" method="POST">
                    <input type="hidden" name="academic_year_id" value="<?php echo $current_year_id; ?>">

                    <div class="card-body">
                        <?php if (!$current_year_id): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No active academic year found. Please activate
                                an academic year first.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transport_fee">Annual Transport Fee <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₹</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control" id="transport_fee"
                                                name="transport_fee"
                                                value="<?php echo htmlspecialchars($settings['transport_fee'] ?? '0.00'); ?>"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gst_rate">GST Rate (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" id="gst_rate"
                                                name="gst_rate"
                                                value="<?php echo htmlspecialchars($settings['gst_rate'] ?? '0.00'); ?>">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Description / Remarks</label>
                                <textarea class="form-control" id="description" name="description"
                                    rows="3"><?php echo htmlspecialchars($settings['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                        value="1" <?php echo (isset($settings['is_active']) && $settings['is_active'] == 0) ? '' : 'checked'; ?>>
                                    <label class="custom-control-label" for="is_active">Status (Active/Inactive)</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" <?php echo !$current_year_id ? 'disabled' : ''; ?>>
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>

            <div class="callout callout-info mt-3">
                <h5><i class="fas fa-info-circle"></i> Info</h5>
                <p>This setting applies globally to all students who opt for transport facilities for the current
                    academic year.</p>
            </div>
        </div>
    </section>
</div>

<?php require_once '../../include/footer.php'; ?>
