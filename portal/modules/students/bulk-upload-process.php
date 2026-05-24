<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Check if user has appropriate role
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Redirect back to upload page
$redirect_path = 'upload.php';

// Get academic information from POST
if (!isset($_POST['academic_year_id']) || !isset($_POST['school_id']) || !isset($_POST['board_id']) || !isset($_POST['medium_id']) || !isset($_POST['group_id']) || !isset($_POST['course_id'])) {
    set_flash_message('error', 'Please select Academic Year, School, Board, Medium, Group, and Course');
    header('Location: upload.php');
    exit();
}

$academic_year_id = intval($_POST['academic_year_id']);
$school_id = intval($_POST['school_id']);
$board_id = intval($_POST['board_id']);
$medium_id = intval($_POST['medium_id']);
$group_id = intval($_POST['group_id']);
$course_id = intval($_POST['course_id']);

// Validate IDs
if ($academic_year_id <= 0 || $school_id <= 0 || $board_id <= 0 || $medium_id <= 0 || $group_id <= 0 || $course_id <= 0) {
    set_flash_message('error', 'Invalid selection. Please check your inputs.');
    header('Location: upload.php');
    exit();
}

// Fetch standard from selected course
$standard = null;
try {
    // MIME type validation for CSV upload
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['csv_file']['tmp_name']);
        finfo_close($finfo);
        $allowed_mime_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

        if (!in_array($mimeType, $allowed_mime_types)) {
            set_flash_message('error', 'Invalid file type. Please upload a valid CSV file.');
            header('Location: upload.php');
            exit();
        }
    }

    $stmt = $conn->prepare("SELECT standard FROM tbl_courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_data = $stmt->fetch();
    if ($course_data) {
        $standard = !empty($course_data['standard']) ? $course_data['standard'] : null;
    }
}
catch (PDOException $e) {
    logDatabaseError($e, "Fetch Course Standard");
}

// Fetch valid board IDs, medium IDs, and group IDs from database
try {
    // Verify that selected Academic Year exists and is active
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_academic_years WHERE id = ? AND is_active = 1");
    $stmt->execute([$academic_year_id]);
    if ($stmt->fetchColumn() == 0) {
        set_flash_message('error', 'Selected academic year is not valid');
        header('Location: upload.php');
        exit();
    }

    // Verify that selected IDs exist in database
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_boards WHERE id = ? AND is_active = 1");
    $stmt->execute([$board_id]);
    if ($stmt->fetchColumn() == 0) {
        set_flash_message('error', 'Selected board is not valid');
        header('Location: upload.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_medium WHERE id = ? AND is_active = 1");
    $stmt->execute([$medium_id]);
    if ($stmt->fetchColumn() == 0) {
        set_flash_message('error', 'Selected medium is not valid');
        header('Location: upload.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_group WHERE id = ? AND is_active = 1");
    $stmt->execute([$group_id]);
    if ($stmt->fetchColumn() == 0) {
        set_flash_message('error', 'Selected group is not valid');
        header('Location: upload.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_courses WHERE id = ? AND is_active = 1");
    $stmt->execute([$course_id]);
    if ($stmt->fetchColumn() == 0) {
        set_flash_message('error', 'Selected course is not valid');
        header('Location: upload.php');
        exit();
    }
}
catch (PDOException $e) {
    logDatabaseError($e, "Validate IDs for bulk upload");
    set_flash_message('error', 'Database error occurred during validation');
    header('Location: upload.php');
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['csv_file'])) {
    logAppError('Student Bulk Upload', 'CSV file field not found in POST request');
    set_flash_message('error', 'Please select a CSV file to upload');
    header('Location: ' . $redirect_path);
    exit();
}

if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by PHP extension'
    ];
    $error_code = $_FILES['csv_file']['error'];
    $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Unknown upload error';

    logAppError('Student Bulk Upload', 'File upload error', [
        'error_code' => $error_code,
        'error_message' => $error_message,
        'file_size' => $_FILES['csv_file']['size'] ?? 0,
        'file_name' => $_FILES['csv_file']['name'] ?? 'unknown'
    ]);

    set_flash_message('error', 'File upload failed: ' . $error_message);
    header('Location: ' . $redirect_path);
    exit();
}

$file = $_FILES['csv_file'];
$allowed = ['csv'];
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Check file extension
if (!in_array($ext, $allowed)) {
    logValidationError('csv_file', $ext, 'Invalid file extension');
    set_flash_message('error', 'Invalid file type. Only .csv files are allowed');
    header('Location: ' . $redirect_path);
    exit();
}

// Check file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    logValidationError('csv_file', '', 'File size exceeds 5MB limit: ' . $file['size'] . ' bytes');
    set_flash_message('error', 'File size exceeds 5MB limit');
    header('Location: ' . $redirect_path);
    exit();
}

// Move uploaded file to temp location
$upload_dir = '../uploads/temp/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$temp_file = $upload_dir . uniqid('student_upload_') . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
    logFileError('upload', $filename, 'Failed to move uploaded file to temp directory');
    set_flash_message('error', 'Failed to upload file. Please check permissions.');
    header('Location: ' . $redirect_path);
    exit();
}

// Parse CSV file
try {
    $rows = [];
    if (($handle = fopen($temp_file, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    else {
        logFileError('open', $temp_file, 'Failed to open CSV file');
        set_flash_message('error', 'Failed to open CSV file');
        @unlink($temp_file);
        header('Location: ' . $redirect_path);
        exit();
    }

    if (count($rows) < 2) {
        logValidationError('csv_file', '', 'CSV file empty or contains only headers');
        set_flash_message('error', 'CSV file is empty or contains only headers');
        @unlink($temp_file);
        header('Location: ' . $redirect_path);
        exit();
    }

    // Map headers to indices
    $header_row = array_shift($rows);
    if (!$header_row) {
        set_flash_message('error', 'CSV file is empty or missing headers');
        @unlink($temp_file);
        header('Location: ' . $redirect_path);
        exit();
    }

    // Sanitize headers for matching
    $headers = array_map(function ($h) {
        return strtolower(trim($h));
    }, $header_row);

    // Define aliases for each field to support various template formats
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
    $stmt_div = $conn->query("SELECT id, UPPER(division_name) as name FROM tbl_division WHERE is_active = 1");
    while ($div_row = $stmt_div->fetch(PDO::FETCH_ASSOC)) {
        $divisions_map[$div_row['name']] = $div_row['id'];
    }

    /**
     * Sanitize string and handle encoding issues
     * Replaces non-breaking spaces and ensures valid UTF-8
     */
    $sanitize = function ($str) {
        if ($str === null || $str === '')
            return '';

        // Replace non-breaking space (0xA0) and other common problematic bytes
        $str = str_replace(["\xA0", "\x85"], [' ', '...'], $str);

        // Ensure UTF-8 encoding and remove invalid characters
        // First try to detect encoding and convert if not UTF-8
        $current_encoding = mb_detect_encoding($str, 'UTF-8, ISO-8859-1, WINDOWS-1252', true);
        if ($current_encoding !== 'UTF-8') {
            $str = mb_convert_encoding($str, 'UTF-8', $current_encoding ?: 'auto');
        }

        // Clean up any remaining invalid UTF-8 sequences to prevent database errors
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');

        return trim($str);
    };

    // Helper to get value from row based on mapped index
    $get_val = function ($row, $field) use ($indices, $sanitize) {
        $idx = $indices[$field];
        $val = ($idx !== -1 && isset($row[$idx])) ? $row[$idx] : '';
        return $sanitize($val);
    };

    $success_count = 0;
    $update_count = 0;
    $error_count = 0;
    $errors = [];
    $row_number = 2; // Starting from row 2 (after header)

    foreach ($rows as $row) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            $row_number++;
            continue;
        }

        // Extract and sanitize data using dynamic mapping
        $surname = $get_val($row, 'surname');
        $student_name = $get_val($row, 'student_name');
        $fathers_name = $get_val($row, 'fathers_name');
        $dob_raw = $get_val($row, 'dob');
        $gender = $get_val($row, 'gender');
        $mob = $get_val($row, 'mob');
        $parent_mob = $get_val($row, 'parent_mob');
        if (empty($parent_mob))
            $parent_mob = $mob; // Fallback to student mob if parent_mob not provided

        $amob = $get_val($row, 'amob');
        $email = $get_val($row, 'email');
        $aadhaar_raw = $get_val($row, 'aadhaar');
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
            $fathername = $fathers_name; // Default to father's name

        $fatheredu = $get_val($row, 'fatheredu');
        $ocupation = $get_val($row, 'ocupation');
        $ofcaddr = $get_val($row, 'ofcaddr');
        $hostel_required_raw = $get_val($row, 'hostel_required');
        $transport_required_raw = $get_val($row, 'transport_required');

        // Core validation
        if (
        empty($surname) || empty($student_name) || empty($fathers_name) || empty($dob_raw) ||
        empty($gender) || empty($mob) || empty($aadhaar) || empty($schoolname) ||
        empty($schaddr) || empty($district)
        ) {
            $errors[] = "Row $row_number: Required fields are missing (Surname, Name, Father's Name, DOB, Gender, Mobile, Aadhaar, School Name, School Address, District)";
            $error_count++;
            $row_number++;
            continue;
        }

        // Convert date from DD-MM-YYYY or DD/MM/YYYY to YYYY-MM-DD
        $dob = $dob_raw;
        if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $dob_raw, $matches)) {
            // DD-MM-YYYY or DD/MM/YYYY format
            $dob = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        // Handle empty or invalid dates - set to NULL
        if (empty($dob) || $dob === '0000-00-00' || !strtotime($dob)) {
            $dob = null;
        }

        // Validate mobile number (10 digits)
        if (!preg_match('/^[0-9]{10}$/', $mob)) {
            $errors[] = "Row $row_number: Invalid mobile number format";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate Aadhaar (12 digits)
        if (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
            $errors[] = "Row $row_number: Invalid Aadhaar number format";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate gender
        if (!in_array($gender, ['Male', 'Female', 'Other'])) {
            $gender = (strcasecmp($gender, 'M') === 0 || strcasecmp($gender, 'boy') === 0) ? 'Male' :
                ((strcasecmp($gender, 'F') === 0 || strcasecmp($gender, 'girl') === 0) ? 'Female' : 'Male');
        }

        // Normalize flags
        $hostel_required = (strcasecmp($hostel_required_raw, 'Yes') === 0) ? 'Yes' : 'No';
        $transport_required = (strcasecmp($transport_required_raw, 'Yes') === 0) ? 'Yes' : 'No';

        // Check if student already exists (Match by Mobile or Aadhaar)
        $existing_student = null;
        $stmt_check = $conn->prepare("SELECT id, is_enrolled, enrollment_id FROM tbl_gm_std_registration WHERE mob = ? AND aadhaar = ? LIMIT 1");
        $stmt_check->execute([$mob, $aadhaar]);
        $existing_student = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existing_student) {
            // UPDATE Logic
            $update_sql = "UPDATE tbl_gm_std_registration SET 
                academic_year_id = ?, school_id = ?, board_id = ?, medium_id = ?, group_id = ?, course_id = ?, standard = ?,
                surname = ?, student_name = ?, fathers_name = ?, dob = ?, gender = ?, 
                parent_mob = ?, amob = ?, email = ?, gr_no = ?, religion = ?, caste = ?, 
                schoolname = ?, schaddr = ?, addr = ?, district = ?, 
                fathername = ?, fatheredu = ?, ocupation = ?, ofcaddr = ?, 
                hostel_required = ?, transport_required = ?, 
                updated_at = NOW() 
                WHERE id = ?";

            try {
                $stmt_upd = $conn->prepare($update_sql);
                $stmt_upd->execute([
                    $academic_year_id, $school_id, $board_id, $medium_id, $group_id, $course_id, $standard,
                    $surname, $student_name, $fathers_name, $dob, $gender,
                    $parent_mob, $amob, $email, $gr_no, $religion, $caste,
                    $schoolname, $schaddr, $addr, $district,
                    $fathername, $fatheredu, $ocupation, $ofcaddr,
                    $hostel_required, $transport_required,
                    $existing_student['id']
                ]);

                // Update enrollment details if division/roll_no provided and student is enrolled
                if ($existing_student['is_enrolled'] && $existing_student['enrollment_id']) {
                    $div_id = $divisions_map[$division_name] ?? null;
                    if ($div_id || !empty($roll_no)) {
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
                            $enr_params[] = $existing_student['enrollment_id'];
                            $stmt_enr_upd = $conn->prepare("UPDATE tbl_enrolled_students SET " . implode(", ", $enr_upd_parts) . " WHERE enrollment_id = ?");
                            $stmt_enr_upd->execute($enr_params);
                        }
                    }
                }

                $update_count++;
            }
            catch (PDOException $e) {
                $errors[] = "Row $row_number: Update failed - " . $e->getMessage();
                $error_count++;
            }
        }
        else {
            // INSERT Logic
            $hash_password = password_hash($mob, PASSWORD_DEFAULT);
            $counsellor_id = ($user_role == 3) ? $user_id : null;
            $declaration_agreed = 1;
            $status = 1;

            $sql_ins = "INSERT INTO tbl_gm_std_registration (
                    academic_year_id, school_id, board_id, medium_id, group_id, course_id, standard,
                    surname, student_name, fathers_name, dob, gender, mob, parent_mob, amob, email, aadhaar, gr_no, 
                    religion, caste, schoolname, schaddr, addr, district,
                    fathername, fatheredu, ocupation, ofcaddr, hostel_required, transport_required, 
                    hash_password, password, declaration_agreed, counsellor_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            try {
                $stmt_ins = $conn->prepare($sql_ins);
                $stmt_ins->execute([
                    $academic_year_id, $school_id, $board_id, $medium_id, $group_id, $course_id, $standard,
                    $surname, $student_name, $fathers_name, $dob, $gender, $mob, $parent_mob, $amob, $email, $aadhaar, $gr_no,
                    $religion, $caste, $schoolname, $schaddr, $addr, $district,
                    $fathername, $fatheredu, $ocupation, $ofcaddr, $hostel_required, $transport_required,
                    $hash_password, $mob, $declaration_agreed, $counsellor_id, $status
                ]);
                $success_count++;
            }
            catch (PDOException $e) {
                $errors[] = "Row $row_number: Insert failed - " . $e->getMessage();
                $error_count++;
            }
        }
        $row_number++;
    }

    // Prepare success message
    $message = "Bulk upload completed! ";
    if ($success_count > 0)
        $message .= "$success_count new student(s) imported. ";
    if ($update_count > 0)
        $message .= "$update_count student(s) updated. ";
    if ($error_count > 0) {
        $message .= "$error_count student(s) failed.";
        if (!empty($errors)) {
            $message .= "<br><strong>Errors:</strong><br>" . implode("<br>", array_slice($errors, 0, 5));
            if (count($errors) > 5)
                $message .= "<br>... and " . (count($errors) - 5) . " more errors";
        }
    }

    set_flash_message(($error_count == 0 || $success_count > 0 || $update_count > 0) ? 'success' : 'error', $message);
    @unlink($temp_file);
}
catch (Exception $e) {
    logError('Error processing student bulk upload file: ' . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', 'Error processing file: ' . $e->getMessage());
    @unlink($temp_file);
}

header('Location: ' . $redirect_path);
exit();
