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
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/font-awesome/all.min.css">
    <!-- jQuery (Moved here to support inline scripts in modules) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/bootstrap/bootstrap.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/select2/select2.min.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/flatpickr/flatpickr.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/sweetalert2/sweetalert2.min.css">
    <!-- GCA Portal Layout Bundle (Includes: Global Theme, Layout Engine, Components, and Include styles) -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/portal-layout.css">

    <!-- Online Exam System (OES) Dependencies -->
    <script src="<?php echo PORTAL_URL; ?>/assets/vendor/tailwind/tailwind.min.js"></script>

    <script>
        // System URLs for JavaScript
        const BASE_URL = <?php echo gca_safe_js(BASE_URL); ?>;
        const PORTAL_URL = <?php echo gca_safe_js(PORTAL_URL); ?>;
        const BACKEND_URL = <?php echo gca_safe_js(BACKEND_URL); ?>;
        const API_URL = <?php echo gca_safe_js(API_URL); ?>;
    </script>
</head>

<body class="bg-body-tertiary preload-transitions">
    <div class="app-wrapper">