<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once SERVICE_WHATSAPP;

// Check if user is Super Admin, Principle or Establishment
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$date = $_POST['date'] ?? date('Y-m-d');
$division_id = $_POST['division_id'] ?? null;
$attendanceList = $_POST['attendance'] ?? []; // Array of [student_id => 'Present']

if (!$division_id) {
    echo json_encode(['success' => false, 'message' => 'Division ID is required']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Fetch all students currently enrolled in this division
    $stmt = $conn->prepare("SELECT e.enrollment_id, s.student_name, s.mob, s.surname
                            FROM tbl_enrolled_students e
                            JOIN tbl_gm_std_registration s ON e.registration_id = s.id
                            WHERE e.division_id = ?");
    $stmt->execute([$division_id]);
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allStudents)) {
        throw new Exception("No students found in this division.");
    }

    $whatsappHelper = new WhatsAppHelper($conn);
    $absenteesCount = 0;
    $presentCount = 0;

    foreach ($allStudents as $student) {
        $student_id = $student['enrollment_id'];
        // Determine status: If checked in UI, it's 'Present'. Else 'Absent'.
        $status = isset($attendanceList[$student_id]) ? 'Present' : 'Absent';
        
        // 2. Save or Update Attendance Record
        $sql = "INSERT INTO tbl_student_attendance (student_id, division_id, attendance_date, status, marked_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), updated_at = NOW()";
        
        $upsertStmt = $conn->prepare($sql);
        $upsertStmt->execute([$student_id, $division_id, $date, $status, $_SESSION['user_id']]);

        // 3. Trigger WhatsApp for Absentees
        if ($status === 'Absent' && !empty($student['mob'])) {
            $studentFullName = trim($student['surname'] . ' ' . $student['student_name']);
            // Template Code: student_absent_alert
            // Params: Student Name, Date
            $whatsappHelper->sendMessage($student['mob'], 'student_absent_alert', [
                $studentFullName,
                date('d-m-Y', strtotime($date))
            ], $_SESSION['user_id'], 'student_attendance', $student_id);
            $absenteesCount++;
        } else {
            $presentCount++;
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => "Attendance saved. Present: $presentCount, Absent: $absenteesCount. WhatsApp alerts sent to absentees.",
        'absentees' => $absenteesCount
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
