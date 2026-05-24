<?php

/**
 * Division and Roll Number Helper Functions
 * Provides utility functions for managing student division and roll number assignments
 */

require_once __DIR__ . '/../db_connect.php';
require_once OPERATION_FILE;

/**
 * Automatically assign division and roll number to an enrolled student
 * 
 * @param PDO $conn Database connection
 * @param int $enrollment_id Student enrollment ID
 * @param int $course_division_id Course-division mapping ID (optional, auto-select if null)
 * @return array ['success' => bool, 'message' => string, 'roll_no' => int, 'division_id' => int]
 */
function autoAssignDivisionAndRoll($conn, $enrollment_id, $course_division_id = null)
{
    try {
        $conn->beginTransaction();

        // Get student enrollment details
        $stmt = $conn->prepare("SELECT * FROM tbl_enrolled_students WHERE enrollment_id = ?");
        $stmt->execute([$enrollment_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception("Student enrollment not found.");
        }

        // If already assigned, return existing assignment
        if ($student['division_id'] && $student['roll_no']) {
            $conn->rollBack();
            return [
                'success' => true,
                'message' => 'Student already has division and roll number assigned.',
                'roll_no' => $student['roll_no'],
                'division_id' => $student['division_id'],
                'already_assigned' => true
            ];
        }

        // Get course-division mapping
        if (!$course_division_id) {
            // Auto-select first available division with capacity
            $stmt_cd = $conn->prepare("SELECT * FROM tbl_course_division 
                                      WHERE course_id = ? 
                                      AND group_id = ? 
                                      AND is_active = 1
                                      AND (max_capacity IS NULL OR total_students < max_capacity)
                                      ORDER BY division_id
                                      LIMIT 1");
            $stmt_cd->execute([$student['course_id'], $student['group_id']]);
            $course_division = $stmt_cd->fetch(PDO::FETCH_ASSOC);

            if (!$course_division) {
                throw new Exception("No available division found for this course and group.");
            }
        } else {
            $stmt_cd = $conn->prepare("SELECT * FROM tbl_course_division WHERE id = ? AND is_active = 1");
            $stmt_cd->execute([$course_division_id]);
            $course_division = $stmt_cd->fetch(PDO::FETCH_ASSOC);

            if (!$course_division) {
                throw new Exception("Invalid course-division mapping.");
            }

            // Verify match
            if (
                $student['course_id'] != $course_division['course_id'] ||
                $student['group_id'] != $course_division['group_id']
            ) {
                throw new Exception("Division does not match student's course and group.");
            }

            // Check capacity
            if (
                $course_division['max_capacity'] &&
                $course_division['total_students'] >= $course_division['max_capacity']
            ) {
                throw new Exception("Division has reached maximum capacity.");
            }
        }

        // Assign next roll number
        $new_roll_no = $course_division['current_roll_no'] + 1;
        $division_id = $course_division['division_id'];

        // Update student record
        $stmt_update = $conn->prepare("UPDATE tbl_enrolled_students 
                                      SET division_id = ?, 
                                          roll_no = ?,
                                          updated_at = NOW()
                                      WHERE enrollment_id = ?");
        $stmt_update->execute([$division_id, $new_roll_no, $enrollment_id]);

        // Update course-division counters
        $stmt_update_cd = $conn->prepare("UPDATE tbl_course_division 
                                         SET current_roll_no = current_roll_no + 1,
                                             total_students = total_students + 1,
                                             updated_at = NOW()
                                         WHERE id = ?");
        $stmt_update_cd->execute([$course_division['id']]);

        $conn->commit();

        return [
            'success' => true,
            'message' => "Division and roll number assigned successfully.",
            'roll_no' => $new_roll_no,
            'division_id' => $division_id,
            'course_division_id' => $course_division['id'],
            'already_assigned' => false
        ];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'roll_no' => null,
            'division_id' => null
        ];
    }
}

/**
 * Get next available roll number for a division
 * 
 * @param PDO $conn Database connection
 * @param int $course_division_id Course-division mapping ID
 * @return int Next roll number
 */
function getNextRollNumber($conn, $course_division_id)
{
    $stmt = $conn->prepare("SELECT current_roll_no FROM tbl_course_division WHERE id = ?");
    $stmt->execute([$course_division_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ? ($result['current_roll_no'] + 1) : 1;
}

/**
 * Check if roll number is available in a division
 * 
 * @param PDO $conn Database connection
 * @param int $division_id Division ID
 * @param int $roll_no Roll number to check
 * @param int $course_id Course ID
 * @param int $group_id Group ID
 * @param int $exclude_enrollment_id Enrollment ID to exclude from check (for updates)
 * @return bool True if available, false if already assigned
 */
function isRollNumberAvailable($conn, $division_id, $roll_no, $course_id, $group_id, $exclude_enrollment_id = null)
{
    $query = "SELECT COUNT(*) as count FROM tbl_enrolled_students 
              WHERE division_id = ? 
              AND roll_no = ? 
              AND course_id = ?
              AND group_id = ?";

    $params = [$division_id, $roll_no, $course_id, $group_id];

    if ($exclude_enrollment_id) {
        $query .= " AND enrollment_id != ?";
        $params[] = $exclude_enrollment_id;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] == 0;
}

/**
 * Get division statistics
 * 
 * @param PDO $conn Database connection
 * @param int $course_division_id Course-division mapping ID
 * @return array Division statistics
 */
function getDivisionStats($conn, $course_division_id)
{
    $stmt = $conn->prepare("SELECT cd.*, 
                           c.course_name,
                           g.group_name,
                           d.division_name
                           FROM tbl_course_division cd
                           LEFT JOIN tbl_courses c ON cd.course_id = c.id
                           LEFT JOIN tbl_group g ON cd.group_id = g.id
                           LEFT JOIN tbl_division d ON cd.division_id = d.id
                           WHERE cd.id = ?");
    $stmt->execute([$course_division_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $stats['available_capacity'] = $stats['max_capacity']
            ? max(0, $stats['max_capacity'] - $stats['total_students'])
            : null;
        $stats['is_full'] = $stats['max_capacity']
            ? ($stats['total_students'] >= $stats['max_capacity'])
            : false;
        $stats['next_roll_number'] = $stats['current_roll_no'] + 1;
    }

    return $stats;
}

/**
 * Reassign student to different division
 * 
 * @param PDO $conn Database connection
 * @param int $enrollment_id Student enrollment ID
 * @param int $new_course_division_id New course-division mapping ID
 * @param bool $auto_roll Auto-assign roll number or keep existing
 * @return array Result array
 */
function reassignDivision($conn, $enrollment_id, $new_course_division_id, $auto_roll = true)
{
    try {
        $conn->beginTransaction();

        // Get current student details
        $stmt = $conn->prepare("SELECT * FROM tbl_enrolled_students WHERE enrollment_id = ?");
        $stmt->execute([$enrollment_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception("Student not found.");
        }

        $old_division_id = $student['division_id'];
        $old_roll_no = $student['roll_no'];

        // Get new division details
        $stmt_new = $conn->prepare("SELECT * FROM tbl_course_division WHERE id = ? AND is_active = 1");
        $stmt_new->execute([$new_course_division_id]);
        $new_division = $stmt_new->fetch(PDO::FETCH_ASSOC);

        if (!$new_division) {
            throw new Exception("Invalid division.");
        }

        // Verify course/group match
        if (
            $student['course_id'] != $new_division['course_id'] ||
            $student['group_id'] != $new_division['group_id']
        ) {
            throw new Exception("Division does not match student's course and group.");
        }

        // Check capacity
        if (
            $new_division['max_capacity'] &&
            $new_division['total_students'] >= $new_division['max_capacity']
        ) {
            throw new Exception("New division has reached maximum capacity.");
        }

        // Determine new roll number
        $new_roll_no = $auto_roll ? ($new_division['current_roll_no'] + 1) : $old_roll_no;

        // Update student
        $stmt_update = $conn->prepare("UPDATE tbl_enrolled_students 
                                      SET division_id = ?, 
                                          roll_no = ?,
                                          updated_at = NOW()
                                      WHERE enrollment_id = ?");
        $stmt_update->execute([$new_division['division_id'], $new_roll_no, $enrollment_id]);

        // Update old division counters (if was assigned)
        if ($old_division_id) {
            $stmt_old = $conn->prepare("SELECT id FROM tbl_course_division 
                                       WHERE course_id = ? 
                                       AND group_id = ? 
                                       AND division_id = ?");
            $stmt_old->execute([$student['course_id'], $student['group_id'], $old_division_id]);
            $old_cd = $stmt_old->fetch();

            if ($old_cd) {
                $update_old = $conn->prepare("UPDATE tbl_course_division 
                                             SET total_students = GREATEST(0, total_students - 1),
                                                 updated_at = NOW()
                                             WHERE id = ?");
                $update_old->execute([$old_cd['id']]);
            }
        }

        // Update new division counters
        $update_query = "UPDATE tbl_course_division 
                        SET total_students = total_students + 1";

        if ($auto_roll) {
            $update_query .= ", current_roll_no = current_roll_no + 1";
        }

        $update_query .= ", updated_at = NOW() WHERE id = ?";

        $stmt_update_new = $conn->prepare($update_query);
        $stmt_update_new->execute([$new_course_division_id]);

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Division reassigned successfully.',
            'old_division' => $old_division_id,
            'new_division' => $new_division['division_id'],
            'old_roll_no' => $old_roll_no,
            'new_roll_no' => $new_roll_no
        ];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get all available divisions for a course and group
 * 
 * @param PDO $conn Database connection
 * @param int $course_id Course ID
 * @param int $group_id Group ID
 * @return array Array of available divisions
 */
function getAvailableDivisions($conn, $course_id, $group_id)
{
    $stmt = $conn->prepare("SELECT cd.*, 
                           d.division_name,
                           c.course_name,
                           g.group_name
                           FROM tbl_course_division cd
                           LEFT JOIN tbl_division d ON cd.division_id = d.id
                           LEFT JOIN tbl_courses c ON cd.course_id = c.id
                           LEFT JOIN tbl_group g ON cd.group_id = g.id
                           WHERE cd.course_id = ? 
                           AND cd.group_id = ? 
                           AND cd.is_active = 1
                           ORDER BY d.display_order");
    $stmt->execute([$course_id, $group_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
