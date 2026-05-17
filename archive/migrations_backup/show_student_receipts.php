<?php
/**
 * Show All Students and Their Receipt Numbers
 */

require_once __DIR__ . '/../../common/db_connect.php';

echo "=== Students and Their Receipt Numbers ===\n\n";

// Check if tbl_payments has the receipts or if they're in tbl_receipts
$stmt = $conn->query("SHOW TABLES LIKE 'tbl_payments'");
$payments_table_exists = $stmt->rowCount() > 0;

$stmt = $conn->query("SHOW TABLES LIKE 'tbl_receipts'");
$receipts_table_exists = $stmt->rowCount() > 0;

if ($payments_table_exists) {
    // Query from tbl_payments
    $query = "SELECT 
                p.receipt_no,
                p.fee_component,
                p.amount,
                p.payment_date,
                p.payment_mode,
                CONCAT(s.surname, ' ', s.student_name) as student_name,
                s.id as student_id,
                s.school_id
              FROM tbl_payments p
              LEFT JOIN tbl_gm_std_registration s ON p.student_id = s.id
              WHERE p.receipt_no IS NOT NULL AND p.receipt_no != ''
              ORDER BY p.payment_date DESC, p.id DESC";

    $stmt = $conn->query($query);

    if ($stmt->rowCount() > 0) {
        echo "Receipts from Payment Records:\n";
        echo str_repeat('=', 140) . "\n";
        printf(
            "%-15s %-30s %-12s %-25s %-15s %-20s %-12s\n",
            'Receipt No',
            'Student Name',
            'Student ID',
            'Fee Type',
            'Amount',
            'Payment Date',
            'School'
        );
        echo str_repeat('=', 140) . "\n";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $school = $row['school_id'] == 1 ? 'GM' : ($row['school_id'] == 2 ? 'SGM' : 'N/A');
            printf(
                "%-15s %-30s %-12s %-25s ₹%-14s %-20s %-12s\n",
                $row['receipt_no'],
                substr($row['student_name'], 0, 28),
                $row['student_id'],
                $row['fee_component'],
                number_format($row['amount'], 2),
                date('d-M-Y H:i', strtotime($row['payment_date'])),
                $school
            );
        }
        echo str_repeat('=', 140) . "\n";
        echo "\nTotal receipts found: " . $stmt->rowCount() . "\n";
    } else {
        echo "No payment receipts found in tbl_payments table.\n";
    }
}

if ($receipts_table_exists) {
    echo "\n\nReceipts from Receipt Table:\n";

    $query = "SELECT 
                r.receipt_no,
                r.amount,
                r.issued_date,
                r.payment_for,
                r.payment_mode,
                CONCAT(s.surname, ' ', s.student_name) as student_name,
                s.id as student_id,
                s.school_id
              FROM tbl_receipts r
              LEFT JOIN tbl_gm_std_registration s ON r.student_id = s.id
              ORDER BY r.issued_date DESC, r.id DESC";

    $stmt = $conn->query($query);

    if ($stmt->rowCount() > 0) {
        echo str_repeat('=', 140) . "\n";
        printf(
            "%-15s %-30s %-12s %-25s %-15s %-20s %-12s\n",
            'Receipt No',
            'Student Name',
            'Student ID',
            'Payment For',
            'Amount',
            'Issue Date',
            'School'
        );
        echo str_repeat('=', 140) . "\n";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $school = $row['school_id'] == 1 ? 'GM' : ($row['school_id'] == 2 ? 'SGM' : 'N/A');
            printf(
                "%-15s %-30s %-12s %-25s ₹%-14s %-20s %-12s\n",
                $row['receipt_no'],
                substr($row['student_name'], 0, 28),
                $row['student_id'],
                $row['payment_for'],
                number_format($row['amount'], 2),
                date('d-M-Y H:i', strtotime($row['issued_date'])),
                $school
            );
        }
        echo str_repeat('=', 140) . "\n";
        echo "\nTotal receipts found: " . $stmt->rowCount() . "\n";
    } else {
        echo "No receipts found in tbl_receipts table.\n";
    }
}

// Group by student
echo "\n\n=== Receipts Grouped by Student ===\n";
echo str_repeat('=', 120) . "\n";

if ($payments_table_exists) {
    $query = "SELECT 
                s.id,
                CONCAT(s.surname, ' ', s.student_name) as student_name,
                s.school_id,
                GROUP_CONCAT(CONCAT(p.fee_component, ': ', p.receipt_no) ORDER BY p.payment_date SEPARATOR ', ') as receipts
              FROM tbl_gm_std_registration s
              LEFT JOIN tbl_payments p ON s.id = p.student_id
              WHERE p.receipt_no IS NOT NULL AND p.receipt_no != ''
              GROUP BY s.id
              ORDER BY s.id";

    $stmt = $conn->query($query);

    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $school = $row['school_id'] == 1 ? 'GM' : ($row['school_id'] == 2 ? 'SGM' : 'N/A');
            echo "\nStudent ID: {$row['id']} | {$row['student_name']} ({$school})\n";
            echo "  Receipts: {$row['receipts']}\n";
        }
    }
}

echo "\n" . str_repeat('=', 120) . "\n";
