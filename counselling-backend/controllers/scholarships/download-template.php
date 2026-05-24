<?php

// Don't include bootstrap.php to avoid output buffering issues
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;

// Get sample data from database
try {
    // Get a few active courses
    $stmt = $conn->prepare("SELECT course_name FROM tbl_courses WHERE is_active = 1 LIMIT 3");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get a few active groups
    $stmt = $conn->prepare("SELECT group_name FROM tbl_group WHERE is_active = 1 LIMIT 2");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Fallback to default values if database query fails
    $courses = ['11th Science', '12th Science', '11th Commerce'];
    $groups = ['Science Group', 'Commerce Group'];
}

// Clear any existing output
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for CSV download - must be done before any output
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="scholarship_rules_template.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write header row
fputcsv($output, [
    'scholarship_type_code',
    'course_name',
    'group_name',
    'min_range',
    'max_range',
    'discount_type',
    'discount_value',
    'is_active'
]);

// Write sample data rows using actual database values
$sampleData = [
    ['GMSAT', $courses[0] ?? '11th Science', $groups[0] ?? 'Science Group', '450', '500', 'percentage', '50', '1'],
    ['BOARD', $courses[1] ?? '12th Science', $groups[0] ?? 'Science Group', '90', '100', 'amount', '5000', '1'],
    ['GMSAT', $courses[2] ?? '11th Commerce', $groups[1] ?? 'Commerce Group', '400', '449', 'percentage', '30', '1'],
    ['BOARD', $courses[0] ?? '11th Science', $groups[1] ?? 'Commerce Group', '85', '89.99', 'percentage', '40', '1'],
    ['GMSAT', $courses[1] ?? '12th Science', '', '500', '550', 'amount', '10000', '0']
];

foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;