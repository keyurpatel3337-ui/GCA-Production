<?php
/**
 * Audit Log Helper Functions
 * Provides comprehensive logging for ALL user actions from login to logout
 * 
 * Created: January 20, 2026
 * Updated: January 20, 2026 - Comprehensive tracking implementation
 */

/**
 * Core function to log an audit entry with full context
 * 
 * @param PDO $conn Database connection
 * @param string $action_type Type of action (login_success, page_viewed, receipt_generated, etc.)
 * @param string $action_category Category (authentication, session, navigation, payment, receipt, etc.)
 * @param string $description Human-readable description
 * @param array $options Additional options:
 *   - session_id: PHP session ID
 *   - entity_type: Type of entity (student, payment, user, etc.)
 *   - entity_id: ID of entity being acted upon
 *   - student_id: Student ID
 *   - payment_id: Payment ID
 *   - receipt_no: Receipt number
 *   - transaction_id: Transaction ID
 *   - enrollment_id: Enrollment ID
 *   - user_id: User being acted upon
 *   - action_data: Array of additional data (will be JSON encoded)
 *   - old_value: State before change
 *   - new_value: State after change
 *   - changes_summary: Summary of changes
 *   - request_method: HTTP method
 *   - request_url: URL accessed
 *   - request_params: Query/form parameters
 *   - referrer_url: Previous page URL
 *   - performed_by: User ID who performed the action
 *   - user_role: User role
 *   - user_name: Username
 *   - status: success, failed, warning, info, pending (default: success)
 *   - http_status_code: HTTP response code
 *   - error_message: Error message if failed
 *   - error_code: Error code
 *   - execution_time_ms: Execution time in milliseconds
 * @return bool Success status
 */
function logAuditAction($conn, $action_type, $action_category, $description, $options = [])
{
    try {
        // Get session information
        $session_id = $options['session_id'] ?? (session_id() ?: null);

        // Get user information
        $performed_by = $options['performed_by'] ?? ($_SESSION['user_id'] ?? null);
        $user_role = $options['user_role'] ?? ($_SESSION['user_role'] ?? $_SESSION['role'] ?? null);
        $user_name = $options['user_name'] ?? ($_SESSION['username'] ?? $_SESSION['user_name'] ?? null);

        // Get request information
        $request_method = $options['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? null);
        $request_url = $options['request_url'] ?? ($_SERVER['REQUEST_URI'] ?? null);
        $referrer_url = $options['referrer_url'] ?? ($_SERVER['HTTP_REFERER'] ?? null);

        // Get request parameters (sanitized)
        $request_params = $options['request_params'] ?? null;
        if (!$request_params && !empty($_REQUEST)) {
            $sanitized_params = $_REQUEST;
            // Remove sensitive data
            unset(
                $sanitized_params['password'],
                $sanitized_params['password_confirmation'],
                $sanitized_params['old_password'],
                $sanitized_params['new_password']
            );
            $request_params = json_encode($sanitized_params);
        }

        // Get IP and user agent
        $ip_address = $options['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $user_agent = $options['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

        // Detect device type
        $device_type = $options['device_type'] ?? detectDeviceType($user_agent);

        // Prepare action data
        $action_data = $options['action_data'] ?? [];
        $action_data_json = !empty($action_data) ? json_encode($action_data) : null;

        // Insert audit log
        $stmt = $conn->prepare("
            INSERT INTO tbl_audit_logs 
            (session_id, action_type, action_category, description, 
             entity_type, entity_id, student_id, payment_id, receipt_no, transaction_id, 
             enrollment_id, user_id, action_data, old_value, new_value, changes_summary,
             request_method, request_url, request_params, referrer_url,
             performed_by, user_role, user_name, ip_address, user_agent, device_type,
             status, http_status_code, error_message, error_code, execution_time_ms, created_at)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $session_id,
            $action_type,
            $action_category,
            $description,
            $options['entity_type'] ?? null,
            $options['entity_id'] ?? null,
            $options['student_id'] ?? null,
            $options['payment_id'] ?? null,
            $options['receipt_no'] ?? null,
            $options['transaction_id'] ?? null,
            $options['enrollment_id'] ?? null,
            $options['user_id'] ?? null,
            $action_data_json,
            $options['old_value'] ?? null,
            $options['new_value'] ?? null,
            $options['changes_summary'] ?? null,
            $request_method,
            $request_url,
            $request_params,
            $referrer_url,
            $performed_by,
            $user_role,
            $user_name,
            $ip_address,
            $user_agent,
            $device_type,
            $options['status'] ?? 'success',
            $options['http_status_code'] ?? null,
            $options['error_message'] ?? null,
            $options['error_code'] ?? null,
            $options['execution_time_ms'] ?? null
        ]);

        return true;

    } catch (PDOException $e) {
        // Log to error log as fallback
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Detect device type from user agent
 */
function detectDeviceType($user_agent)
{
    if (empty($user_agent))
        return 'unknown';

    // Check for API calls
    if (stripos($user_agent, 'curl') !== false || stripos($user_agent, 'postman') !== false) {
        return 'api';
    }

    // Check for mobile
    if (preg_match('/(android|iphone|ipad|mobile)/i', $user_agent)) {
        if (stripos($user_agent, 'ipad') !== false || stripos($user_agent, 'tablet') !== false) {
            return 'tablet';
        }
        return 'mobile';
    }

    return 'desktop';
}

// ============================================================================
// AUTHENTICATION & SESSION EVENTS
// ============================================================================

/**
 * Log login attempt
 */
function logLoginAttempt($conn, $username, $status = 'failed', $error = null)
{
    $description = $status === 'success'
        ? "User '{$username}' logged in successfully"
        : "Failed login attempt for '{$username}'";

    return logAuditAction($conn, 'login_' . $status, 'authentication', $description, [
        'action_data' => ['username' => $username],
        'status' => $status,
        'error_message' => $error
    ]);
}

/**
 * Log logout
 */
function logLogout($conn, $user_id = null, $username = null)
{
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    $username = $username ?? ($_SESSION['username'] ?? 'Unknown');
    $description = "User '{$username}' logged out";

    return logAuditAction($conn, 'logout', 'session', $description, [
        'performed_by' => $user_id,
        'action_data' => ['username' => $username],
        'status' => 'info'
    ]);
}

/**
 * Log session timeout
 */
function logSessionTimeout($conn, $user_id, $username)
{
    $description = "Session timeout for user '{$username}'";

    return logAuditAction($conn, 'session_timeout', 'session', $description, [
        'performed_by' => $user_id,
        'action_data' => ['username' => $username],
        'status' => 'warning'
    ]);
}

/**
 * Log password change
 */
function logPasswordChange($conn, $user_id, $changed_by = null)
{
    $description = $user_id === $changed_by
        ? "User changed their password"
        : "Password changed by administrator";

    return logAuditAction($conn, 'password_changed', 'authentication', $description, [
        'user_id' => $user_id,
        'performed_by' => $changed_by ?? $user_id,
        'status' => 'success'
    ]);
}

// ============================================================================
// NAVIGATION & PAGE ACCESS EVENTS
// ============================================================================

/**
 * Log page view
 */
function logPageView($conn, $page_url, $page_title = null)
{
    $description = $page_title
        ? "Viewed page: {$page_title}"
        : "Viewed page: {$page_url}";

    return logAuditAction($conn, 'page_viewed', 'navigation', $description, [
        'action_data' => ['page_title' => $page_title],
        'status' => 'info'
    ]);
}

/**
 * Log unauthorized access attempt
 */
function logUnauthorizedAccess($conn, $attempted_url)
{
    $description = "Unauthorized access attempt to: {$attempted_url}";

    return logAuditAction($conn, 'unauthorized_access', 'navigation', $description, [
        'action_data' => ['attempted_url' => $attempted_url],
        'status' => 'warning',
        'http_status_code' => 403
    ]);
}

// ============================================================================
// PAYMENT & RECEIPT EVENTS (Existing functions updated)
// ============================================================================

/**
 * Log receipt generation
 */
function logReceiptGeneration($conn, $student_id, $receipt_no, $fee_type, $amount, $payment_id = null, $status = 'success', $error = null)
{
    $description = $status === 'success'
        ? "Receipt #{$receipt_no} generated for {$fee_type}"
        : "Failed to generate receipt for {$fee_type}";

    return logAuditAction($conn, 'receipt_generated', 'receipt', $description, [
        'entity_type' => 'receipt',
        'entity_id' => $payment_id,
        'student_id' => $student_id,
        'payment_id' => $payment_id,
        'receipt_no' => $receipt_no,
        'action_data' => [
            'fee_type' => $fee_type,
            'amount' => $amount,
            'receipt_no' => $receipt_no
        ],
        'status' => $status,
        'error_message' => $error
    ]);
}

/**
 * Log payment processing
 */
function logPaymentProcessing($conn, $student_id, $payment_id, $amount, $payment_mode, $fee_types, $transaction_id = null, $status = 'success', $error = null)
{
    $fee_types_str = is_array($fee_types) ? implode(', ', $fee_types) : $fee_types;
    $description = $status === 'success'
        ? "Payment of ₹{$amount} processed via {$payment_mode} for {$fee_types_str}"
        : "Payment processing failed for {$fee_types_str}";

    return logAuditAction($conn, 'payment_processed', 'payment', $description, [
        'entity_type' => 'payment',
        'entity_id' => $payment_id,
        'student_id' => $student_id,
        'payment_id' => $payment_id,
        'transaction_id' => $transaction_id,
        'action_data' => [
            'amount' => $amount,
            'payment_mode' => $payment_mode,
            'fee_types' => $fee_types,
            'transaction_id' => $transaction_id
        ],
        'status' => $status,
        'error_message' => $error
    ]);
}

/**
 * Log sequence update
 */
function logSequenceUpdate($conn, $fee_type, $school_id, $old_sequence, $new_sequence)
{
    $school_info = $school_id ? "school_id={$school_id}" : "all schools";
    $description = "Receipt sequence updated for {$fee_type} ({$school_info}): {$old_sequence} â†’ {$new_sequence}";

    return logAuditAction($conn, 'sequence_updated', 'sequence', $description, [
        'entity_type' => 'sequence',
        'action_data' => [
            'fee_type' => $fee_type,
            'school_id' => $school_id,
            'old_sequence' => $old_sequence,
            'new_sequence' => $new_sequence
        ],
        'old_value' => $old_sequence,
        'new_value' => $new_sequence,
        'status' => 'info'
    ]);
}

/**
 * Log student enrollment
 */
function logStudentEnrollment($conn, $student_id, $enrollment_id, $status = 'success', $error = null)
{
    $description = $status === 'success'
        ? "Student enrolled successfully (Enrollment ID: {$enrollment_id})"
        : "Student enrollment failed";

    return logAuditAction($conn, 'enrollment_completed', 'enrollment', $description, [
        'entity_type' => 'enrollment',
        'entity_id' => $enrollment_id,
        'student_id' => $student_id,
        'enrollment_id' => $enrollment_id,
        'action_data' => [
            'enrollment_id' => $enrollment_id
        ],
        'status' => $status,
        'error_message' => $error
    ]);
}

/**
 * Log notification sent
 */
function logNotificationSent($conn, $student_id, $notification_type, $recipient, $status = 'success', $error = null)
{
    $description = $status === 'success'
        ? "Notification sent: {$notification_type} to {$recipient}"
        : "Failed to send notification: {$notification_type}";

    return logAuditAction($conn, strtolower($notification_type) . '_sent', 'notification', $description, [
        'entity_type' => 'notification',
        'student_id' => $student_id,
        'action_data' => [
            'notification_type' => $notification_type,
            'recipient' => $recipient
        ],
        'status' => $status,
        'error_message' => $error
    ]);
}

/**
 * Log division assignment
 */
function logDivisionAssignment($conn, $student_id, $enrollment_id, $division_id, $roll_number, $status = 'success', $error = null)
{
    $description = $status === 'success'
        ? "Division assigned: Division {$division_id}, Roll No. {$roll_number}"
        : "Division assignment failed";

    return logAuditAction($conn, 'division_assigned', 'enrollment', $description, [
        'entity_type' => 'enrollment',
        'entity_id' => $enrollment_id,
        'student_id' => $student_id,
        'enrollment_id' => $enrollment_id,
        'action_data' => [
            'division_id' => $division_id,
            'roll_number' => $roll_number
        ],
        'status' => $status,
        'error_message' => $error
    ]);
}

// ============================================================================
// STUDENT MANAGEMENT EVENTS
// ============================================================================

/**
 * Log student CRUD operations
 */
function logStudentAction($conn, $action_type, $student_id, $action_data = [], $old_value = null, $new_value = null)
{
    $descriptions = [
        'student_created' => 'New student registered',
        'student_updated' => 'Student information updated',
        'student_deleted' => 'Student record deleted',
        'student_viewed' => 'Student profile viewed'
    ];

    return logAuditAction($conn, $action_type, 'student', $descriptions[$action_type] ?? $action_type, [
        'entity_type' => 'student',
        'entity_id' => $student_id,
        'student_id' => $student_id,
        'action_data' => $action_data,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'status' => ($action_type === 'student_deleted') ? 'warning' : 'success'
    ]);
}

/**
 * Log search operations
 */
function logSearch($conn, $search_type, $search_query, $results_count = null)
{
    $description = "Search performed: {$search_type} - '{$search_query}'";
    if ($results_count !== null) {
        $description .= " ({$results_count} results)";
    }

    return logAuditAction($conn, 'search_performed', 'data_access', $description, [
        'action_data' => [
            'search_type' => $search_type,
            'search_query' => $search_query,
            'results_count' => $results_count
        ],
        'status' => 'info'
    ]);
}

/**
 * Log data export
 */
function logExport($conn, $export_type, $format, $record_count = null, $entity_type = null)
{
    $description = "Data exported: {$export_type} ({$format})";
    if ($record_count) {
        $description .= " - {$record_count} records";
    }

    return logAuditAction($conn, 'data_exported_' . strtolower($format), 'export', $description, [
        'entity_type' => $entity_type,
        'action_data' => [
            'export_type' => $export_type,
            'format' => $format,
            'record_count' => $record_count
        ],
        'status' => 'success'
    ]);
}

// ============================================================================
// QUERY & REPORTING FUNCTIONS
// ============================================================================

/**
 * Get audit logs for a student
 */
function getStudentAuditLogs($conn, $student_id, $limit = 50)
{
    $stmt = $conn->prepare("
        SELECT * FROM tbl_audit_logs 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$student_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get audit logs by action type
 */
function getAuditLogsByAction($conn, $action_type, $limit = 100)
{
    $stmt = $conn->prepare("
        SELECT * FROM tbl_audit_logs 
        WHERE action_type = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$action_type, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get audit logs by session
 */
function getAuditLogsBySession($conn, $session_id, $limit = 100)
{
    $stmt = $conn->prepare("
        SELECT * FROM tbl_audit_logs 
        WHERE session_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$session_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get audit logs by date range
 */
function getAuditLogsByDateRange($conn, $start_date, $end_date, $action_category = null)
{
    if ($action_category) {
        $stmt = $conn->prepare("
            SELECT * FROM tbl_audit_logs 
            WHERE DATE(created_at) BETWEEN ? AND ? 
              AND action_category = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$start_date, $end_date, $action_category]);
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM tbl_audit_logs 
            WHERE DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$start_date, $end_date]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get failed actions
 */
function getFailedAuditLogs($conn, $limit = 50)
{
    $stmt = $conn->prepare("
        SELECT * FROM tbl_audit_logs 
        WHERE status = 'failed' 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user activity summary
 */
function getUserActivitySummary($conn, $user_id, $start_date = null, $end_date = null)
{
    $where = "WHERE performed_by = ?";
    $params = [$user_id];

    if ($start_date && $end_date) {
        $where .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    $stmt = $conn->prepare("
        SELECT 
            action_category,
            COUNT(*) as action_count,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
        FROM tbl_audit_logs 
        {$where}
        GROUP BY action_category
        ORDER BY action_count DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



