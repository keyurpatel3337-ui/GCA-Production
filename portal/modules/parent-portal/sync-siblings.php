<?php
/**
 * Sync Siblings Script
 * Automatically identifies potential siblings and unifies their parent_mob field.
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'parent_functions.php';

// Check access - Only Super Admin and Principal (Bypass for CLI)
if (php_sapi_name() !== 'cli' && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

header('Content-Type: text/plain');
echo "Starting Sibling Mapping Process...\n\n";

try {
    // 1. Find potential sibling groups (Same Surname, Father Name, and Address)
    $sql = "SELECT surname, fathers_name, addr, COUNT(*) as count, 
            GROUP_CONCAT(id) as student_ids,
            GROUP_CONCAT(parent_mob) as parent_mobs,
            GROUP_CONCAT(mob) as student_mobs
            FROM tbl_gm_std_registration 
            WHERE surname IS NOT NULL AND fathers_name IS NOT NULL AND addr IS NOT NULL
            GROUP BY surname, fathers_name, addr 
            HAVING count > 1";
    
    $stmt = $conn->query($sql);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($groups) . " potential sibling groups.\n";

    $total_updated = 0;
    $parents_created = 0;

    foreach ($groups as $group) {
        $student_ids = explode(',', $group['student_ids']);
        $parent_mobs = array_filter(explode(',', $group['parent_mobs']));
        $student_mobs = array_filter(explode(',', $group['student_mobs']));

        // Determine the best parent mobile number to use
        // Priority: 1. Existing parent_mob, 2. First student mob
        $unified_mob = '';
        if (!empty($parent_mobs)) {
            $unified_mob = $parent_mobs[0]; // Use the first available parent_mob
        } elseif (!empty($student_mobs)) {
            $unified_mob = $student_mobs[0]; // Fallback to first student mob
        }

        if (empty($unified_mob)) {
            echo "Skipping group ({$group['surname']} {$group['fathers_name']}): No mobile number found.\n";
            continue;
        }

        echo "Family: {$group['surname']} {$group['fathers_name']} -> Unified Mob: $unified_mob\n";

        // Update all students in the group to use this unified parent_mob
        $updateStmt = $conn->prepare("UPDATE tbl_gm_std_registration SET parent_mob = ? WHERE id = ?");
        foreach ($student_ids as $sid) {
            if ($updateStmt->execute([$unified_mob, $sid])) {
                $total_updated++;
                echo "  - Updated Student ID: $sid\n";
            }
        }

        // Ensure parent account exists for this unified number
        if (createParentAccount($unified_mob, $conn)) {
            $parents_created++;
        }
    }

    echo "\nProcess Completed.\n";
    echo "Total individual records updated: $total_updated\n";
    echo "Parent accounts ensured: $parents_created\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
