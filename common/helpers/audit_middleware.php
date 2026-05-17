<?php
/**
 * Audit Middleware - Automatically Track All Page Views and Actions
 * 
 * This middleware logs every page access and provides helper functions
 * for tracking user actions throughout the session.
 * 
 * Created: January 20, 2026
 */

// Don't log if this is an AJAX request (to avoid excessive logging)
// You can customize this logic based on your needs
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Don't log API endpoints or assets
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$is_asset = preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|woff|woff2|ttf|svg)$/i', $request_uri);
$is_api = strpos($request_uri, '/api/') !== false;

// Only log actual page views (GET requests to PHP files)
$should_log = !$is_ajax &&
    !$is_asset &&
    !$is_api &&
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';

if ($should_log && isset($conn) && $conn instanceof PDO) {
    try {
        // Start execution time tracking
        if (!defined('PAGE_START_TIME')) {
            define('PAGE_START_TIME', microtime(true));
        }

        // Log the page view
        require_once __DIR__ . '/audit_log_helper.php';

        // Get page title from request URI and filename
        $current_file = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
        $page_title = getPageTitle($request_uri, $current_file);

        logPageView($conn, $request_uri, $page_title);

        // Register shutdown function to log page exit (optional)
        register_shutdown_function(function () use ($conn, $request_uri) {
            if (defined('PAGE_START_TIME')) {
                $execution_time = round((microtime(true) - PAGE_START_TIME) * 1000); // Convert to ms

                // You can log the page load time if needed
                // logAuditAction($conn, 'page_loaded', 'system', 'Page load complete', [
                //     'execution_time_ms' => $execution_time,
                //     'status' => 'info'
                // ]);
            }
        });

    } catch (Exception $e) {
        // Silently fail - don't break the page if audit logging fails
        error_log("Audit middleware error: " . $e->getMessage());
    }
}

/**
 * Get a friendly page title from URL and filename
 */
function getPageTitle($request_uri, $filename)
{
    // Check for route parameter in URL (for index.php?route=module/action pattern)
    if (strpos($request_uri, '?route=') !== false || strpos($request_uri, '&route=') !== false) {
        parse_str(parse_url($request_uri, PHP_URL_QUERY), $params);
        if (isset($params['route'])) {
            $route = $params['route'];

            // Dashboard routes
            if (strpos($route, 'dashboard/admin') !== false)
                return 'Admin Dashboard';
            if (strpos($route, 'dashboard/principle') !== false)
                return 'Principal Dashboard';
            if (strpos($route, 'dashboard/counsellor') !== false)
                return 'Counsellor Dashboard';
            if (strpos($route, 'dashboard/student') !== false)
                return 'Student Dashboard';
            if (strpos($route, 'dashboard/accountant') !== false)
                return 'Accountant Dashboard';
            if (strpos($route, 'dashboard') !== false)
                return 'Dashboard';

            // Payment routes
            if (strpos($route, 'payments/token-fee-collection') !== false)
                return 'Token Fee Collection';
            if (strpos($route, 'payments/add') !== false || strpos($route, 'payments/save') !== false)
                return 'Add Payment';
            if (strpos($route, 'payments/list') !== false)
                return 'Payment List';
            if (strpos($route, 'payments/view') !== false)
                return 'View Payment';
            if (strpos($route, 'payments/receipt') !== false)
                return 'Receipt Management';
            if (strpos($route, 'payments') !== false)
                return 'Payments Module';

            // Student routes
            if (strpos($route, 'students/admission-confirm') !== false)
                return 'Confirm Admission';
            if (strpos($route, 'students/list') !== false)
                return 'Student List';
            if (strpos($route, 'students/add') !== false)
                return 'Add Student';
            if (strpos($route, 'students/edit') !== false)
                return 'Edit Student';
            if (strpos($route, 'students/view') !== false)
                return 'View Student';
            if (strpos($route, 'students/registration') !== false)
                return 'Student Registration';
            if (strpos($route, 'students') !== false)
                return 'Students Module';

            // Report routes
            if (strpos($route, 'reports/collection') !== false)
                return 'Collection Report';
            if (strpos($route, 'reports/payment') !== false)
                return 'Payment Report';
            if (strpos($route, 'reports/student') !== false)
                return 'Student Report';
            if (strpos($route, 'reports/daily') !== false)
                return 'Daily Report';
            if (strpos($route, 'reports') !== false)
                return 'Reports Module';

            // Settings routes
            if (strpos($route, 'settings/system') !== false)
                return 'System Settings';
            if (strpos($route, 'settings/users') !== false)
                return 'User Management';
            if (strpos($route, 'settings/fee-config') !== false)
                return 'Fee Configuration';
            if (strpos($route, 'settings') !== false)
                return 'Settings Module';

            // Scholarship routes
            if (strpos($route, 'scholarships/rules') !== false)
                return 'Scholarship Rules';
            if (strpos($route, 'scholarships/applications') !== false)
                return 'Scholarship Applications';
            if (strpos($route, 'scholarships') !== false)
                return 'Scholarships Module';

            // Admission/Counselling routes
            if (strpos($route, 'admission/form') !== false)
                return 'Admission Form';
            if (strpos($route, 'admission/list') !== false)
                return 'Admission List';
            if (strpos($route, 'counselling/list') !== false)
                return 'Counselling List';
            if (strpos($route, 'counselling') !== false || strpos($route, 'admission') !== false)
                return 'Admission Module';

            // Convert route to readable title
            return ucwords(str_replace(['/', '-', '_'], ' ', $route));
        }
    }

    // Check URL path patterns (for non-route URLs)
    if (strpos($request_uri, '/dashboard/') !== false) {
        if (strpos($request_uri, 'admin_dashboard') !== false)
            return 'Admin Dashboard';
        if (strpos($request_uri, 'principle_dashboard') !== false)
            return 'Principal Dashboard';
        if (strpos($request_uri, 'counsellor_dashboard') !== false)
            return 'Counsellor Dashboard';
        if (strpos($request_uri, 'student_dashboard') !== false)
            return 'Student Dashboard';
        if (strpos($request_uri, 'accountant_dashboard') !== false)
            return 'Accountant Dashboard';
        return 'Dashboard';
    }

    // Payment related pages
    if (strpos($request_uri, '/payments/') !== false) {
        if (strpos($request_uri, 'payment-save') !== false)
            return 'Add Payment';
        if (strpos($request_uri, 'receipt-save') !== false)
            return 'Add Receipt';
        if (strpos($request_uri, 'payment-list') !== false)
            return 'Payment List';
        if (strpos($request_uri, 'receipt-list') !== false)
            return 'Receipt List';
        if (strpos($request_uri, 'payment-view') !== false)
            return 'View Payment';
        return 'Payments Module';
    }

    // Student related pages
    if (strpos($request_uri, '/students/') !== false || strpos($request_uri, '/student/') !== false) {
        if (strpos($request_uri, 'student-list') !== false)
            return 'Student List';
        if (strpos($request_uri, 'add-student') !== false)
            return 'Add Student';
        if (strpos($request_uri, 'edit-student') !== false)
            return 'Edit Student';
        if (strpos($request_uri, 'view-student') !== false)
            return 'View Student';
        if (strpos($request_uri, 'student-registration') !== false)
            return 'Student Registration';
        return 'Students Module';
    }

    // Reports
    if (strpos($request_uri, '/reports/') !== false || strpos($request_uri, '/report/') !== false) {
        if (strpos($request_uri, 'collection-report') !== false)
            return 'Collection Report';
        if (strpos($request_uri, 'payment-report') !== false)
            return 'Payment Report';
        if (strpos($request_uri, 'student-report') !== false)
            return 'Student Report';
        return 'Reports Module';
    }

    // Settings
    if (strpos($request_uri, '/settings/') !== false) {
        if (strpos($request_uri, 'system-settings') !== false)
            return 'System Settings';
        if (strpos($request_uri, 'user-management') !== false)
            return 'User Management';
        if (strpos($request_uri, 'fee-configuration') !== false)
            return 'Fee Configuration';
        return 'Settings Module';
    }

    // Admission/Counselling
    if (strpos($request_uri, '/admission/') !== false || strpos($request_uri, '/counselling/') !== false) {
        if (strpos($request_uri, 'admission-form') !== false)
            return 'Admission Form';
        if (strpos($request_uri, 'counselling-list') !== false)
            return 'Counselling List';
        return 'Admission Module';
    }

    // Check filename patterns
    $filename_titles = [
        'index.php' => 'Home Page',
        'login.php' => 'Login Page',
        'logout.php' => 'Logout',
        'payment-save.php' => 'Add Payment',
        'receipt-save.php' => 'Add Receipt',
        'student-list.php' => 'Student List',
        'payment-list.php' => 'Payment List',
        'report.php' => 'Reports',
        'settings.php' => 'Settings',
        'profile.php' => 'User Profile',
        'dashboard.php' => 'Dashboard',
    ];

    if (isset($filename_titles[$filename])) {
        return $filename_titles[$filename];
    }

    // Fallback: convert filename to readable title
    return ucwords(str_replace(['-', '_', '.php'], ' ', $filename));
}

/**
 * Helper function to track form submissions
 * Call this at the start of POST request handlers
 */
function trackFormSubmission($conn, $form_name, $form_data = [])
{
    require_once __DIR__ . '/audit_log_helper.php';

    // Remove sensitive data
    $safe_data = $form_data;
    unset(
        $safe_data['password'],
        $safe_data['password_confirmation'],
        $safe_data['old_password'],
        $safe_data['new_password']
    );

    return logAuditAction($conn, 'form_submitted', 'navigation', "Form submitted: {$form_name}", [
        'action_data' => [
            'form_name' => $form_name,
            'field_count' => count($form_data),
            'fields' => array_keys($safe_data)
        ],
        'status' => 'info'
    ]);
}

/**
 * Helper function to track downloads
 */
function trackDownload($conn, $file_name, $file_type = null)
{
    require_once __DIR__ . '/audit_log_helper.php';

    return logAuditAction($conn, 'file_downloaded', 'export', "File downloaded: {$file_name}", [
        'action_data' => [
            'file_name' => $file_name,
            'file_type' => $file_type
        ],
        'status' => 'success'
    ]);
}

/**
 * Helper function to track errors
 */
function trackError($conn, $error_message, $error_code = null, $context = [])
{
    require_once __DIR__ . '/audit_log_helper.php';

    return logAuditAction($conn, 'system_error', 'system', "System error occurred", [
        'error_message' => $error_message,
        'error_code' => $error_code,
        'action_data' => $context,
        'status' => 'failed'
    ]);
}
