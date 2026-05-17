<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
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

            // Get student registration details
            $reg_sql = "SELECT e.registration_id, r.scholarship_amount, r.additional_scholarship_amount, e.post_admission_discount_amount, r.academic_year_id, ay.year_name as current_year_name
                       FROM tbl_enrolled_students e
                       INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                       LEFT JOIN tbl_academic_years ay ON r.academic_year_id = ay.id
                       WHERE e.enrollment_id = ?";
            $reg_stmt = $conn->prepare($reg_sql);
            $reg_stmt->execute([$enrollment_id]);
            $reg_data = $reg_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reg_data) {
                throw new Exception("Student enrollment not found");
            }

            $registration_id = $reg_data['registration_id'];
            $current_year_name = $reg_data['current_year_name'];
            $current_year_id = $reg_data['academic_year_id'];

            // 1. MANDATORY FEE CHECK: Class 11 fees must be paid
            $fee_check_sql = "SELECT SUM(pending_amount) as total_pending 
                             FROM tbl_student_fee_allocation 
                             WHERE student_id = ? AND academic_year = ?";
            $fee_check_stmt = $conn->prepare($fee_check_sql);
            $fee_check_stmt->execute([$registration_id, $current_year_name]);
            $total_pending = floatval($fee_check_stmt->fetchColumn() ?: 0);

            if ($total_pending > 0) {
                throw new Exception("Student has pending fees of ₹" . formatIndianCurrency($total_pending) . " for Class 11. Promotion denied.");
            }

            // 2. Identify NEXT Academic Year
            $next_year_stmt = $conn->prepare("SELECT id FROM tbl_academic_years WHERE id > ? ORDER BY id DESC LIMIT 1");
            $next_year_stmt->execute([$current_year_id]);
            $next_year_id = $next_year_stmt->fetchColumn();

            if (!$next_year_id) {
                throw new Exception("Next academic year not found in system.");
            }

            // 3. Get Class 12 fee config details
            $config_sql = "SELECT total_fees, academic_year FROM tbl_fee_config WHERE id = ?";
            $config_stmt = $conn->prepare($config_sql);
            $config_stmt->execute([$fee_config_id]);
            $config = $config_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                throw new Exception("Class 12 fee configuration not found.");
            }

            // Check if Class 12 fee already assigned
            $check_assigned = $conn->prepare("SELECT id FROM tbl_student_fee_allocation WHERE student_id = ? AND fee_config_id = ?");
            $check_assigned->execute([$registration_id, $fee_config_id]);
            if ($check_assigned->fetch()) {
                throw new Exception("Class 12 fee already assigned to this student.");
            }

            // 4. Update Student Standard and Academic Year
            $update_reg_sql = "UPDATE tbl_gm_std_registration SET standard = 12, academic_year_id = ? WHERE id = ?";
            $update_reg_stmt = $conn->prepare($update_reg_sql);
            $update_reg_stmt->execute([$next_year_id, $registration_id]);

            // 5. Update Enrollment - Reset Term to Semester 1 (Term ID: 1)
            $update_enroll_sql = "UPDATE tbl_enrolled_students SET current_term_id = 1 WHERE enrollment_id = ?";
            $update_enroll_stmt = $conn->prepare($update_enroll_sql);
            $update_enroll_stmt->execute([$enrollment_id]);

            // 6. Allocate Class 12 Fees
            $allocated_amount = floatval($config['total_fees']);
            $scholarship = floatval($reg_data['scholarship_amount'] ?? 0);
            $additional_scholarship = floatval($reg_data['additional_scholarship_amount'] ?? 0);
            $post_admission_discount = floatval($reg_data['post_admission_discount_amount'] ?? 0);

            $pending_amount = $allocated_amount - $scholarship - $additional_scholarship - $post_admission_discount;

            $insert_fee_sql = "INSERT INTO tbl_student_fee_allocation (
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

            $insert_fee_stmt = $conn->prepare($insert_fee_sql);
            $insert_fee_stmt->execute([
                $registration_id,
                $fee_config_id,
                1, // Reset to Semester 1 for Class 12
                $allocated_amount,
                0,
                $scholarship,
                $additional_scholarship,
                $post_admission_discount,
                $pending_amount,
                $config['academic_year'],
                $created_by,
                $created_by
            ]);

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

