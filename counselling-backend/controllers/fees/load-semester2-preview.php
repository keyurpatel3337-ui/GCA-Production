<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once DB_CONNECT_FILE;

header('Content-Type: application/json');

try {
    $academic_year = $_POST['academic_year'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $school_id = $_POST['school_id'] ?? '';
    $payment_status = $_POST['payment_status'] ?? 'all';

    if (empty($academic_year)) {
        throw new Exception('Academic year is required');
    }

    // Build query - Find students currently in Semester 1 who need to be promoted to Semester 2
    $sql = "
        SELECT DISTINCT
            e.enrollment_id,
            e.registration_id,
            e.enrollment_no,
            e.current_term_id,
            t.term_name as current_term,
            CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) as student_name,
            r.course_id,
            c.course_name,
            r.school_id,
            s.school_code,
            r.medium_id,
            r.group_id,
            g.group_name,
            e.post_admission_discount_amount,
            fc2.id as sem2_config_id,
            fc2.total_fees as sem2_total_fees,
            fc2.term as sem2_term,
            t2.id as next_term_id
        FROM tbl_enrolled_students e
        INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
        LEFT JOIN tbl_academic_years ay ON r.academic_year_id = ay.id
        INNER JOIN tbl_courses c ON r.course_id = c.id
        INNER JOIN tbl_schools s ON r.school_id = s.id
        LEFT JOIN tbl_term t ON e.current_term_id = t.id
        LEFT JOIN tbl_term t2 ON t2.term_name = 'Semester 2'
        LEFT JOIN tbl_group g ON r.group_id = g.id
        LEFT JOIN tbl_fee_config fc2 ON 
            r.course_id = fc2.course_id AND 
            r.school_id = fc2.school_id AND 
            r.medium_id = fc2.medium_id AND
            (r.group_id = fc2.group_id OR (r.group_id IS NULL AND fc2.group_id IS NULL)) AND
            fc2.academic_year = ? AND
            (fc2.term = '2nd Term' OR fc2.term = 'Semester 2') AND
            fc2.is_active = 1
        WHERE ay.year_name = ?
            AND e.is_active = 1
            AND e.enrollment_status = 'active'
            AND t.term_name = 'Semester 1'
    ";

    $params = [$academic_year, $academic_year];

    if (!empty($course_id)) {
        $sql .= " AND r.course_id = ?";
        $params[] = $course_id;
    }

    if (!empty($school_id)) {
        $sql .= " AND r.school_id = ?";
        $params[] = $school_id;
    }

    if (!empty($_POST['group_id'])) {
        $sql .= " AND r.group_id = ?";
        $params[] = $_POST['group_id'];
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process results
    $students = [];
    $eligible_count = 0;
    $already_assigned_count = 0;
    $to_assign_count = 0;

    foreach ($results as $row) {
        $has_sem2_config = !empty($row['sem2_config_id']);

        // Check if already assigned
        $check_stmt = $conn->prepare("SELECT id FROM tbl_student_fee_allocation 
                                      WHERE student_id = ? AND fee_config_id = ?");
        $check_stmt->execute([$row['registration_id'], $row['sem2_config_id']]);
        $already_assigned = $check_stmt->fetch() ? true : false;

        $eligible_count++;
        if ($already_assigned) {
            $already_assigned_count++;
        } elseif ($has_sem2_config) {
            $to_assign_count++;
        }

        $students[] = [
            'enrollment_id' => $row['enrollment_id'],
            'enrollment_no' => $row['enrollment_no'],
            'student_name' => $row['student_name'],
            'current_term' => $row['current_term'],
            'current_term_id' => $row['current_term_id'],
            'next_term_id' => $row['next_term_id'],
            'course_name' => $row['course_name'],
            'school_code' => $row['school_code'],
            'sem1_total_fees' => 'N/A',
            'payment_status' => 'Currently in Semester 1',
            'sem2_config_id' => $row['sem2_config_id'],
            'sem2_config_display' => $has_sem2_config
                ? $row['sem2_term'] . ' - ₹' . formatIndianCurrency($row['sem2_total_fees'])
                : null,
            'post_admission_discount' => floatval($row['post_admission_discount_amount'] ?? 0),
            'already_assigned' => $already_assigned
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'eligible_count' => $eligible_count,
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
