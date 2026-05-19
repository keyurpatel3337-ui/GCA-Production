<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_helper.php';

/**
 * Payment History Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));
require_once BACKEND_GLOBALVARIABLE;

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Auth check
    if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    if ($is_api_call) {
        sendErrorResponse('Student ID is required', 400);
    } else {
        set_flash_message('error', "Student ID is required!");
        header('Location: pending-payments.php');
        exit;
    }
}

try {
    // Get student details with joined info
    $sql_student = "SELECT r.*, 
                           es.enrollment_no, es.roll_no,
                           s.school_name,
                           c.course_name,
                           g.group_name,
                           d.division_name
                    FROM tbl_gm_std_registration r
                    LEFT JOIN tbl_enrolled_students es ON r.id = es.registration_id AND es.is_active = 1
                    LEFT JOIN tbl_schools s ON r.school_id = s.id
                    LEFT JOIN tbl_courses c ON r.course_id = c.id
                    LEFT JOIN tbl_group g ON r.group_id = g.id
                    LEFT JOIN tbl_division d ON (r.group_id IS NOT NULL AND es.division_id = d.id)
                    WHERE r.id = ?";

    $student_res = $dbOps->customSelect($sql_student, [$student_id]);
    $student = !empty($student_res) ? $student_res[0] : null;

    if ($student) {
        $student['full_name'] = trim(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? ''));
    }

    if (!$student) {
        if ($is_api_call) {
            sendErrorResponse('Student not found', 404);
        } else {
            set_flash_message('error', "Student not found!");
            header('Location: pending-payments.php');
            exit;
        }
    }

    // Get all payments for this student (Only regular payments for history view per user request)
    $sql_payments = "SELECT *, (CASE WHEN receipt_no = '0' AND payment_mode != 'deduction' THEN 1 ELSE 0 END) as is_without_gst FROM tbl_payments WHERE student_id = ?
                    ORDER BY payment_date DESC, created_at DESC";
    $payments = $dbOps->customSelect($sql_payments, [$student_id]);
    if ($payments === false)
        $payments = [];

    // Get fee allocation details
    $full_ledger = calculateFullStudentLedger($conn, $student_id);

    if (!$full_ledger) {
        throw new Exception("Could not calculate fee summary");
    }

    $ledger_terms = $full_ledger['ledger'];

    // For backward compatibility and summary stats, we provide the "aggregrate" summary 
    // across all terms, or just the latest? 
    // User wants "har ek course aur har semester wise". 
    // We'll calculate a global summary too.

    $total_allocated = 0;
    $total_paid = 0;
    $total_scholarship = 0;
    $total_payable = 0;
    $total_pending = 0;

    $cats = [
        'Academic' => ['allocated' => 0, 'paid' => 0, 'waived' => 0, 'payable' => 0, 'pending' => 0],
        'Hostel' => ['allocated' => 0, 'paid' => 0, 'waived' => 0, 'payable' => 0, 'pending' => 0],
        'Transport' => ['allocated' => 0, 'paid' => 0, 'waived' => 0, 'payable' => 0, 'pending' => 0],
        'Other' => ['allocated' => 0, 'paid' => 0, 'waived' => 0, 'payable' => 0, 'pending' => 0],
    ];

    foreach ($ledger_terms as $term) {
        $summary = $term['summary'];
        $total_allocated += $summary['total_allocated'];
        $total_paid += $summary['total_paid'];
        $total_scholarship += $summary['total_waiver'];
        $total_pending += $summary['total_pending'];
        $total_payable += ($summary['total_allocated'] - $summary['total_waiver']);

        // Populate categories
        if (isset($summary['allocations'])) {
            foreach ($summary['allocations'] as $alloc) {
                $cat = $alloc['category'] ?? 'Other';
                if (!isset($cats[$cat])) {
                   $cats[$cat] = ['allocated' => 0, 'paid' => 0, 'waived' => 0, 'payable' => 0, 'pending' => 0];
                }
                $cats[$cat]['allocated'] += $alloc['gross_amount'];
                $cats[$cat]['paid'] += $alloc['paid_amount'];
                $cats[$cat]['waived'] += $alloc['waived_amount'];
                $cats[$cat]['payable'] += ($alloc['gross_amount'] - $alloc['waived_amount']);
                $cats[$cat]['pending'] += $alloc['pending_amount'];
            }
        }
    }


    $overpayment = ($total_paid > $total_payable) ? ($total_paid - $total_payable) : 0;

    if ($is_api_call) {
        sendSuccessResponse([
            'student' => $student,
            'payments' => $payments,
            'ledger' => $ledger_terms, // NEW: Full multi-term data
            'summary' => [
                'total_allocated' => $total_allocated,
                'total_scholarship' => $total_scholarship,
                'total_paid' => $total_paid,
                'total_payable' => $total_payable,
                'total_pending' => $total_pending,
                'total_pending_display' => $total_pending,
                'overpayment' => $overpayment,
                'categories' => $cats
            ]
        ]);
    }
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    } else {
        if (function_exists('logDatabaseError')) {
            logDatabaseError($e, "Payment History");
        }
        set_flash_message('error', "Failed to load payment history!");
        header('Location: pending-payments.php');
        exit;
    }
}
