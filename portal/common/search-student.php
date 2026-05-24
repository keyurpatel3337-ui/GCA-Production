<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1);

// Set JSON header before any output
header('Content-Type: application/json');

// Catch any fatal errors and return JSON
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode([
            'success' => false,
            'message' => 'Server error occurred',
            'debug' => $_ENV['APP_ENV'] === 'development' ? $error['message'] : null
        ]);
    }
});

try {
    session_start();
    require_once dirname(dirname(__DIR__)) . '/common/constants.php';
    require_once DB_CONNECT_FILE;
    require_once OPERATION_FILE;
    require_once '../common/globalvariable.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
} catch (Exception $e) {
    error_log("Search Student Init Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server initialization error']);
    exit;
}

$search = trim($_POST['search'] ?? '');

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
                               OR CONCAT(student_name, ' ', fathers_name) LIKE ?
                               OR CONCAT(surname, ' ', student_name, ' ', fathers_name) LIKE ?) 
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
