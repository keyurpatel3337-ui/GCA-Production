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
$logout_path = $portal_root . 'logout.php';

// Determine dashboard link
$dashboard_link = 'dashboard.php';
if (hasRole(ROLE_SUPER_ADMIN)) {
    $dashboard_link = $dashboard_prefix . 'admin_dashboard.php';
} elseif (hasRole(ROLE_PRINCIPLE)) {
    $dashboard_link = $dashboard_prefix . 'principle_dashboard.php';
} elseif (hasRole(ROLE_COUNSELLOR)) {
    $dashboard_link = $dashboard_prefix . 'counsellor_dashboard.php';
} elseif (hasRole(ROLE_STUDENT)) {
    $dashboard_link = $dashboard_prefix . 'student_dashboard.php';
} elseif (hasRole(ROLE_ACCOUNTANT)) {
    $dashboard_link = $dashboard_prefix . 'accountant_dashboard.php';
} elseif (hasRole(ROLE_WEBSITE_ADMIN)) {
    $dashboard_link = $dashboard_prefix . 'website_admin_dashboard.php';
}

// Define website module prefix
$website_prefix = $portal_root . 'modules/website/';
?>
<!-- AdminLTE 4 Sidebar -->
<aside class="app-sidebar" data-bs-theme="dark">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <a href="<?php echo $dashboard_link; ?>" class="brand-link">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" alt="GM Logo"
                class="brand-image opacity-75 shadow">
            <span
                class="brand-text fw-light"><?php echo defined('SYSTEM_SHORT_NAME') ? SYSTEM_SHORT_NAME : 'GCA'; ?></span>
        </a>
    </div>

    <!-- Sidebar Wrapper -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="true">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo $dashboard_link; ?>" class="nav-link">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

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
                                        class="fas fa-user "></i>
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
                                    <p>Courses</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $academics_prefix; ?>schools.php" class="nav-link"><i
                                        class="fas fa-school"></i>
                                    <p>Schools</p>
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
                                    <p>Course Division</p>
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

                    <!-- System Config -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>System Config<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>api-config.php" class="nav-link"><i
                                        class="fas fa-plug"></i>
                                    <p>API Config</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>receipt-config.php"
                                    class="nav-link"><i class="fas fa-receipt"></i>
                                    <p>Receipt Config</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>payment-gateways.php"
                                    class="nav-link"><i class="fas fa-credit-card"></i>
                                    <p>Payment Gateways</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- WhatsApp -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fab fa-whatsapp"></i>
                            <p>WhatsApp<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>whatsapp-api-management.php"
                                    class="nav-link"><i class="fas fa-cog"></i>
                                    <p>API Management</p>
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

                    <!-- Email -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>Email<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>smtp-config.php"
                                    class="nav-link"><i class="fas fa-server"></i>
                                    <p>SMTP Config</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-templates.php"
                                    class="nav-link"><i class="fas fa-file-alt"></i>
                                    <p>Templates</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $settings_prefix; ?>email-logs.php" class="nav-link"><i
                                        class="fas fa-list"></i>
                                    <p>Email Logs</p>
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
                            <li class="nav-item"><a href="<?php echo $fees_prefix; ?>assign-fees.php" class="nav-link"><i
                                        class="fas fa-hand-holding-usd"></i>
                                    <p>Assign Fees</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>installment-requests.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Installment Requests</p>
                                </a></li>
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
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>list.php" class="nav-link"><i
                                        class="fas fa-user-graduate"></i>
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
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>division-shuffle.php"
                                    class="nav-link"><i class="fas fa-sync-alt"></i>
                                    <p>Division Shuffle</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Paper Sets -->
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Paper Sets<i class="nav-arrow fas fa-angle-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $test_management_prefix; ?>paper-sets.php"
                                    class="nav-link"><i class="fas fa-file-pdf"></i>
                                    <p>Paper Sets</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_management_prefix; ?>blueprint-upload.php"
                                    class="nav-link"><i class="fas fa-upload"></i>
                                    <p>Blueprint Upload</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $test_management_prefix; ?>answer-keys.php"
                                    class="nav-link"><i class="fas fa-key"></i>
                                    <p>Answer Keys</p>
                                </a></li>
                        </ul>
                    </li>


                    <!-- Test Results -->
                    <li class="nav-item">
                        <a href="<?php echo $results_prefix; ?>results.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Test Results</p>
                        </a>
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
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>installment-requests.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Installment Requests</p>
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
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>list.php" class="nav-link"><i
                                        class="fas fa-users"></i>
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
                        </ul>
                    </li>


                    <!-- Reports -->
                    <li class="nav-item">
                        <a href="<?php echo $reports_prefix; ?>reports.php" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Reports</p>
                        </a>
                    </li>
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
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>enrolled-students.php"
                                    class="nav-link"><i class="fas fa-user-check"></i>
                                    <p>Enrolled Students</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>registered-students.php"
                                    class="nav-link"><i class="fas fa-user-clock"></i>
                                    <p>Pending Token Fee</p>
                                </a></li>
                            <li class="nav-item"><a href="<?php echo $students_prefix; ?>list.php" class="nav-link"><i
                                        class="fas fa-users"></i>
                                    <p>All Students</p>
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
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>token-fee-collection.php"
                                    class="nav-link"><i class="fas fa-coins"></i>
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
                            <?php if (hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_SUPER_ADMIN)): ?>
                                <li class="nav-item"><a href="<?php echo $students_prefix; ?>post-admission-discount.php"
                                        class="nav-link"><i class="fas fa-percentage"></i>
                                        <p>Post-Admission Discount</p>
                                    </a></li>
                            <?php endif; ?>
                            <li class="nav-item"><a href="<?php echo $payments_prefix; ?>installment-requests.php"
                                    class="nav-link"><i class="fas fa-calendar-check"></i>
                                    <p>Installment Requests</p>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Financial Reports -->
                    <li class="nav-item">
                        <a href="<?php echo $payments_prefix; ?>financial-reports.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Financial Reports</p>
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
                <?php endif; ?>

                <?php if (!hasRole(ROLE_STUDENT) && !hasRole(ROLE_COUNSELLOR) && !(isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true)): ?>
                    <!-- Settings (for non-students and non-counsellors) -->
                    <li class="nav-header">ACCOUNT</li>
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
                    <li class="nav-header">ACCOUNT</li>
                    <li class="nav-item">
                        <a href="<?php echo $profile_prefix; ?>profile.php" class="nav-link">
                            <i class="nav-icon fas fa-user"></i>
                            <p>Profile</p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Logout -->
                <li class="nav-item">
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