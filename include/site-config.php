<?php

/**
 * Site Settings & Navigation Helper
 * Provides functions to fetch site configuration from database
 */

require_once dirname(__DIR__) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
global $dbOps;
if (!isset($dbOps)) {
    $dbOps = new DatabaseOperations();
}

/**
 * Get a single site setting value
 * @param string $key The setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null)
{
    global $dbOps;
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $result = $dbOps->selectOne(
            'tbl_site_settings',
            ['setting_value'],
            ['setting_key' => $key]
        );
        $cache[$key] = $result !== false && isset($result['setting_value']) ? $result['setting_value'] : $default;
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
    global $dbOps;

    try {
        $results = $dbOps->select(
            'tbl_site_settings',
            ['setting_key', 'setting_value'],
            ['setting_group' => $group],
            'display_order'
        );

        if ($results === false) {
            return [];
        }

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
 * Get all site settings as associative array
 * @return array All settings
 */
function getAllSettings()
{
    global $dbOps;

    try {
        $results = $dbOps->customSelect(
            "SELECT setting_key, setting_value FROM tbl_site_settings ORDER BY setting_group, display_order"
        );

        if ($results === false) {
            return [];
        }

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
 * @return array Menu items with nested children
 */
function getHeaderMenu()
{
    global $dbOps;

    try {
        // Get all header menu items
        $items = $dbOps->select(
            'tbl_navigation_menu',
            ['*'],
            ['menu_type' => 'header', 'is_active' => 1],
            'display_order'
        );

        if ($items === false) {
            return [];
        }

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
    global $dbOps;

    try {
        $results = $dbOps->select(
            'tbl_navigation_menu',
            ['*'],
            ['menu_type' => 'footer', 'footer_column' => $column, 'is_active' => 1],
            'display_order'
        );
        return $results !== false ? $results : [];
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
    global $dbOps;

    try {
        $results = $dbOps->select(
            'tbl_social_links',
            ['*'],
            ['is_active' => 1],
            'display_order'
        );
        return $results !== false ? $results : [];
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
    global $dbOps;

    try {
        $result = $dbOps->update(
            'tbl_site_settings',
            ['setting_value' => $value],
            ['setting_key' => $key]
        );
        return $result !== false;
    } catch (Exception $e) {
        error_log("Error updating setting '$key': " . $e->getMessage());
        return false;
    }
}
