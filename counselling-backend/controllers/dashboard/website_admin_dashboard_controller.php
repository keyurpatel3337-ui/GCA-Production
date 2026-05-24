<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Website Admin Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Website Admin or Super Admin
    if (!hasRole(ROLE_WEBSITE_ADMIN) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Website Admin Dashboard";

// Helper function to check if table exists
if (!function_exists('tableExists')) {
    function tableExists($conn, $tableName)
    {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Get statistics with table existence checks
$stats = [];
$recent_items = [];

try {
    // Total hero slides
    if (tableExists($conn, 'tbl_website_hero')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_website_hero", []);
        $stats['total_hero_slides'] = $result['total'];

        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_website_hero WHERE is_active = 1", []);
        $stats['active_hero_slides'] = $result['total'];

        $recent_items['hero'] = $dbOps->customSelect("SELECT * FROM tbl_website_hero ORDER BY created_at DESC LIMIT 5", []);
    } else {
        $stats['total_hero_slides'] = $stats['active_hero_slides'] = 0;
        $recent_items['hero'] = [];
    }

    // Total gallery images
    if (tableExists($conn, 'tbl_website_gallery')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_website_gallery", []);
        $stats['total_gallery'] = $result['total'];

        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_website_gallery WHERE is_active = 1", []);
        $stats['active_gallery'] = $result['total'];

        $recent_items['gallery'] = $dbOps->customSelect("SELECT * FROM tbl_website_gallery ORDER BY created_at DESC LIMIT 6", []);
    } else {
        $stats['total_gallery'] = $stats['active_gallery'] = 0;
        $recent_items['gallery'] = [];
    }

    // Total testimonials
    if (tableExists($conn, 'tbl_website_testimonials')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_website_testimonials", []);
        $stats['total_testimonials'] = $result['total'];

        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_website_testimonials WHERE is_active = 1", []);
        $stats['active_testimonials'] = $result['total'];

        $recent_items['testimonials'] = $dbOps->customSelect("SELECT * FROM tbl_website_testimonials ORDER BY created_at DESC LIMIT 5", []);
    } else {
        $stats['total_testimonials'] = $stats['active_testimonials'] = 0;
        $recent_items['testimonials'] = [];
    }

    // Pages CMS stats
    if (tableExists($conn, 'tbl_pages')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_pages", []);
        $stats['total_pages'] = $result['total'];

        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_pages WHERE is_active = 1", []);
        $stats['active_pages'] = $result['total'];
    } else {
        $stats['total_pages'] = $stats['active_pages'] = 0;
    }

    // Navigation menu stats
    if (tableExists($conn, 'tbl_navigation_menu')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_navigation_menu WHERE menu_type = 'header'", []);
        $stats['total_header_menu'] = $result['total'];

        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_navigation_menu WHERE menu_type = 'footer'", []);
        $stats['total_footer_menu'] = $result['total'];
    } else {
        $stats['total_header_menu'] = $stats['total_footer_menu'] = 0;
    }

    // Site settings count
    if (tableExists($conn, 'tbl_site_settings')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_site_settings", []);
        $stats['total_settings'] = $result['total'];
    } else {
        $stats['total_settings'] = 0;
    }

    // Social links count
    if (tableExists($conn, 'tbl_social_links')) {
        $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_social_links", []);
        $stats['total_social'] = $result['total'];
    } else {
        $stats['total_social'] = 0;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Website Admin Dashboard Stats");
    }
    $stats = [];
    $recent_items = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'stats' => $stats,
        'recent_items' => $recent_items
    ]);
}

// For direct inclusion - set legacy variables
$total_hero_slides = $stats['total_hero_slides'] ?? 0;
$active_hero_slides = $stats['active_hero_slides'] ?? 0;
$total_gallery = $stats['total_gallery'] ?? 0;
$active_gallery = $stats['active_gallery'] ?? 0;
$total_testimonials = $stats['total_testimonials'] ?? 0;
$active_testimonials = $stats['active_testimonials'] ?? 0;
$total_pages = $stats['total_pages'] ?? 0;
$active_pages = $stats['active_pages'] ?? 0;
$total_header_menu = $stats['total_header_menu'] ?? 0;
$total_footer_menu = $stats['total_footer_menu'] ?? 0;
$total_settings = $stats['total_settings'] ?? 0;
$total_social = $stats['total_social'] ?? 0;
$recent_hero = $recent_items['hero'] ?? [];
$recent_gallery = $recent_items['gallery'] ?? [];
$recent_testimonials = $recent_items['testimonials'] ?? [];

$page_title = "Website Admin Dashboard";
$page_breadcrumb = "Dashboard";


