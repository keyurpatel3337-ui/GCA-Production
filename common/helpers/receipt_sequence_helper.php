<?php
/**
 * Receipt Sequence Helper Functions
 * Generates unique receipt numbers for each fee type
 * 
 * Modified to use GROUPED sequences with SIMPLE NUMBERS (1, 2, 3...) as requested.
 * - School 1: 1, 2, 3...
 * - School 2: 1, 2, 3...
 * - Tuition: 1, 2, 3...
 * - MST: 1, 2, 3...
 * 
 * Note: This requires the database to allow duplicate receipt numbers across different fee types.
 * 
 * Created: January 20, 2026
 */

/**
 * Get next receipt number for a fee type
 * Uses row-level locking to prevent duplicate receipt numbers in concurrent transactions
 * 
 * @param PDO $conn Database connection
 * @param string $fee_type Fee type (school_fee, trust_facilities_fee, etc.)
 * @param int|null $school_id School ID (required for school_fee, NULL for others)
 * @param string|null $academic_year_ignored Ignored legacy parameter
 * @param int|null $student_id Student ID (optional, used for course-specific overrides)
 * @return array ['success' => bool, 'receipt_no' => string, 'sequence' => int, 'error' => string]
 */
function getNextReceiptNumber($conn, $fee_type, $school_id = null, $academic_year_ignored = null, $student_id = null)
{
    try {
        // Map fee types to Sequence Keys
        $fee_type_key = $fee_type;
        $school_id_key = null;

        if ($fee_type === 'school_fee') {
            $fee_type_key = 'school_fee';
            $school_id_key = $school_id;
        } elseif (in_array($fee_type, ['tuition_fee', 'tuition_fee_part1', 'tuition_fee_part2', 'token_fee'])) {
            $fee_type_key = 'tuition_fee';
            $school_id_key = null;
        } elseif (in_array($fee_type, ['trust_facilities_fee', 'hostel_fee', 'transport_fee', 'hostel_security'])) {
            $fee_type_key = ($fee_type === 'hostel_security') ? 'hostel_fee' : $fee_type;
            $school_id_key = null;
        } else {
            // Other/General
            $fee_type_key = 'other_fee';
            $school_id_key = null;
        }

        // OVERRIDE: If student is in Re-Neet (Course 3), use the Trust Facilities fee sequence only
        // for trust facilities charges. Hostel security must still use the hostel_fee sequence.
        if ($student_id !== null) {
            $course_id = getStudentCourseId($conn, $student_id);
            if ($course_id == 3 && $fee_type_key === 'trust_facilities_fee') {
                $fee_type_key = 'trust_facilities_fee';
                $school_id_key = null;
            }
        }

        // Check if transaction is already active
        $is_nested = $conn->inTransaction();

        // Start transaction only if not already started
        if (!$is_nested) {
            $conn->beginTransaction();
        }

        // Find or create the sequence record
        $sql = "SELECT id, last_sequence 
                FROM tbl_receipt_sequences 
                WHERE fee_type = ? ";

        $params = [$fee_type_key];

        if ($school_id_key !== null) {
            $sql .= " AND school_id = ?";
            $params[] = $school_id_key;
        } else {
            $sql .= " AND (school_id IS NULL OR school_id = 0)";
        }

        $sql .= " FOR UPDATE";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $sequence_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sequence_record) {
            try {
                // Try to insert new sequence (Unique index on fee_type, school_id prevents duplicates)
                $insert_sql = "INSERT INTO tbl_receipt_sequences (fee_type, school_id, last_sequence) VALUES (?, ?, 0)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->execute([$fee_type_key, $school_id_key]);
                $sequence_id = $conn->lastInsertId();
                $last_sequence = 0;
            } catch (PDOException $e) {
                // If insert fails, someone else just inserted it. Re-select with lock.
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $sequence_record = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$sequence_record) {
                    throw new Exception("Failed to find or create receipt sequence record.");
                }
                $sequence_id = $sequence_record['id'];
                $last_sequence = (int) $sequence_record['last_sequence'];
            }
        } else {
            $sequence_id = $sequence_record['id'];
            $last_sequence = (int) $sequence_record['last_sequence'];
        }

        // Increment sequence
        $new_sequence = $last_sequence + 1;

        // Update sequence
        $stmt = $conn->prepare("UPDATE tbl_receipt_sequences SET last_sequence = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_sequence, $sequence_id]);

        // Generate simple receipt number (1, 2, 3...)
        $receipt_no = (string) $new_sequence;

        // Commit transaction only if we started it
        if (!$is_nested) {
            $conn->commit();
        }

        return [
            'success' => true,
            'receipt_no' => $receipt_no,
            'sequence' => $new_sequence
        ];

    } catch (PDOException $e) {
        // Rollback on error only if we started the transaction
        if (!$is_nested && $conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log("Error generating receipt number: " . $e->getMessage());

        return [
            'success' => false,
            'error' => 'Failed to generate receipt number: ' . $e->getMessage()
        ];
    }
}

/**
 * Get payment label for display purposes (optional helper)
 * Payment labels are for display only and not used in sequence tracking
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @param string $fee_type Fee type
 * @return string Payment label for display (GHSS, SGM, MST, GCA)
 */
function getPaymentLabelForDisplay($conn, $student_id, $fee_type)
{
    try {
        // Get fee config for student
        $stmt = $conn->prepare("
            SELECT fc.school_fee_label, fc.trust_fee_label, fc.token_fee_label, fc.tuition_fee_label
            FROM tbl_gm_std_registration s
            INNER JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                AND s.medium_id = fc.medium_id 
                AND s.group_id = fc.group_id
            WHERE s.id = ? AND fc.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            return 'N/A';
        }

        // Map fee type to payment label (for display only)
        switch ($fee_type) {
            case 'school_fee':
                return $config['school_fee_label'] ?? 'School';
            case 'trust_facilities_fee':
                return 'Trust Facilities';
            case 'hostel_fee':
                return 'Hostel';
            case 'transport_fee':
                return 'Transport';

            case 'tuition_fee_part1':
            case 'token_fee':
            case 'tuition_fee_part2':
            case 'tuition_fee':
                return $config['token_fee_label'] ?? 'Tuition';

            default:
                return 'N/A';
        }

    } catch (PDOException $e) {
        error_log("Error getting payment label: " . $e->getMessage());
        return 'N/A';
    }
}

/**
 * Get student's school ID
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @return int|null School ID or null if not found
 */
function getStudentSchoolId($conn, $student_id)
{
    try {
        $stmt = $conn->prepare("SELECT school_id FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['school_id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error getting student school ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get student's course ID
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @return int|null Course ID or null if not found
 */
function getStudentCourseId($conn, $student_id)
{
    try {
        $stmt = $conn->prepare("SELECT course_id FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['course_id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error getting student course ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get current academic year
 * 
 * @param PDO $conn Database connection
 * @return string|null Current academic year or null
 */
function getCurrentAcademicYear($conn)
{
    try {
        // Get active academic year from settings or academic_years table
        // Modified to handle multiple active years by taking the latest ID
        $stmt = $conn->prepare("
            SELECT year_name 
            FROM tbl_academic_years 
            WHERE is_active = 1 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['year_name'] ?? null;
    } catch (PDOException $e) {
        error_log("Error getting academic year: " . $e->getMessage());
        return null;
    }
}

/**
 * Get current sequence info for a fee type (for debugging/admin purposes)
 * 
 * @param PDO $conn Database connection
 * @param string $fee_type Fee type
 * @param int|null $school_id School ID (for school_fee)
 * @return array|null Sequence info or null if not found
 */
function getSequenceInfo($conn, $fee_type, $school_id = null)
{
    try {
        $stmt = $conn->prepare("
            SELECT * 
            FROM tbl_receipt_sequences 
            WHERE fee_type = ? 
              AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$fee_type, $school_id, $school_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting sequence info: " . $e->getMessage());
        return null;
    }
}


