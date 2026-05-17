<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Student (student-specific login)
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: student-login.php');
    exit;
}

$page_title = "Settings" ;
$page_breadcrumb = "Settings";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>



    <div class="container-fluid">
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                <?php echo $_SESSION['success_msg'];
                ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Preferences</h3>
                    </div>
                    <form action="settings-save.php" method="POST">
                        <div class="card-body">
                            <h5>Notification Preferences</h5>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="email_notifications"
                                        name="email_notifications" checked>
                                    <label class="custom-control-label" for="email_notifications">Enable Email
                                        Notifications</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="result_notifications"
                                        name="result_notifications" checked>
                                    <label class="custom-control-label" for="result_notifications">Notify When Results
                                        are Published</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="appointment_reminders"
                                        name="appointment_reminders" checked>
                                    <label class="custom-control-label" for="appointment_reminders">Appointment
                                        Reminders</label>
                                </div>
                            </div>

                            <hr>
                            <h5>Privacy Settings</h5>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="show_profile"
                                        name="show_profile" checked>
                                    <label class="custom-control-label" for="show_profile">Show Profile to
                                        Counsellors</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="share_results"
                                        name="share_results" checked>
                                    <label class="custom-control-label" for="share_results">Share Test Results with
                                        Counsellors</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Information Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">Help & Support</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Need help?</strong></p>
                        <ul>
                            <li>Contact your counsellor for guidance</li>
                            <li>Check the FAQ section for common questions</li>
                            <li>Reach out to the administration office for technical support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>
