<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once DB_CONNECT_FILE;

header('Content-Type: application/json');

try {
    $current_academic_year = $_POST['academic_year'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $school_id = $_POST['school_id'] ?? '';
    $group_id = $_POST['group_id'] ?? '';

    if (empty($current_academic_year)) {
        throw new Exception('Current academic year is required');
    }

    // Identify the Next Academic Year
    // Current format is YYYY-YYYY (e.g., 2025-2026)
    $parts = explode('-', $current_academic_year);
    if (count($parts) === 2) {
        $next_start = intval($parts[1]);
        $next_end = $next_start + 1;
        $next_academic_year = $next_start . '-' . $next_end;
    } else {
        // Fallback or error
        throw new Exception('Invalid academic year format');
    }

    // Build query - Find students currently in Class 11
    $sql = "
        SELECT DISTINCT
            e.enrollment_id,
            e.registration_id,
            e.enrollment_no,
            r.standard as current_standard,
            CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) as student_name,
            r.course_id,
            c.course_name,
            r.school_id,
            s.school_code,
            r.medium_id,
            r.group_id,
            g.group_name,
            fc_next.id as class12_config_id,
            fc_next.total_fees as class12_total_fees,
            fc_next.term as class12_term
        FROM tbl_enrolled_students e
        INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
        LEFT JOIN tbl_academic_years ay ON r.academic_year_id = ay.id
        INNER JOIN tbl_courses c ON r.course_id = c.id
        INNER JOIN tbl_schools s ON r.school_id = s.id
        LEFT JOIN tbl_group g ON r.group_id = g.id
        -- Find Class 12 Fee Config for NEXT year
        -- We assume the course_id doesn't change, but it might. 
        -- For now, we reuse the course mapping.
        LEFT JOIN tbl_fee_config fc_next ON 
            r.course_id = fc_next.course_id AND 
            r.school_id = fc_next.school_id AND 
            r.medium_id = fc_next.medium_id AND
            (r.group_id = fc_next.group_id OR (r.group_id IS NULL AND fc_next.group_id IS NULL)) AND
            fc_next.academic_year = ? AND
            fc_next.term = 'Semester 1' AND 
            fc_next.is_active = 1
        WHERE ay.year_name = ?
            AND e.is_active = 1
            AND e.enrollment_status = 'active'
            AND r.standard = 11
    ";

    $params = [$next_academic_year, $current_academic_year];

    if (!empty($course_id)) {
        $sql .= " AND r.course_id = ?";
        $params[] = $course_id;
    }

    if (!empty($school_id)) {
        $sql .= " AND r.school_id = ?";
        $params[] = $school_id;
    }

    if (!empty($group_id)) {
        $sql .= " AND r.group_id = ?";
        $params[] = $group_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $students = [];
    $eligible_count = 0;
    $pending_fees_count = 0;
    $already_assigned_count = 0;
    $to_assign_count = 0;

    foreach ($results as $row) {
        // CHECK PENDING FEES for 11th standard (Current Year)
        // Check all allocations for this student in the current year
        $fee_check_sql = "SELECT SUM(pending_amount) as total_pending 
                         FROM tbl_student_fee_allocation 
                         WHERE student_id = ? AND academic_year = ?";
        $fee_check_stmt = $conn->prepare($fee_check_sql);
        $fee_check_stmt->execute([$row['registration_id'], $current_academic_year]);
        $fee_data = $fee_check_stmt->fetch(PDO::FETCH_ASSOC);
        $total_pending = floatval($fee_data['total_pending'] ?? 0);

        // Check if Class 12 fee is already assigned (already promoted)
        // We look for any allocation in the NEXT academic year
        $already_promoted_stmt = $conn->prepare("SELECT id FROM tbl_student_fee_allocation 
                                                WHERE student_id = ? AND academic_year = ?");
        $already_promoted_stmt->execute([$row['registration_id'], $next_academic_year]);
        $already_promoted = $already_promoted_stmt->fetch() ? true : false;

        $has_next_config = !empty($row['class12_config_id']);

        if ($already_promoted) {
            $already_assigned_count++;
        } else if ($total_pending > 0) {
            $pending_fees_count++;
        } else if ($has_next_config) {
            $eligible_count++;
            $to_assign_count++;
        }

        $students[] = [
            'enrollment_id' => $row['enrollment_id'],
            'enrollment_no' => $row['enrollment_no'],
            'student_name' => $row['student_name'],
            'current_standard' => $row['current_standard'],
            'course_id' => $row['course_id'],
            'course_name' => $row['course_name'],
            'school_code' => $row['school_code'],
            'class12_config_id' => $row['class12_config_id'],
            'class12_config_display' => $has_next_config
                ? $row['class12_term'] . ' (' . $next_academic_year . ') - ₹' . formatIndianCurrency($row['class12_total_fees'])
                : null,
            'total_pending_11th' => $total_pending,
            'already_assigned' => $already_promoted
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'eligible_count' => $eligible_count,
            'pending_fees_count' => $pending_fees_count,
            'already_assigned_count' => $already_assigned_count,
            'to_assign_count' => $to_assign_count,
            'students' => $students
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
