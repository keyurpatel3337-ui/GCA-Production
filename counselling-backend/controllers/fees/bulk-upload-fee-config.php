<?php

// Include required files
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;

header('Content-Type: application/json');

try {
    if (!isset($_FILES['bulk_file']) || $_FILES['bulk_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['bulk_file']['tmp_name'];
    $handle = fopen($file, 'r');

    if (!$handle) {
        throw new Exception('Unable to open uploaded file');
    }

    // Read and validate headers
    $headers = fgetcsv($handle);

    // Remove UTF-8 BOM if present
    if (!empty($headers)) {
        $headers[0] = str_replace("\xEF\xBB\xBF", '', $headers[0]);
    }

    // Normalize headers
    $headers = array_map(function ($h) {
        return trim(strtolower($h));
    }, $headers);

    $expected_headers = [
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

    $missing_headers = array_diff($expected_headers, $headers);
    if (!empty($missing_headers)) {
        throw new Exception('Missing required columns: ' . implode(', ', $missing_headers));
    }

    // Prepare lookup caches
    $course_cache = [];
    $school_cache = [];
    $medium_cache = [];
    $group_cache = [];

    // Fetch all courses
    $stmt = $conn->query("SELECT id, course_name FROM tbl_courses WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $course_cache[strtolower(trim($row['course_name']))] = $row['id'];
    }

    // Fetch all schools
    $stmt = $conn->query("SELECT id, school_code, school_name FROM tbl_schools WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = strtolower(trim($row['school_code']));
        $school_cache[$key] = $row['id'];
        // Also index by school name
        $name_key = strtolower(trim($row['school_name']));
        $school_cache[$name_key] = $row['id'];
    }

    // Fetch all mediums
    $stmt = $conn->query("SELECT id, medium_name FROM tbl_medium WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $medium_cache[strtolower(trim($row['medium_name']))] = $row['id'];
    }

    // Fetch all groups
    $stmt = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $group_cache[strtolower(trim($row['group_name']))] = $row['id'];
    }

    // Process rows
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $row_number = 1;

    $conn->beginTransaction();

    while (($data = fgetcsv($handle)) !== false) {
        $row_number++;

        // Skip empty rows
        if (empty(array_filter($data))) {
            continue;
        }

        try {
            // Map data to associative array
            $row_data = array_combine($headers, $data);

            // Validate required fields
            $required_fields = ['academic_year', 'term', 'course_name', 'school_code', 'medium_name'];
            foreach ($required_fields as $field) {
                if (empty(trim($row_data[$field]))) {
                    throw new Exception("$field is required");
                }
            }

            // Lookup course_id
            $course_key = strtolower(trim($row_data['course_name']));
            if (!isset($course_cache[$course_key])) {
                // Try partial match
                $found = false;
                foreach ($course_cache as $key => $id) {
                    if (strpos($key, $course_key) !== false || strpos($course_key, $key) !== false) {
                        $course_id = $id;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $available = implode(', ', array_keys($course_cache));
                    throw new Exception("Standard '{$row_data['course_name']}' not found. Available: $available");
                }
            } else {
                $course_id = $course_cache[$course_key];
            }

            // Lookup school_id
            $school_key = strtolower(trim($row_data['school_code']));
            if (!isset($school_cache[$school_key])) {
                $available = [];
                foreach ($school_cache as $key => $id) {
                    if (!is_numeric($key)) { // Skip numeric keys (school IDs)
                        $available[] = $key;
                    }
                }
                $available = array_unique($available);
                throw new Exception("School '{$row_data['school_code']}' not found. Available: " . implode(', ', $available));
            }
            $school_id = $school_cache[$school_key];

            // Lookup medium_id
            $medium_key = strtolower(trim($row_data['medium_name']));
            if (!isset($medium_cache[$medium_key])) {
                $available = implode(', ', array_keys($medium_cache));
                throw new Exception("Medium '{$row_data['medium_name']}' not found. Available: $available");
            }
            $medium_id = $medium_cache[$medium_key];

            // Lookup group_id (optional)
            $group_id = null;
            if (!empty(trim($row_data['group_name']))) {
                $group_key = strtolower(trim($row_data['group_name']));
                if (!isset($group_cache[$group_key])) {
                    // Try partial match
                    $found = false;
                    foreach ($group_cache as $key => $id) {
                        if (strpos($key, $group_key) !== false || strpos($group_key, $key) !== false) {
                            $group_id = $id;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $available = implode(', ', array_keys($group_cache));
                        throw new Exception("Group '{$row_data['group_name']}' not found. Available: $available");
                    }
                } else {
                    $group_id = $group_cache[$group_key];
                }
            }

            // Check for duplicate
            $duplicate_check = "SELECT id FROM tbl_fee_config WHERE academic_year = ? AND term = ? 
                               AND course_id = ? AND school_id = ? AND medium_id = ? 
                               AND (group_id = ? OR (group_id IS NULL AND ? IS NULL))";
            $stmt = $conn->prepare($duplicate_check);
            $stmt->execute([
                trim($row_data['academic_year']),
                trim($row_data['term']),
                $course_id,
                $school_id,
                $medium_id,
                $group_id,
                $group_id
            ]);

            if ($stmt->fetch()) {
                throw new Exception("Duplicate configuration exists for this combination");
            }

            // Prepare insert data
            $insert_data = [
                'academic_year' => trim($row_data['academic_year']),
                'term' => trim($row_data['term']),
                'course_id' => $course_id,
                'course_name' => trim($row_data['course_name']),
                'school_id' => $school_id,
                'medium_id' => $medium_id,
                'group_id' => $group_id,
                'school_fee' => floatval($row_data['school_fee'] ?? 0),
                'school_fee_label' => trim($row_data['school_fee_label'] ?? 'School Fee'),
                'school_fee_gst' => intval($row_data['school_fee_gst'] ?? 0),
                'trust_facilities_fee' => floatval($row_data['trust_facilities_fee'] ?? 0),
                'trust_fee_label' => trim($row_data['trust_fee_label'] ?? 'Trust Fee'),
                'trust_fee_gst' => intval($row_data['trust_fee_gst'] ?? 0),
                'tuition_fee_part1' => floatval($row_data['tuition_fee_part1'] ?? 0),
                'token_fee_label' => trim($row_data['token_fee_label'] ?? 'Token Fee'),
                'token_fee_gst' => intval($row_data['token_fee_gst'] ?? 0),
                'tuition_fee_part2' => floatval($row_data['tuition_fee_part2'] ?? 0),
                'tuition_fee_label' => trim($row_data['tuition_fee_label'] ?? 'Tuition Fee'),
                'tuition_fee_gst' => intval($row_data['tuition_fee_gst'] ?? 0),
                'token_fee' => floatval($row_data['token_fee'] ?? 0),
                'total_fees' => floatval($row_data['total_fees'] ?? 0),
                'number_of_installments' => intval($row_data['number_of_installments'] ?? 1),
                'is_active' => intval($row_data['is_active'] ?? 1)
            ];

            // Insert into database
            $sql = "INSERT INTO tbl_fee_config (
                academic_year, term, course_id, course_name, school_id, medium_id, group_id,
                school_fee, school_fee_label, school_fee_gst,
                trust_facilities_fee, trust_fee_label, trust_fee_gst,
                tuition_fee_part1, token_fee_label, token_fee_gst,
                tuition_fee_part2, tuition_fee_label, tuition_fee_gst,
                token_fee, total_fees, number_of_installments, is_active, created_by
            ) VALUES (
                :academic_year, :term, :course_id, :course_name, :school_id, :medium_id, :group_id,
                :school_fee, :school_fee_label, :school_fee_gst,
                :trust_facilities_fee, :trust_fee_label, :trust_fee_gst,
                :tuition_fee_part1, :token_fee_label, :token_fee_gst,
                :tuition_fee_part2, :tuition_fee_label, :tuition_fee_gst,
                :token_fee, :total_fees, :number_of_installments, :is_active, :created_by
            )";

            $insert_data['created_by'] = $_SESSION['user_id'] ?? 1;

            $stmt = $conn->prepare($sql);
            $stmt->execute($insert_data);
            $success_count++;

        } catch (Exception $e) {
            $error_count++;
            $errors[] = [
                'row' => $row_number,
                'message' => $e->getMessage()
            ];
        }
    }

    fclose($handle);
    $conn->commit();

    echo json_encode([
        'success' => true,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    if (isset($handle)) {
        fclose($handle);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
