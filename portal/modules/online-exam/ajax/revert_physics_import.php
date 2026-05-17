<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Delete newly added chapters and topics for Physics 11 (ID 1) and 12 (ID 17)
    // Note: This assumes we want to clear them all.
    
    echo "Reverting Physics Import...\n";
    
    $conn->exec("DELETE FROM tbl_topics WHERE subject_id IN (1, 17)");
    echo "Deleted topics for Physics 11 & 12\n";
    
    $conn->exec("DELETE FROM chapters WHERE subid IN (1, 17)");
    echo "Deleted chapters for Physics 11 & 12\n";
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Revert complete.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
