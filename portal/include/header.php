<?php
// Include request sanitizer first - Auto-sanitizes all $_GET, $_POST, $_COOKIE
require_once __DIR__ . '/../common/request-sanitizer.php';

// Include security output functions globally for XSS protection
require_once __DIR__ . '/../common/security_output.php';

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
    <title><?php echo strip_tags($page_title ?? SYSTEM_NAME); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">

    <!-- Google Font: Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jQuery (Moved here to support inline scripts in modules) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Global Theme CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/global-theme.css">
    <!-- Fixed Layout CSS -->
    <!-- Fixed Layout CSS -->
    <!-- Fixed Layout CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/fixed-layout.css">
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modal-fixes.css">
    <!-- Student Search Component CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/student-search.css">
    <!-- Notification Bell CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/notification-bell.css">

    <!-- Online Exam System (OES) Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mathlive"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/katex/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.3/dist/tesseract.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/contrib/mhchem.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>

    <style>
        /* GCA Portal Custom Overrides */
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

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Navbar spacing tweak to align flush with sidebar */
        .app-header.navbar {
            padding-left: 0;
            padding-right: 0;
        }

        .app-header .container-fluid {
            padding-left: 0;
            padding-right: 1rem;
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
            box-shadow: 0 4px 6px -1px rgba(13, 110, 253, 0.4), 0 2px 4px -1px rgba(13, 110, 253, 0.06);
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

        /* Smooth transitions for sidebar */
        .app-sidebar {
            transition: transform 0.3s ease-in-out, margin-left 0.3s ease-in-out !important;
        }

        .app-main {
            transition: margin-left 0.3s ease-in-out !important;
        }

        /* ========================================== */
        /* FORCE SIDEBAR TEXT TO NOT TRUNCATE        */
        /* ========================================== */
        .sidebar-menu .nav-link>p,
        .nav-sidebar .nav-link>p,
        .sidebar .nav-link>p {
            overflow: visible !important;
            text-overflow: clip !important;
            white-space: normal !important;
        }
    </style>

    <!-- Environment Configuration for JavaScript -->
    <script>
        const BACKEND_URL = <?php echo gca_safe_js(BACKEND_URL); ?>;
        const BASE_URL = <?php echo gca_safe_js(BASE_URL); ?>;
        const PORTAL_URL = <?php echo gca_safe_js(PORTAL_URL); ?>;

        // Removed old sidebar state cleanup to allow persistence
    </script>
</head>

<body class="bg-body-tertiary preload-transitions">
    <div class="app-wrapper">