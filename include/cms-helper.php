<?php

/**
 * CMS Helper Functions
 */

require_once OPERATION_FILE;
$dbOps = new DatabaseOperations();

function get_cms_content($page_slug)
{
    global $dbOps;
    $content = [];

    try {
        $query = "
            SELECT pc.field_key, pc.field_value 
            FROM tbl_page_content pc
            JOIN tbl_page_sections ps ON pc.section_id = ps.id
            JOIN tbl_pages p ON ps.page_id = p.id
            WHERE p.page_slug = ? AND p.is_active = 1 AND ps.is_active = 1
        ";
        $results = $dbOps->customSelect($query, [$page_slug], false) ?: [];
        foreach ($results as $row) {
            $content[$row['field_key']] = $row['field_value'];
        }
    } catch (Exception $e) {
        // Handle error
    }

    return $content;
}

/**
 * Render a CMS field with the appropriate data-cms-key for the editor
 */
function cms_field($key, $default = '', $tag = 'span', $class = '')
{
    global $cms_data;
    $value = isset($cms_data[$key]) ? $cms_data[$key] : $default;

    // In preview mode, add the data attribute
    $attr = isset($_GET['preview']) ? " data-cms-key=\"$key\"" : "";

    return "<$tag class=\"$class\"$attr>$value</$tag>";
}
