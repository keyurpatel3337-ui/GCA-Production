<?php
// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="blueprint-template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Sample blueprint template structure
// Row 1: Headers with subject categories
fputcsv($output, ['', '', 'Mathematics', 'Mathematics', 'Mathematics', 'Mathematics', 'Mathematics', 'Mathematics', 'Science', 'Science', 'Science', 'Science', 'Science', 'Science']);

// Row 2: Difficulty levels
fputcsv($output, ['Sr.No', 'Topic (English)', 'Low', 'Low', 'Medium', 'Medium', 'High', 'High', 'Low', 'Low', 'Medium', 'Medium', 'High', 'High']);

// Sample data rows
fputcsv($output, ['01', 'Real Numbers', '1', '2', '3', '4', '5', '6', '51', '52', '53', '54', '55', '56']);
fputcsv($output, ['02', 'Polynomials', '7', '8', '9', '10', '11', '12', '57', '58', '59', '60', '61', '62']);
fputcsv($output, ['03', 'Linear Equations', '13', '14', '15', '16', '17', '18', '63', '64', '65', '66', '67', '68']);
fputcsv($output, ['04', 'Coordinate Geometry', '19', '20', '21', '22', '23', '24', '69', '70', '71', '72', '73', '74']);
fputcsv($output, ['05', 'Trigonometry', '25', '26', '27', '28', '29', '30', '75', '76', '77', '78', '79', '80']);
fputcsv($output, ['06', 'Statistics', '31', '32', '33', '34', '35', '36', '81', '82', '83', '84', '85', '86']);
fputcsv($output, ['07', 'Chemistry', '37', '38', '39', '40', '41', '42', '87', '88', '89', '90', '91', '92']);
fputcsv($output, ['08', 'Physics', '43', '44', '45', '46', '47', '48', '93', '94', '95', '96', '97', '98']);
fputcsv($output, ['09', 'Biology', '49', '', '', '', '', '', '99', '', '', '', '', '']);
fputcsv($output, ['10', 'Environment', '50', '', '', '', '', '', '100', '', '', '', '', '']);

fclose($output);
exit;
