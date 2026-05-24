<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Financial Reports Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Accountant
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Get date range and chart view for filters
$chart_view = $_GET['chart_view'] ?? 'daily';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

try {
    // Total Collection
    $sql_collection = "SELECT COUNT(*) as count, SUM(amount) as total 
                        FROM tbl_payments
                        WHERE payment_date BETWEEN ? AND ? 
                        AND status = 'paid'";
    $collection_result = $dbOps->customSelect($sql_collection, [$from_date, $to_date]);
    $collection_stats = $collection_result[0] ?? ['count' => 0, 'total' => 0];

    // Payment Mode Breakdown
    $sql_mode = "SELECT payment_mode, COUNT(*) as count, SUM(amount) as total 
                FROM tbl_payments
                WHERE payment_date BETWEEN ? AND ? 
                AND status = 'paid'
                GROUP BY payment_mode";
    $mode_breakdown = $dbOps->customSelect($sql_mode, [$from_date, $to_date]);

    // Payment Type Breakdown
    $sql_type = "SELECT payment_type, COUNT(*) as count, SUM(amount) as total 
                FROM tbl_payments
                WHERE payment_date BETWEEN ? AND ? 
                AND status = 'paid'
                GROUP BY payment_type";
    $type_breakdown = $dbOps->customSelect($sql_type, [$from_date, $to_date]);

    // Day on Day Comparison & Overall Total
    $sql_comparison = "SELECT 
                        SUM(CASE WHEN payment_date = CURDATE() THEN amount ELSE 0 END) as today_total,
                        SUM(CASE WHEN payment_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN amount ELSE 0 END) as yesterday_total,
                        SUM(amount) as overall_total
                      FROM tbl_payments
                      WHERE status = 'paid'";
    $comparison_result = $dbOps->customSelect($sql_comparison, []);
    $comparison_stats = $comparison_result[0] ?? ['today_total' => 0, 'yesterday_total' => 0, 'overall_total' => 0];

    // Chart Data - Grouping based on chart_view (daily/weekly/monthly)
    if ($chart_view === 'daily') {
        $sql_chart = "SELECT DATE(payment_date) as date, SUM(amount) as total 
                    FROM tbl_payments
                    WHERE payment_date BETWEEN ? AND ? 
                    AND status = 'paid'
                    GROUP BY DATE(payment_date)
                    ORDER BY date";
    } elseif ($chart_view === 'weekly') {
        $sql_chart = "SELECT DATE_FORMAT(payment_date, '%x-W%v') as week, SUM(amount) as total 
                    FROM tbl_payments
                    WHERE payment_date BETWEEN ? AND ? 
                    AND status = 'paid'
                    GROUP BY DATE_FORMAT(payment_date, '%x-W%v')
                    ORDER BY week";
    } else {
        // Monthly grouping
        $sql_chart = "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total 
                    FROM tbl_payments
                    WHERE payment_date BETWEEN ? AND ? 
                    AND status = 'paid'
                    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                    ORDER BY month";
    }
    $chart_data = $dbOps->customSelect($sql_chart, [$from_date, $to_date]);

    // Daily Pivot Table Data: Get all data needed for pivot
    // 1. Get distinct payment types first
    $sql_types = "SELECT DISTINCT payment_type FROM tbl_payments WHERE payment_date BETWEEN ? AND ? AND status = 'paid' ORDER BY payment_type";
    $payment_types_result = $dbOps->customSelect($sql_types, [$from_date, $to_date]);
    $payment_types = array_column($payment_types_result, 'payment_type');

    // 2. Get daily summary with online/offline and payment type breakdowns
    $sql_pivot = "SELECT 
                    DATE(payment_date) as date,
                    SUM(CASE WHEN payment_mode IN ('online', 'upi', 'bank transfer', 'bank_transfer') THEN amount ELSE 0 END) as online_total,
                    SUM(CASE WHEN payment_mode IN ('cash', 'cheque', 'dd', 'card', 'offline', 'deduction') THEN amount ELSE 0 END) as offline_total,
                    payment_type,
                    SUM(amount) as type_total,
                    SUM(amount) as row_total
                FROM tbl_payments 
                WHERE payment_date BETWEEN ? AND ? 
                AND status = 'paid'
                GROUP BY DATE(payment_date), payment_type
                ORDER BY date DESC, payment_type";
    $pivot_raw = $dbOps->customSelect($sql_pivot, [$from_date, $to_date]);

    // 3. Get daily totals with mode breakdown
    $sql_daily_totals = "SELECT 
                    DATE(payment_date) as date,
                    SUM(CASE WHEN payment_mode IN ('online', 'upi', 'bank transfer', 'bank_transfer') THEN amount ELSE 0 END) as online_total,
                    SUM(CASE WHEN payment_mode IN ('cash', 'cheque', 'dd', 'card', 'offline', 'deduction') THEN amount ELSE 0 END) as offline_total,
                    SUM(amount) as day_total
                FROM tbl_payments 
                WHERE payment_date BETWEEN ? AND ? 
                AND status = 'paid'
                GROUP BY DATE(payment_date)
                ORDER BY date DESC";
    $daily_totals = $dbOps->customSelect($sql_daily_totals, [$from_date, $to_date]);

    // 4. Get payment type totals per date
    $sql_type_per_date = "SELECT 
                    DATE(payment_date) as date,
                    payment_type,
                    SUM(amount) as total
                FROM tbl_payments 
                WHERE payment_date BETWEEN ? AND ? 
                AND status = 'paid'
                GROUP BY DATE(payment_date), payment_type
                ORDER BY date DESC";
    $type_per_date = $dbOps->customSelect($sql_type_per_date, [$from_date, $to_date]);

    // Build pivot data structure
    $daily_pivot = [];
    // First add all dates with online/offline totals
    foreach ($daily_totals as $day) {
        $daily_pivot[$day['date']] = [
            'date' => $day['date'],
            'online_total' => $day['online_total'] ?? 0,
            'offline_total' => $day['offline_total'] ?? 0,
            'types' => [],
            'day_total' => $day['day_total'] ?? 0
        ];
        // Initialize all payment types to 0
        foreach ($payment_types as $pt) {
            $daily_pivot[$day['date']]['types'][$pt] = 0;
        }
    }
    // Fill in payment type amounts
    foreach ($type_per_date as $row) {
        if (isset($daily_pivot[$row['date']])) {
            $daily_pivot[$row['date']]['types'][$row['payment_type']] = $row['total'];
        }
    }

    $daily_breakdown = [
        'payment_types' => $payment_types,
        'data' => array_values($daily_pivot)
    ];

    // Course-wise Breakdown (summary)
    $sql_course = "SELECT
                    r.course_id,
                    IFNULL(c.course_name, 'Unknown') AS course_name,
                    COUNT(DISTINCT p.student_id) AS student_count,
                    COUNT(*) AS transaction_count,
                    SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END) AS cash,
                    SUM(CASE WHEN p.payment_mode IN ('online','upi','bank transfer','bank_transfer') THEN p.amount ELSE 0 END) AS online_amount,
                    SUM(CASE WHEN p.payment_mode NOT IN ('cash','online','upi','bank transfer','bank_transfer') THEN p.amount ELSE 0 END) AS offline_amount,
                    SUM(p.amount) AS total_collection
                FROM tbl_payments p
                JOIN tbl_gm_std_registration r ON p.student_id = r.id
                LEFT JOIN tbl_courses c ON r.course_id = c.id
                WHERE p.payment_date BETWEEN ? AND ?
                  AND p.status = 'paid'
                GROUP BY r.course_id, c.course_name
                ORDER BY r.course_id";
    $course_breakdown_raw = $dbOps->customSelect($sql_course, [$from_date, $to_date]) ?: [];

    // Per-course type breakdown
    $sql_course_type = "SELECT
                    r.course_id,
                    p.payment_type,
                    COUNT(*) AS transaction_count,
                    SUM(p.amount) AS total
                FROM tbl_payments p
                JOIN tbl_gm_std_registration r ON p.student_id = r.id
                WHERE p.payment_date BETWEEN ? AND ?
                  AND p.status = 'paid'
                GROUP BY r.course_id, p.payment_type
                ORDER BY r.course_id, total DESC";
    $course_type_raw = $dbOps->customSelect($sql_course_type, [$from_date, $to_date]) ?: [];

    // Per-course mode breakdown
    $sql_course_mode = "SELECT
                    r.course_id,
                    p.payment_mode,
                    COUNT(*) AS transaction_count,
                    SUM(p.amount) AS total
                FROM tbl_payments p
                JOIN tbl_gm_std_registration r ON p.student_id = r.id
                WHERE p.payment_date BETWEEN ? AND ?
                  AND p.status = 'paid'
                GROUP BY r.course_id, p.payment_mode
                ORDER BY r.course_id, total DESC";
    $course_mode_raw = $dbOps->customSelect($sql_course_mode, [$from_date, $to_date]) ?: [];

    // Build detail maps keyed by course_id
    $course_type_map = [];
    foreach ($course_type_raw as $row) {
        $course_type_map[$row['course_id']][] = $row;
    }
    $course_mode_map = [];
    foreach ($course_mode_raw as $row) {
        $course_mode_map[$row['course_id']][] = $row;
    }

    // Merge into final course_breakdown
    $course_breakdown = [];
    foreach ($course_breakdown_raw as $row) {
        $cid = $row['course_id'];
        $row['type_breakdown'] = $course_type_map[$cid] ?? [];
        $row['mode_breakdown'] = $course_mode_map[$cid] ?? [];
        $course_breakdown[] = $row;
    }

} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Financial Reports");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    $collection_stats = ['count' => 0, 'total' => 0];
    $mode_breakdown = [];
    $type_breakdown = [];
    $chart_data = [];
    $daily_breakdown = [];
    $course_breakdown = [];
}

if ($is_api_call) {
    sendSuccessResponse([
        'collection_stats' => $collection_stats,
        'mode_breakdown' => $mode_breakdown,
        'type_breakdown' => $type_breakdown,
        'chart_data' => $chart_data,
        'daily_breakdown' => $daily_breakdown,
        'course_breakdown' => $course_breakdown,
        'comparison_stats' => $comparison_stats,
        'applied_filters' => [
            'from_date' => $from_date,
            'to_date' => $to_date,
            'chart_view' => $chart_view
        ]
    ]);
}


