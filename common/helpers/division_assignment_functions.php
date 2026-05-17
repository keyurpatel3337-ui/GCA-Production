<?php
/**
 * Division Assignment Functions
 * 
 * Handles automatic division and roll number assignment for enrolled students
 * Priority: Female students first, then Male students
 * Within each gender: ordered by enrollment date
 */

require_once __DIR__ . '/error_logger.php';

/**
 * Get the default course-division for a student based on their course and group
 * 
 * @param PDO $conn Database connection
 * @param int $course_id Course ID
 * @param int $group_id Group ID
 * @return array|null Course division record or null
 */
function getDefaultCourseDivision($conn, $course_id, $group_id)
{
    try {
        $stmt = $conn->prepare("SELECT cd.*, d.division_name 
                               FROM tbl_course_division cd
                               LEFT JOIN tbl_division d ON cd.division_id = d.id
                               WHERE cd.course_id = ? 
                               AND cd.group_id = ? 
                               AND cd.is_active = 1
                               ORDER BY d.display_order DESC
                               LIMIT 1");
        $stmt->execute([$course_id, $group_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e, "Get Default Course Division");
        return null;
    }
}

/**
 * Assign division and roll number to newly enrolled student
 * Priority: Female students first, then Male students
 * Within each gender: ordered by enrollment date
 * 
 * @param PDO $conn Database connection
 * @param int $enrollment_id Enrollment ID from tbl_enrolled_students
 * @param int|null $course_division_id Specific course-division to assign (optional)
 * @return array Result with success status and message
 */
function assignDivisionAndRollNumber($conn, $enrollment_id, $course_division_id = null)
{
    try {
        // Get enrollment details
        $stmt = $conn->prepare("SELECT e.*, r.gender, r.student_name, r.surname, r.fathers_name
                               FROM tbl_enrolled_students e
                               LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                               WHERE e.enrollment_id = ?");
        $stmt->execute([$enrollment_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enrollment) {
            return ['success' => false, 'message' => 'Enrollment not found'];
        }
        
        // If no specific division provided, get default
        if (!$course_division_id) {
            $courseDivision = getDefaultCourseDivision($conn, $enrollment['course_id'], $enrollment['group_id']);
            if (!$courseDivision) {
                logAppError('Division Assignment', 'No course division found for course=' . $enrollment['course_id'] . ', group=' . $enrollment['group_id']);
                return ['success' => false, 'message' => 'No division available for this course/group combination'];
            }
            $course_division_id = $courseDivision['id'];
            $division_id = $courseDivision['division_id'];
        } else {
            // Get division details from course_division_id
            $stmt = $conn->prepare("SELECT * FROM tbl_course_division WHERE id = ?");
            $stmt->execute([$course_division_id]);
            $courseDivision = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$courseDivision) {
                return ['success' => false, 'message' => 'Course division not found'];
            }
            $division_id = $courseDivision['division_id'];
        }
        
        // Update enrollment with division assignment (without roll number yet)
        $stmt = $conn->prepare("UPDATE tbl_enrolled_students 
                               SET division_id = ?, updated_at = NOW()
                               WHERE enrollment_id = ?");
        $stmt->execute([$division_id, $enrollment_id]);
        
        // Recalculate all roll numbers for this division
        $result = recalculateDivisionRollNumbers($conn, $course_division_id);
        
        if ($result['success']) {
            // Get the assigned roll number
            $stmt = $conn->prepare("SELECT roll_no FROM tbl_enrolled_students WHERE enrollment_id = ?");
            $stmt->execute([$enrollment_id]);
            $updated = $stmt->fetch();
            
            return [
                'success' => true, 
                'message' => 'Division and roll number assigned successfully',
                'division_id' => $division_id,
                'roll_no' => $updated['roll_no'] ?? null
            ];
        }
        
        return $result;
        
    } catch (PDOException $e) {
        logDatabaseError($e, "Assign Division and Roll Number");
        return ['success' => false, 'message' => 'Database error during assignment'];
    }
}

/**
 * Recalculate all roll numbers for a division based on gender priority
 * Called after new enrollment or admin shuffle
 * 
 * Order: Female students first (by enrollment_date), then Male students (by enrollment_date)
 * 
 * @param PDO $conn Database connection
 * @param int $course_division_id Course-Division ID from tbl_course_division
 * @return array Result with success status
 */
function recalculateDivisionRollNumbers($conn, $course_division_id)
{
    try {
        // Get course division details
        $stmt = $conn->prepare("SELECT * FROM tbl_course_division WHERE id = ?");
        $stmt->execute([$course_division_id]);
        $courseDivision = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courseDivision) {
            return ['success' => false, 'message' => 'Course division not found'];
        }
        
        // Get all students in this division, ordered by gender (Female first) then enrollment date
        $stmt = $conn->prepare("SELECT e.enrollment_id, e.registration_id, r.gender, r.student_name, r.surname, r.fathers_name,
                                      e.enrollment_date, e.roll_no as current_roll_no
                               FROM tbl_enrolled_students e
                               LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                               WHERE e.division_id = ? 
                               AND e.course_id = ?
                               AND e.group_id = ?
                               AND e.is_active = 1
                               AND e.enrollment_status = 'active'
                               ORDER BY 
                                   CASE WHEN LOWER(r.gender) = 'female' THEN 0 ELSE 1 END,
                                   e.enrollment_date ASC,
                                   e.enrollment_id ASC");
        $stmt->execute([$courseDivision['division_id'], $courseDivision['course_id'], $courseDivision['group_id']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            // No students in division, reset counters
            $stmt = $conn->prepare("UPDATE tbl_course_division 
                                   SET current_roll_no = ?, total_students = 0, updated_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([$courseDivision['start_roll_no'] - 1, $course_division_id]);
            return ['success' => true, 'message' => 'Division counters reset'];
        }
        
        // Assign sequential roll numbers starting from start_roll_no
        $rollNo = $courseDivision['start_roll_no'];
        
        foreach ($students as $student) {
            $stmt = $conn->prepare("UPDATE tbl_enrolled_students 
                                   SET roll_no = ?, updated_at = NOW()
                                   WHERE enrollment_id = ?");
            $stmt->execute([$rollNo, $student['enrollment_id']]);
            $rollNo++;
        }
        
        // Update course_division counters
        $totalStudents = count($students);
        $currentRollNo = $rollNo - 1; // Last assigned roll number
        
        $stmt = $conn->prepare("UPDATE tbl_course_division 
                               SET current_roll_no = ?, total_students = ?, updated_at = NOW()
                               WHERE id = ?");
        $stmt->execute([$currentRollNo, $totalStudents, $course_division_id]);
        
        return [
            'success' => true, 
            'message' => "Recalculated roll numbers for $totalStudents students",
            'total_students' => $totalStudents,
            'last_roll_no' => $currentRollNo
        ];
        
    } catch (PDOException $e) {
        logDatabaseError($e, "Recalculate Division Roll Numbers");
        return ['success' => false, 'message' => 'Database error during recalculation'];
    }
}

/**
 * Move student to a different division
 * 
 * @param PDO $conn Database connection
 * @param int $enrollment_id Enrollment ID
 * @param int $new_course_division_id New course-division ID
 * @return array Result with success status
 */
function moveStudentToDivision($conn, $enrollment_id, $new_course_division_id)
{
    try {
        // Get current division
        $stmt = $conn->prepare("SELECT e.*, cd.id as current_cd_id
                               FROM tbl_enrolled_students e
                               LEFT JOIN tbl_course_division cd ON cd.course_id = e.course_id 
                                   AND cd.group_id = e.group_id 
                                   AND cd.division_id = e.division_id
                               WHERE e.enrollment_id = ?");
        $stmt->execute([$enrollment_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enrollment) {
            return ['success' => false, 'message' => 'Enrollment not found'];
        }
        
        $old_cd_id = $enrollment['current_cd_id'];
        
        // Get new division details
        $stmt = $conn->prepare("SELECT * FROM tbl_course_division WHERE id = ?");
        $stmt->execute([$new_course_division_id]);
        $newDivision = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newDivision) {
            return ['success' => false, 'message' => 'Target division not found'];
        }
        
        // Check capacity
        if ($newDivision['max_capacity'] && $newDivision['total_students'] >= $newDivision['max_capacity']) {
            return ['success' => false, 'message' => 'Target division is at maximum capacity'];
        }
        
        // Update student's division
        $stmt = $conn->prepare("UPDATE tbl_enrolled_students 
                               SET division_id = ?, roll_no = NULL, updated_at = NOW()
                               WHERE enrollment_id = ?");
        $stmt->execute([$newDivision['division_id'], $enrollment_id]);
        
        // Recalculate roll numbers in both divisions
        if ($old_cd_id) {
            recalculateDivisionRollNumbers($conn, $old_cd_id);
        }
        recalculateDivisionRollNumbers($conn, $new_course_division_id);
        
        // Get new roll number
        $stmt = $conn->prepare("SELECT roll_no FROM tbl_enrolled_students WHERE enrollment_id = ?");
        $stmt->execute([$enrollment_id]);
        $updated = $stmt->fetch();
        
        return [
            'success' => true,
            'message' => 'Student moved to new division successfully',
            'new_division_id' => $newDivision['division_id'],
            'new_roll_no' => $updated['roll_no'] ?? null
        ];
        
    } catch (PDOException $e) {
        logDatabaseError($e, "Move Student to Division");
        return ['success' => false, 'message' => 'Database error during move'];
    }
}

/**
 * Get division class list with proper ordering (for display)
 * 
 * @param PDO $conn Database connection  
 * @param int $course_division_id Course-division ID
 * @return array Students list ordered by roll number
 */
function getDivisionClassList($conn, $course_division_id)
{
    try {
        $stmt = $conn->prepare("SELECT cd.*, d.division_name 
                               FROM tbl_course_division cd
                               LEFT JOIN tbl_division d ON cd.division_id = d.id
                               WHERE cd.id = ?");
        $stmt->execute([$course_division_id]);
        $courseDivision = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courseDivision) {
            return ['success' => false, 'students' => []];
        }
        
        $stmt = $conn->prepare("SELECT e.*, r.student_name, r.surname, r.fathers_name, r.gender, r.mob
                               FROM tbl_enrolled_students e
                               LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                               WHERE e.division_id = ?
                               AND e.course_id = ?
                               AND e.group_id = ?
                               AND e.is_active = 1
                               AND e.enrollment_status = 'active'
                               ORDER BY e.roll_no ASC");
        $stmt->execute([$courseDivision['division_id'], $courseDivision['course_id'], $courseDivision['group_id']]);
        
        return [
            'success' => true,
            'division' => $courseDivision,
            'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch (PDOException $e) {
        logDatabaseError($e, "Get Division Class List");
        return ['success' => false, 'students' => []];
    }
}

