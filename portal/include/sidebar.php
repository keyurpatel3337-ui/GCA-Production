<?php
// Use absolute paths for consistent navigation
$portal_root = PORTAL_URL . '/';

// Define all path prefixes
$modules_prefix = $portal_root . 'modules/';
$dashboard_prefix = $portal_root . 'modules/dashboard/';
$students_prefix = $portal_root . 'modules/students/';
$fees_prefix = $portal_root . 'modules/fees/';
$payments_prefix = $portal_root . 'modules/payments/';
$academics_prefix = $portal_root . 'modules/academics/';
$settings_prefix = $portal_root . 'modules/settings/';
$profile_prefix = $portal_root . 'modules/profile/';
$student_portal_prefix = $portal_root . 'modules/student-portal/';
$hostel_prefix = $portal_root . 'modules/hostel/';
$scholarships_prefix = $portal_root . 'modules/scholarships/';
$test_management_prefix = $portal_root . 'modules/test-management/';
$results_prefix = $portal_root . 'modules/results/';
$reports_prefix = $portal_root . 'modules/reports/';
$counsellors_prefix = $portal_root . 'modules/counsellors/';
$test_marks_prefix = $portal_root . 'modules/test-marks/';
$logout_path = $portal_root . 'logout.php';

// Determine dashboard link
$dashboard_link = 'dashboard.php';
if (hasRole(ROLE_SUPER_ADMIN)) {
    $dashboard_link = $dashboard_prefix . 'admin_dashboard.php';
}
elseif (hasRole(ROLE_PRINCIPLE)) {
    $dashboard_link = $dashboard_prefix . 'principle_dashboard.php';
}
elseif (hasRole(ROLE_COUNSELLOR)) {
    $dashboard_link = $dashboard_prefix . 'counsellor_dashboard.php';
}
elseif (hasRole(ROLE_STUDENT)) {
    $dashboard_link = $dashboard_prefix . 'student_dashboard.php';
}
elseif (hasRole(ROLE_ACCOUNTANT)) {
    $dashboard_link = $dashboard_prefix . 'accountant_dashboard.php';
}
elseif (hasRole(ROLE_WEBSITE_ADMIN)) {
    $dashboard_link = $dashboard_prefix . 'website_admin_dashboard.php';
}
elseif (hasRole(ROLE_MAINTENANCE)) {
    $dashboard_link = $modules_prefix . 'dashboard/maintenance_dashboard.php';
}
elseif (hasRole(ROLE_RECEPTION)) {
    $dashboard_link = $modules_prefix . 'dashboard/reception_dashboard.php';
}
elseif (hasRole(ROLE_ESTABLISHMENT)) {
    $dashboard_link = $modules_prefix . 'dashboard/establishment_dashboard.php';
}

elseif (hasRole(ROLE_WALLET_MANAGER)) {
    $dashboard_link = $modules_prefix . 'dashboard/wallet_manager_dashboard.php';
}
elseif (hasRole(ROLE_COMPUTER_OPERATOR)) {
    $dashboard_link = $modules_prefix . 'dashboard/computer_operator_dashboard.php';
}
elseif (isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true) {
    $dashboard_link = $modules_prefix . 'parent-portal/dashboard.php';
}

// Define website module prefix
$website_prefix = $portal_root . 'modules/website/';
$maintenance_prefix = $portal_root . 'modules/maintenance/';
?>
<!-- GCA Portal Sidebar -->
<script>console.log('Sidebar element rendering...');</script>
<aside id="main-sidebar" class="app-sidebar bg-white shadow-sm d-flex flex-column" data-bs-theme="light">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand bg-white border-bottom">
        <a href="<?php echo $dashboard_link; ?>"
            class="brand-link text-decoration-none d-flex align-items-center w-100">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" alt="Logo" class="brand-image me-2 sidebar-custom-1">
            <div class="brand-info flex-grow-1">
                <span class="brand-text fw-bold text-dark d-block sidebar-custom-2"><?php echo defined('SYSTEM_SHORT_NAME') ? SYSTEM_SHORT_NAME : 'GCA'; ?></span>
                <span class="brand-subtitle text-muted small d-block">Portal v2.0</span>
            </div>
        </a>
    </div>

    <!-- Sidebar Wrapper -->
    <div class="sidebar-wrapper px-2">
        <nav>
            <ul class="nav nav-pills sidebar-menu flex-column" data-lte-toggle="treeview" role="menu"
                data-accordion="true">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo $dashboard_link; ?>" class="nav-link">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <?php if (hasRole(ROLE_COMPUTER_OPERATOR)): ?>
                    <!-- ===== COMPUTER OPERATOR MENU ===== -->
                    <li class="nav-header text-uppercase opacity-75 small fw-bold">Data Management</li>
                    <!-- Student Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Student Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=all" class="nav-link"><i class="fas fa-users"></i><p>All Students</p></a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>add.php" class="nav-link"><i class="fas fa-user-plus"></i><p>New Admission</p></a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=enrolled" class="nav-link"><i class="fas fa-user-check"></i><p>Enrolled Students</p></a></li>
                        </ul>
                    </li>
                    <!-- Student Upload -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-import"></i>
                            <p>Student Upload<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>upload.php" class="nav-link"><i class="fas fa-upload"></i><p>Bulk Student Import</p></a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>direct-admission-upload.php" class="nav-link"><i class="fas fa-user-check"></i><p>Direct Admission (12th)</p></a></li>
                        </ul>
                    </li>
                    <!-- Dynamic WhatsApp -->
                    <li class="nav-item">
                        <a href="<?php echo $students_prefix; ?>send-whatsapp-dynamic.php" class="nav-link">
                            <i class="nav-icon fab fa-whatsapp"></i>
                            <p>Send WhatsApp</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_SUPER_ADMIN)): ?>
                    <!-- ===== SUPER ADMIN MENU ===== -->

                    <!-- User Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-users-cog"></i>
                            <p>User Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>users.php" class="nav-link"><i
                                        class="fas fa-user"></i>
                                    <p>Users</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>roles.php" class="nav-link"><i
                                        class="fas fa-user-tag"></i>
                                    <p>Roles</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Master Data -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-database"></i>
                            <p>Master Data<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>academic-years.php"
                                    class="nav-link"><i class="fas fa-calendar-alt"></i>
                                    <p>Academic Years</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>boards.php" class="nav-link"><i
                                        class="fas fa-chalkboard"></i>
                                    <p>Boards</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>courses.php" class="nav-link"><i
                                        class="fas fa-book"></i>
                                    <p>Standards</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>schools.php" class="nav-link"><i
                                        class="fas fa-school"></i>
                                    <p>Schools</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>campuses.php" class="nav-link"><i
                                        class="fas fa-university"></i>
                                    <p>Campuses</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>medium.php" class="nav-link"><i
                                        class="fas fa-language"></i>
                                    <p>Medium</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>group.php" class="nav-link"><i
                                        class="fas fa-layer-group"></i>
                                    <p>Groups</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>term.php" class="nav-link"><i
                                        class="fas fa-calendar-week"></i>
                                    <p>Terms</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_management_prefix; ?>subjects.php"
                                    class="nav-link"><i class="fas fa-book-open"></i>
                                    <p>Subjects</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Student Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Student Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo $students_prefix; ?>students.php?view=all" class="nav-link">
                                    <i class="fas fa-users"></i>
                                    <p>All Students</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $students_prefix; ?>add.php" class="nav-link">
                                    <i class="fas fa-user-plus"></i>
                                    <p>New Admission</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $students_prefix; ?>direct-admission-upload.php" class="nav-link">
                                    <i class="fas fa-user-check"></i>
                                    <p>Direct Admission (12th)</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $students_prefix; ?>send-whatsapp-dynamic.php" class="nav-link">
                                    <i class="fab fa-whatsapp"></i>
                                    <p>Send Dynamic WhatsApp</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>parent-portal/manage-parents.php" class="nav-link">
                                    <i class="fas fa-user-friends"></i>
                                    <p>Manage Parents</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>certificates/index.php" class="nav-link">
                                    <i class="fas fa-certificate"></i>
                                    <p>Certificates</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>establishment/attendance_mark.php" class="nav-link">
                                    <i class="fas fa-calendar-check text-success"></i>
                                    <p>Student Attendance</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>reception/absentee_followup.php" class="nav-link">
                                    <i class="fas fa-phone-volume text-danger"></i>
                                    <p>Absentee Follow-up</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Division Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-sitemap"></i>
                            <p>Division Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>divisions.php" class="nav-link"><i
                                         class="fas fa-object-group"></i>
                                    <p>Divisions</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>course-division.php"
                                    class="nav-link"><i class="fas fa-link"></i>
                                    <p>Standard Division</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>division-assignment.php"
                                    class="nav-link"><i class="fas fa-user-check"></i>
                                    <p>Assign Division</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>pending-division-requests.php"
                                    class="nav-link"><i class="fas fa-random"></i>
                                    <p>Division Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>division-shuffle.php"
                                    class="nav-link"><i class="fas fa-sync-alt"></i>
                                    <p>Division Shuffle</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Online Exam System -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-laptop-code text-primary"></i>
                            <p>Online Exam<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/index.php" class="nav-link">
                                    <i class="fas fa-plus"></i>
                                    <p>Create Question</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/question-bank.php" class="nav-link">
                                    <i class="fas fa-book"></i>
                                    <p>Question Bank</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/manage-subjects.php" class="nav-link">
                                    <i class="fas fa-layer-group"></i>
                                    <p>Manage Subjects</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/manage-chapters.php" class="nav-link">
                                    <i class="fas fa-folder"></i>
                                    <p>Manage Chapters</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/manage-topics.php" class="nav-link">
                                    <i class="fas fa-tag"></i>
                                    <p>Manage Topics</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/exam-templates.php" class="nav-link">
                                    <i class="fas fa-layer-group"></i>
                                    <p>Exam Templates</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/manage-exams.php" class="nav-link">
                                    <i class="fas fa-file-invoice"></i>
                                    <p>Manage Exams</p>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <!-- WhatsApp -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fab fa-whatsapp"></i>
                            <p>WhatsApp<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">

                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>pending-reminders.php"
                                    class="nav-link"><i class="fas fa-bell"></i>
                                    <p>Due Reminders</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>whatsapp-templates.php"
                                    class="nav-link"><i class="fas fa-file-alt"></i>
                                    <p>Templates</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>whatsapp-message-logs.php"
                                    class="nav-link"><i class="fas fa-list"></i>
                                    <p>Message Logs</p>
                                </a></li>
                        </ul>
                    </li>


                    <!-- Fee Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-money-bill-wave"></i>
                            <p>Fee Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>fee-config.php" class="nav-link"><i
                                        class="fas fa-cog"></i>
                                    <p>Fee Config</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $hostel_prefix; ?>hostel-fee-config.php"
                                    class="nav-link"><i class="fas fa-bed"></i>
                                    <p>Hostel Fee</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>transport-fee-config.php"
                                    class="nav-link"><i class="fas fa-bus"></i>
                                    <p>Transport Fee</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>assign-fees.php" class="nav-link"><i
                                        class="fas fa-hand-holding-usd"></i>
                                    <p>Assign Fees</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>auto-assign-semester2.php"
                                    class="nav-link"><i class="fas fa-graduation-cap"></i>
                                    <p>Promote to Semester 2</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>auto-assign-class12.php"
                                    class="nav-link"><i class="fas fa-graduation-cap"></i>
                                    <p>Promote to Class 12</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>installment-requests.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Installment Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>receipt-config.php"
                                    class="nav-link"><i class="fas fa-receipt"></i>
                                    <p>Receipt Config</p>
                                </a></li>

                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>manual-wallet-deposit.php"
                                    class="nav-link"><i class="fas fa-wallet text-success"></i>
                                    <p>Wallet Deposit</p>
                                </a></li>
                            <li class="nav-item">
                                <a href="<?php echo $payments_prefix; ?>gateway-logs.php" class="nav-link">
                                    <i class="fas fa-terminal text-primary"></i>
                                    <p>Gateway Logs</p>
                                </a>
                            </li>
                            <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPAL)): ?>
                            <li class="nav-item">
                                <a href="<?php echo $payments_prefix; ?>refund-management.php" class="nav-link">
                                    <i class="fas fa-undo-alt text-danger"></i>
                                    <p>Refund Management</p>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <!-- Scholarship -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-graduation-cap"></i>
                            <p>Scholarship<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $scholarships_prefix; ?>scholarship-types.php"
                                    class="nav-link"><i class="fas fa-award"></i>
                                    <p>Types</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $scholarships_prefix; ?>scholarship-rules.php"
                                    class="nav-link"><i class="fas fa-gavel"></i>
                                    <p>Rules</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Test Marks -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Test Marks<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>index.php" class="nav-link"><i
                                        class="fas fa-list"></i>
                                    <p>All Test Marks</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>add.php" class="nav-link"><i
                                        class="fas fa-plus-circle"></i>
                                    <p>Add Single</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>bulk-upload.php"
                                    class="nav-link"><i class="fas fa-cloud-upload-alt"></i>
                                    <p>Bulk Upload</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Financial Reports -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Financial Reports<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/" class="nav-link"><i
                                        class="fas fa-th-large"></i>
                                    <p>Dashboard</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/receipt-register.php"
                                    class="nav-link"><i class="fas fa-receipt"></i>
                                    <p>Receipt Register</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/receipt-breakdown.php"
                                    class="nav-link"><i class="fas fa-receipt"></i>
                                    <p>Receipt Breakdown</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/day-book.php"
                                    class="nav-link"><i class="fas fa-book-open"></i>
                                    <p>Day Book</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/pending-fees.php"
                                    class="nav-link"><i class="fas fa-exclamation-circle"></i>
                                    <p>Pending Fees</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/fee-defaulters.php"
                                    class="nav-link"><i class="fas fa-user-times"></i>
                                    <p>Fee Defaulters</p>
                                </a></li>
                        </ul>
                    </li>
                    <!-- Email Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>Email<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-templates.php" class="nav-link"><i class="fas fa-file-alt"></i><p>Templates</p></a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-logs.php" class="nav-link"><i class="fas fa-list"></i><p>Email Logs</p></a></li>
                        </ul>
                    </li>
                <?php endif; ?>



                <?php if (hasRole(ROLE_PRINCIPLE)): ?>
                    <!-- ===== PRINCIPLE MENU ===== -->

                    <!-- Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $counsellors_prefix; ?>list.php" class="nav-link"><i
                                        class="fas fa-user-tie"></i>
                                    <p>Counsellors</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>appointments.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Appointments</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=all"
                                    class="nav-link"><i class="fas fa-user-graduate"></i>
                                    <p>Students</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>student-assignment.php"
                                    class="nav-link"><i class="fas fa-user-check"></i>
                                    <p>Student Assignment</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>division-assignment.php"
                                    class="nav-link"><i class="fas fa-object-group"></i>
                                    <p>Division Assignment</p>
                                </a></li>
                            <li class="nav-item"><a
                                    href="<?php echo $portal_root; ?>modules/group-change/pending-requests.php"
                                    class="nav-link"><i class="fas fa-exchange-alt"></i>
                                    <p>Group Changes</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>pending-division-requests.php"
                                    class="nav-link"><i class="fas fa-random"></i>
                                    <p>Division Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>leave-applications.php"
                                    class="nav-link"><i class="fas fa-clipboard-list"></i>
                                    <p>Leave Applications</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>division-shuffle.php"
                                    class="nav-link"><i class="fas fa-sync-alt"></i>
                                    <p>Division Shuffle</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>certificates/index.php"
                                    class="nav-link"><i class="fas fa-certificate"></i>
                                    <p>Certificates</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>approvals/cancellation-requests.php"
                                    class="nav-link"><i class="fas fa-user-times"></i>
                                    <p>Cancellation Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>parent-portal/manage-parents.php"
                                    class="nav-link"><i class="fas fa-user-friends"></i>
                                    <p>Manage Parents</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>direct-admission-upload.php"
                                    class="nav-link"><i class="fas fa-user-plus"></i>
                                    <p>Direct Admission (12th)</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>campuses.php"
                                    class="nav-link"><i class="fas fa-university"></i>
                                    <p>Campuses</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>establishment/attendance_mark.php"
                                    class="nav-link"><i class="fas fa-calendar-check text-success"></i>
                                    <p>Student Attendance</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>reception/absentee_followup.php"
                                    class="nav-link"><i class="fas fa-phone-volume text-danger"></i>
                                    <p>Absentee Follow-up</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Legacy Paper Sets Deprecated: Use Online Exam -> Exam Templates -->


                    <!-- Test Results -->
                    <li class="nav-item">
                        <a href="<?php echo $results_prefix; ?>results.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Test Results</p>
                        </a>
                    </li>

                    <!-- Online Exam System -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-laptop-code text-primary"></i>
                            <p>Online Exam<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/index.php" class="nav-link">
                                    <i class="fas fa-plus"></i>
                                    <p>Create Question</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $modules_prefix; ?>online-exam/question-bank.php" class="nav-link">
                                    <i class="fas fa-book"></i>
                                    <p>Question Bank</p>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Fee Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-money-bill-wave"></i>
                            <p>Fee Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>post-admission-discount.php"
                                    class="nav-link"><i class="fas fa-percentage"></i>
                                    <p>Post-Admission Discount</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>pending-discount-approvals.php"
                                    class="nav-link"><i class="fas fa-clock"></i>
                                    <p>Approval Queue</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>installment-requests.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Installment Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>create-installment.php"
                                    class="nav-link"><i class="fas fa-calendar-plus"></i>
                                    <p>Create Installment</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>auto-assign-semester2.php"
                                    class="nav-link"><i class="fas fa-graduation-cap"></i>
                                    <p>Promote to Semester 2</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>auto-assign-class12.php"
                                    class="nav-link"><i class="fas fa-graduation-cap"></i>
                                    <p>Promote to Class 12</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>manual-wallet-deposit.php"
                                    class="nav-link"><i class="fas fa-wallet text-success"></i>
                                    <p>Wallet Deposit</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Reports -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Reports<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>reports.php" class="nav-link"><i
                                        class="fas fa-chart-bar"></i>
                                    <p>All Reports</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/" class="nav-link"><i
                                        class="fas fa-chart-line"></i>
                                    <p>Financial Reports</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/pending-fees.php"
                                    class="nav-link"><i class="fas fa-exclamation-circle"></i>
                                    <p>Pending Fees</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/fee-defaulters.php"
                                    class="nav-link"><i class="fas fa-user-times"></i>
                                    <p>Fee Defaulters</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Test Marks -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Test Marks<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>index.php" class="nav-link"><i
                                        class="fas fa-list"></i>
                                    <p>All Test Marks</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>add.php" class="nav-link"><i
                                        class="fas fa-plus-circle"></i>
                                    <p>Add Single</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>bulk-upload.php"
                                    class="nav-link"><i class="fas fa-cloud-upload-alt"></i>
                                    <p>Bulk Upload</p>
                                </a></li>
                        </ul>
                    </li>
                    <!-- Email Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>Email<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-templates.php" class="nav-link"><i class="fas fa-file-alt"></i><p>Templates</p></a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-logs.php" class="nav-link"><i class="fas fa-list"></i><p>Email Logs</p></a></li>
                        </ul>
                    </li>

                    <!-- Online Exam -->
                    <?php if (hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-laptop-code"></i>
                            <p>Online Exam<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $portal_root; ?>modules/online-exam/index.php" class="nav-link"><i class="fas fa-plus-circle"></i><p>Create Question</p></a></li>
                            <li class="nav-item"><a href="<?php echo $portal_root; ?>modules/online-exam/question-bank.php" class="nav-link"><i class="fas fa-university"></i><p>Question Bank</p></a></li>
                            <li class="nav-item"><a href="<?php echo $portal_root; ?>modules/online-exam/exam-setup.php" class="nav-link"><i class="fas fa-cog"></i><p>Exam Setup</p></a></li>
                            <li class="nav-item"><a href="<?php echo $portal_root; ?>modules/online-exam/exam-templates.php" class="nav-link"><i class="fas fa-layer-group"></i><p>Exam Templates</p></a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'accountant'): ?>
                    <!-- ===== ACCOUNTANT MENU ===== -->
                    <!-- Student Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Student Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=enrolled"
                                    class="nav-link"><i class="fas fa-user-check"></i>
                                    <p>Enrolled Students</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=registered"
                                    class="nav-link"><i class="fas fa-user-clock"></i>
                                    <p>Pending Token Fee</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>approvals/cancellation-requests.php"
                                    class="nav-link"><i class="fas fa-user-times"></i>
                                    <p>Cancellation Requests</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Financial -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-rupee-sign"></i>
                            <p>Financial<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="../students/students.php?view=registered" class="nav-link"><i
                                         class="fas fa-coins"></i>
                                    <p>Token Fee</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>payments.php" class="nav-link"><i
                                        class="fas fa-money-check-alt"></i>
                                    <p>Payments</p>
                                </a></li>

                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>pending-payments.php"
                                    class="nav-link"><i class="fas fa-exclamation-triangle"></i>
                                    <p>Pending</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>post-admission-discount.php"
                                    class="nav-link"><i class="fas fa-percentage"></i>
                                    <p>Post-Admission Discount</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>my-discount-requests.php"
                                    class="nav-link"><i class="fas fa-list"></i>
                                    <p>Discount Tracking</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>installment-requests.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Installment Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>manual-wallet-deposit.php"
                                    class="nav-link"><i class="fas fa-wallet text-success"></i>
                                    <p>Wallet Deposit</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>pending-reminders.php"
                                    class="nav-link text-success"><i class="fab fa-whatsapp"></i>
                                    <p>WhatsApp Reminders</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Financial Reports -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Financial Reports<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/" class="nav-link"><i
                                        class="fas fa-th-large"></i>
                                    <p>Dashboard</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/receipt-register.php"
                                    class="nav-link"><i class="fas fa-receipt"></i>
                                    <p>Receipt Register</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/receipt-breakdown.php"
                                    class="nav-link"><i class="fas fa-receipt"></i>
                                    <p>Receipt Breakdown</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/day-book.php"
                                    class="nav-link"><i class="fas fa-book-open"></i>
                                    <p>Day Book</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/pending-fees.php"
                                    class="nav-link"><i class="fas fa-exclamation-circle"></i>
                                    <p>Pending Fees</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $reports_prefix; ?>financial/fee-defaulters.php"
                                    class="nav-link"><i class="fas fa-user-times"></i>
                                    <p>Fee Defaulters</p>
                                </a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_COUNSELLOR)): ?>
                    <!-- ===== COUNSELLOR MENU ===== -->

                    <!-- Students -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Students<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=all"
                                    class="nav-link"><i class="fas fa-users"></i>
                                    <p>All Students</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>appointments.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Appointments</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>sessions.php" class="nav-link"><i
                                        class="fas fa-chalkboard-teacher"></i>
                                    <p>Sessions</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>admission-confirm-list.php"
                                    class="nav-link"><i class="fas fa-check-circle"></i>
                                    <p>Admission Confirm</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>pending-division-requests.php"
                                    class="nav-link"><i class="fas fa-random"></i>
                                    <p>Division Requests</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>leave-applications.php"
                                    class="nav-link"><i class="fas fa-clipboard-list"></i>
                                    <p>Leave Applications</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Installment Requests -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Installment<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $counsellors_prefix; ?>create-request-for-student.php"
                                    class="nav-link"><i class="fas fa-plus-circle"></i>
                                    <p>Request for Student</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $counsellors_prefix; ?>my-installment-requests.php"
                                    class="nav-link"><i class="fas fa-list"></i>
                                    <p>My Requests</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- OMR & Results -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-clipboard-check"></i>
                            <p>OMR & Results<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $omr_prefix; ?>omr-sheets.php" class="nav-link"><i
                                        class="fas fa-table"></i>
                                    <p>OMR Sheets</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $results_prefix; ?>results.php" class="nav-link"><i
                                        class="fas fa-chart-line"></i>
                                    <p>Results</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Reports -->
                    <li class="nav-item">
                        <a href="<?php echo $reports_prefix; ?>reports.php" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Reports</p>
                        </a>
                    </li>

                    <!-- Test Marks -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Test Marks<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>index.php" class="nav-link"><i
                                        class="fas fa-list"></i>
                                    <p>All Test Marks</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>add.php" class="nav-link"><i
                                        class="fas fa-plus-circle"></i>
                                    <p>Add Single</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_marks_prefix; ?>bulk-upload.php"
                                    class="nav-link"><i class="fas fa-cloud-upload-alt"></i>
                                    <p>Bulk Upload</p>
                                </a></li>
                        </ul>
                    </li>
                    <!-- Email Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>Email<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-templates.php" class="nav-link"><i class="fas fa-file-alt"></i><p>Templates</p></a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-logs.php" class="nav-link"><i class="fas fa-list"></i><p>Email Logs</p></a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_WALLET_MANAGER)): ?>
                    <li class="nav-item">
                        <a href="<?php echo $fees_prefix; ?>manual-wallet-deposit.php" class="nav-link">
                            <i class="nav-icon fas fa-wallet text-success"></i>
                            <p>Manual Deposit</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $reports_prefix; ?>financial/wallet-reports.php" class="nav-link">
                            <i class="nav-icon fas fa-file-invoice text-secondary"></i>
                            <p>Wallet Reports</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true): ?>
                    <!-- ===== STUDENT MENU ===== -->

                    <!-- Appointments -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>Appointments<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>appointments.php"
                                    class="nav-link"><i class="fas fa-calendar-plus"></i>
                                    <p>Book Appointment</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>my-appointments.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>My Appointments</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Leaves -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-times"></i>
                            <p>Leaves<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>apply-leave.php"
                                    class="nav-link"><i class="fas fa-plus-circle"></i>
                                    <p>Apply for Leave</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>my-leaves.php"
                                    class="nav-link"><i class="fas fa-list"></i>
                                    <p>My Leaves</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- My Area -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-folder-open"></i>
                            <p>My Area<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>my-results.php"
                                    class="nav-link"><i class="fas fa-chart-bar"></i>
                                    <p>My Results</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>my-fees.php"
                                    class="nav-link"><i class="fas fa-dollar-sign"></i>
                                    <p>My Fees</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>records.php"
                                    class="nav-link"><i class="fas fa-folder-open"></i>
                                    <p>Records</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>my-wallet.php"
                                    class="nav-link"><i class="fas fa-wallet text-primary"></i>
                                    <p>Digital Wallet</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Division Change -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-exchange-alt"></i>
                            <p>Division Change<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $student_portal_prefix; ?>request-division-change.php"
                                    class="nav-link"><i class="fas fa-plus-circle"></i>
                                    <p>Request Change</p>
                                </a></li>
                            <li class="nav-item"><a
                                    href="<?php echo $student_portal_prefix; ?>my-division-change-requests.php"
                                    class="nav-link"><i class="fas fa-list"></i>
                                    <p>My Requests</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Hostel Services -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-hotel"></i>
                            <p>Hostel Services<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo $student_portal_prefix; ?>hostel-services.php" class="nav-link">
                                    <i class="fas fa-bed"></i>
                                    <p>My Room & Requests</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php
endif; ?>







                <?php if (isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true): ?>
                    <!-- ===== PARENT MENU ===== -->
                    <li class="nav-item">
                        <a href="<?php echo $modules_prefix; ?>parent-portal/dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Parent Dashboard</p>
                        </a>
                    </li>

                    <li class="nav-header text-uppercase opacity-75 small fw-bold">Active Child: <?php
    $active_child_name = 'Student';
    foreach ($_SESSION['children'] as $child) {
        if ($child['id'] == $_SESSION['active_student_id']) {
            $active_child_name = $child['student_name'];
            break;
        }
    }
    echo htmlspecialchars($active_child_name ?? '');
?></li>

                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-exchange-alt"></i>
                            <p>Switch Child<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php foreach ($_SESSION['children'] as $child): ?>
                                <li class="nav-item">
                                    <a href="<?php echo $modules_prefix; ?>parent-portal/switch-child.php?student_id=<?php echo $child['id']; ?>"
                                        class="nav-link">
                                        <i
                                            class="<?php echo($child['id'] == $_SESSION['active_student_id']) ? 'fas fa-check-circle text-success' : 'far fa-circle'; ?> nav-icon"></i>
                                        <p><?php echo htmlspecialchars($child['student_name'] ?? ''); ?></p>
                                    </a>
                                </li>
                            <?php
    endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-header text-uppercase opacity-75 small fw-bold">Academic & Fees</li>

                    <li class="nav-item">
                        <a href="<?php echo $student_portal_prefix; ?>my-fees.php" class="nav-link">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <p>Fees & Payments</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $student_portal_prefix; ?>my-results.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Test Results</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $student_portal_prefix; ?>hostel-services.php" class="nav-link">
                            <i class="nav-icon fas fa-hotel"></i>
                            <p>Hostel Services</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $student_portal_prefix; ?>profile.php" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Student Profile</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_MAINTENANCE)): ?>
                    <!-- ===== MAINTENANCE ADMIN MENU ===== -->
                    <!-- Backup Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-database"></i>
                            <p>Backup Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>backup/database.php" class="nav-link"><i class="fas fa-server"></i><p>Database Backup</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>backup/files.php" class="nav-link"><i class="fas fa-folder"></i><p>Files Backup</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>backup/receipt-reports.php" class="nav-link"><i class="fas fa-file-excel"></i><p>Receipt Reports</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>backup/history.php" class="nav-link"><i class="fas fa-history"></i><p>Backup History</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>backup/restore.php" class="nav-link"><i class="fas fa-undo"></i><p>Restore</p></a></li>
                        </ul>
                    </li>
                    <!-- API Debugger -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-plug"></i>
                            <p>API Debugger<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>api-debug/index.php" class="nav-link"><i class="fas fa-list"></i><p>API Logs</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>api-debug/test.php" class="nav-link"><i class="fas fa-flask"></i><p>API Tester</p></a></li>
                        </ul>
                    </li>
                    <!-- System -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-heartbeat"></i>
                            <p>System<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>system/health.php" class="nav-link"><i class="fas fa-stethoscope"></i><p>System Health</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>system/statistics.php" class="nav-link"><i class="fas fa-chart-bar"></i><p>Statistics</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>system/info.php" class="nav-link"><i class="fas fa-info-circle"></i><p>PHP Info</p></a></li>
                        </ul>
                    </li>
                    <!-- Logs -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Logs<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>error-logs/errors.php" class="nav-link"><i class="fas fa-exclamation-circle"></i><p>Error Logs</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>logs/activity.php" class="nav-link"><i class="fas fa-user-clock"></i><p>Activity Logs</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>logs/security.php" class="nav-link"><i class="fas fa-shield-alt"></i><p>Security Audit</p></a></li>
                        </ul>
                    </li>
                    <!-- Tools -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-tools"></i>
                            <p>Tools<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>tools/cache.php" class="nav-link"><i class="fas fa-broom"></i><p>Cache Manager</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>tools/sessions.php" class="nav-link"><i class="fas fa-users-cog"></i><p>Sessions</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>tools/database.php" class="nav-link"><i class="fas fa-database"></i><p>Database Tools</p></a></li>
                            <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>tools/queue.php" class="nav-link"><i class="fas fa-envelope"></i><p>Email/SMS Queue</p></a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>cron/index.php" class="nav-link"><i class="nav-icon fas fa-clock"></i><p>Cron Jobs</p></a></li>
                    <li class="nav-item"><a href="<?php echo $maintenance_prefix; ?>settings/config.php" class="nav-link"><i class="nav-icon fas fa-cog"></i><p>Configuration</p></a></li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_RECEPTION)): ?>
                    <!-- ===== RECEPTION MENU ===== -->
                    <!-- Student Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Student Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=all" class="nav-link"><i class="fas fa-users"></i><p>All Students</p></a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>add.php" class="nav-link"><i class="fas fa-user-plus"></i><p>New Admission</p></a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>admission-confirm-list.php" class="nav-link"><i class="fas fa-check-circle"></i><p>Confirm Admission</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>approvals/cancellation-requests.php" class="nav-link"><i class="fas fa-user-times text-danger"></i><p>Cancellation Requests</p></a></li>
                        </ul>
                    </li>
                    <!-- Appointments -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>Appointments<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i><p>Today's Schedule</p></a></li>
                        </ul>
                    </li>
                    <!-- Attendance Follow-up -->
                    <li class="nav-item">
                        <a href="<?php echo $modules_prefix; ?>reception/absentee_followup.php" class="nav-link">
                            <i class="nav-icon fas fa-phone-volume text-danger"></i>
                            <p>Absentee Follow-up</p>
                        </a>
                    </li>
                    <!-- Financial -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-rupee-sign"></i>
                            <p>Financial<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=registered" class="nav-link"><i class="fas fa-coins"></i><p>Token Fee</p></a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_ESTABLISHMENT)): ?>
                    <!-- ===== ESTABLISHMENT MENU ===== -->
                    <!-- Academics Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-graduation-cap"></i>
                            <p>Academics<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>academics/boards.php" class="nav-link"><i class="fas fa-clipboard-list"></i><p>Boards</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>academics/courses.php" class="nav-link"><i class="fas fa-book"></i><p>Courses</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>academics/academic-years.php" class="nav-link"><i class="fas fa-calendar-alt"></i><p>Academic Years</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>academics/group.php" class="nav-link"><i class="fas fa-users-class"></i><p>Groups/Batches</p></a></li>
                        </ul>
                    </li>
                    <!-- Attendance -->
                    <li class="nav-item">
                        <a href="<?php echo $modules_prefix; ?>establishment/attendance_mark.php" class="nav-link">
                            <i class="nav-icon fas fa-calendar-check text-success"></i>
                            <p>Student Attendance</p>
                        </a>
                    </li>
                    <!-- Student Life Cycle -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-edit"></i>
                            <p>Student Cycle<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>students.php?view=all" class="nav-link"><i class="fas fa-users"></i><p>All Students</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>establishment/docs.php" class="nav-link"><i class="fas fa-file-upload"></i><p>Document Manager</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>certificates/index.php" class="nav-link"><i class="fas fa-certificate"></i><p>Certificates</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>establishment/initiate-cancellation.php" class="nav-link"><i class="fas fa-user-times"></i><p>Admission Cancellation</p></a></li>
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>approvals/cancellation-requests.php" class="nav-link"><i class="fas fa-tasks text-warning"></i><p>Cancellation Status</p></a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>auto-assign-semester2.php" class="nav-link"><i class="fas fa-graduation-cap"></i><p>Promote to Semester 2</p></a></li>
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>auto-assign-class12.php" class="nav-link"><i class="fas fa-graduation-cap"></i><p>Promote to Class 12</p></a></li>
                        </ul>
                    </li>
                    <!-- Staff Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-tie"></i>
                            <p>Staff Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>settings/users.php?role=counsellor" class="nav-link"><i class="fas fa-users"></i><p>Counsellors</p></a></li>
                        </ul>
                    </li>
                    <!-- Reports -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>Institutional Reports<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $modules_prefix; ?>reports/consolidated_report.php" class="nav-link"><i class="fas fa-file-pdf"></i><p>Consolidated</p></a></li>
                        </ul>
                    </li>
                <?php endif; ?>


                <?php if (hasRole(ROLE_WEBSITE_ADMIN)): ?>
                    <!-- ===== WEBSITE ADMIN MENU ===== -->
                    <!-- Content Management -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-globe"></i>
                            <p>Website Management<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $website_prefix; ?>config/index.php" class="nav-link"><i class="fas fa-cog"></i><p>Configuration</p></a></li>
                            <li class="nav-item"><a href="<?php echo $website_prefix; ?>navigation/index.php" class="nav-link"><i class="fas fa-bars"></i><p>Navigation</p></a></li>
                            <li class="nav-item"><a href="<?php echo $website_prefix; ?>pages/index.php" class="nav-link"><i class="fas fa-file-alt"></i><p>Pages</p></a></li>
                            <li class="nav-item"><a href="<?php echo $website_prefix; ?>editor/index.php" class="nav-link"><i class="fas fa-edit"></i><p>Visual Editor</p></a></li>
                            <li class="nav-item"><a href="<?php echo $website_prefix; ?>social/index.php" class="nav-link"><i class="fas fa-share-alt"></i><p>Social Links</p></a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a href="<?php echo $website_prefix; ?>db-content-monitor.php" class="nav-link"><i class="nav-icon fas fa-database"></i><p>DB Monitor</p></a></li>
                <?php endif; ?>

                <?php if (!hasRole(ROLE_STUDENT) && !hasRole(ROLE_COUNSELLOR) && !(isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true) && !(isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true)): ?>
                    <!-- Settings (for non-students and non-counsellors) -->

                    <li class="nav-item">
                        <a href="<?php echo $profile_prefix; ?>profile.php" class="nav-link">
                            <i class="nav-icon fas fa-user"></i>
                            <p>Profile</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $profile_prefix; ?>settings.php" class="nav-link">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Settings</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_COUNSELLOR)): ?>

                    <li class="nav-item">
                        <a href="<?php echo $profile_prefix; ?>profile.php" class="nav-link">
                            <i class="nav-icon fas fa-user"></i>
                            <p>Profile</p>
                        </a>
                    </li>
                <?php endif; ?>


                <!-- Security -->
                <li class="nav-item">
                    <a href="<?php echo PORTAL_URL; ?>/2fa-setup.php" class="nav-link">
                        <i class="nav-icon fas fa-user-shield text-info"></i>
                        <p>MFA Settings</p>
                    </a>
                </li>

                <!-- Logout -->
                <li class="nav-item border-top">
                    <a href="<?php echo $logout_path; ?>" class="nav-link text-danger">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside><!-- Main Content Wrapper Start -->
<main class="app-main">
