<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once '../../common/settings_helper.php';

// Check if user is Super Admin or Principle
$isSuperAdmin = hasRole(ROLE_SUPER_ADMIN);
$isPrinciple = hasRole(ROLE_PRINCIPLE);

if (!$isSuperAdmin && !$isPrinciple) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "System Settings" ;
$page_breadcrumb = "Settings";

// Load all settings
$generalSettings = getSettingsByCategory($conn, 'general');
$securitySettings = getSettingsByCategory($conn, 'security');
$academicSettings = getSettingsByCategory($conn, 'academic');
$notificationSettings = getSettingsByCategory($conn, 'notification');
$paymentSettings = getSettingsByCategory($conn, 'payment');

// Get success/error messages
$success = $_SESSION['success_message'] ?? null;
$error = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>



    <div class="container-fluid">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success ?? ''); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error ?? ''); ?>
            </div>
        <?php endif; ?>

        <!-- Settings Tabs -->
        <div class="bg-white rounded shadow-sm">
            <ul class="nav nav-tabs border-bottom px-3 pt-3" id="settingsTabs" role="tablist">
                <?php if ($isSuperAdmin): ?>
                    <li class="nav-item">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general"
                            type="button">
                            <i class="fas fa-building"></i> General
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security"
                            type="button">
                            <i class="fas fa-shield-alt"></i> Security
                        </button>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <button class="nav-link <?php echo !$isSuperAdmin ? 'active' : ''; ?>" id="academic-tab"
                        data-bs-toggle="tab" data-bs-target="#academic" type="button">
                        <i class="fas fa-graduation-cap"></i> Academic
                    </button>
                </li>
                <?php if ($isSuperAdmin): ?>
                    <li class="nav-item">
                        <button class="nav-link" id="enrollment-tab" data-bs-toggle="tab" data-bs-target="#enrollment"
                            type="button">
                            <i class="fas fa-user-graduate"></i> Enrollment
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="notification-tab" data-bs-toggle="tab" data-bs-target="#notification"
                            type="button">
                            <i class="fas fa-bell"></i> Notifications
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment"
                            type="button">
                            <i class="fas fa-credit-card"></i> Payment
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup"
                            type="button">
                            <i class="fas fa-database"></i> Backup & Logs
                        </button>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content p-4" id="settingsTabContent">

                <?php if ($isSuperAdmin): ?>
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <form action="settings-save.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="category" value="general">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Organization
                                        Information</h5>

                                    <div class="mb-3">
                                        <label class="form-label">System Name</label>
                                        <input type="text" name="system_name" class="form-control"
                                            value="<?php echo htmlspecialchars($generalSettings['system_name']['value'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Organization Name</label>
                                        <input type="text" name="organization_name" class="form-control"
                                            value="<?php echo htmlspecialchars($generalSettings['organization_name']['value'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Contact Email</label>
                                        <input type="email" name="contact_email" class="form-control"
                                            value="<?php echo htmlspecialchars($generalSettings['contact_email']['value'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Contact Phone</label>
                                        <input type="text" name="contact_phone" class="form-control"
                                            value="<?php echo htmlspecialchars($generalSettings['contact_phone']['value'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control"
                                            rows="3"><?php echo htmlspecialchars($generalSettings['address']['value'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-image text-primary"></i> Logo &
                                        Appearance</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Upload Logo</label>
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                        <?php if (!empty($generalSettings['logo_path']['value'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo PORTAL_URL; ?>/<?php echo htmlspecialchars($generalSettings['logo_path']['value'] ?? ''); ?>"
                                                    alt="Current Logo" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="my-4"></div>

                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-wrench text-warning"></i> System
                                        Status</h5>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="maintenance_mode"
                                            id="maintenance_mode" <?php echo ($generalSettings['maintenance_mode']['value'] ?? false) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            <strong>Maintenance Mode</strong>
                                            <br><small class="text-muted">When enabled, only admins can access the
                                                system</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save General Settings
                            </button>
                        </form>
                    </div>

                    <!-- Security Settings Tab -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <form action="settings-save.php" method="POST">
                            <input type="hidden" name="category" value="security">

                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-key text-primary"></i> Password
                                        Policy</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Minimum Password Length</label>
                                        <input type="number" name="password_min_length" class="form-control" min="6"
                                            max="32"
                                            value="<?php echo $securitySettings['password_min_length']['value'] ?? 8; ?>">
                                    </div>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="password_require_uppercase"
                                            id="req_upper" <?php echo ($securitySettings['password_require_uppercase']['value'] ?? false) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="req_upper">Require Uppercase Letter
                                            (A-Z)</label>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="password_require_number"
                                            id="req_number" <?php echo ($securitySettings['password_require_number']['value'] ?? false) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="req_number">Require Number (0-9)</label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="password_require_special"
                                            id="req_special" <?php echo ($securitySettings['password_require_special']['value'] ?? false) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="req_special">Require Special Character
                                            (!@#$%)</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-clock text-primary"></i> Session &
                                        Login</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" name="session_timeout" class="form-control" min="5" max="1440"
                                            value="<?php echo $securitySettings['session_timeout']['value'] ?? 30; ?>">
                                        <small class="text-muted">Auto-logout after inactivity</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Max Login Attempts</label>
                                        <input type="number" name="max_login_attempts" class="form-control" min="3" max="10"
                                            value="<?php echo $securitySettings['max_login_attempts']['value'] ?? 5; ?>">
                                        <small class="text-muted">Before account lockout</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Lockout Duration (minutes)</label>
                                        <input type="number" name="lockout_duration" class="form-control" min="5" max="60"
                                            value="<?php echo $securitySettings['lockout_duration']['value'] ?? 15; ?>">
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Security Settings
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Academic Settings Tab -->
                <div class="tab-pane fade <?php echo !$isSuperAdmin ? 'show active' : ''; ?>" id="academic"
                    role="tabpanel">
                    <form action="settings-save.php" method="POST">
                        <input type="hidden" name="category" value="academic">

                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-percent text-primary"></i> Pass
                                    Marks & Grading</h5>

                                <div class="mb-3">
                                    <label class="form-label">Default Pass Marks (%)</label>
                                    <input type="number" name="default_pass_marks" class="form-control" min="0"
                                        max="100"
                                        value="<?php echo $academicSettings['default_pass_marks']['value'] ?? 40; ?>">
                                </div>

                                <h6 class="mt-4 mb-3">Grade Boundaries</h6>
                                <?php
                                $grades = $academicSettings['grade_boundaries']['value'] ?? ['A+' => 90, 'A' => 80, 'B+' => 70, 'B' => 60, 'C' => 50, 'D' => 40, 'F' => 0];
                                foreach ($grades as $grade => $min):
                                    ?>
                                    <div class="row mb-2">
                                        <div class="col-4">
                                            <label class="form-label">Grade <?php echo $grade; ?></label>
                                        </div>
                                        <div class="col-8">
                                            <div class="input-group">
                                                <input type="number"
                                                    name="grade_<?php echo strtolower(str_replace('+', 'plus', $grade)); ?>"
                                                    class="form-control" min="0" max="100" value="<?php echo $min; ?>">
                                                <span class="input-group-text">% and above</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="col-md-6">
                                <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-users text-primary"></i> Student
                                    Management</h5>

                                <div class="mb-3">
                                    <label class="form-label">Max Students per Counsellor</label>
                                    <input type="number" name="max_students_per_counsellor" class="form-control"
                                        min="10" max="200"
                                        value="<?php echo $academicSettings['max_students_per_counsellor']['value'] ?? 50; ?>">
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_assign_students"
                                        id="auto_assign" <?php echo ($academicSettings['auto_assign_students']['value'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_assign">
                                        <strong>Auto-assign Students</strong>
                                        <br><small class="text-muted">Automatically assign new students to
                                            counsellors</small>
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Result Display Format</label>
                                    <select name="result_display_format" class="form-select">
                                        <option value="detailed" <?php echo ($academicSettings['result_display_format']['value'] ?? '') === 'detailed' ? 'selected' : ''; ?>>Detailed (All marks)</option>
                                        <option value="summary" <?php echo ($academicSettings['result_display_format']['value'] ?? '') === 'summary' ? 'selected' : ''; ?>>Summary (Grade only)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Academic Settings
                        </button>
                    </form>
                </div>

                <?php if ($isSuperAdmin): ?>
                    <!-- Enrollment Settings Tab -->
                    <div class="tab-pane fade" id="enrollment" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-user-graduate text-primary"></i>
                                    Division Assignment Settings</h5>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Control automatic division and roll number assignment
                                    during student enrollment.
                                </div>

                                <div class="card">
                                    <div class="card-body">
                                        <form id="enrollmentSettingsForm">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h6 class="mb-2"><i class="fas fa-magic"></i> Automatic Division & Roll
                                                        Number Assignment</h6>
                                                    <p class="text-muted mb-0">
                                                        When enabled, divisions and roll numbers are automatically assigned
                                                        when students complete their token fee payment.
                                                        Disable this to assign divisions manually.
                                                    </p>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <div class="form-check form-switch d-flex justify-content-end">
                                                        <input class="form-check-input me-2 css-settings-190359" type="checkbox" role="switch"
                                                            id="autoAssignToggle">
                                                        <label class="form-check-label" for="autoAssignToggle"
                                                            id="toggleLabel">
                                                            <span class="badge bg-secondary">Loading...</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-primary" id="saveEnrollmentBtn">
                                                    <i class="fas fa-save"></i> Save Changes
                                                </button>
                                                <div class="d-inline-block ms-2" id="enrollmentSaveStatus"></div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> When auto-assignment is disabled, you'll need to manually assign
                                    divisions and roll numbers
                                    to students from the Division Management section.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings Tab -->
                    <div class="tab-pane fade" id="notification" role="tabpanel">
                        <form action="settings-save.php" method="POST">
                            <input type="hidden" name="category" value="notification">

                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-toggle-on text-primary"></i> Enable
                                        Notifications</h5>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="enable_email_notifications"
                                            id="enable_email" <?php echo ($notificationSettings['enable_email_notifications']['value'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_email">
                                            <strong>Email Notifications</strong>
                                            <br><small class="text-muted">Send notifications via email</small>
                                        </label>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="enable_sms_notifications"
                                            id="enable_sms" <?php echo ($notificationSettings['enable_sms_notifications']['value'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_sms">
                                            <strong>SMS Notifications</strong>
                                            <br><small class="text-muted">Send notifications via SMS</small>
                                        </label>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="enable_whatsapp_notifications"
                                            id="enable_whatsapp" <?php echo ($notificationSettings['enable_whatsapp_notifications']['value'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_whatsapp">
                                            <strong>WhatsApp Notifications</strong>
                                            <br><small class="text-muted">Send notifications via WhatsApp</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-envelope text-primary"></i> Email
                                        Configuration</h5>

                                    <div class="mb-3">
                                        <label class="form-label">From Email Address</label>
                                        <input type="email" name="notification_from_email" class="form-control"
                                            value="<?php echo htmlspecialchars($notificationSettings['notification_from_email']['value'] ?? 'noreply@gyanmanjari.edu'); ?>">
                                        <small class="text-muted">Email address used as sender</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">From Name</label>
                                        <input type="text" name="notification_from_name" class="form-control"
                                            value="<?php echo htmlspecialchars($notificationSettings['notification_from_name']['value'] ?? 'Gyan Manjari Portal'); ?>">
                                        <small class="text-muted">Display name for sent emails</small>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> Configure API keys in <a
                                            href="../settings/api-config.php">API Configuration</a> page.
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Notification Settings
                            </button>
                        </form>
                    </div>

                    <!-- Payment Settings Tab -->
                    <div class="tab-pane fade" id="payment" role="tabpanel">
                        <form action="settings-save.php" method="POST">
                            <input type="hidden" name="category" value="payment">

                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-money-bill-wave text-primary"></i>
                                        Payment Gateway</h5>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="enable_online_payment"
                                            id="enable_payment" <?php echo ($paymentSettings['enable_online_payment']['value'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_payment">
                                            <strong>Enable Online Payment</strong>
                                            <br><small class="text-muted">Allow students to pay fees online</small>
                                        </label>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Payment Gateway Provider</label>
                                        <select name="payment_gateway" class="form-select">
                                            <option value="easebuzz" selected>Easebuzz</option>
                                        </select>
                                        <small class="text-muted">Configure API keys in <a
                                                href="../settings/api-config.php">API Configuration</a></small>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="enable_partial_payment"
                                            id="enable_partial" <?php echo ($paymentSettings['enable_partial_payment']['value'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_partial">
                                            <strong>Allow Partial Payments</strong>
                                            <br><small class="text-muted">Students can pay in installments</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-percentage text-primary"></i> Fees
                                        Configuration</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Late Fee Percentage</label>
                                        <div class="input-group">
                                            <input type="number" name="late_fee_percentage" class="form-control" min="0"
                                                max="10" step="0.5"
                                                value="<?php echo $paymentSettings['late_fee_percentage']['value'] ?? 2; ?>">
                                            <span class="input-group-text">% per month</span>
                                        </div>
                                        <small class="text-muted">Percentage charged on overdue payments</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Grace Period</label>
                                        <div class="input-group">
                                            <input type="number" name="grace_period_days" class="form-control" min="0"
                                                max="30"
                                                value="<?php echo $paymentSettings['grace_period_days']['value'] ?? 7; ?>">
                                            <span class="input-group-text">days</span>
                                        </div>
                                        <small class="text-muted">Days before late fees apply</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">GST Percentage</label>
                                        <div class="input-group">
                                            <input type="number" name="gst_percentage" class="form-control" min="0" max="28"
                                                step="0.1"
                                                value="<?php echo $paymentSettings['gst_percentage']['value'] ?? 18; ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">GST rate applied on fees</small>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Payment Settings
                            </button>
                        </form>
                    </div>

                    <!-- Backup & Logs Tab -->
                    <div class="tab-pane fade" id="backup" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-download text-success"></i> Database Backup
                                        </h5>
                                        <p class="card-text text-muted">Download a complete backup of the database.</p>
                                        <a href="backup-download.php" class="btn btn-success">
                                            <i class="fas fa-download"></i> Download Backup (SQL)
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-history text-info"></i> Audit Logs</h5>
                                        <p class="card-text text-muted">View recent system activity and changes.</p>
                                        <a href="audit-logs.php" class="btn btn-info">
                                            <i class="fas fa-list"></i> View Audit Logs
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mb-3 mt-4 pb-2 border-bottom"><i class="fas fa-history text-info"></i> Recent Activity
                        </h5>
                        <div class="bg-white rounded shadow-sm">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Module</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recentLogs = getAuditLogs($conn, 10);
                                    if (empty($recentLogs)):
                                        ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                                <br>No audit logs yet
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentLogs as $log): ?>
                                            <tr>
                                                <td><small><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['user_name'] ?? ''); ?></td>
                                                <td><span
                                                        class="badge bg-info"><?php echo htmlspecialchars($log['action'] ?? ''); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['module'] ?? '-'); ?></td>
                                                <td><small><?php echo htmlspecialchars($log['details'] ?? '-'); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</div>

<!-- API Client Library -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/api-client.js"></script>
<script>
    // Initialize API Client
    const APIClient = new CounsellingAPI();

    // Load enrollment settings when tab is shown
    document.addEventListener('DOMContentLoaded', function () {
        <?php if ($isSuperAdmin): ?>
            // Load enrollment settings on page load
            loadEnrollmentSettings();

            // Also reload when enrollment tab is clicked
            const enrollmentTab = document.getElementById('enrollment-tab');
            if (enrollmentTab) {
                enrollmentTab.addEventListener('shown.bs.tab', function () {
                    loadEnrollmentSettings();
                });
            }

            // Handle form submission
            const enrollmentForm = document.getElementById('enrollmentSettingsForm');
            if (enrollmentForm) {
                enrollmentForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    saveEnrollmentSettings();
                });
            }
        <?php endif; ?>
    });

    function loadEnrollmentSettings() {
        const toggle = document.getElementById('autoAssignToggle');
        const label = document.getElementById('toggleLabel');

        if (!toggle || !label) return;

        // Show loading state
        toggle.disabled = true;
        label.innerHTML = '<span class="badge bg-secondary">Loading...</span>';

        APIClient.get('settings/enrollment-settings')
            .then(response => {
                if (response.success && response.data) {
                    const isEnabled = response.data.auto_assign_division_on_enrollment === '1' ||
                        response.data.auto_assign_division_on_enrollment === true;
                    toggle.checked = isEnabled;
                    updateToggleLabel(isEnabled);
                    toggle.disabled = false;
                } else {
                    showError('Failed to load enrollment settings');
                }
            })
            .catch(error => {
                console.error('Error loading enrollment settings:', error);
                showError('Error loading settings: ' + error.message);
                toggle.disabled = false;
            });
    }

    function saveEnrollmentSettings() {
        const toggle = document.getElementById('autoAssignToggle');
        const saveBtn = document.getElementById('saveEnrollmentBtn');
        const statusDiv = document.getElementById('enrollmentSaveStatus');

        if (!toggle || !saveBtn || !statusDiv) return;

        // Disable form
        toggle.disabled = true;
        saveBtn.disabled = true;
        statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        const isEnabled = toggle.checked;

        APIClient.post('settings/update-enrollment-settings', {
            auto_assign_division_on_enrollment: isEnabled ? 1 : 0
        })
            .then(response => {
                if (response.success) {
                    updateToggleLabel(isEnabled);
                    statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Saved successfully!</span>';
                    setTimeout(() => {
                        statusDiv.innerHTML = '';
                    }, 3000);
                } else {
                    throw new Error(response.error || 'Failed to save settings');
                }
            })
            .catch(error => {
                console.error('Error saving enrollment settings:', error);
                statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> ' + error.message + '</span>';
            })
            .finally(() => {
                toggle.disabled = false;
                saveBtn.disabled = false;
            });
    }

    function updateToggleLabel(isEnabled) {
        const label = document.getElementById('toggleLabel');
        if (label) {
            if (isEnabled) {
                label.innerHTML = '<span class="badge bg-success">Enabled</span>';
            } else {
                label.innerHTML = '<span class="badge bg-danger">Disabled</span>';
            }
        }
    }

    function showError(message) {
        const label = document.getElementById('toggleLabel');
        if (label) {
            label.innerHTML = '<span class="badge bg-danger">Error</span>';
        }
        alert(message);
    }
</script>

<?php include '../../include/footer.php'; ?>
