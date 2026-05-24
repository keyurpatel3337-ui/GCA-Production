<?php
/**
 * Session Save Handler
 * Saves or updates a counselling session
 */

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method', 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    parse_str(file_get_contents('php://input'), $input);
}

$session_id = $input['session_id'] ?? $input['id'] ?? null;
$student_id = $input['student_id'] ?? null;
$counsellor_id = $_SESSION['user_id'] ?? null;
$session_date = $input['session_date'] ?? date('Y-m-d');
$session_type = $input['session_type'] ?? 'general';
$session_notes = $input['session_notes'] ?? $input['notes'] ?? '';
$parent_feedback = $input['parent_feedback'] ?? '';
$status = $input['status'] ?? 'completed';

if (!$student_id) {
    sendErrorResponse('Student ID is required', 400);
}

if (!$counsellor_id) {
    sendErrorResponse('Unauthorized', 401);
}

try {
    if ($session_id) {
        // Update existing session
        $stmt = $conn->prepare("UPDATE tbl_counselling_sessions SET 
            session_date = ?, 
            session_type = ?, 
            session_notes = ?,
            parent_feedback = ?,
            status = ?,
            updated_at = NOW()
            WHERE id = ? AND counsellor_id = ?");
        $stmt->execute([$session_date, $session_type, $session_notes, $parent_feedback, $status, $session_id, $counsellor_id]);
        
        sendSuccessResponse(['id' => $session_id], 'Session updated successfully');
    } else {
        // Create new session
        $stmt = $conn->prepare("INSERT INTO tbl_counselling_sessions 
            (student_id, counsellor_id, session_date, session_type, session_notes, parent_feedback, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$student_id, $counsellor_id, $session_date, $session_type, $session_notes, $parent_feedback, $status]);
        
        $newId = $conn->lastInsertId();
        sendSuccessResponse(['id' => $newId], 'Session saved successfully');
    }
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
}
