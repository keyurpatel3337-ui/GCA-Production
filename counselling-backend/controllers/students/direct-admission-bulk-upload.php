<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(__DIR__)) . '/../common/helpers/fee_allocation_helper.php';

// Increase execution time limit for handling large CSV files (e.g., 500+ records)
set_time_limit(300);

require_once BACKEND_GLOBALVARIABLE;

require_once HELPER_ERROR_LOGGER;

// Check Permission
if (!isset($_SESSION['user_id']) || !hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COMPUTER_OPERATOR])) {
    logError("Unauthorized access attempt to Direct Admission Bulk Upload", __FILE__, __LINE__, null, LOG_CATEGORY_AUTH);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize Upload Session ID
$upload_session_id = time() . '-' . ($_SESSION['user_id'] ?? '0');
$_SESSION['upload_session_id'] = $upload_session_id;

logUploadProgress("CSV Parsing Started for Direct Admission Bulk Upload", $upload_session_id);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$academic_year_id = intval($_POST['academic_year_id'] ?? 0);
$school_id = intval($_POST['school_id'] ?? 0);
$board_id = intval($_POST['board_id'] ?? 0);
$medium_id = intval($_POST['medium_id'] ?? 0);
$group_id = intval($_POST['group_id'] ?? 0);
$course_id = intval($_POST['course_id'] ?? 0);

if (!$academic_year_id || !$school_id || !$board_id || !$medium_id || !$group_id || !$course_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required academic details']);
    exit;
}

$dbOps = new DatabaseOperations();
$conn = $dbOps->getConnection();

// Fetch standard from selected course
$standard = 0;
try {
    $stmt_std = $conn->prepare("SELECT standard FROM tbl_courses WHERE id = ?");
    $stmt_std->execute([$course_id]);
    $course_data = $stmt_std->fetch(PDO::FETCH_ASSOC);
    if ($course_data) {
        $standard = $course_data['standard'];
    }
}
catch (PDOException $e) {
// Fallback to 12 if table fetch fails, or handle error
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Failed to open CSV file']);
    exit;
}

// Map headers to indices
$header_row = fgetcsv($handle);
if (!$header_row) {
    echo json_encode(['success' => false, 'message' => 'CSV file is empty or missing headers']);
    exit;
}

$headers = array_map(function ($h) {
    return strtolower(trim($h));
}, $header_row);

$expected_column_count = count($headers);

// Define aliases for each field
$field_map = [
    'surname' => ['surname'],
    'student_name' => ['student name', 'name', 'first name'],
    'fathers_name' => ["father's name", 'father name'],
    'dob' => ['date of birth', 'dob', 'birth date'],
    'gender' => ['gender'],
    'mob' => ['mobile number', 'mobile', 'phone', 'contact number', 'mob'],
    'parent_mob' => ['parent mobile', 'father mobile', 'parent_mob'],
    'amob' => ['alternate mobile', 'alternate number', 'alt mobile', 'alternate mob', 'amob'],
    'email' => ['email address', 'email'],
    'aadhaar' => ['aadhaar number', 'aadhar number', 'aadhaar card', 'aadhar', 'aadhaar'],
    'gr_no' => ['gr number', 'gr no', 'gr_no'],
    'division' => ['division', 'div'],
    'roll_no' => ['roll number', 'roll no', 'roll_no'],
    'religion' => ['religion'],
    'caste' => ['caste'],
    'schoolname' => ['school name', 'school name (10th)', 'previous school', 'schoolname'],
    'schaddr' => ['school address', 'school address (10th)', 'prev school addr', 'schaddr'],
    'addr' => ['residence address', 'address', 'current address', 'addr'],
    'district' => ['district', 'residence district'],
    'fathername' => ["father's full name", 'father full name', "father's first name", 'fathername'],
    'fatheredu' => ["father's education", 'father education', 'fatheredu'],
    'ocupation' => ['occupation', 'ocupation'],
    'ofcaddr' => ['office address', 'ofcaddr'],
    'hostel_required' => ['hostel required', 'hostel'],
    'transport_required' => ['transport required', 'transport']
];

// Build index mapping
$indices = [];
foreach ($field_map as $field => $aliases) {
    $indices[$field] = -1;
    foreach ($aliases as $alias) {
        $idx = array_search($alias, $headers);
        if ($idx !== false) {
            $indices[$field] = $idx;
            break;
        }
    }
}

// Pre-fetch divisions for mapping
$divisions_map = [];
try {
    $stmt_div = $conn->query("SELECT id, UPPER(division_name) as name FROM tbl_division WHERE is_active = 1");
    while ($div_row = $stmt_div->fetch(PDO::FETCH_ASSOC)) {
        $divisions_map[$div_row['name']] = $div_row['id'];
    }
}
catch (PDOException $e) { /* Table might not exist or be empty */
}

// Helper to get value
$get_val = function ($row, $field) use ($indices) {
    $idx = $indices[$field];
    $val = ($idx !== -1 && isset($row[$idx])) ? trim($row[$idx]) : '';

    // Robust character encoding sanitization
    if ($val !== '') {
        // Replace non-breaking space (0xA0) and other common problematic bytes
        $val = str_replace(["\xA0", "\x85"], [' ', '...'], $val);

        // Detect and convert to UTF-8 if necessary
        $current_encoding = mb_detect_encoding($val, 'UTF-8, ISO-8859-1, WINDOWS-1252', true);
        if ($current_encoding !== 'UTF-8') {
            $val = mb_convert_encoding($val, 'UTF-8', $current_encoding ?: 'auto');
        }

        // Strip any remaining invalid UTF-8 sequences to prevent database errors
        $val = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
    }

    return trim($val);
};


$success_count = 0;
$update_count = 0;
$error_count = 0;
$errors = [];
$row_number = 2;

try {
    // Fetch Academic Year name for the selected year ID
    $stmt_ay = $conn->prepare("SELECT year_name FROM tbl_academic_years WHERE id = ?");
    $stmt_ay->execute([$academic_year_id]);
    $academic_year_name = $stmt_ay->fetchColumn();

    if (!$academic_year_name) {
        throw new Exception("Invalid academic year selected.");
    }

    // Fetch Other Names for Context
    $stmt_sch = $conn->prepare("SELECT school_name FROM tbl_schools WHERE id = ?");
    $stmt_sch->execute([$school_id]);
    $school_name = $stmt_sch->fetchColumn();

    $stmt_bd = $conn->prepare("SELECT board_name FROM tbl_boards WHERE id = ?");
    $stmt_bd->execute([$board_id]);
    $board_name = $stmt_bd->fetchColumn();

    $stmt_md = $conn->prepare("SELECT medium_name FROM tbl_medium WHERE id = ?");
    $stmt_md->execute([$medium_id]);
    $medium_name = $stmt_md->fetchColumn();

    $stmt_gr = $conn->prepare("SELECT group_name FROM tbl_group WHERE id = ?");
    $stmt_gr->execute([$group_id]);
    $group_name = $stmt_gr->fetchColumn();

    $stmt_cs = $conn->prepare("SELECT course_name FROM tbl_courses WHERE id = ?");
    $stmt_cs->execute([$course_id]);
    $course_name = $stmt_cs->fetchColumn();

    // Fetch Fee Config for the selected course and academic year
    $stmt_fee = $conn->prepare("SELECT * FROM tbl_fee_config 
                                WHERE academic_year = ? AND course_id = ? AND school_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 
                                LIMIT 1");
    $stmt_fee->execute([$academic_year_name, $course_id, $school_id, $medium_id, $group_id]);
    $fee_config = $stmt_fee->fetch(PDO::FETCH_ASSOC);

    if (!$fee_config) {
        throw new Exception("Fee configuration not found for the selected academic details.");
    }

    $added_students = [];
    $updated_students = [];
    $pending_bulk_updates = [];

    $conn->beginTransaction();

    $original_filename = $_FILES['csv_file']['name'] ?? 'Unknown';
    logUploadProgress("CSV Parsing Started for File: $original_filename", $upload_session_id);

    while (($row = fgetcsv($handle)) !== false) {
        if (empty(array_filter($row))) {
            $row_number++;
            continue;
        }

        // Column count validation to prevent data shifting
        if (count($row) !== $expected_column_count) {
            $msg = "Row $row_number: Column count mismatch (Found " . count($row) . ", Expected $expected_column_count). Skipping row.";
            $errors[] = $msg;
            logUploadProgress("VALIDATION ERROR: $msg", $upload_session_id);
            $error_count++;
            $row_number++;
            continue;
        }

        $surname = $get_val($row, 'surname');
        $student_name = $get_val($row, 'student_name');
        $fathers_name = $get_val($row, 'fathers_name');
        $dob_raw = $get_val($row, 'dob');
        $gender = $get_val($row, 'gender');
        $mob = $get_val($row, 'mob');
        $parent_mob = $get_val($row, 'parent_mob');
        if (empty($parent_mob))
            $parent_mob = $mob;

        $amob = substr($get_val($row, 'amob'), 0, 15);
        $email = $get_val($row, 'email');
        
        $aadhaar_raw = $get_val($row, 'aadhaar');
        
        // Handle scientific notation from Excel (e.g. 1.23E+11)
        if (stripos($aadhaar_raw, 'E+') !== false) {
            $aadhaar_raw = number_format((float)$aadhaar_raw, 0, '', '');
        } elseif (strpos($aadhaar_raw, '.') !== false) {
            $parts = explode('.', $aadhaar_raw);
            if (isset($parts[1]) && intval($parts[1]) === 0) {
                $aadhaar_raw = $parts[0]; 
            }
        }
        
        $aadhaar = preg_replace('/[^0-9]/', '', $aadhaar_raw);
        $gr_no = $get_val($row, 'gr_no');
        $division_name = strtoupper($get_val($row, 'division'));
        $roll_no = $get_val($row, 'roll_no');

        $religion = $get_val($row, 'religion');
        $caste = $get_val($row, 'caste');
        $schoolname = $get_val($row, 'schoolname');
        $schaddr = $get_val($row, 'schaddr');
        $addr = $get_val($row, 'addr');
        $district = $get_val($row, 'district');
        $fathername = $get_val($row, 'fathername');
        if (empty($fathername))
            $fathername = $fathers_name;

        $fatheredu = $get_val($row, 'fatheredu');
        $ocupation = $get_val($row, 'ocupation');
        $ofcaddr = $get_val($row, 'ofcaddr');
        $hostel_required_raw = $get_val($row, 'hostel_required');
        $transport_required_raw = $get_val($row, 'transport_required');

        // Core validation
        if (empty($student_name) || empty($mob)) {
            $msg = "Row $row_number: Student Name and Mobile are required";
            $errors[] = $msg;
            logUploadProgress("VALIDATION ERROR: $msg", $upload_session_id);
            $error_count++;
            $row_number++;
            continue;
        }

        // Convert DOB
        $dob = $dob_raw;
        if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $dob_raw, $matches)) {
            $dob = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        else if (preg_match('/^(\d{2})-(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)(-\d{2,4})?$/i', $dob_raw)) {
            $dob_time = strtotime($dob_raw);
            if ($dob_time)
                $dob = date('Y-m-d', $dob_time);
        }

        if (empty($dob) || !strtotime($dob)) {
            $dob = '1970-01-01';
        }

        // Normalize flags
        $hostel_required = (strcasecmp($hostel_required_raw, 'Yes') === 0) ? 'Yes' : 'No';
        $transport_required = (strcasecmp($transport_required_raw, 'Yes') === 0) ? 'Yes' : 'No';

        try {
            // Robust identification logic
            $existing = null;
            
            // 1. Try matching by Aadhaar first (if provided and valid)
            if (!empty($aadhaar) && strlen($aadhaar) >= 12) {
                $stmt_check = $conn->prepare("SELECT id, surname, student_name, mob, aadhaar, is_enrolled, enrollment_id FROM tbl_gm_std_registration WHERE aadhaar = ? LIMIT 1");
                $stmt_check->execute([$aadhaar]);
                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
            }
            
            // 2. Fallback to matching by Mobile if no Aadhaar match
            if (!$existing && !empty($mob)) {
                $stmt_check = $conn->prepare("SELECT id, surname, student_name, mob, aadhaar, is_enrolled, enrollment_id FROM tbl_gm_std_registration WHERE mob = ? LIMIT 1");
                $stmt_check->execute([$mob]);
                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
            }

            // 3. Name Verification for existing students to prevent data mismatch
            if ($existing) {
                $db_full_name = strtoupper(trim(($existing['surname'] ?? '') . ' ' . ($existing['student_name'] ?? '')));
                $csv_full_name = strtoupper(trim($surname . ' ' . $student_name));
                
                // If names are completely different, skip to prevent overwriting one student with another
                if ($db_full_name !== '' && $db_full_name !== $csv_full_name) {
                    $db_id = $existing['id'];
                    $db_mob = $existing['mob'] ?? 'N/A';
                    $db_aadhaar = $existing['aadhaar'] ?? 'N/A';
                    
                    $msg = "Row $row_number: Data Mismatch Alert.\n";
                    $msg .= "Database: [ID: $db_id, Name: $db_full_name, Mob: $db_mob, Aadhaar: $db_aadhaar]\n";
                    $msg .= "CSV Record: [Name: $csv_full_name, Mob: $mob, Aadhaar: $aadhaar]\n";
                    $msg .= "Skipping to prevent data corruption.";

                    $errors[] = $msg;
                    logUploadProgress("SKIP: Data mismatch for row $row_number. DB: $db_full_name, CSV: $csv_full_name", $upload_session_id);
                    $error_count++;
                    $row_number++;
                    continue;
                }
            }

            if ($existing) {
                // STAGE FOR APPROVAL (Instead of direct UPDATE)
                $db_data = [
                    'surname' => $existing['surname'],
                    'student_name' => $existing['student_name'],
                    'mob' => $existing['mob'] ?? $mob, // Fallback if missing in DB fetch
                    'aadhaar' => $existing['aadhaar'] ?? $aadhaar,
                    'is_enrolled' => $existing['is_enrolled']
                ];

                $csv_data = [
                    'surname' => $surname,
                    'student_name' => $student_name,
                    'fathers_name' => $fathers_name,
                    'dob' => $dob,
                    'gender' => $gender,
                    'parent_mob' => $parent_mob,
                    'amob' => $amob,
                    'email' => $email,
                    'aadhaar' => $aadhaar,
                    'gr_no' => $gr_no,
                    'division_name' => $division_name,
                    'roll_no' => $roll_no,
                    'religion' => $religion,
                    'caste' => $caste,
                    'schoolname' => $schoolname,
                    'schaddr' => $schaddr,
                    'addr' => $addr,
                    'district' => $district,
                    'fathername' => $fathername,
                    'fatheredu' => $fatheredu,
                    'ocupation' => $ocupation,
                    'ofcaddr' => $ofcaddr,
                    'hostel_required' => $hostel_required,
                    'transport_required' => $transport_required
                ];

                // Identify mismatches for highlighting
                $mismatches = [];
                if (strcasecmp($db_data['student_name'], $csv_data['student_name']) !== 0) $mismatches[] = 'student_name';
                if ($db_data['aadhaar'] !== $csv_data['aadhaar']) $mismatches[] = 'aadhaar';

                // Calculate Name Similarity Score
                $db_full = strtoupper(trim($db_data['surname'] . ' ' . $db_data['student_name']));
                $csv_full = strtoupper(trim($csv_data['surname'] . ' ' . $csv_data['student_name']));
                similar_text($db_full, $csv_full, $similarity);

                $pending_bulk_updates[] = [
                    'student_id' => $existing['id'],
                    'db_data' => $db_data,
                    'csv_data' => $csv_data,
                    'mismatches' => $mismatches,
                    'similarity' => $similarity,
                    'row_number' => $row_number
                ];
            }
            else {
                // INSERT
                $admission_letter_number = 'ADM-DIR-' . date('Y') . '-' . time() . '-' . $row_number;
                $pass_hash = password_hash($mob, PASSWORD_DEFAULT);

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
                    $surname, $student_name, $fathers_name, $dob, $gender, $mob, $parent_mob, $amob, $email, $aadhaar, $gr_no,
                    $religion, $caste, $schoolname, $schaddr, $addr, $district, $fathername, $fatheredu,
                    $ocupation, $ofcaddr, $hostel_required, $transport_required,
                    $pass_hash, $mob, $_SESSION['user_id'], $admission_letter_number
                ]);
                $student_id = $conn->lastInsertId();
                $success_count++;
                $added_students[] = ['name' => $surname . ' ' . $student_name, 'mob' => $mob, 'aadhaar' => $aadhaar];
                logUploadProgress("SUCCESS: {$surname} {$student_name} ({$aadhaar}) inserted", $upload_session_id);

                // Process Enrollment and Fee only for NEW students
                // (Existing students will be processed after approval)
                
                // Generate/Update Enrollment
                $div_id = $divisions_map[$division_name] ?? null;
                $enrollment_id = null;

                $stmt_enr_check = $conn->prepare("SELECT enrollment_id FROM tbl_enrolled_students WHERE registration_id = ?");
                $stmt_enr_check->execute([$student_id]);
                $existing_enr = $stmt_enr_check->fetch(PDO::FETCH_ASSOC);

                if ($existing_enr) {
                    $enrollment_id = $existing_enr['enrollment_id'];
                    $enr_upd_parts = [];
                    $enr_params = [];
                    if ($div_id) {
                        $enr_upd_parts[] = "division_id = ?";
                        $enr_params[] = $div_id;
                    }
                    if (!empty($roll_no)) {
                        $enr_upd_parts[] = "roll_no = ?";
                        $enr_params[] = $roll_no;
                    }

                    if (!empty($enr_upd_parts)) {
                        $enr_params[] = $enrollment_id;
                        $stmt_enr_upd = $conn->prepare("UPDATE tbl_enrolled_students SET " . implode(", ", $enr_upd_parts) . " WHERE enrollment_id = ?");
                        $stmt_enr_upd->execute($enr_params);
                    }
                }
                else {
                    // Create Enrollment
                    $admission_year = date('y');
                    $completion_year = date('y', strtotime('+1 year'));
                    $prefix = $admission_year . $completion_year;

                    $stmt_seq = $conn->prepare("SELECT MAX(CAST(SUBSTRING(enrollment_no, 5) AS UNSIGNED)) as last_seq FROM tbl_enrolled_students WHERE enrollment_no LIKE ?");
                    $stmt_seq->execute([$prefix . '%']);
                    $last_seq = intval($stmt_seq->fetchColumn() ?: 0);
                    $enrollment_no = $prefix . str_pad($last_seq + 1, 5, '0', STR_PAD_LEFT);

                    $stmt_enr = $conn->prepare("INSERT INTO tbl_enrolled_students (registration_id, enrollment_no, division_id, roll_no, current_term_id, enrollment_date, enrollment_status, is_active, created_at, enrolled_by) VALUES (?, ?, ?, ?, 1, NOW(), 'active', 1, NOW(), ?)");
                    $stmt_enr->execute([$student_id, $enrollment_no, $div_id, $roll_no, $_SESSION['user_id']]);
                    $enrollment_id = $conn->lastInsertId();
                }

                // Link registration
                $stmt_link = $conn->prepare("UPDATE tbl_gm_std_registration SET is_enrolled = 1, enrollment_id = ?, enrollment_date = NOW() WHERE id = ?");
                $stmt_link->execute([$enrollment_id, $student_id]);

                // Allocate Fees (Only if not already allocated)
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

                    // Check if pending payment already exists to avoid duplicate key error
                    $stmt_pending_check = $conn->prepare("SELECT id FROM tbl_pending_payments WHERE student_id = ? AND payment_type = 'total_fee'");
                    $stmt_pending_check->execute([$student_id]);
                    if (!$stmt_pending_check->fetch()) {
                        $stmt_pending = $conn->prepare("INSERT INTO tbl_pending_payments (student_id, payment_type, amount, status, created_at) VALUES (?, 'total_fee', ?, 'pending', NOW())");
                        $stmt_pending->execute([$student_id, $total_fees]);
                    }
                }
            }

        }
        catch (Exception $e) {
            $msg = "Row $row_number: " . $e->getMessage();
            $errors[] = $msg;
            logUploadProgress("PROCESSING ERROR: $msg", $upload_session_id);
            $error_count++;
        }
        $row_number++;
    }

    $conn->commit();
    logUploadProgress("CSV Parsing Completed: {$success_count} new entries, " . count($pending_bulk_updates) . " updates pending, {$error_count} errors.", $upload_session_id);

    $_SESSION['pending_bulk_updates'] = $pending_bulk_updates;
    $_SESSION['direct_admission_results'] = [
        'added' => $added_students,
        'errors' => $errors,
        'success_count' => $success_count,
        'error_count' => $error_count,
        // Context for final processing
        'context' => [
            'academic_year_id' => $academic_year_id,
            'academic_year_name' => $academic_year_name,
            'school_id' => $school_id,
            'school_name' => $school_name,
            'board_id' => $board_id,
            'board_name' => $board_name,
            'medium_id' => $medium_id,
            'medium_name' => $medium_name,
            'group_id' => $group_id,
            'group_name' => $group_name,
            'course_id' => $course_id,
            'course_name' => $course_name,
            'standard' => $standard,
            'fee_config' => $fee_config
        ]
    ];

    fclose($handle);

    if (!empty($pending_bulk_updates)) {
        header("Location: " . BASE_URL . "/portal/modules/students/direct-admission-review.php");
    } else {
        header("Location: " . BASE_URL . "/portal/modules/students/direct-admission-results.php");
    }
    exit;

}
catch (Exception $e) {
    if (isset($handle))
        fclose($handle);
    if (isset($conn) && $conn->inTransaction())
        $conn->rollBack();

    set_flash_message('error', "Import Error: " . $e->getMessage());
    header("Location: " . BASE_URL . "/portal/modules/students/direct-admission-upload.php");
    exit;
}
