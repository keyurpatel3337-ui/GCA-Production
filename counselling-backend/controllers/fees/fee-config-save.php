<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
// Don't start session again if already started (API mode)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_path = dirname(dirname(__DIR__));
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
$dbOps = new DatabaseOperations();

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Read POST data - handle both form data, JSON, and URL-encoded input
$postData = $_POST;

// If $_POST is empty, try to read from php://input
if (empty($postData)) {
    $rawInput = file_get_contents('php://input');

    // Try to decode as JSON first
    $jsonData = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $postData = $jsonData;
    } else {
        // Try URL-encoded format
        parse_str($rawInput, $postData);
    }
}

try {
    $conn->beginTransaction();

    // Validate required text fields
    $required_text_fields = ['academic_year', 'term', 'medium', 'group_type', 'school_id', 'course_id'];
    $missing_fields = [];

    foreach ($required_text_fields as $field) {
        if (!isset($postData[$field]) || trim((string)$postData[$field]) === '') {
            $missing_fields[] = $field;
        }
    }

    // Validate required numeric fields (can be 0)
    $required_numeric_fields = ['school_fee', 'trust_facilities_fee', 'tuition_fee_part1', 'tuition_fee_part2', 'token_fee', 'total_fees', 'number_of_installments'];
    foreach ($required_numeric_fields as $field) {
        if (!isset($postData[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }

    $school_id = intval($postData['school_id']);
    if ($school_id <= 0) {
        throw new Exception('Please select a valid school');
    }

    // Get medium_id from medium_name
    $medium = $dbOps->selectOne('tbl_medium', ['id'], ['medium_name' => $postData['medium'], 'is_active' => 1]);
    $medium_id = $medium ? $medium['id'] : null;
    if (!$medium_id) {
        throw new Exception('Invalid medium selected');
    }

    // Get group_id from group_name
    $group = $dbOps->selectOne('tbl_group', ['id'], ['group_name' => $postData['group_type'], 'is_active' => 1]);
    $group_id = $group ? $group['id'] : null;
    if (!$group_id) {
        throw new Exception('Invalid group selected');
    }

    // Get course_name from course_id
    $course_id = intval($postData['course_id']);
    $course = $dbOps->selectOne('tbl_courses', ['course_name'], ['id' => $course_id, 'is_active' => 1]);
    $course_name = $course ? $course['course_name'] : null;
    if (!$course_name) {
        throw new Exception('Invalid course selected');
    }

    // Insert fee configuration with split labels, GST flags, and school_id
    $stmt = $conn->prepare("INSERT INTO tbl_fee_config 
        (academic_year, term, course_id, course_name, medium_id, group_id, school_id,
         school_fee, school_fee_label, school_fee_gst,
         trust_facilities_fee, trust_fee_label, trust_fee_gst,
         tuition_fee_part1, token_fee_label, token_fee_gst,
         tuition_fee_part2, tuition_fee_label, tuition_fee_gst,
         token_fee, total_fees, number_of_installments, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $postData['academic_year'],
        $postData['term'],
        $course_id,
        $course_name,
        $medium_id,
        $group_id,
        $school_id,
        $postData['school_fee'],
        $postData['school_fee_label'] ?? null,
        isset($postData['school_fee_gst']) && $postData['school_fee_gst'] ? 1 : 0,
        $postData['trust_facilities_fee'],
        $postData['trust_fee_label'] ?? null,
        isset($postData['trust_fee_gst']) && $postData['trust_fee_gst'] ? 1 : 0,
        $postData['tuition_fee_part1'],
        $postData['token_fee_label'] ?? null,
        isset($postData['token_fee_gst']) && $postData['token_fee_gst'] ? 1 : 0,
        $postData['tuition_fee_part2'],
        $postData['tuition_fee_label'] ?? null,
        isset($postData['tuition_fee_gst']) && $postData['tuition_fee_gst'] ? 1 : 0,
        $postData['token_fee'],
        $postData['total_fees'],
        $postData['number_of_installments'],
        $_SESSION['user_id']
    ]);

    $config_id = $conn->lastInsertId();

    // Insert installments
    if (!empty($postData['installments'])) {
        $stmt = $conn->prepare("INSERT INTO tbl_fee_installments (fee_config_id, installment_number, installment_name, percentage, due_date) 
                                VALUES (?, ?, ?, ?, ?)");

        $installment_number = 1;
        foreach ($postData['installments'] as $installment) {
            $due_date = !empty($installment['due_date']) ? $installment['due_date'] : null;
            $stmt->execute([
                $config_id,
                $installment_number,
                $installment['name'],
                $installment['percentage'],
                $due_date
            ]);
            $installment_number++;
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Fee configuration saved successfully']);
} catch (PDOException $e) {
    $conn->rollBack();
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Configuration already exists for this course and academic year']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
