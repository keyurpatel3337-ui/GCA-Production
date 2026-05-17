<?php
// Use absolute paths for consistent navigation
$portal_root = PORTAL_URL . '/';
$logout_path = $portal_root . 'logout.php';
$profile_path = $portal_root . 'modules/profile/profile.php';
$settings_path = $portal_root . 'modules/profile/settings.php';

// Get page title from global variable or use default
$page_title_display = strip_tags($page_title ?? 'Dashboard');

// Handle breadcrumb - can be string or array
if (isset($page_breadcrumb)) {
    if (is_array($page_breadcrumb)) {
        $breadcrumb_text = end($page_breadcrumb)['title'] ?? '';
    } else {
        $breadcrumb_text = $page_breadcrumb;
    }
    $breadcrumb_text = trim($breadcrumb_text);
    $breadcrumb_text = preg_replace('/^Home\s*[\/|>]\s*/i', '', $breadcrumb_text);
    $breadcrumb_text = trim($breadcrumb_text);
} else {
    $breadcrumb_text = '';
}

// Get user information
if (isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true) {
    $user_name = $_SESSION['student_name'] ?? 'Student';
} elseif (isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true) {
    $user_name = $_SESSION['parent_mobile'] ?? 'Parent';
} else {
    $user_name = $_SESSION['user_name'] ?? 'User';
}
$user_avatar = $_SESSION['user_avatar'] ?? '';

// Get role display
$role_display = 'USER';
if (isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true) {
    $role_display = 'PARENT';
} elseif (hasRole(ROLE_SUPER_ADMIN)) {
    $role_display = 'SUPER ADMIN';
} elseif (hasRole(ROLE_PRINCIPLE)) {
    $role_display = 'PRINCIPLE';
} elseif (hasRole(ROLE_COUNSELLOR)) {
    $role_display = 'COUNSELLOR';
} elseif (hasRole(ROLE_STUDENT)) {
    $role_display = 'STUDENT';
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'accountant') {
    $role_display = 'ACCOUNTANT';
}

// Get initials
$initials = 'U';
if (!empty($user_name)) {
    $name_parts = explode(' ', trim($user_name));
    if (count($name_parts) >= 2) {
        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($name_parts[0], 0, 2));
    }
}
?>

<!-- AdminLTE 4 Navbar -->
<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <!-- Start navbar links -->
        <ul class="navbar-nav">
            <!-- Mobile sidebar toggle - only visible on small screens -->
            <li class="nav-item d-lg-none">
                <a class="nav-link" data-lte-toggle="sidebar-mobile" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <!-- Desktop sidebar toggle -->
            <li class="nav-item d-none d-lg-block">
                <a class="nav-link" data-lte-toggle="sidebar-desktop" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block ms-2">
                <div class="nav-link d-flex flex-column justify-content-center navbar-custom-1">
                    <span class="fw-bold text-dark"><?php echo $page_title_display; ?></span>
                    <?php if (!empty($breadcrumb_text)): ?>
                        <small class="text-muted navbar-custom-2">
                            <?php echo htmlspecialchars($breadcrumb_text ?? ''); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </li>
        </ul>

        <!-- End navbar links -->
        <ul class="navbar-nav ms-auto">
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link notification-bell" id="notification-bell" data-bs-toggle="dropdown" href="#"
                    data-api-url="<?php echo PORTAL_URL; ?>/api/notifications.php">
                    <i class="far fa-bell"></i>
                    <span class="navbar-badge badge bg-danger d-none" id="notification-badge">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end notification-dropdown"
                    id="notification-dropdown">
                    <span class="dropdown-header">Notifications</span>
                    <div class="dropdown-divider"></div>
                    <div class="notification-list" id="notification-list">
                        <div class="dropdown-item text-center text-muted py-3">
                            <i class="far fa-bell fa-2x mb-2"></i>
                            <p class="mb-0">No notifications</p>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer" id="mark-all-read">
                        Mark All as Read
                    </a>
                </div>
            </li>

            <!-- User Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                    <?php if (!empty($user_avatar)): ?>
                        <img src="<?php echo $user_avatar; ?>" class="user-image rounded-circle shadow me-2 navbar-custom-3"
                            alt="User Image">
                    <?php else: ?>
                        <span
                            class="user-image rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center me-2 navbar-custom-4">
                            <?php echo $initials; ?>
                        </span>
                    <?php endif; ?>
                    <span class="d-none d-md-inline text-truncate navbar-custom-5">
                        <?php echo htmlspecialchars($user_name ?? ''); ?>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!-- User image -->
                    <li class="user-header text-bg-primary">
                        <?php if (!empty($user_avatar)): ?>
                            <img src="<?php echo $user_avatar; ?>" class="rounded-circle shadow" alt="User Image">
                        <?php else: ?>
                            <div class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center mx-auto navbar-custom-6">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <p>
                            <?php echo htmlspecialchars($user_name ?? ''); ?>
                            <small><?php echo $role_display; ?></small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer">
                        <a href="<?php echo $profile_path; ?>" class="btn btn-default btn-flat">Profile</a>
                        <a href="<?php echo $logout_path; ?>" class="btn btn-default btn-flat float-end">Sign out</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>