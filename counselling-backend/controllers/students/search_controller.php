<?php

/**
 * Student Search Controller
 * Handles student search requests by ID, mobile number, or name
 * 
 * Route: GET /index.php?route=students/search&search={term}
 */

// Include Database Operations
require_once OPERATION_FILE;
$dbOps = new DatabaseOperations();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Unauthorized'
    ], 401);
}

// Get search parameter
$search = trim($_GET['search'] ?? '');

if (empty($search)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Search term required'
    ], 400);
}

try {
    // Search by ID, Mobile Number, or Name - include enrollment and course info
    $searchQuery = "SELECT 
                                s.id, 
                                s.student_name, 
                                s.surname, 
                                s.fathers_name, 
                                CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as full_name,
                                s.mob, 
                                s.aadhaar, 
                                s.addr, 
                                s.dob, 
                                s.gender,
                                s.email,
                                s.course_id,
                                s.group_id,
                                s.board_id,
                                s.medium_id,
                                s.academic_year_id,
                                s.enrollment_id,
                                s.is_enrolled,
                                CASE WHEN p.id IS NOT NULL OR s.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                                c.course_name,
                                g.group_name,
                                m.medium_name,
                                b.board_name,
                                IFNULL(t.term_name, 'Semester 1') as term_name,
                                sc.school_name
                            FROM tbl_gm_std_registration s
                            LEFT JOIN tbl_payments p ON s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                            LEFT JOIN tbl_courses c ON s.course_id = c.id
                            LEFT JOIN tbl_group g ON s.group_id = g.id
                            LEFT JOIN tbl_medium m ON s.medium_id = m.id
                            LEFT JOIN tbl_boards b ON s.board_id = b.id
                            LEFT JOIN (
                                SELECT es1.* 
                                FROM tbl_enrolled_students es1
                                WHERE es1.is_active = 1
                                AND es1.enrollment_id IN (
                                    SELECT MAX(enrollment_id) 
                                    FROM tbl_enrolled_students 
                                    WHERE is_active = 1 
                                    GROUP BY registration_id
                                )
                            ) es ON s.id = es.registration_id
                            LEFT JOIN tbl_term t ON es.current_term_id = t.id
                            LEFT JOIN tbl_schools sc ON s.school_id = sc.id
                            WHERE (s.id = ? 
                               OR s.mob LIKE ? 
                               OR s.student_name LIKE ? 
                               OR s.surname LIKE ? 
                               OR s.fathers_name LIKE ?
                               OR s.enrollment_id LIKE ?
                               OR CONCAT(s.surname, ' ', s.student_name) LIKE ?
                               OR CONCAT(s.student_name, ' ', s.fathers_name) LIKE ?
                               OR CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) LIKE ?) 
                            AND s.status = 1
                            ORDER BY s.id ASC
                            LIMIT 15";

    $fuzzySearch = "%" . str_replace(' ', '%', $search) . "%";
    $students = $dbOps->customSelect($searchQuery, [
        $search,
        $fuzzySearch,
        $fuzzySearch,
        $fuzzySearch,
        $fuzzySearch,
        $fuzzySearch,
        $fuzzySearch,
        $fuzzySearch,
        $fuzzySearch
    ], false) ?: [];

    if (empty($students)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'No student found'
        ]);
    }

    sendJsonResponse([
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ]);
} catch (PDOException $e) {
    error_log("Student Search Error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred'
    ], 500);
}


