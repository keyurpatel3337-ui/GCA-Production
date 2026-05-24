<?php

/**
 * Student Enrollment After Token Payment
 * This script handles the enrollment of students from inquiry/registration (tbl_gm_std_registration)
 * to enrolled students (tbl_enrolled_students) after token fee payment
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $registration_id = $_POST['registration_id'];
        $token_payment_id = null;
        $token_transaction_id = null;
        $token_amount = 0;
        $course_id = $_POST['course_id'];
        $school_id = $_POST['school_id'];
        $board_id = $_POST['board_id'];
        $standard = $_POST['standard'];
        $total_fees = $_POST['total_fees'];
        $total_installments = $_POST['total_installments'];
        $enrolled_by = $_SESSION['user_id'];

        // Call stored procedure to enroll student
        $stmt = $conn->prepare("
            CALL sp_enroll_student_after_token_payment(
                :registration_id,
                :token_payment_id,
                :token_transaction_id,
                :token_amount,
                :course_id,
                :school_id,
                :board_id,
                :standard,
                :total_fees,
                :total_installments,
                :enrolled_by,
                @enrollment_id,
                @enrollment_no,
                @status,
                @message
            )
        ");

        $stmt->bindParam(':registration_id', $registration_id, PDO::PARAM_INT);
        $stmt->bindParam(':token_payment_id', $token_payment_id, PDO::PARAM_STR);
        $stmt->bindParam(':token_transaction_id', $token_transaction_id, PDO::PARAM_STR);
        $stmt->bindParam(':token_amount', $token_amount, PDO::PARAM_STR);
        $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindParam(':board_id', $board_id, PDO::PARAM_INT);
        $stmt->bindParam(':standard', $standard, PDO::PARAM_INT);
        $stmt->bindParam(':total_fees', $total_fees, PDO::PARAM_STR);
        $stmt->bindParam(':total_installments', $total_installments, PDO::PARAM_INT);
        $stmt->bindParam(':enrolled_by', $enrolled_by, PDO::PARAM_INT);

        $stmt->execute();

        // Get output parameters
        $result = $conn->query("SELECT @enrollment_id, @enrollment_no, @status, @message")->fetch(PDO::FETCH_ASSOC);

        if ($result['@status'] === 'SUCCESS') {
            $_SESSION['success_message'] = $result['@message'];
            $_SESSION['enrollment_id'] = $result['@enrollment_id'];
            $_SESSION['enrollment_no'] = $result['@enrollment_no'];

            header('Location: enrollment-success.php?id=' . $result['@enrollment_id']);
        } else {
            $_SESSION['error_message'] = $result['@message'];
            header('Location: enroll-student.php?id=' . $registration_id);
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Enrollment failed: ' . $e->getMessage();
        header('Location: enroll-student.php?id=' . $registration_id);
    }
    exit;
}

// If GET request, redirect to enrollment form
if (isset($_POST['id'])) {
    header('Location: enroll-student.php?id=' . $_POST['id']);
} else {
    header('Location: pending-enrollments.php');
}
exit;
