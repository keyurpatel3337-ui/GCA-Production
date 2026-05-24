<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Super Admin or Principle
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    set_flash_message('error', 'Access denied. Only Super Admin and Principle can access this page.');
    header('Location: ../dashboard/index.php');
    exit;
}

// Fetch current settings
$api = new APIClient();
$response = $api->get('settings/enrollment-settings');

if ($response && isset($response['success']) && $response['success']) {
    $settings = $response['data'] ?? [];
    $auto_assign_enabled = $settings['auto_assign_division_on_enrollment'] ?? false;
} else {
    $auto_assign_enabled = false;
    $settings = [];
}

$page_title = 'Enrollment Settings';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg'];
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg'];
            ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-4 rounded shadow-sm">
        <h5 class="mb-4"><i class="fas fa-user-graduate"></i> Division Assignment Settings</h5>

        <form method="POST" id="enrollmentSettingsForm">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-2"><i class="fas fa-magic"></i> Automatic Division & Roll Number Assignment
                            </h6>
                            <p class="text-muted mb-0">
                                Enable automatic assignment of division and roll number when student completes token fee
                                payment during enrollment.
                                <br><small>When disabled, divisions must be assigned manually from the Student
                                    Assignment page.</small>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="form-check form-switch" style="font-size: 1.5rem;">
                                <input class="form-check-input" type="checkbox" id="autoAssignEnabled"
                                    name="auto_assign_enabled" value="1" <?php echo $auto_assign_enabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoAssignEnabled">
                                    <span
                                        class="badge <?php echo $auto_assign_enabled ? 'bg-success' : 'bg-secondary'; ?>"
                                        id="statusBadge">
                                        <?php echo $auto_assign_enabled ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> How Automatic Assignment Works:</h6>
                <ul class="mb-0">
                    <li>Students are assigned to divisions based on their course and group</li>
                    <li><strong>Priority:</strong> Female students are assigned first, followed by male students</li>
                    <li>Within each gender group, students are sorted by enrollment date (earlier enrollments first)
                    </li>
                    <li>Roll numbers are automatically calculated and assigned sequentially</li>
                    <li>When a student changes division, roll numbers are automatically recalculated for both divisions
                    </li>
                </ul>
            </div>

            <div class="alert alert-warning" id="warningBox"
                style="<?php echo $auto_assign_enabled ? 'display:none;' : ''; ?>">
                <h6><i class="fas fa-exclamation-triangle"></i> Important:</h6>
                <p class="mb-0">
                    When automatic assignment is disabled, you must manually assign divisions to students from:
                    <br><strong>Students â†’ Student Assignment</strong> page
                </p>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    // Toggle status badge on checkbox change
    $('#autoAssignEnabled').on('change', function () {
        const isChecked = $(this).is(':checked');
        const badge = $('#statusBadge');
        const warning = $('#warningBox');

        if (isChecked) {
            badge.removeClass('bg-secondary').addClass('bg-success').text('Enabled');
            warning.hide();
        } else {
            badge.removeClass('bg-success').addClass('bg-secondary').text('Disabled');
            warning.show();
        }
    });

    // Form submission
    $('#enrollmentSettingsForm').on('submit', function (e) {
        e.preventDefault();

        const isEnabled = $('#autoAssignEnabled').is(':checked');
        const formData = {
            auto_assign_division_on_enrollment: isEnabled
        };

        $.ajax({
            url: '<?php echo API_BASE_URL; ?>/settings/update-enrollment-settings',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message || 'Settings updated successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', response.error || 'Failed to update settings');
                }
            },
            error: function () {
                showAlert('danger', 'An error occurred while updating settings');
            }
        });
    });

    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
            </div>
        `;
        $('.container-fluid').prepend(alertHtml);

        setTimeout(() => {
            $('.alert').fadeOut();
        }, 3000);
    }
</script>