<?php
session_start();
require_once '../db_connect.php';
require_once OPERATION_FILE;
require_once '../common/globalvariable.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = trim($_GET['search'] ?? '');

if (empty($search)) {
    echo json_encode(['success' => false, 'message' => 'Search term required']);
    exit;
}

try {
    // Search by ID, Mobile Number, or Name
    $stmt = $conn->prepare("SELECT id, student_name, surname, fathers_name, mob, aadhaar, addr, dob, gender 
                            FROM tbl_gm_std_registration 
                            WHERE (id = ? 
                               OR mob LIKE ? 
                               OR student_name LIKE ? 
                               OR surname LIKE ? 
                               OR fathers_name LIKE ?
                               OR CONCAT(surname, ' ', student_name) LIKE ?
                               OR CONCAT(student_name, ' ', fathers_name) LIKE ?) 
                            AND status = 1
                            LIMIT 15");
    $searchLike = "%{$search}%";
    $stmt->execute([
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    ]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No student found']);
        exit;
    }

    echo json_encode(['success' => true, 'students' => $students]);
} catch (PDOException $e) {
    error_log("Student Search Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
