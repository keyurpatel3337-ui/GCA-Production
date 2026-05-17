<?php
/**
 * Migration: Add created_by columns to all tables where needed
 * Date: 2026-01-18
 * Run this file via browser or CLI to add created_by tracking columns
 */

// Include database connection
require_once 'C:/xampp/htdocs/common/db_connect.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Migration: Adding created_by Columns</h2>";
echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

$success_count = 0;
$error_count = 0;
$skipped_count = 0;

/**
 * Check if column exists in table
 */
function columnExists($conn, $table, $column)
{
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Add column to table if it doesn't exist
 */
function addColumn($conn, $table, $column, $definition, $after = null)
{
    global $success_count, $error_count, $skipped_count;

    if (columnExists($conn, $table, $column)) {
        echo "⚠️ <span style='color: orange;'>SKIP:</span> Column <b>$column</b> already exists in <b>$table</b><br>";
        $skipped_count++;
        return false;
    }

    try {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if ($after) {
            $sql .= " AFTER `$after`";
        }
        $conn->exec($sql);
        echo "✅ <span style='color: green;'>SUCCESS:</span> Added <b>$column</b> to <b>$table</b><br>";
        $success_count++;
        return true;
    } catch (Exception $e) {
        echo "❌ <span style='color: red;'>ERROR:</span> Failed to add <b>$column</b> to <b>$table</b>: " . $e->getMessage() . "<br>";
        $error_count++;
        return false;
    }
}

/**
 * Add index if it doesn't exist
 */
function addIndex($conn, $table, $indexName, $column)
{
    try {
        // Check if index exists
        $stmt = $conn->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        if ($stmt->rowCount() > 0) {
            return false; // Index already exists
        }

        $conn->exec("ALTER TABLE `$table` ADD INDEX `$indexName` (`$column`)");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

try {
    $conn->beginTransaction();

    echo "<h3>Adding created_by Columns to Tables</h3>";

    // 1. tbl_installment_requests
    echo "<h4>1. tbl_installment_requests</h4>";
    addColumn(
        $conn,
        'tbl_installment_requests',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the request (null for student-initiated)'",
        'review_remarks'
    );
    addColumn(
        $conn,
        'tbl_installment_requests',
        'request_type',
        "ENUM('student', 'counsellor', 'direct') NOT NULL DEFAULT 'student' COMMENT 'Origin of request'",
        'created_by'
    );
    if (addIndex($conn, 'tbl_installment_requests', 'idx_created_by', 'created_by')) {
        echo "✅ <span style='color: green;'>INDEX:</span> Added index on created_by<br>";
    }
    if (addIndex($conn, 'tbl_installment_requests', 'idx_request_type', 'request_type')) {
        echo "✅ <span style='color: green;'>INDEX:</span> Added index on request_type<br>";
    }

    // 2. tbl_fee_installments
    echo "<h4>2. tbl_fee_installments</h4>";
    addColumn(
        $conn,
        'tbl_fee_installments',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the installment'",
        'payment_date'
    );
    addIndex($conn, 'tbl_fee_installments', 'idx_fee_installments_created_by', 'created_by');

    // 3. tbl_hostel_installments
    echo "<h4>3. tbl_hostel_installments</h4>";
    addColumn(
        $conn,
        'tbl_hostel_installments',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the installment'",
        'remarks'
    );
    addIndex($conn, 'tbl_hostel_installments', 'idx_hostel_installments_created_by', 'created_by');

    // 4. tbl_group_change_requests
    echo "<h4>4. tbl_group_change_requests</h4>";
    addColumn(
        $conn,
        'tbl_group_change_requests',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the request'",
        'rejection_remarks'
    );
    addIndex($conn, 'tbl_group_change_requests', 'idx_group_change_created_by', 'created_by');

    // 5. tbl_division_change_requests
    echo "<h4>5. tbl_division_change_requests</h4>";
    addColumn(
        $conn,
        'tbl_division_change_requests',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the request'",
        'rejection_remarks'
    );
    addIndex($conn, 'tbl_division_change_requests', 'idx_division_change_created_by', 'created_by');

    // 6. tbl_group_change_fee_adjustments
    echo "<h4>6. tbl_group_change_fee_adjustments</h4>";
    addColumn(
        $conn,
        'tbl_group_change_fee_adjustments',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the adjustment'",
        'refund_transaction_id'
    );
    addIndex($conn, 'tbl_group_change_fee_adjustments', 'idx_group_fee_adj_created_by', 'created_by');

    // 7. tbl_student_fee_allocation
    echo "<h4>7. tbl_student_fee_allocation</h4>";
    addColumn(
        $conn,
        'tbl_student_fee_allocation',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the allocation'",
        'updated_at'
    );
    addIndex($conn, 'tbl_student_fee_allocation', 'idx_fee_allocation_created_by', 'created_by');

    // 8. tbl_pending_payments
    echo "<h4>8. tbl_pending_payments</h4>";
    addColumn(
        $conn,
        'tbl_pending_payments',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who initiated the payment'",
        'status'
    );
    addIndex($conn, 'tbl_pending_payments', 'idx_pending_payments_created_by', 'created_by');

    // 9. tbl_payment_orders
    echo "<h4>9. tbl_payment_orders</h4>";
    addColumn(
        $conn,
        'tbl_payment_orders',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the order'",
        'status'
    );
    addIndex($conn, 'tbl_payment_orders', 'idx_payment_orders_created_by', 'created_by');

    // 10. tbl_notifications
    echo "<h4>10. tbl_notifications</h4>";
    addColumn(
        $conn,
        'tbl_notifications',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the notification'",
        'is_read'
    );
    addIndex($conn, 'tbl_notifications', 'idx_notifications_created_by', 'created_by');

    // 11. tbl_omr_sheets
    echo "<h4>11. tbl_omr_sheets</h4>";
    addColumn(
        $conn,
        'tbl_omr_sheets',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who uploaded the sheet'",
        'status'
    );
    addIndex($conn, 'tbl_omr_sheets', 'idx_omr_sheets_created_by', 'created_by');

    // 12. tbl_paper_sets
    echo "<h4>12. tbl_paper_sets</h4>";
    addColumn(
        $conn,
        'tbl_paper_sets',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the paper set'",
        'is_active'
    );
    addIndex($conn, 'tbl_paper_sets', 'idx_paper_sets_created_by', 'created_by');

    // 13. tbl_question_answers
    echo "<h4>13. tbl_question_answers</h4>";
    addColumn(
        $conn,
        'tbl_question_answers',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the answer'",
        'updated_at'
    );
    addIndex($conn, 'tbl_question_answers', 'idx_question_answers_created_by', 'created_by');

    // 14. tbl_school_change_log
    echo "<h4>14. tbl_school_change_log</h4>";
    addColumn(
        $conn,
        'tbl_school_change_log',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who made the change'",
        'change_date'
    );
    addIndex($conn, 'tbl_school_change_log', 'idx_school_change_created_by', 'created_by');

    // 15. tbl_counselling_sessions
    echo "<h4>15. tbl_counselling_sessions</h4>";
    addColumn(
        $conn,
        'tbl_counselling_sessions',
        'created_by',
        "INT DEFAULT NULL COMMENT 'User ID who created the session record'",
        'updated_at'
    );
    addIndex($conn, 'tbl_counselling_sessions', 'idx_counselling_sessions_created_by', 'created_by');

    $conn->commit();

    echo "<hr>";
    echo "<h3>Migration Summary</h3>";
    echo "<p><span style='color: green;'>✅ Successful operations: <b>$success_count</b></span></p>";
    echo "<p><span style='color: orange;'>⚠️ Skipped (already exists): <b>$skipped_count</b></span></p>";
    echo "<p><span style='color: red;'>❌ Errors: <b>$error_count</b></span></p>";
    echo "<hr>";
    echo "<p><b>Migration completed at: " . date('Y-m-d H:i:s') . "</b></p>";

    if ($error_count === 0) {
        echo "<p style='color: green; font-size: 18px;'><b>✅ All migrations completed successfully!</b></p>";
    } else {
        echo "<p style='color: orange; font-size: 18px;'><b>⚠️ Migration completed with some errors. Please check above.</b></p>";
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<hr>";
    echo "<p style='color: red; font-size: 18px;'><b>❌ Migration FAILED: " . $e->getMessage() . "</b></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><i>Note: Tables that already have created_by columns were not modified:<br>
tbl_payments, tbl_receipts, tbl_refunds, tbl_refund_requests, tbl_fee_config, 
tbl_scholarship_types, tbl_receipt_configuration, tbl_academic_years, tbl_boards, 
tbl_courses, tbl_medium, tbl_schools, tbl_group, tbl_division, tbl_answer_keys, 
tbl_blueprint_questions, tbl_blueprint_topics, tbl_test_marks, tbl_users, 
tbl_email_templates, tbl_whatsapp_templates</i></p>";
