<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Installment Requests Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;
$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Auth check
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) || (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN))) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Handle review actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $remarks = $_POST['remarks'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;

    // Validate input
    if (!$request_id || !in_array($action, ['approve', 'reject'])) {
        sendErrorResponse('Invalid input: request_id and action (approve/reject) are required', 400);
    }

    try {
        $conn->beginTransaction();

        // Get request details
        $stmt = $conn->prepare("SELECT ir.*, s.id as student_id 
                                FROM tbl_installment_requests ir 
                                JOIN tbl_gm_std_registration s ON ir.student_id = s.id 
                                WHERE ir.id = ? AND ir.status = 'pending'");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception("Request not found or already processed");
        }

        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE tbl_installment_requests 
                                SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_remarks = ? 
                                WHERE id = ?");
        $stmt->execute([$status, $user_id, $remarks, $request_id]);

        // If approved, create installment schedule
        if ($action === 'approve') {
            $total_amount = floatval($request['total_amount']);
            $num_installments = intval($request['requested_installments']);
            $installment_amount = $total_amount / $num_installments;

            error_log("Installment Approval (Backend) - Request ID: $request_id, Student ID: {$request['student_id']}, Total: $total_amount, Num: $num_installments");

            // Get or create fee allocation record
            $stmt = $conn->prepare("SELECT id, fee_config_id FROM tbl_student_fee_allocation WHERE student_id = ?");
            $stmt->execute([$request['student_id']]);
            $allocation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($allocation) {
                $allocation_id = $allocation['id'];
                $fee_config_id = $allocation['fee_config_id'];
            } else {
                // Get active fee config
                $stmt = $conn->prepare("SELECT id FROM tbl_fee_config WHERE is_active = 1 LIMIT 1");
                $stmt->execute();
                $fee_config = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$fee_config) {
                    throw new Exception("No active fee configuration found");
                }

                $fee_config_id = $fee_config['id'];
                $current_year = date('Y');

                // Create allocation
                $stmt = $conn->prepare("INSERT INTO tbl_student_fee_allocation 
                                       (student_id, fee_config_id, allocated_amount, paid_amount, pending_amount, 
                                        status, academic_year, allocated_by, created_by, allocated_at, updated_at) 
                                       VALUES (?, ?, ?, 0, ?, 'pending', ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$request['student_id'], $fee_config_id, $total_amount, $total_amount, $current_year, $user_id, $user_id]);
                $allocation_id = $conn->lastInsertId();
            }

            error_log("Installment Approval (Backend) - Creating installments: Allocation ID: $allocation_id, Fee Config ID: $fee_config_id");

            // Create installments
            $stmt = $conn->prepare("INSERT INTO tbl_fee_installments 
                                   (allocation_id, student_id, fee_config_id, installment_number, 
                                    due_amount, paid_amount, payment_status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, 0, 'pending', ?)");

            for ($i = 1; $i <= $num_installments; $i++) {
                $stmt->execute([$allocation_id, $request['student_id'], $fee_config_id, $i, $installment_amount, $user_id]);
                error_log("Installment Approval (Backend) - Created installment #$i, Amount: $installment_amount");
            }

            error_log("Installment Approval (Backend) - All $num_installments installments created successfully");
        }

        $conn->commit();

        sendSuccessResponse(null, "Request " . ucfirst($action) . "d successfully");
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Installment approval error: " . $e->getMessage());
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Installment approval error: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 400);
    }
}

// Fetch requests (GET)
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_clauses = [];
$params = [];

if ($status_filter !== 'all') {
    $where_clauses[] = "ir.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(ir.request_no LIKE ? OR s.student_name LIKE ? OR s.surname LIKE ? OR s.aadhaar LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    // Fetch installment requests
    $requests = $dbOps->customSelect(
        "SELECT ir.*, 
        CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as student_full_name,
        s.aadhaar, s.mob,
        c.course_name,
        u.name as reviewed_by_name
        FROM tbl_installment_requests ir
        JOIN tbl_gm_std_registration s ON ir.student_id = s.id
        LEFT JOIN tbl_courses c ON s.course_id = c.id
        LEFT JOIN tbl_users u ON ir.reviewed_by = u.id
        $where_sql
        ORDER BY 
            CASE ir.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'rejected' THEN 3 
            END,
            ir.created_at DESC",
        $params
    );

    // Get counts
    $counts_result = $dbOps->customSelect(
        "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM tbl_installment_requests",
        []
    );

    // Get first row and ensure all keys exist with default values
    $counts_raw = !empty($counts_result) ? $counts_result[0] : [];
    $counts = [
        'total' => intval($counts_raw['total'] ?? 0),
        'pending' => intval($counts_raw['pending'] ?? 0),
        'approved' => intval($counts_raw['approved'] ?? 0),
        'rejected' => intval($counts_raw['rejected'] ?? 0)
    ];

    if ($is_api_call) {
        sendSuccessResponse([
            'requests' => $requests,
            'counts' => $counts,
            'applied_filters' => [
                'status' => $status_filter,
                'search' => $search
            ]
        ]);
    }
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}
