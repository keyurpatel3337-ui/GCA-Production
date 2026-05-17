<?php
define('APP_INIT', true);
require 'common/db_connect.php';
require_once 'common/helpers/fee_allocation_helper.php';

try {
    syncStudentFeeAllocation($conn, 1472);
    echo "Sync complete for student 1472.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
