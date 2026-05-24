<?php
/**
 * Update Student 149 Scholarship Amounts to Include GST
 * Convert base amounts to amounts with 18% GST
 */

require_once dirname(__DIR__) . '/../common/constants.php';
require_once DB_CONNECT_FILE;

try {
    // Get current scholarship amounts for student 149
    $stmt = $conn->prepare("SELECT id, student_name, scholarship_amount, additional_scholarship_amount 
                           FROM tbl_gm_std_registration WHERE id = 149");
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "Student 149 not found!\n";
        exit(1);
    }

    echo "Current values for Student 149 ({$student['student_name']}):\n";
    echo "Scholarship Amount: {$student['scholarship_amount']}\n";
    echo "Additional Scholarship Amount: {$student['additional_scholarship_amount']}\n\n";

    // Calculate amounts with 18% GST
    $scholarship_base = floatval($student['scholarship_amount']);
    $additional_scholarship_base = floatval($student['additional_scholarship_amount']);

    $scholarship_gst = $scholarship_base * 0.18;
    $additional_scholarship_gst = $additional_scholarship_base * 0.18;

    $scholarship_with_gst = $scholarship_base + $scholarship_gst;
    $additional_scholarship_with_gst = $additional_scholarship_base + $additional_scholarship_gst;

    echo "Calculated values with 18% GST:\n";
    echo "Scholarship Amount: {$scholarship_base} + {$scholarship_gst} = {$scholarship_with_gst}\n";
    echo "Additional Scholarship Amount: {$additional_scholarship_base} + {$additional_scholarship_gst} = {$additional_scholarship_with_gst}\n\n";

    // Update the database
    $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                           SET scholarship_amount = ?, 
                               additional_scholarship_amount = ?
                           WHERE id = 149");

    $stmt->execute([
        round($scholarship_with_gst, 2),
        round($additional_scholarship_with_gst, 2)
    ]);

    echo "✓ Updated successfully!\n";

    // Verify the update
    $stmt = $conn->prepare("SELECT scholarship_amount, additional_scholarship_amount 
                           FROM tbl_gm_std_registration WHERE id = 149");
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\nVerification - New values in database:\n";
    echo "Scholarship Amount: {$updated['scholarship_amount']}\n";
    echo "Additional Scholarship Amount: {$updated['additional_scholarship_amount']}\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
