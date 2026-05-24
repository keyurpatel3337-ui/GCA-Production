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

    <!-- Google Font: Inter & Noto Serif Gujarati -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Serif+Gujarati:wght@400;500;600;700&display=swap">
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
    <script>window.Quill = Quill;</script>
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
        .ql-editor img {
            display: inline-block;
            cursor: pointer;
        }
        /* Ensure resize handles are on top */
        .image-resizer {
            z-index: 1000 !important;
        }
    </style>

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
            font-family: 'Inter', 'Noto Serif Gujarati', sans-serif;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Explicit Gujarati text styling for all Gujarati modules, tabs, and editors */
        .gujarati-text,
        [class*="-guj"],
        [name*="_guj"],
        [id*="-guj"],
        [id*="guj-content"] {
            font-family: 'Noto Serif Gujarati', 'Inter', serif !important;
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

        /* ========================================== */
        /* MODERN MULTI-COLORED SIDEBAR ICONS SYSTEM  */
        /* ========================================== */
        .sidebar-menu .nav-icon {
            font-size: 1.1rem;
            margin-right: 0.5rem;
            width: 1.6rem;
            text-align: center;
            display: inline-block;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.08));
        }

        /* Hover effect on menu item scales the icon */
        .sidebar-menu .nav-link:hover .nav-icon {
            transform: scale(1.25) rotate(3deg);
        }

        /* Assign premium tailormade colors to specific icon groups */
        .sidebar-menu .nav-icon.fa-home { color: #3b82f6 !important; } /* Dashboard - Vibrant blue */
        .sidebar-menu .nav-icon.fa-users-cog { color: #8b5cf6 !important; } /* Users settings - Violet */
        .sidebar-menu .nav-icon.fa-database { color: #f59e0b !important; } /* Master data - Warm amber */
        .sidebar-menu .nav-icon.fa-user-graduate { color: #10b981 !important; } /* Students - Emerald */
        .sidebar-menu .nav-icon.fa-users { color: #14b8a6 !important; } /* Teal users */
        .sidebar-menu .nav-icon.fa-user-plus { color: #06b6d4 !important; } /* Admission - Cyan */
        .sidebar-menu .nav-icon.fa-user-check { color: #22c55e !important; } /* Active students */
        .sidebar-menu .nav-icon.fa-sitemap { color: #0ea5e9 !important; } /* Division - Sky blue */
        .sidebar-menu .nav-icon.fa-laptop-code { color: #6366f1 !important; } /* Online exam - Indigo */
        .sidebar-menu .nav-icon.fa-clipboard-list { color: #a855f7 !important; } /* Test marks - Purple */
        .sidebar-menu .nav-icon.fa-whatsapp { color: #25d366 !important; } /* WhatsApp green */
        .sidebar-menu .nav-icon.fa-money-bill-wave { color: #f59e0b !important; } /* Golden Amber */
        .sidebar-menu .nav-icon.fa-rupee-sign { color: #eab308 !important; } /* Gold */
        .sidebar-menu .nav-icon.fa-wallet { color: #10b981 !important; } /* Wallet emerald */
        .sidebar-menu .nav-icon.fa-graduation-cap { color: #ec4899 !important; } /* Scholarship pink */
        .sidebar-menu .nav-icon.fa-chart-line { color: #f43f5e !important; } /* Finance reports - Coral */
        .sidebar-menu .nav-icon.fa-chart-bar { color: #f97316 !important; } /* Analytics orange */
        .sidebar-menu .nav-icon.fa-file-alt { color: #0ea5e9 !important; } /* Report details */
        .sidebar-menu .nav-icon.fa-envelope { color: #ef4444 !important; } /* Email - Crimson red */
        .sidebar-menu .nav-icon.fa-cog { color: #64748b !important; } /* Settings slate */
        .sidebar-menu .nav-icon.fa-power-off { color: #f43f5e !important; } /* Logout red */
        .sidebar-menu .nav-icon.fa-plus-circle, .sidebar-menu .nav-icon.fa-plus { color: #06b6d4 !important; }
        .sidebar-menu .nav-icon.fa-university { color: #4f46e5 !important; }
        .sidebar-menu .nav-icon.fa-book-open, .sidebar-menu .nav-icon.fa-book { color: #2563eb !important; }
        .sidebar-menu .nav-icon.fa-bell { color: #eab308 !important; }
        .sidebar-menu .nav-icon.fa-phone, .sidebar-menu .nav-icon.fa-phone-volume { color: #22c55e !important; }
        .sidebar-menu .nav-icon.fa-certificate { color: #d97706 !important; }
        .sidebar-menu .nav-icon.fa-file-import { color: #2563eb !important; }

        /* ========================================== */
        /* ELIMINATE SIDEBAR HOVER BULLETS / DOTS     */
        /* ========================================== */
        .sidebar-menu, 
        .sidebar-menu ul, 
        .sidebar-menu li, 
        .sidebar-menu .nav-item, 
        .sidebar-menu .nav-link,
        .nav-sidebar,
        .nav-sidebar ul,
        .nav-sidebar li,
        .nav-sidebar .nav-item,
        .nav-sidebar .nav-link {
            list-style: none !important;
            list-style-type: none !important;
        }

        .sidebar-menu li::before,
        .sidebar-menu .nav-item::before,
        .sidebar-menu .nav-link::before,
        .nav-sidebar li::before,
        .nav-sidebar .nav-item::before,
        .nav-sidebar .nav-link::before {
            display: none !important;
            content: none !important;
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