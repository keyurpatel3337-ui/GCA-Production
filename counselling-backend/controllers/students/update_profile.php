<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Student
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$student_id = $is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null);

if (!$is_student_login && !hasRole(ROLE_STUDENT)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $op = new Operation();
    
    // Allowed fields for student self-edit
    $allowed_fields = [
        'amob' => 'Alternate Mobile',
        'addr' => 'Address',
        'district' => 'District',
        'fatheredu' => 'Father\'s Education',
        'ocupation' => 'Father\'s Occupation',
        'ofcaddr' => 'Father\'s Office Address'
    ];
    
    $update_data = [];
    foreach ($allowed_fields as $field => $label) {
        if (isset($_POST[$field])) {
            $value = trim($_POST[$field]);
            
            // Basic validation for mobile
            if ($field === 'amob' && !empty($value) && !preg_match('/^[0-9]{10}$/', $value)) {
                echo json_encode(['status' => 'error', 'message' => "Invalid $label format"]);
                exit;
            }
            
            $update_data[$field] = $value;
        }
    }
    
    if (empty($update_data)) {
        echo json_encode(['status' => 'error', 'message' => 'No changes provided']);
        exit;
    }
    
    $result = $op->update('tbl_gm_std_registration', $update_data, ['id' => $student_id]);
    
    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No changes were made or update failed']);
    }

} catch (Exception $e) {
    logDatabaseError($e, "Student Profile Update");
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
