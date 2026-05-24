<?php
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(__DIR__)) . '/../common/helpers/fee_allocation_helper.php';

if (!isset($_SESSION['user_id']) || !hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once HELPER_ERROR_LOGGER;
$upload_session_id = $_SESSION['upload_session_id'] ?? 'unknown';

logUploadProgress("Database Commitment Started for Approved Updates", $upload_session_id);

$pending_updates = $_SESSION['pending_bulk_updates'] ?? [];
$results = $_SESSION['direct_admission_results'] ?? [];
$approved_indices = $_POST['approved_indices'] ?? [];

if (empty($pending_updates)) {
    header('Location: ' . BASE_URL . '/portal/modules/students/direct-admission-upload.php');
    exit;
}

$dbOps = new DatabaseOperations();
$conn = $dbOps->getConnection();

$update_count = 0;
$updated_students_list = [];
$errors = $results['errors'] ?? [];

try {
    $conn->beginTransaction();

    $ctx = $results['context'];
    $academic_year_id = $ctx['academic_year_id'];
    $academic_year_name = $ctx['academic_year_name'];
    $school_id = $ctx['school_id'];
    $board_id = $ctx['board_id'];
    $medium_id = $ctx['medium_id'];
    $group_id = $ctx['group_id'];
    $course_id = $ctx['course_id'];
    $standard = $ctx['standard'];
    $fee_config = $ctx['fee_config'];

    foreach ($approved_indices as $index) {
        if (!isset($pending_updates[$index])) continue;

        $update = $pending_updates[$index];
        $csv = $update['csv_data'];
        $student_id = $update['student_id'];

        // Execute UPDATE
        $sql_reg_upd = "UPDATE tbl_gm_std_registration SET 
            academic_year_id = ?, school_id = ?, board_id = ?, medium_id = ?, group_id = ?, course_id = ?, standard = ?,
            surname = ?, student_name = ?, fathers_name = ?, dob = ?, gender = ?, 
            parent_mob = ?, amob = ?, email = ?, gr_no = ?, religion = ?, caste = ?, 
            schoolname = ?, schaddr = ?, addr = ?, district = ?, 
            fathername = ?, fatheredu = ?, ocupation = ?, ofcaddr = ?, 
            hostel_required = ?, transport_required = ?, 
            admission_confirmed = 1, admission_confirmed_by = ?, admission_confirmed_date = NOW(),
            status = 1, updated_at = NOW() 
            WHERE id = ?";

        $stmt_upd = $conn->prepare($sql_reg_upd);
        $stmt_upd->execute([
            $academic_year_id, $school_id, $board_id, $medium_id, $group_id, $course_id, $standard,
            $csv['surname'], $csv['student_name'], $csv['fathers_name'], $csv['dob'], $csv['gender'],
            $csv['parent_mob'], $csv['amob'], $csv['email'], $csv['gr_no'], $csv['religion'], $csv['caste'],
            $csv['schoolname'], $csv['schaddr'], $csv['addr'], $csv['district'],
            $csv['fathername'], $csv['fatheredu'], $csv['ocupation'], $csv['ofcaddr'],
            $csv['hostel_required'], $csv['transport_required'],
            $_SESSION['user_id'], $student_id
        ]);

        // Post-update processes (Enrollment & Fees)
        // ... (Same logic as for new students but tailored for existing)
        
        // Update Enrollment if division/roll no provided
        if (!empty($csv['division_name']) || !empty($csv['roll_no'])) {
            // Fetch division map again or pass it in context
            $stmt_div = $conn->prepare("SELECT id FROM tbl_division WHERE UPPER(division_name) = ? AND is_active = 1 LIMIT 1");
            $stmt_div->execute([strtoupper($csv['division_name'])]);
            $div_id = $stmt_div->fetchColumn();

            $stmt_enr_check = $conn->prepare("SELECT enrollment_id FROM tbl_enrolled_students WHERE registration_id = ?");
            $stmt_enr_check->execute([$student_id]);
            $existing_enr = $stmt_enr_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_enr) {
                $enrollment_id = $existing_enr['enrollment_id'];
                $enr_upd_parts = [];
                $enr_params = [];
                if ($div_id) { $enr_upd_parts[] = "division_id = ?"; $enr_params[] = $div_id; }
                if (!empty($csv['roll_no'])) { $enr_upd_parts[] = "roll_no = ?"; $enr_params[] = $csv['roll_no']; }

                if (!empty($enr_upd_parts)) {
                    $enr_params[] = $enrollment_id;
                    $stmt_enr_upd = $conn->prepare("UPDATE tbl_enrolled_students SET " . implode(", ", $enr_upd_parts) . " WHERE enrollment_id = ?");
                    $stmt_enr_upd->execute($enr_params);
                }
            } else {
                // Create Enrollment if not exists (Duplicate of insertion logic)
                $admission_year = date('y');
                $completion_year = date('y', strtotime('+1 year'));
                $prefix = $admission_year . $completion_year;

                $stmt_seq = $conn->prepare("SELECT MAX(CAST(SUBSTRING(enrollment_no, 5) AS UNSIGNED)) as last_seq FROM tbl_enrolled_students WHERE enrollment_no LIKE ?");
                $stmt_seq->execute([$prefix . '%']);
                $last_seq = intval($stmt_seq->fetchColumn() ?: 0);
                $enrollment_no = $prefix . str_pad($last_seq + 1, 5, '0', STR_PAD_LEFT);

                $stmt_enr = $conn->prepare("INSERT INTO tbl_enrolled_students (registration_id, enrollment_no, division_id, roll_no, current_term_id, enrollment_date, enrollment_status, is_active, created_at, enrolled_by) VALUES (?, ?, ?, ?, 1, NOW(), 'active', 1, NOW(), ?)");
                $stmt_enr->execute([$student_id, $enrollment_no, $div_id, $csv['roll_no'], $_SESSION['user_id']]);
                $enrollment_id = $conn->lastInsertId();
                
                $stmt_link = $conn->prepare("UPDATE tbl_gm_std_registration SET is_enrolled = 1, enrollment_id = ?, enrollment_date = NOW() WHERE id = ?");
                $stmt_link->execute([$enrollment_id, $student_id]);
            }
        }

        // Fee allocation check
        $stmt_alloc_check = $conn->prepare("SELECT id FROM tbl_student_fee_allocation WHERE student_id = ? AND academic_year = ?");
        $stmt_alloc_check->execute([$student_id, $academic_year_name]);
        if (!$stmt_alloc_check->fetch()) {
            $school_fee = floatval($fee_config['school_fee']);
            $trust_fee = floatval($fee_config['trust_facilities_fee']);
            $tuition_part1 = floatval($fee_config['tuition_fee_part1']) * 1.18;
            $tuition_part2 = floatval($fee_config['tuition_fee_part2']) * 1.18;
            $total_fees = $school_fee + $trust_fee + $tuition_part1 + $tuition_part2;

            $stmt_alloc = $conn->prepare("INSERT INTO tbl_student_fee_allocation (student_id, fee_config_id, allocated_amount, paid_amount, pending_amount, status, academic_year, allocated_by, created_by, allocated_at) VALUES (?, ?, ?, 0, ?, 'pending', ?, ?, ?, NOW())");
            $stmt_alloc->execute([$student_id, $fee_config['id'], $total_fees, $total_fees, $academic_year_name, $_SESSION['user_id'], $_SESSION['user_id']]);

            $stmt_pending_check = $conn->prepare("SELECT id FROM tbl_pending_payments WHERE student_id = ? AND payment_type = 'total_fee'");
            $stmt_pending_check->execute([$student_id]);
            if (!$stmt_pending_check->fetch()) {
                $stmt_pending = $conn->prepare("INSERT INTO tbl_pending_payments (student_id, payment_type, amount, status, created_at) VALUES (?, 'total_fee', ?, 'pending', NOW())");
                $stmt_pending->execute([$student_id, $total_fees]);
            }
        }

        $update_count++;
        $updated_students_list[] = ['name' => $csv['surname'] . ' ' . $csv['student_name'], 'mob' => $csv['parent_mob'], 'aadhaar' => $csv['aadhaar']];
        logUploadProgress("UPDATE: {$csv['surname']} {$csv['student_name']} ({$csv['aadhaar']}) updated", $upload_session_id);
    }

    logUploadProgress("Database Commitment Completed: {$update_count} entries updated.", $upload_session_id);
    
    // Finalize session results
    $_SESSION['direct_admission_results']['updated'] = $updated_students_list;
    $_SESSION['direct_admission_results']['update_count'] = $update_count;
    
    // Cleanup staging
    unset($_SESSION['pending_bulk_updates']);

    header('Location: ' . BASE_URL . '/portal/modules/students/direct-admission-results.php');
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    $msg = $e->getMessage();
    logUploadProgress("COMMIT ERROR: $msg", $upload_session_id);
    set_flash_message('error', "Process Error: " . $msg);
    header('Location: ' . BASE_URL . '/portal/modules/students/direct-admission-review.php');
    exit;
}
