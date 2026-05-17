<?php

/**
 * Site Settings & Navigation Helper
 * Provides functions to fetch site configuration from database
 */

require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

/**
 * Get a single site setting value
 * @param string $key The setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null)
{
    global $conn;
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $stmt = $conn->prepare("SELECT setting_value FROM tbl_site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        $cache[$key] = $result !== false ? $result : $default;
        return $cache[$key];
    } catch (Exception $e) {
        error_log("Error fetching setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Get all settings in a group
 * @param string $group The setting group
 * @return array Associative array of key => value
 */
function getSettingsByGroup($group)
{
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM tbl_site_settings WHERE setting_group = ? ORDER BY display_order");
        $stmt->execute([$group]);
        $results = $stmt->fetchAll();

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        error_log("Error fetching settings group '$group': " . $e->getMessage());
        return [];
    }
}

/**
 * Get all site settings as associative array - SQL INJECTION SAFE
 * @return array All settings
 */
function getAllSettings()
{
    global $dbOps;

    try {
        $results = $dbOps->select('tbl_site_settings', ['setting_key', 'setting_value'], [], 'setting_group, display_order');

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        error_log("Error fetching all settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get header navigation menu items
 * @return array Menu items with ne - SQL INJECTION SAFE
 * @return array Menu items with nested children
 */
function getHeaderMenu()
{
    global $dbOps;

    try {
        // Get all header menu items
        $items = $dbOps->select('tbl_navigation_menu', ['*'], ['menu_type' => 'header', 'is_active' => 1], 'display_order');
        // Build tree structure
        $menu = [];
        $children = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === null) {
                $menu[$item['id']] = $item;
                $menu[$item['id']]['children'] = [];
            } else {
                $children[$item['parent_id']][] = $item;
            }
        }

        // Assign children to parents
        foreach ($children as $parentId => $childItems) {
            if (isset($menu[$parentId])) {
                $menu[$parentId]['children'] = $childItems;
            }
        }

        return array_values($menu);
    } catch (Exception $e) {
        error_log("Error fetching header menu: " . $e->getMessage());
        return [];
    }
}

/**
 * Get footer navigation links by column
 * @param int $column Column number (1, 2, or 3)
 * @return array Menu items for that column
 */
function getFooterMenuByColumn($column)
{
    global $conn;

    try {
        require_once OPERATION_FILE;
        $op = new Operation();
        return $op->readAll(
            'tbl_navigation_menu',
            ['menu_type' => 'footer', 'footer_column' => $column, 'is_active' => 1],
            'display_order ASC'
        );
    } catch (Exception $e) {
        error_log("Error fetching footer menu column $column: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all social links
 * @return array Social link items
 */
function getSocialLinks()
{
    global $conn;

    try {
        require_once OPERATION_FILE;
        $op = new Operation();
        return $op->readAll('tbl_social_links', ['is_active' => 1], 'display_order ASC');
    } catch (Exception $e) {
        error_log("Error fetching social links: " . $e->getMessage());
        return [];
    }
}

/**
 * Update a site setting
 * @param string $key Setting key
 * @param mixed $value New value
 * @return bool Success
 */
function updateSetting($key, $value)
{
    global $conn;

    try {
        require_once OPERATION_FILE;
        $op = new Operation();
        return $op->update('tbl_site_settings', ['setting_value' => $value], ['setting_key' => $key]);
    } catch (Exception $e) {
        error_log("Error updating setting '$key': " . $e->getMessage());
        return false;
    }
}
