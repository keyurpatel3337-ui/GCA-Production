<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
session_start();
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;

// Check if user is authorized
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$student_id = intval($_GET['student_id']);

try {
    // Fetch the latest test result for the student
    $stmt = $conn->prepare("
        SELECT score, percentage 
        FROM tbl_test_results 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'score' => $result['score'],
            'percentage' => $result['percentage']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No test results found for this student'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}


