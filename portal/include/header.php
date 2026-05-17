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
    <!-- GCA Custom Layout CSS (Replaces AdminLTE) -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/gca-layout.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/select2/select2.min.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/flatpickr/flatpickr.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/vendor/sweetalert2/sweetalert2.min.css">
    <!-- Global Theme CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/global-theme.css">
    <!-- Fixed Layout CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/fixed-layout.css">
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modal-fixes.css">
    <!-- Student Search Component CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/student-search.css">
    <!-- Notification Bell CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/notification-bell.css">
    <!-- Portal Custom CSS -->
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/portal-custom.css">

    <!-- Online Exam System (OES) Dependencies -->
    <script src="<?php echo PORTAL_URL; ?>/assets/vendor/tailwind/tailwind.min.js"></script>