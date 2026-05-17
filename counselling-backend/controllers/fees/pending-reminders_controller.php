<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Pending Reminders Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    require_once $base_path . '/../common/helpers/whatsapp_functions.php';
    if (!in_array($_SESSION['role_name'], ['super_admin', 'accountant']) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
        header("Location: " . PORTAL_URL . "/dashboard.php");
        exit;
    }
}

$msg = null;
$msg_type = null;

// Handle Send Action (only for non-API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders']) && !$is_api_call) {
    $selected_ids = $_POST['selected_ids'] ?? [];
    $sent_count = 0;

    // Fetch Reminder Template
    $sql_template = "SELECT id FROM tbl_whatsapp_templates WHERE template_name LIKE '%fee_reminder%' OR template_name LIKE '%pending_fee%' AND is_active = 1 LIMIT 1";
    $template = $dbOps->customSelect($sql_template);
    $template = $template[0] ?? null;

    if (!$template) {
        $msg = "Error: Pending Fee Reminder template not found. Please create one first via WhatsApp Templates.";
        $msg_type = "danger";
    } else {
        foreach ($selected_ids as $pending_id) {
            $sql_pending = "SELECT pp.*, s.student_name, s.surname, s.mob, s.whatsapp_contact 
                                   FROM tbl_pending_payments pp
                                   JOIN tbl_gm_std_registration s ON pp.student_id = s.id
                                   WHERE pp.id = ?";
            $pending_result = $dbOps->customSelect($sql_pending, [$pending_id]);
            $pending = $pending_result[0] ?? null;

            if ($pending && !empty($pending['student_name'])) {
                // Determine recipient email
                $sql_student = "SELECT email FROM tbl_gm_std_registration WHERE id = ?";
                $student_extra = $dbOps->customSelect($sql_student, [$pending['student_id']]);
                $recipient_email = $student_extra[0]['email'] ?? null;

                if ($recipient_email || !empty($pending['mob'])) {
                    require_once $base_path . '/../common/helpers/format_helper.php';
                    require_once $base_path . '/../common/helpers/notification_functions.php';

                    $student_full_name = trim(($pending['student_name'] ?? '') . ' ' . ($pending['surname'] ?? ''));
                    $variables = [
                        'payment_type' => ucwords(str_replace('_', ' ', $pending['payment_type'])),
                        'amount' => formatIndianCurrency($pending['amount']),
                        'due_date' => date('d-M-Y', strtotime($pending['updated_at'])),
                        'student_name' => $student_full_name
                    ];

                    $recipient = [
                        'name' => $student_full_name,
                        'email' => $recipient_email,
                        'mobile' => $pending['whatsapp_contact'] ?: $pending['mob']
                    ];

                    $res = sendNotification($conn, 'fee_reminder', $recipient, $variables, [
                        'student_id' => $pending['student_id'],
                        'reference_type' => 'fee_reminder',
                        'reference_id' => $pending_id
                    ]);

                    if ($res['whatsapp']['success'] || $res['email']['success']) {
                        $sent_count++;
                    }
                }
            }
        }
        $msg = "$sent_count reminders sent successfully.";
        $msg_type = "success";
    }
}

// 0. Handle pagination parameters from REQUEST
$page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
$perPage = isset($_REQUEST['per_page']) ? max(1, (int) $_REQUEST['per_page']) : 25;
$offset = ($page - 1) * $perPage;

// Fetch Pending Payments
$pending_list = [];
$totalRecords = 0;
$totalPages = 1;

try {
    // 1. Get query base
    $where_clauses = ["pp.status = 'pending'"];
    $params = [];

    // Search Filter
    if (!empty($_REQUEST['search'])) {
        $search = '%' . trim($_REQUEST['search']) . '%';
        $where_clauses[] = "(s.student_name LIKE ? OR s.surname LIKE ? OR CONCAT(s.student_name, ' ', s.surname) LIKE ? OR CONCAT(s.surname, ' ', s.student_name) LIKE ? OR s.mob LIKE ? OR s.parent_mob LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    // Course Filter
    if (!empty($_REQUEST['course_id'])) {
        $where_clauses[] = "s.course_id = ?";
        $params[] = (int) $_REQUEST['course_id'];
    }

    $where_sql = implode(' AND ', $where_clauses);

    $baseQuery = "FROM tbl_pending_payments pp
                  JOIN tbl_gm_std_registration s ON pp.student_id = s.id
                  LEFT JOIN tbl_courses c ON s.course_id = c.id
                  WHERE $where_sql";

    // 2. Count Total
    $countQuery = "SELECT COUNT(*) as total $baseQuery";
    $countResult = $dbOps->customSelect($countQuery, $params);
    $totalRecords = isset($countResult[0]['total']) ? $countResult[0]['total'] : 0;
    $totalPages = ceil($totalRecords / $perPage);

    // 3. Get Data
    $query = "SELECT pp.*, pp.updated_at as due_date, s.student_name, s.surname, c.course_name 
              $baseQuery
              ORDER BY s.surname ASC, s.student_name ASC
              LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $pending_list = $dbOps->customSelect($query, $params) ?: [];

    // Enhance list with actual calculated fee summaries and sync with database
    require_once __DIR__ . '/../../../common/helpers/fee_helper.php';
    $dbConnection = $dbOps->getConnection();
    foreach ($pending_list as &$item) {
        $summary = calculateStudentFeeSummary($dbConnection, $item['student_id']);
        if (!empty($summary)) {
            $calculatedAmount = floatval($summary['total_pending']);
            $item['actual_pending'] = $calculatedAmount;
            $item['fee_summary'] = $summary;

            // Sync with database if different (to fix old/incorrect data)
            if (abs(floatval($item['amount']) - $calculatedAmount) > 0.01) {
                $updateStmt = $dbConnection->prepare("UPDATE tbl_pending_payments SET amount = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$calculatedAmount, $item['id']]);
                $item['amount'] = $calculatedAmount; // Update in current list too
            }
        }
    }
    unset($item);

} catch (PDOException $e) {
    $pending_list = [];
    $totalRecords = 0;
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'pending_list' => $pending_list,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages
        ],
        'total' => $totalRecords
    ]);
}

