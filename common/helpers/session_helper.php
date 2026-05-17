<?php
/**
 * Session Helper
 * 
 * Provides utility functions to restore student/user sessions
 */

require_once __DIR__ . '/../../common/constants.php';

/**
 * Restore a student's session from their ID.
 * Useful for callbacks from payment gateways where session might be lost.
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @return bool True if student found and session restored
 */
function restoreStudentSession($conn, $student_id)
{
    if (empty($student_id))
        return false;

    try {
        // Fetch full student data and token status
        $stmt = $conn->prepare("SELECT 
                                    r.id, r.student_name, r.surname, r.fathers_name, r.mob, r.aadhaar,
                                    CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as token_fees_paid
                                FROM tbl_gm_std_registration r
                                LEFT JOIN tbl_payments p ON r.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                                WHERE r.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            // Ensure session is started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Populate essential session variables
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = trim(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? ''));
            $_SESSION['full_name'] = $_SESSION['student_name']; // For sidebar compatibility
            $_SESSION['student_mobile'] = $student['mob'];
            $_SESSION['student_aadhaar'] = $student['aadhaar'];
            $_SESSION['user_role'] = 'student';
            $_SESSION['is_student_login'] = true;
            $_SESSION['login_time'] = time();

            // Check token payment status
            if ($student['token_fees_paid'] == 1) {
                $_SESSION['token_payment_verified'] = true;
                unset($_SESSION['token_payment_pending']);
            } else {
                $_SESSION['token_payment_pending'] = true;
            }

            return true;
        }
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError("Error restoring student session: " . $e->getMessage());
        } else {
            error_log("Error restoring student session: " . $e->getMessage());
        }
    }

    return false;
}
