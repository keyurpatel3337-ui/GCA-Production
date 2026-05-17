<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;

header('Content-Type: application/json');

try {
    $assignments = $_POST['assignments'] ?? [];

    if (empty($assignments)) {
        throw new Exception('No assignments provided');
    }

    $conn->beginTransaction();

    $success_count = 0;
    $error_count = 0;
    $errors = [];

    $created_by = $_SESSION['user_id'] ?? 1;
    $assignment_date = date('Y-m-d');

    foreach ($assignments as $assignment) {
        try {
            $enrollment_id = $assignment['enrollment_id'] ?? null;
            $fee_config_id = $assignment['fee_config_id'] ?? null;

            if (empty($enrollment_id) || empty($fee_config_id)) {
                throw new Exception("Invalid enrollment_id or fee_config_id");
            }

            // Get student registration details for scholarship and discount
            $reg_sql = "SELECT e.registration_id, r.scholarship_amount, r.additional_scholarship_amount, e.post_admission_discount_amount 
                       FROM tbl_enrolled_students e
                       INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                       WHERE e.enrollment_id = ?";
            $reg_stmt = $conn->prepare($reg_sql);
            $reg_stmt->execute([$enrollment_id]);
            $reg_data = $reg_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reg_data) {
                throw new Exception("Student enrollment not found");
            }

            $registration_id = $reg_data['registration_id'];

            // Check if already assigned
            $check_sql = "SELECT id FROM tbl_student_fee_allocation 
                         WHERE student_id = ? AND fee_config_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$registration_id, $fee_config_id]);

            if ($check_stmt->fetch()) {
                throw new Exception("Fee already assigned to this student");
            }

            // Get fee config details
            $config_sql = "SELECT total_fees, token_fee, number_of_installments, academic_year 
                          FROM tbl_fee_config WHERE id = ?";
            $config_stmt = $conn->prepare($config_sql);
            $config_stmt->execute([$fee_config_id]);
            $config = $config_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                throw new Exception("Fee configuration not found");
            }

            // Calculate amounts
            $allocated_amount = floatval($config['total_fees']);
            $scholarship = floatval($reg_data['scholarship_amount'] ?? 0);
            $additional_scholarship = floatval($reg_data['additional_scholarship_amount'] ?? 0);
            $post_admission_discount = floatval($reg_data['post_admission_discount_amount'] ?? 0);

            // Pending calculation: Total - Scholarship - Additional Scholarship - Post Admission Discount
            $pending_amount = $allocated_amount - $scholarship - $additional_scholarship - $post_admission_discount;

            // Insert fee allocation
            $insert_sql = "INSERT INTO tbl_student_fee_allocation (
                student_id,
                fee_config_id,
                term_id,
                allocated_amount,
                paid_amount,
                scholarship_amount,
                additional_scholarship,
                post_admission_discount,
                pending_amount,
                academic_year,
                allocated_by,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute([
                $registration_id,
                $fee_config_id,
                2, // term_id for Semester 2
                $allocated_amount,
                0, // paid_amount
                $scholarship,
                $additional_scholarship,
                $post_admission_discount,
                $pending_amount,
                $config['academic_year'],
                $created_by,
                $created_by
            ]);

            // Update student's current_term_id to Semester 2 (ID: 2)
            $update_term_sql = "UPDATE tbl_enrolled_students 
                               SET current_term_id = 2
                               WHERE enrollment_id = ?";
            $update_term_stmt = $conn->prepare($update_term_sql);
            $update_term_stmt->execute([$enrollment_id]);

            $success_count++;

        } catch (Exception $e) {
            $error_count++;
            $errors[] = [
                'enrollment_id' => $enrollment_id ?? 'Unknown',
                'message' => $e->getMessage()
            ];
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
