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

    <style>
        /* AdminLTE 4 Custom Overrides */
        :root {
            --lte-sidebar-width: 280px;
            --lte-sidebar-collapsed-width: 70px;
        }

        /* Global Reset for Top Whitespace */
        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100%;
        }

        /* Specific fix for AdminLTE wrapper */
        .app-wrapper {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Ensure navbar sticks to top */
        .app-header {
            margin-top: 0 !important;
        }

        /* Custom scrollbar for better look */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0 !important;
            padding: 0 !important;
        }

        .app-wrapper {
            min-height: 100vh;
            /* margin-top: 0 !important; */
            /* This rule is now redundant and can be removed or commented out */
            /* padding-top: 0 !important; */
            /* This rule is now redundant and can be removed or commented out */
        }

        .app-header {
            /* margin-top: 0 !important; */
            /* This rule is now redundant and can be removed or commented out */
        }

        /* Card enhancements */
        .card {
            border: none;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        /* Info box improvements */
        .info-box,
        .small-box {
            border-radius: 0.5rem;
        }

        /* ============================================ */
        /* DISABLE ALL SIDEBAR TRANSITIONS ON PAGE LOAD */
        /* ============================================ */
        body.preload-transitions *,
        body.preload-transitions aside,
        body.preload-transitions aside *,
        body.preload-transitions .sidebar,
        body.preload-transitions .sidebar *,
        body.preload-transitions .app-sidebar,
        body.preload-transitions .app-sidebar *,
        body.preload-transitions .nav-item,
        body.preload-transitions .nav-item *,
        body.preload-transitions .nav-link,
        body.preload-transitions .nav-link *,
        body.preload-transitions .nav-arrow,
        body.preload-transitions .nav-treeview,
        body.preload-transitions .sidebar-menu,
        body.preload-transitions .sidebar-menu *,
        body.preload-transitions i {
            -webkit-transition: none !important;
            -moz-transition: none !important;
            -o-transition: none !important;
            transition: none !important;
            -webkit-animation: none !important;
            -moz-animation: none !important;
            animation: none !important;
        }

        /* Fix Table Dark Header Visibility */
        .table-dark th,
        .table-dark td,
        .table-dark thead th,
        .table-dark tbody+tbody {
            background-color: #343a40 !important;
            color: #fff !important;
            border-color: #454d55;
        }

        /* Pagination Styling */
        .pagination {
            gap: 0.25rem;
        }

        .pagination .page-item .page-link {
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }

        .pagination .page-item:not(.active):not(.disabled) .page-link:hover {
            background-color: #e9ecef;
            color: #0d6efd;
        }

        .pagination .page-item.disabled .page-link {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #6c757d;
        }

        /* ========================================== */
        /* SIDEBAR COLLAPSE ANIMATION & FUNCTIONALITY */
        /* ========================================== */

        /* Sidebar toggle button highlight */
        [data-lte-toggle="sidebar"] {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        [data-lte-toggle="sidebar"]:hover {
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 0.25rem;
        }

        /* Smooth transitions for sidebar (let AdminLTE handle positioning) */
        .app-sidebar {
            transition: transform 0.3s ease-in-out, margin-left 0.3s ease-in-out !important;
        }

        .app-main {
            transition: margin-left 0.3s ease-in-out !important;
        }

        /* Mobile overlay when sidebar is open */
        @media (max-width: 991.98px) {
            body.sidebar-open::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1037;
            }
        }
    </style>
</head>

<body class="sidebar-expand-lg sidebar-mini bg-body-tertiary preload-transitions">
    <div class="app-wrapper">