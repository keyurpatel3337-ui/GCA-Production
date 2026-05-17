<?php

/**
 * Bulk Upload Controller
 * API endpoint for bulk student upload via CSV
 * 
 * Route: POST /index.php?route=students/bulk-upload
 * Accepts: multipart/form-data with CSV file and form fields
 */

require_once __DIR__ . '/../../../common/bootstrap.php';
require_once __DIR__ . '/../../../common/helpers/parent_functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('Unauthorized', 401);
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Validate required POST fields
$required_fields = ['academic_year_id', 'school_id', 'board_id', 'medium_id', 'group_id', 'course_id'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        sendErrorResponse("Missing required field: $field", 400);
    }
}

$academic_year_id = intval($_POST['academic_year_id']);
$school_id = intval($_POST['school_id']);
$board_id = intval($_POST['board_id']);
$medium_id = intval($_POST['medium_id']);
$group_id = intval($_POST['group_id']);
$course_id = intval($_POST['course_id']);

// Validate IDs are positive
if ($academic_year_id <= 0 || $school_id <= 0 || $board_id <= 0 || $medium_id <= 0 || $group_id <= 0 || $course_id <= 0) {
    sendErrorResponse('Invalid selection. Please check your inputs.', 400);
}

// Fetch standard from selected course
$standard = '';
try {
    $stmt = $conn->prepare("SELECT standard FROM tbl_courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_data = $stmt->fetch();
    if ($course_data) {
        $standard = $course_data['standard'] ?? '';
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Course Standard");
}

// Verify that selected IDs exist in database
try {
    // Validate CSV file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        sendErrorResponse('Please upload a valid CSV file', 400);
    }

    // MIME type validation for CSV
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['csv_file']['tmp_name']);
    finfo_close($finfo);
    $allowed_mime_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

    if (!in_array($mimeType, $allowed_mime_types)) {
        sendErrorResponse('Invalid file type. Please upload a valid CSV file.', 400);
    }
    $validations = [
        ['table' => 'tbl_academic_years', 'id' => $academic_year_id, 'name' => 'academic year'],
        ['table' => 'tbl_boards', 'id' => $board_id, 'name' => 'board'],
        ['table' => 'tbl_medium', 'id' => $medium_id, 'name' => 'medium'],
        ['table' => 'tbl_group', 'id' => $group_id, 'name' => 'group'],
        ['table' => 'tbl_courses', 'id' => $course_id, 'name' => 'course']
    ];

    foreach ($validations as $v) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM {$v['table']} WHERE id = ? AND is_active = 1");
        $stmt->execute([$v['id']]);
        if ($stmt->fetchColumn() == 0) {
            sendErrorResponse("Selected {$v['name']} is not valid", 400);
        }
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Validate IDs for bulk upload");
    sendErrorResponse('Database error occurred during validation', 500);
}

// Check if file was uploaded
if (!isset($_FILES['csv_file'])) {
    sendErrorResponse('Please select a CSV file to upload', 400);
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
    $error_message = $upload_errors[$error_code] ?? 'Unknown upload error';
    sendErrorResponse('File upload failed: ' . $error_message, 400);
}

$file = $_FILES['csv_file'];
$allowed = ['csv'];
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Check file extension
if (!in_array($ext, $allowed)) {
    sendErrorResponse('Invalid file type. Only .csv files are allowed', 400);
}

// Check file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    sendErrorResponse('File size exceeds 5MB limit', 400);
}

// Move uploaded file to temp location
$upload_dir = __DIR__ . '/../../uploads/temp/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$temp_file = $upload_dir . uniqid('student_upload_') . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
    sendErrorResponse('Failed to upload file. Please check permissions.', 500);
}

// Parse CSV file
try {
    $rows = [];
    if (($handle = fopen($temp_file, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    } else {
        @unlink($temp_file);
        sendErrorResponse('Failed to open CSV file', 500);
    }

    if (count($rows) < 2) {
        @unlink($temp_file);
        sendErrorResponse('CSV file is empty or contains only headers', 400);
    }

    // Remove header row
    array_shift($rows);

    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $row_number = 2; // Starting from row 2 (after header)

    foreach ($rows as $row) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            $row_number++;
            continue;
        }

        // Validate core required fields
        if (
            empty($row[0]) || empty($row[1]) || empty($row[2]) || empty($row[3]) ||
            empty($row[4]) || empty($row[5]) || empty($row[7]) || empty($row[8]) ||
            empty($row[9]) || empty($row[11])
        ) {
            $errors[] = "Row $row_number: Required fields are missing";
            $error_count++;
            $row_number++;
            continue;
        }

        // Extract and sanitize data
        $surname = trim($row[0]);
        $student_name = trim($row[1]);
        $fathers_name = trim($row[2]);
        $dob_raw = trim($row[3]);
        $gender = trim($row[4]);
        $mob = trim($row[5]);
        $amob = !empty($row[6]) ? trim($row[6]) : '';
        $aadhaar = trim($row[7]);
        $schoolname = trim($row[8]);
        $schaddr = trim($row[9]);
        $addr = !empty($row[10]) ? trim($row[10]) : '';
        $district = trim($row[11]);
        $fathername = !empty($row[12]) ? trim($row[12]) : $fathers_name;
        $fatheredu = !empty($row[13]) ? trim($row[13]) : '';
        $ocupation = !empty($row[14]) ? trim($row[14]) : '';
        $ofcaddr = !empty($row[15]) ? trim($row[15]) : '';
        $hostel_required_raw = !empty($row[16]) ? trim($row[16]) : 'No';

        // Convert date format
        $dob = $dob_raw;
        if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $dob_raw, $matches)) {
            $dob = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        $password = $mob;

        // Validate mobile number
        if (!preg_match('/^[0-9]{10}$/', $mob)) {
            $errors[] = "Row $row_number: Invalid mobile number format";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate alternate mobile if provided
        if (!empty($amob) && !preg_match('/^[0-9]{10}$/', $amob)) {
            $errors[] = "Row $row_number: Invalid alternate mobile number format";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate Aadhaar
        if (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
            $errors[] = "Row $row_number: Invalid Aadhaar number format";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $errors[] = "Row $row_number: Invalid date format";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate gender
        if (!in_array($gender, ['Male', 'Female', 'Other'])) {
            $errors[] = "Row $row_number: Invalid gender";
            $error_count++;
            $row_number++;
            continue;
        }

        // Validate hostel required
        $hostel_required = ucfirst(strtolower($hostel_required_raw));
        if (!in_array($hostel_required, ['Yes', 'No'])) {
            $errors[] = "Row $row_number: Invalid hostel required value";
            $error_count++;
            $row_number++;
            continue;
        }

        // Check if mobile number already exists
        $check_mob = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE mob = ?");
        $check_mob->execute([$mob]);
        if ($check_mob->fetch()) {
            $errors[] = "Row $row_number: Mobile number already exists";
            $error_count++;
            $row_number++;
            continue;
        }

        // Check if Aadhaar already exists
        $check_aadhaar = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE aadhaar = ?");
        $check_aadhaar->execute([$aadhaar]);
        if ($check_aadhaar->fetch()) {
            $errors[] = "Row $row_number: Aadhaar number already exists";
            $error_count++;
            $row_number++;
            continue;
        }

        // Hash password
        $hash_password = password_hash($password, PASSWORD_DEFAULT);

        // Determine counsellor_id
        $counsellor_id = null;
        if ($user_role == 3) { // ROLE_COUNSELLOR
            $counsellor_id = $user_id;
        }

        $declaration_agreed = 1;
        $status = 1;

        // Insert student record
        $sql = "INSERT INTO tbl_gm_std_registration (
                academic_year_id, school_id, surname, student_name, fathers_name, dob, gender, board_id, medium_id, 
                group_id, course_id, standard, mob, amob, aadhaar, schoolname, schaddr, addr, district,
                fathername, fatheredu, ocupation, ofcaddr, hostel_required, 
                hash_password, password, declaration_agreed, counsellor_id, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $academic_year_id,
                $school_id,
                $surname,
                $student_name,
                $fathers_name,
                $dob,
                $gender,
                $board_id,
                $medium_id,
                $group_id,
                $course_id,
                $standard,
                $mob,
                $amob,
                $aadhaar,
                $schoolname,
                $schaddr,
                $addr,
                $district,
                $fathername,
                $fatheredu,
                $ocupation,
                $ofcaddr,
                $hostel_required,
                $hash_password,
                $password,
                $declaration_agreed,
                $counsellor_id,
                $status
            ]);

            // Create or link parent account automatically
            createParentAccount($mob, $conn);

            $success_count++;
        } catch (PDOException $e) {
            $errors[] = "Row $row_number: Database error - " . $e->getMessage();
            $error_count++;
        }

        $row_number++;
    }

    // Clean up temp file
    @unlink($temp_file);

    // Return response
    sendJsonResponse([
        'success' => $success_count > 0,
        'message' => "Bulk upload completed! $success_count student(s) imported successfully." .
            ($error_count > 0 ? " $error_count student(s) failed." : ""),
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => array_slice($errors, 0, 10) // Limit to first 10 errors
    ]);
} catch (Exception $e) {
    @unlink($temp_file);
    sendErrorResponse('Error processing file: ' . $e->getMessage(), 500);
}
