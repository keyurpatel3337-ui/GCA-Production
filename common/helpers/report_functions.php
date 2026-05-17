<?php
require_once __DIR__ . '/../constants.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/format_helper.php';
require_once __DIR__ . '/notification_functions.php';

/**
 * Sends a simple daily collection summary via WhatsApp
 */
function sendDailyCollectionSummary($conn, $mobile, $date = null)
{
    $target_date = $date ?? date('Y-m-d');
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as grand_total,
                    SUM(CASE WHEN payment_mode = 'cash' THEN amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_mode IN ('online', 'upi', 'card') THEN amount ELSE 0 END) as online_total,
                    SUM(CASE WHEN payment_mode = 'cheque' THEN amount ELSE 0 END) as cheque_total
                FROM tbl_payments 
                WHERE status = 'paid' AND DATE(payment_date) = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$target_date]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats || $stats['total_count'] == 0) {
            return ['success' => false, 'message' => "No transactions found for $target_date"];
        }
        
        $stmt_counts = $conn->prepare("SELECT 
                                        SUM(CASE WHEN payment_mode = 'cash' THEN 1 ELSE 0 END) as cash_count,
                                        SUM(CASE WHEN payment_mode IN ('online', 'upi', 'card') THEN 1 ELSE 0 END) as online_count,
                                        SUM(CASE WHEN payment_mode = 'cheque' THEN 1 ELSE 0 END) as cheque_count
                                      FROM tbl_payments 
                                      WHERE status = 'paid' AND DATE(payment_date) = ?");
        $stmt_counts->execute([$target_date]);
        $counts = $stmt_counts->fetch(PDO::FETCH_ASSOC);
        
        // Prepare exactly 9 variables for daily_summary_simple
        $variables = [
            date('d-M-Y', strtotime($target_date)), // {{1}} Date
            (string)$stats['total_count'],           // {{2}} Total Receipts
            (string)($counts['cash_count'] ?? 0),    // {{3}} Cash Receipts
            formatWhatsAppAmount($stats['cash_total']), // {{4}} Cash Amount
            (string)($counts['online_count'] ?? 0),  // {{5}} Online Receipts
            formatWhatsAppAmount($stats['online_total']), // {{6}} Online Amount
            (string)($counts['cheque_count'] ?? 0),  // {{7}} Cheque Receipts
            formatWhatsAppAmount($stats['cheque_total']), // {{8}} Cheque Amount
            formatWhatsAppAmount($stats['grand_total']) // {{9}} Grand Total
        ];

        // Clean all variables: Remove commas and newlines just in case
        foreach ($variables as $k => $v) {
            $variables[$k] = str_replace([',', "\n", "\r"], '', (string)$v);
        }
        
        // Use the new template daily_summary_simple
        $result = sendNotification($conn, 'daily_summary_simple', ['name' => 'Account Dept', 'mobile' => $mobile], $variables, ['force_whatsapp' => true]);
        return $result['whatsapp'] ?? ['success' => false, 'message' => 'WhatsApp engine failed'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Sends a detailed component-wise collection summary via WhatsApp
 */
function sendDetailedCollectionSummary($conn, $mobile, $date = null)
{
    $target_date = $date ?? date('Y-m-d');
    
    try {
        $sql_summary = "SELECT COUNT(*) as total_count, SUM(amount) as grand_total FROM tbl_payments WHERE status = 'paid' AND DATE(payment_date) = ?";
        $stmt = $conn->prepare($sql_summary);
        $stmt->execute([$target_date]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats || $stats['total_count'] == 0) {
            return ['success' => false, 'message' => "No transactions found"];
        }
        
        $sql = "SELECT 
                    p.fee_component,
                    p.payment_mode,
                    COUNT(*) as mode_count,
                    SUM(p.amount) as mode_total
                FROM tbl_payments p
                WHERE p.status = 'paid' AND DATE(p.payment_date) = ?
                GROUP BY p.fee_component, p.payment_mode";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$target_date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $fee_labels = [
            'school_fee' => 'School Fee',
            'hostel_fee' => 'Hostel Fee',
            'hostel_security' => 'Hostel Security',
            'transport_fee' => 'Transport Fee',
            'trust_facilities_fee' => 'Trust Facilities Fee',
            'tuition_fee_part1' => 'Tuition Fee Part 1',
            'tuition_fee_part2' => 'Tuition Fee Part 2'
        ];

        $components = [];
        // Pre-initialize with 0s to FIX the structure
        foreach ($fee_labels as $slug => $label) {
            $components[$label] = ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0];
        }

        foreach ($rows as $row) {
            $slug = $row['fee_component'] ?: 'other';
            $label = $fee_labels[$slug] ?? ucwords(str_replace('_', ' ', $slug));
            if (!isset($components[$label])) {
                $components[$label] = ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0];
            }
            $amt = $row['mode_total'];
            $cnt = $row['mode_count'];
            $components[$label]['total'] += $amt;
            
            if ($row['payment_mode'] == 'cash') {
                $components[$label]['cash_amt'] += $amt;
                $components[$label]['cash_cnt'] += $cnt;
            } elseif ($row['payment_mode'] == 'cheque') {
                $components[$label]['cheque_amt'] += $amt;
                $components[$label]['cheque_cnt'] += $cnt;
            } else {
                $components[$label]['online_amt'] += $amt;
                $components[$label]['online_cnt'] += $cnt;
            }
        }
        
        $body = "";
        foreach ($components as $label => $data) {
            $body .= "*{$label}*\n";
            $body .= "  Cash: {$data['cash_cnt']} (Rs. " . formatWhatsAppAmount($data['cash_amt']) . ")\n";
            $body .= "  Online: {$data['online_cnt']} (Rs. " . formatWhatsAppAmount($data['online_amt']) . ")\n";
            $body .= "  Subtotal: Rs. " . formatWhatsAppAmount($data['total']) . "/-\n\n";
        }

        // Map components to the 5 categories requested by user
        $cat_map = [
            'school_fee' => 'school',
            'tuition_fee_part1' => 'school',
            'tuition_fee_part2' => 'school',
            'hostel_fee' => 'hostel',
            'hostel_security' => 'security',
            'transport_fee' => 'transport',
            'trust_facilities_fee' => 'trust'
        ];

        $summary = [
            'school' => ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0],
            'hostel' => ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0],
            'security' => ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0],
            'transport' => ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0],
            'trust' => ['total' => 0, 'cash_amt' => 0, 'cash_cnt' => 0, 'online_amt' => 0, 'online_cnt' => 0, 'cheque_amt' => 0, 'cheque_cnt' => 0]
        ];

        foreach ($rows as $row) {
            $slug = $row['fee_component'] ?: 'school_fee'; // Default to school if empty
            $cat = $cat_map[$slug] ?? 'school'; // Default to school for unmapped components
            
            $amt = (float)$row['mode_total'];
            $cnt = (int)$row['mode_count'];
            $summary[$cat]['total'] += $amt;

            if ($row['payment_mode'] == 'cash') {
                $summary[$cat]['cash_amt'] += $amt;
                $summary[$cat]['cash_cnt'] += $cnt;
            } elseif ($row['payment_mode'] == 'cheque') {
                $summary[$cat]['cheque_amt'] += $amt;
                $summary[$cat]['cheque_cnt'] += $cnt;
            } else {
                $summary[$cat]['online_amt'] += $amt;
                $summary[$cat]['online_cnt'] += $cnt;
            }
        }

        // Prepare exactly 37 variables for detailed_summary_report
        $variables = [
            date('d-M-Y', strtotime($target_date)) // {{1}} Date
        ];

        $cats_ordered = ['school', 'hostel', 'security', 'transport', 'trust'];
        foreach ($cats_ordered as $cat) {
            $d = $summary[$cat];
            $variables[] = (string)$d['cash_cnt'];           // Count
            $variables[] = formatWhatsAppAmount($d['cash_amt']); // Amt
            $variables[] = (string)$d['online_cnt'];         // Count
            $variables[] = formatWhatsAppAmount($d['online_amt']); // Amt
            $variables[] = (string)$d['cheque_cnt'];         // Count
            $variables[] = formatWhatsAppAmount($d['cheque_amt']); // Amt
            $variables[] = formatWhatsAppAmount($d['total']);      // Subtotal
        }
        $variables[] = formatWhatsAppAmount($stats['grand_total']); // {{37}} Total Collection

        // Strict cleaning of all variables
        foreach ($variables as $k => $v) {
            $variables[$k] = str_replace([',', "\n", "\r", "\t"], '', trim((string)$v));
        }

        $result = sendNotification($conn, 'detailed_summary_report', ['name' => 'Account Dept', 'mobile' => $mobile], $variables, ['force_whatsapp' => true]);
        return $result['whatsapp'] ?? ['success' => false, 'message' => 'WhatsApp engine failed'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Sends a granular course-wise collection summary via WhatsApp
 */
function sendCourseWiseCollectionSummary($conn, $mobile, $date = null)
{
    $target_date = $date ?? date('Y-m-d');
    
    try {
        $sql_summary = "SELECT COUNT(*) as total_count, SUM(amount) as grand_total FROM tbl_payments WHERE status = 'paid' AND DATE(payment_date) = ?";
        $stmt = $conn->prepare($sql_summary);
        $stmt->execute([$target_date]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats || $stats['total_count'] == 0) {
            return ['success' => false, 'message' => "No transactions found"];
        }
        
        $sql = "SELECT 
                    c.course_name,
                    p.fee_component,
                    p.payment_mode,
                    COUNT(*) as mode_count,
                    SUM(p.amount) as mode_total
                FROM tbl_payments p
                JOIN tbl_gm_std_registration r ON p.student_id = r.id
                JOIN tbl_courses c ON r.course_id = c.id
                WHERE p.status = 'paid' AND DATE(p.payment_date) = ?
                GROUP BY c.course_name, p.fee_component, p.payment_mode";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$target_date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $fee_labels = [
            'school_fee' => 'School Fee',
            'hostel_fee' => 'Hostel Fee',
            'hostel_security' => 'Hostel Security',
            'transport_fee' => 'Transport Fee',
            'trust_facilities_fee' => 'Trust Facilities Fee',
            'tuition_fee_part1' => 'Tuition Fee Part 1',
            'tuition_fee_part2' => 'Tuition Fee Part 2',
            'other' => 'Other Fee'
        ];

        $hierarchy = [];
        foreach ($rows as $row) {
            $course = $row['course_name'];
            $slug = $row['fee_component'] ?: 'other';
            $fee_type = $fee_labels[$slug] ?? ucwords(str_replace('_', ' ', $slug));
            if (!isset($hierarchy[$course])) {
                $hierarchy[$course] = ['course_total' => 0, 'components' => []];
            }
            if (!isset($hierarchy[$course]['components'][$fee_type])) {
                $hierarchy[$course]['components'][$fee_type] = [
                    'total' => 0, 
                    'cash_amt' => 0, 'cash_cnt' => 0, 
                    'online_amt' => 0, 'online_cnt' => 0,
                    'cheque_amt' => 0, 'cheque_cnt' => 0
                ];
            }
            
            $amt = $row['mode_total'];
            $cnt = $row['mode_count'];
            $hierarchy[$course]['course_total'] += $amt;
            $hierarchy[$course]['components'][$fee_type]['total'] += $amt;
            
            if ($row['payment_mode'] == 'cash') {
                $hierarchy[$course]['components'][$fee_type]['cash_amt'] += $amt;
                $hierarchy[$course]['components'][$fee_type]['cash_cnt'] += $cnt;
            } elseif ($row['payment_mode'] == 'cheque') {
                $hierarchy[$course]['components'][$fee_type]['cheque_amt'] += $amt;
                $hierarchy[$course]['components'][$fee_type]['cheque_cnt'] += $cnt;
            } else {
                $hierarchy[$course]['components'][$fee_type]['online_amt'] += $amt;
                $hierarchy[$course]['components'][$fee_type]['online_cnt'] += $cnt;
            }
        }
        
        $body = "";
        foreach ($hierarchy as $course => $c_data) {
            $body .= "*{$course}*\r\n";
            foreach ($c_data['components'] as $label => $comp_data) {
                $body .= "_{$label}_\r\n";
                if ($comp_data['cash_cnt'] > 0) {
                    $body .= " Cash: {$comp_data['cash_cnt']} | Rs. " . formatWhatsAppAmount($comp_data['cash_amt']) . "\r\n";
                }
                if ($comp_data['online_cnt'] > 0) {
                    $body .= " Online: {$comp_data['online_cnt']} | Rs. " . formatWhatsAppAmount($comp_data['online_amt']) . "\r\n";
                }
                if ($comp_data['cheque_cnt'] > 0) {
                    $body .= " Cheque: {$comp_data['cheque_cnt']} | Rs. " . formatWhatsAppAmount($comp_data['cheque_amt']) . "\r\n";
                }
            }
            $body .= "Course Total: Rs. " . formatWhatsAppAmount($c_data['course_total']) . "\r\n\r\n";
        }

        $clean_body = str_replace([",", "\n", "\r"], ' ', $body);
        $variables = [
            str_replace(',', '', date('d-M-Y', strtotime($target_date))), // {{1}} Date
            $clean_body,                                                  // {{2}} Body
            str_replace(',', '', formatWhatsAppAmount($stats['grand_total'])) // {{3}} Total
        ];

        // Final safety check: strip commas and newlines from all variables
        foreach ($variables as $k => $v) {
            $variables[$k] = str_replace([",", "\n", "\r"], ' ', (string)$v);
        }

        $result = sendNotification($conn, 'daily_summary_coursewise', ['name' => 'Account Dept', 'mobile' => $mobile], $variables, ['force_whatsapp' => true]);
        return $result['whatsapp'] ?? ['success' => false, 'message' => 'WhatsApp engine failed'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
