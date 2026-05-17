<?php

// Don't include bootstrap.php to avoid output buffering issues
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;

try {
    // Fetch actual data from tbl_fee_config where id = 1
    $stmt = $conn->prepare("
        SELECT fc.*, 
               s.school_code, 
               g.group_name,
               m.medium_name
        FROM tbl_fee_config fc
        LEFT JOIN tbl_schools s ON fc.school_id = s.id
        LEFT JOIN tbl_group g ON fc.group_id = g.id
        LEFT JOIN tbl_medium m ON fc.medium_id = m.id
        WHERE fc.id = 1
    ");
    $stmt->execute();
    $actual_data = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $actual_data = null;
}

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fee_configuration_template_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Output UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

// Create output stream
$output = fopen('php://output', 'w');

// CSV Headers
$headers = [
    'academic_year',
    'term',
    'course_name',
    'school_code',
    'medium_name',
    'group_name',
    'school_fee',
    'school_fee_label',
    'school_fee_gst',
    'trust_facilities_fee',
    'trust_fee_label',
    'trust_fee_gst',
    'tuition_fee_part1',
    'token_fee_label',
    'token_fee_gst',
    'tuition_fee_part2',
    'tuition_fee_label',
    'tuition_fee_gst',
    'token_fee',
    'total_fees',
    'number_of_installments',
    'is_active'
];

fputcsv($output, $headers);

// Use actual data from database if available
if ($actual_data) {
    $row = [
        $actual_data['academic_year'] ?? '',
        $actual_data['term'] ?? '',
        $actual_data['course_name'] ?? '',
        $actual_data['school_code'] ?? '',
        $actual_data['medium_name'] ?? '',
        $actual_data['group_name'] ?? '',
        $actual_data['school_fee'] ?? '0',
        $actual_data['school_fee_label'] ?? 'School Fee',
        $actual_data['school_fee_gst'] ?? '0',
        $actual_data['trust_facilities_fee'] ?? '0',
        $actual_data['trust_fee_label'] ?? 'Trust Fee',
        $actual_data['trust_fee_gst'] ?? '0',
        $actual_data['tuition_fee_part1'] ?? '0',
        $actual_data['token_fee_label'] ?? 'Token Fee',
        $actual_data['token_fee_gst'] ?? '0',
        $actual_data['tuition_fee_part2'] ?? '0',
        $actual_data['tuition_fee_label'] ?? 'Tuition Fee',
        $actual_data['tuition_fee_gst'] ?? '0',
        $actual_data['token_fee'] ?? '0',
        $actual_data['total_fees'] ?? '0',
        $actual_data['number_of_installments'] ?? '1',
        $actual_data['is_active'] ?? '1'
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
