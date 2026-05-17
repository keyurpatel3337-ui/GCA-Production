<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Assign Fees Controller
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

require_once OPERATION_FILE;
// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));
if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Super Admin
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Initialize results array
$results = [];
$has_processing_results = false;

/**
 * Process fee assignment for a single student
 */
if (!function_exists('assignFeeToStudent')) {
    function assignFeeToStudent($student_id, $fee_config_id, $dbOps, $conn)
    {
        global $results;

        try {
            // Get fee configuration
            $sql_fee = "SELECT fc.*, 
                (fc.total_fees - fc.token_fee) as payable_fees
                FROM tbl_fee_config fc 
                WHERE fc.id = ? AND fc.is_active = 1";
            $fee_result = $dbOps->customSelect($sql_fee, [$fee_config_id]);
            $fee_config = $fee_result[0] ?? null;

            if (!$fee_config) {
                $results[] = [
                    'type' => 'error',
                    'student_id' => $student_id,
                    'message' => 'Fee configuration not found or inactive'
                ];
                return false;
            }

            // Get student data including scholarship info and school_id for validation
            $sql_student = "SELECT id, surname, student_name, fathers_name, gender, mob, school_id, course_id, academic_year_id,
                scholarship_amount, additional_scholarship_amount, hostel_required, transport_required,
                (SELECT COUNT(*) FROM tbl_student_fee_allocation WHERE student_id = ? AND term_id = 1) as already_assigned
                FROM tbl_gm_std_registration WHERE id = ? AND status = 1";
            $student_result = $dbOps->customSelect($sql_student, [$student_id, $student_id]);
            $student = $student_result[0] ?? null;

            if (!$student) {
                $results[] = [
                    'type' => 'error',
                    'student_id' => $student_id,
                    'message' => 'Student not found'
                ];
                return false;
            }

            $student_name = trim($student['surname'] . ' ' . $student['student_name']);

            // Validate that fee config school matches student's school
            if (intval($fee_config['school_id']) !== intval($student['school_id'])) {
                $results[] = [
                    'type' => 'error',
                    'student_id' => $student_id,
                    'student_name' => $student_name,
                    'message' => 'Fee config school mismatch: student school_id=' . $student['school_id'] . ' but config school_id=' . $fee_config['school_id'] . '. Please select correct fee configuration.'
                ];
                return false;
            }

            // Check if already assigned for this term (regardless of fee_config_id to prevent duplicates)
            if ($student['already_assigned'] > 0) {
                $results[] = [
                    'type' => 'warning',
                    'student_id' => $student_id,
                    'student_name' => $student_name,
                    'message' => 'Fees already assigned for this student (Semester 1)'
                ];
                return false;
            }

            // Begin transaction
            $conn->beginTransaction();

            // Create fee allocation record
            // We store Gross Total Fees in allocated_amount and scholarships in dedicated columns
            $stmt = $conn->prepare("INSERT INTO tbl_student_fee_allocation 
                (student_id, fee_config_id, allocated_amount, paid_amount, 
                scholarship_amount, additional_scholarship, pending_amount, 
                academic_year, allocated_by, created_by, allocated_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

            require_once HELPERS_PATH . 'fee_helper.php';
            require_once HELPERS_PATH . 'fee_allocation_helper.php';
            
            $hostel_cfg = fetchHostelSettings($conn, $student['academic_year_id']);
            $transport_cfg = fetchTransportSettings($conn, $student['academic_year_id'], $student['course_id']);
            
            // Build live allocation payload to get TRUE gross including hostel/transport
            $allocations_breakdown = buildFeeAllocationPayload($student, $fee_config, $hostel_cfg, $transport_cfg);
            
            $gross_fees = 0;
            foreach($allocations_breakdown as $a) {
                $gross_fees += $a['gross_amount'];
            }

            $token_fee = floatval($fee_config['token_fee']);
            $scholarship = floatval($student['scholarship_amount'] ?? 0);
            $additional = floatval($student['additional_scholarship_amount'] ?? 0);

            // Calculate pending: Gross - Token - Scholarship
            $pending_amount = max(0, $gross_fees - $token_fee - $scholarship - $additional);

            $stmt->execute([
                $student_id,
                $fee_config_id,
                $gross_fees, // Allocated = Gross
                $token_fee,  // Paid = Token Fee (initially)
                $scholarship,
                $additional,
                $pending_amount,
                $fee_config['academic_year'],
                $_SESSION['user_id'] ?? 0,
                $_SESSION['user_id'] ?? 0
            ]);

            // Calculate amount per installment (on the pending amount)
            $amount_per_installment = $pending_amount / ($fee_config['number_of_installments'] ?: 1);

            $allocation_id = $conn->lastInsertId();

            // Create installment records
            for ($i = 1; $i <= $fee_config['number_of_installments']; $i++) {
                $stmt = $conn->prepare("INSERT INTO tbl_fee_installments 
                    (allocation_id, student_id, fee_config_id, installment_number, 
                    due_amount, paid_amount, payment_status, created_by) 
                    VALUES (?, ?, ?, ?, ?, 0.00, 'pending', ?)");

                $stmt->execute([
                    $allocation_id,
                    $student_id,
                    $fee_config_id,
                    $i,
                    $amount_per_installment,
                    $_SESSION['user_id'] ?? 0
                ]);
            }

            // Commit transaction
            $conn->commit();

            $results[] = [
                'type' => 'success',
                'student_id' => $student_id,
                'student_name' => $student_name,
                'course' => $fee_config['course_name'],
                'academic_year' => $fee_config['academic_year'],
                'total_fees' => $fee_config['total_fees'],
                'token_fee' => $fee_config['token_fee'],
                'payable_fees' => $fee_config['payable_fees'],
                'installments' => $fee_config['number_of_installments'],
                'per_installment' => $amount_per_installment,
                'message' => 'Fees assigned successfully with ' . $fee_config['number_of_installments'] . ' installments'
            ];

            return true;
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            if (function_exists('logDatabaseError')) {
                logDatabaseError($e, "Assign Fee to Student");
            }

            $results[] = [
                'type' => 'error',
                'student_id' => $student_id,
                'message' => 'Error: ' . $e->getMessage()
            ];

            return false;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_individual']) || (isset($_GET['action']) && $_GET['action'] === 'assign_individual')) {
        // Individual assignment
        $student_id = $_POST['student_id'] ?? $_GET['student_id'] ?? null;
        $fee_config_id = $_POST['fee_config_id'] ?? $_GET['fee_config_id'] ?? null;

        if ($student_id && $fee_config_id) {
            assignFeeToStudent($student_id, $fee_config_id, $dbOps, $conn);
            $has_processing_results = true;
        }

        if ($is_api_call) {
            sendSuccessResponse(['results' => $results, 'has_processing_results' => $has_processing_results]);
        }
    } elseif (isset($_POST['assign_bulk']) || (isset($_GET['action']) && $_GET['action'] === 'assign_bulk')) {
        // Bulk assignment
        $fee_config_id = $_POST['fee_config_id'] ?? $_GET['fee_config_id'] ?? null;
        $student_ids = $_POST['student_ids'] ?? (isset($_GET['student_ids']) ? explode(',', $_GET['student_ids']) : []);

        if (empty($student_ids)) {
            if ($is_api_call) {
                sendErrorResponse('No students selected for bulk assignment', 400);
            }
            $_SESSION['message_type'] = 'warning';
            $_SESSION['message'] = 'No students selected for bulk assignment';
        } else {
            $success_count = 0;
            $error_count = 0;

            foreach ($student_ids as $student_id) {
                if (assignFeeToStudent($student_id, $fee_config_id, $dbOps, $conn)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }

            if (!$is_api_call) {
                $_SESSION['message_type'] = $success_count > 0 ? 'success' : 'warning';
                $_SESSION['message'] = "Bulk assignment completed: {$success_count} successful, {$error_count} failed/skipped";
            }
            $has_processing_results = true;
        }

        if ($is_api_call) {
            sendSuccessResponse(['results' => $results, 'has_processing_results' => $has_processing_results, 'success_count' => $success_count ?? 0, 'error_count' => $error_count ?? 0]);
        }
    }
}

// Fetch fee configurations for dropdown with additional details
$sql_configs = "SELECT 
    fc.id, 
    fc.academic_year, 
    fc.term,
    fc.course_name, 
    fc.token_fee, 
    fc.total_fees, 
    (fc.total_fees - fc.token_fee) as payable_fees, 
    fc.number_of_installments,
    s.school_code as school_short_name,
    g.group_name
    FROM tbl_fee_config fc
    LEFT JOIN tbl_schools s ON fc.school_id = s.id
    LEFT JOIN tbl_group g ON fc.group_id = g.id
    WHERE fc.is_active = 1 
    ORDER BY fc.academic_year DESC, fc.course_name desc";
$fee_configs = $dbOps->customSelect($sql_configs);

// Fetch only enrolled students with enrollment details
$sql_students = "SELECT 
    e.enrollment_id,
    r.id,
    r.surname,
    r.student_name,
    r.fathers_name
    FROM tbl_enrolled_students e
    INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
    WHERE r.status = 1
    ORDER BY r.id ASC";
$students = $dbOps->customSelect($sql_students);

if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'GET') {
    sendSuccessResponse([
        'fee_configs' => $fee_configs,
        'students' => $students
    ]);
}


