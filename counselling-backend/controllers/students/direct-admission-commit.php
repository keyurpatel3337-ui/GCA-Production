<?php
/**
 * Direct Admission Commit Controller
 * Processes approved updates and new entries from the preview phase.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(__DIR__)) . '/../common/helpers/fee_allocation_helper.php';
require_once HELPER_ERROR_LOGGER;
require_once BACKEND_GLOBALVARIABLE;

if (!isset($_SESSION['user_id']) || !hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    die("Unauthorized");
}

$pending = $_SESSION['pending_admission_data'] ?? null;
if (!$pending) {
    die("No pending data found in session.");
}

$upload_session_id = $_SESSION['upload_session_id'] ?? 'N/A';
logUploadProgress("Commit Phase Started. Approved Updates: " . count($approved_indices) . ", New Entries: " . count($pending['new']), $upload_session_id);

$approved_indices = $_POST['approved_updates'] ?? [];
$dbOps = new DatabaseOperations();
$conn = $dbOps->getConnection();

$success_count = 0;
$update_count = 0;
$error_count = 0;
$errors = [];
$added_students = [];
$updated_students = [];

try {
    $conn->beginTransaction();

    $meta = $pending['meta'];
    $academic_year_id = $meta['academic_year_id'];
    $course_id = $meta['course_id'];
    $school_id = $meta['school_id'];
    $board_id = $meta['board_id'];
    $medium_id = $meta['medium_id'];
    $group_id = $meta['group_id'];
    $standard = $meta['standard'];

    // Pre-fetch AY name and Fee Config
    $stmt_ay = $conn->prepare("SELECT year_name FROM tbl_academic_years WHERE id = ?");
    $stmt_ay->execute([$academic_year_id]);
    $academic_year_name = $stmt_ay->fetchColumn();

    $stmt_fee = $conn->prepare("SELECT * FROM tbl_fee_config 
                                WHERE academic_year = ? AND course_id = ? AND school_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 LIMIT 1");
    $stmt_fee->execute([$academic_year_name, $course_id, $school_id, $medium_id, $group_id]);
    $fee_config = $stmt_fee->fetch(PDO::FETCH_ASSOC);

    // Pre-fetch divisions
    $divisions_map = [];
    $stmt_div = $conn->query("SELECT id, UPPER(division_name) as name FROM tbl_division WHERE is_active = 1");
    while ($div_row = $stmt_div->fetch(PDO::FETCH_ASSOC)) {
        $divisions_map[$div_row['name']] = $div_row['id'];
    }

    // 1. PROCESS NEW ENTRIES
    foreach ($pending['new'] as $student) {
        try {
            $admission_letter_number = 'ADM-DIR-' . date('Y') . '-' . time() . '-' . rand(100, 999);
            $pass_hash = password_hash($student['mob'], PASSWORD_DEFAULT);

            $sql_reg = "INSERT INTO tbl_gm_std_registration (
                            academic_year_id, school_id, board_id, medium_id, group_id, course_id, standard,
                            surname, student_name, fathers_name, dob, gender, mob, parent_mob, amob, email, aadhaar, gr_no,
                            religion, caste, schoolname, schaddr, addr, district, fathername, fatheredu,
                            ocupation, ofcaddr, hostel_required, transport_required,
                            hash_password, password, admission_confirmed, admission_confirmed_by, admission_confirmed_date,
                            admission_letter_number, admission_letter_generated, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, 1, 1, NOW())";

            $stmt_reg = $conn->prepare($sql_reg);
            $stmt_reg->execute([
                $academic_year_id, $school_id, $board_id, $medium_id, $group_id, $course_id, $standard,
                $student['surname'], $student['student_name'], $student['fathers_name'], $student['dob'], $student['gender'], 
                $student['mob'], $student['parent_mob'], $student['amob'], $student['email'], $student['aadhaar'], $student['gr_no'],
                $student['religion'], $student['caste'], $student['schoolname'], $student['schaddr'], $student['addr'], $student['district'], 
                $student['fathername'], $student['fatheredu'], $student['ocupation'], $student['ofcaddr'], 
                $student['hostel_required'], $student['transport_required'],
                $pass_hash, $student['mob'], $_SESSION['user_id'], $admission_letter_number
            ]);
            $student_id = $conn->lastInsertId();
            
            processStudentEnrollment($conn, $student_id, $student, $divisions_map, $_SESSION['user_id']);
            processStudentFees($conn, $student_id, $fee_config, $academic_year_name, $_SESSION['user_id']);

            $success_count++;
            $added_students[] = ['name' => $student['surname'] . ' ' . $student['student_name'], 'mob' => $student['mob'], 'aadhaar' => $student['aadhaar']];
            logUploadProgress("SUCCESS: New student $student[student_name] ($student[aadhaar]) inserted. Registration ID: $student_id", $upload_session_id, 'SUCCESS');
        } catch (Exception $e) {
            $err_msg = "Error adding " . $student['student_name'] . ": " . $e->getMessage();
            $errors[] = $err_msg;
            logUploadProgress($err_msg, $upload_session_id, 'ERROR');
            $error_count++;
        }
    }

    // 2. PROCESS APPROVED UPDATES
    foreach ($approved_indices as $idx) {
        if (!isset($pending['updates'][$idx])) continue;
        $item = $pending['updates'][$idx];
        $student = $item['new'];
        $id = $item['id'];

        try {
            $sql_upd = "UPDATE tbl_gm_std_registration SET 
                surname = ?, student_name = ?, fathers_name = ?, dob = ?, gender = ?, 
                parent_mob = ?, amob = ?, email = ?, gr_no = ?, religion = ?, caste = ?, 
                schoolname = ?, schaddr = ?, addr = ?, district = ?, 
                fathername = ?, fatheredu = ?, ocupation = ?, ofcaddr = ?, 
                hostel_required = ?, transport_required = ?, 
                updated_at = NOW() 
                WHERE id = ?";

            $stmt_upd = $conn->prepare($sql_upd);
            $stmt_upd->execute([
                $student['surname'], $student['student_name'], $student['fathers_name'], $student['dob'], $student['gender'],
                $student['parent_mob'], $student['amob'], $student['email'], $student['gr_no'], $student['religion'], $student['caste'],
                $student['schoolname'], $student['schaddr'], $student['addr'], $student['district'],
                $student['fathername'], $student['fatheredu'], $student['ocupation'], $student['ofcaddr'],
                $student['hostel_required'], $student['transport_required'],
                $id
            ]);

            processStudentEnrollment($conn, $id, $student, $divisions_map, $_SESSION['user_id']);
            // Note: Fees usually don't need re-allocation on update unless course changed, which we aren't handling here for simplicity.

            $update_count++;
            $updated_students[] = ['name' => $student['surname'] . ' ' . $student['student_name'], 'mob' => $student['mob'], 'aadhaar' => $student['aadhaar']];
            logUploadProgress("UPDATE: Student $student[student_name] ($student[aadhaar]) updated. ID: $id", $upload_session_id, 'UPDATE');
        } catch (Exception $e) {
            $err_msg = "Error updating " . $student['student_name'] . ": " . $e->getMessage();
            $errors[] = $err_msg;
            logUploadProgress($err_msg, $upload_session_id, 'ERROR');
            $error_count++;
        }
    }

    $conn->commit();
    
    $_SESSION['direct_admission_results'] = [
        'added' => $added_students,
        'updated' => $updated_students,
        'errors' => $errors,
        'success_count' => $success_count,
        'update_count' => $update_count,
        'error_count' => $error_count
    ];
    unset($_SESSION['pending_admission_data']);
    unset($_SESSION['upload_session_id']);
    logUploadProgress("--- UPLOAD COMPLETED --- Success: $success_count, Updates: $update_count, Errors: $error_count", $upload_session_id);

    header("Location: " . BASE_URL . "/portal/modules/students/direct-admission-results.php");
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    die("Transaction Failed: " . $e->getMessage());
}

/** Helper Functions **/

function processStudentEnrollment($conn, $student_id, $student, $divisions_map, $user_id) {
    $div_id = $divisions_map[$student['division']] ?? null;
    $roll_no = $student['roll_no'];

    $stmt_enr_check = $conn->prepare("SELECT enrollment_id FROM tbl_enrolled_students WHERE registration_id = ?");
    $stmt_enr_check->execute([$student_id]);
    $existing_enr = $stmt_enr_check->fetch(PDO::FETCH_ASSOC);

    if ($existing_enr) {
        $enrollment_id = $existing_enr['enrollment_id'];
        $stmt_enr_upd = $conn->prepare("UPDATE tbl_enrolled_students SET division_id = ?, roll_no = ?, updated_at = NOW() WHERE enrollment_id = ?");
        $stmt_enr_upd->execute([$div_id, $roll_no, $enrollment_id]);
    } else {
        $prefix = date('y') . date('y', strtotime('+1 year'));
        $stmt_seq = $conn->prepare("SELECT MAX(CAST(SUBSTRING(enrollment_no, 5) AS UNSIGNED)) as last_seq FROM tbl_enrolled_students WHERE enrollment_no LIKE ?");
        $stmt_seq->execute([$prefix . '%']);
        $last_seq = intval($stmt_seq->fetchColumn() ?: 0);
        $enrollment_no = $prefix . str_pad($last_seq + 1, 5, '0', STR_PAD_LEFT);

        $stmt_enr = $conn->prepare("INSERT INTO tbl_enrolled_students (registration_id, enrollment_no, division_id, roll_no, current_term_id, enrollment_date, enrollment_status, is_active, created_at, enrolled_by) VALUES (?, ?, ?, ?, 1, NOW(), 'active', 1, NOW(), ?)");
        $stmt_enr->execute([$student_id, $enrollment_no, $div_id, $roll_no, $user_id]);
        $enrollment_id = $conn->lastInsertId();
    }

    $stmt_link = $conn->prepare("UPDATE tbl_gm_std_registration SET is_enrolled = 1, enrollment_id = ?, enrollment_date = NOW() WHERE id = ?");
    $stmt_link->execute([$enrollment_id, $student_id]);
}

function processStudentFees($conn, $student_id, $fee_config, $academic_year_name, $user_id) {
    if (!$fee_config) return;

    $stmt_alloc_check = $conn->prepare("SELECT id FROM tbl_student_fee_allocation WHERE student_id = ? AND academic_year = ?");
    $stmt_alloc_check->execute([$student_id, $academic_year_name]);
    if (!$stmt_alloc_check->fetch()) {
        $school_fee = floatval($fee_config['school_fee']);
        $trust_fee = floatval($fee_config['trust_facilities_fee']);
        $tuition_part1 = floatval($fee_config['tuition_fee_part1']) * 1.18;
        $tuition_part2 = floatval($fee_config['tuition_fee_part2']) * 1.18;
        $total_fees = $school_fee + $trust_fee + $tuition_part1 + $tuition_part2;

        $stmt_alloc = $conn->prepare("INSERT INTO tbl_student_fee_allocation (student_id, fee_config_id, allocated_amount, paid_amount, pending_amount, status, academic_year, allocated_by, created_by, allocated_at) VALUES (?, ?, ?, 0, ?, 'pending', ?, ?, ?, NOW())");
        $stmt_alloc->execute([$student_id, $fee_config['id'], $total_fees, $total_fees, $academic_year_name, $user_id, $user_id]);

        $stmt_pending = $conn->prepare("INSERT INTO tbl_pending_payments (student_id, payment_type, amount, status, created_at) VALUES (?, 'total_fee', ?, 'pending', NOW())");
        $stmt_pending->execute([$student_id, $total_fees]);
    }
}
