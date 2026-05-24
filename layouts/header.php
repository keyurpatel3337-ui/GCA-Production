<?php
// Clean any accumulated whitespace or output before the HTML document starts
if (ob_get_length()) {
    ob_end_clean();
}
// Restart buffering just in case
ob_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title ?? SYSTEM_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">

    <!-- Google Font: Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- AdminLTE 4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Global Theme CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/global-theme.css">
    <!-- Fixed Layout CSS -->
    <!-- Fixed Layout CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/fixed-layout.css">
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/sidebar-custom.css">
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modal-fixes.css">
    <!-- Student Search Component CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/student-search.css">
    <!-- Notification Bell CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/notification-bell.css">

    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/portal.css">
</head>

<body class="sidebar-expand-lg sidebar-mini bg-body-tertiary preload-transitions">
    <div class="app-wrapper">