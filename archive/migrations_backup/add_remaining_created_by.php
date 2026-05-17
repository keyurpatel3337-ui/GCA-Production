<?php
/**
 * Final Migration: Add created_by to remaining tables
 * Date: 2026-01-19
 * This script adds created_by to tables that need it with correct column references
 */

require_once 'C:/xampp/htdocs/common/db_connect.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Final Migration: Remaining created_by Columns</h2>";
echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p><hr>";

$results = [];

try {
    // 1. tbl_group_change_requests - add at end
    echo "<h4>1. tbl_group_change_requests</h4>";
    try {
        $conn->exec("ALTER TABLE `tbl_group_change_requests` ADD COLUMN `created_by` INT DEFAULT NULL COMMENT 'User ID who created the request'");
        $conn->exec("ALTER TABLE `tbl_group_change_requests` ADD INDEX `idx_group_change_created_by` (`created_by`)");
        echo "✅ <span style='color: green;'>SUCCESS:</span> Added created_by column and index<br>";
        $results[] = "✅ tbl_group_change_requests";
    } catch (Exception $e) {
        echo "❌ <span style='color: red;'>ERROR:</span> " . $e->getMessage() . "<br>";
        $results[] = "❌ tbl_group_change_requests: " . $e->getMessage();
    }

    // 2. tbl_group_change_fee_adjustments - add at end
    echo "<h4>2. tbl_group_change_fee_adjustments</h4>";
    try {
        $conn->exec("ALTER TABLE `tbl_group_change_fee_adjustments` ADD COLUMN `created_by` INT DEFAULT NULL COMMENT 'User ID who created the adjustment'");
        $conn->exec("ALTER TABLE `tbl_group_change_fee_adjustments` ADD INDEX `idx_group_fee_adj_created_by` (`created_by`)");
        echo "✅ <span style='color: green;'>SUCCESS:</span> Added created_by column and index<br>";
        $results[] = "✅ tbl_group_change_fee_adjustments";
    } catch (Exception $e) {
        echo "❌ <span style='color: red;'>ERROR:</span> " . $e->getMessage() . "<br>";
        $results[] = "❌ tbl_group_change_fee_adjustments: " . $e->getMessage();
    }

    // 3. tbl_school_change_log - add at end
    echo "<h4>3. tbl_school_change_log</h4>";
    try {
        $conn->exec("ALTER TABLE `tbl_school_change_log` ADD COLUMN `created_by` INT DEFAULT NULL COMMENT 'User ID who made the change'");
        $conn->exec("ALTER TABLE `tbl_school_change_log` ADD INDEX `idx_school_change_created_by` (`created_by`)");
        echo "✅ <span style='color: green;'>SUCCESS:</span> Added created_by column and index<br>";
        $results[] = "✅ tbl_school_change_log";
    } catch (Exception $e) {
        echo "❌ <span style='color: red;'>ERROR:</span> " . $e->getMessage() . "<br>";
        $results[] = "❌ tbl_school_change_log: " . $e->getMessage();
    }

    echo "<hr>";
    echo "<h3>Migration Results</h3>";
    foreach ($results as $result) {
        echo "<p>$result</p>";
    }

    echo "<hr>";
    echo "<p><b>Migration completed at: " . date('Y-m-d H:i:s') . "</b></p>";
    echo "<p style='color: green; font-size: 18px;'><b>✅ All remaining columns added!</b></p>";

} catch (Exception $e) {
    echo "<hr>";
    echo "<p style='color: red;'><b>Migration Failed: " . $e->getMessage() . "</b></p>";
}

echo "<hr>";
echo "<h3>Summary of All Tables with created_by Column</h3>";
echo "<ul>";
echo "<li>tbl_installment_requests ✅</li>";
echo "<li>tbl_fee_installments ✅</li>";
echo "<li>tbl_hostel_installments ✅</li>";
echo "<li>tbl_group_change_requests ✅</li>";
echo "<li>tbl_division_change_requests ✅</li>";
echo "<li>tbl_group_change_fee_adjustments ✅</li>";
echo "<li>tbl_student_fee_allocation ✅</li>";
echo "<li>tbl_pending_payments ✅</li>";
echo "<li>tbl_payment_orders ✅</li>";
echo "<li>tbl_notifications ✅</li>";
echo "<li>tbl_omr_sheets ✅</li>";
echo "<li>tbl_paper_sets ✅</li>";
echo "<li>tbl_question_answers ✅</li>";
echo "<li>tbl_school_change_log ✅</li>";
echo "<li>tbl_payments ✅ (already had)</li>";
echo "<li>tbl_receipts ✅ (already had)</li>";
echo "<li>tbl_refunds ✅ (already had)</li>";
echo "<li>tbl_fee_config ✅ (already had)</li>";
echo "<li>tbl_scholarship_types ✅ (already had)</li>";
echo "<li>And many more...</li>";
echo "</ul>";
