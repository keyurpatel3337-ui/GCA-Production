<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
/**
 * Student Save Controller
 * Handles student registration/creation
 * Supports both API mode (JSON response) and direct form submission mode
 */

$base_path = dirname(dirname(__DIR__));

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Start session only if not API call or session not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once DB_CONNECT_FILE;

// For API calls, we may receive JSON input
if ($is_api_call) {
    $json_input = file_get_contents('php://input');
    $json_data = json_decode($json_input, true);
    if ($json_data) {
        $_POST = array_merge($_POST, $json_data);
    }
} else {
    // require_once OPERATION_FILE; // Removed to avoid duplicate class declaration
    require_once $base_path . '/../common/helpers/notification_functions.php';
    require_once $base_path . '/../common/helpers/whatsapp_functions.php';
    require_once $base_path . '/../common/services/NotificationService.php';
    require_once $base_path . '/../common/helpers/parent_functions.php';
    // Check if user has appropriate role (admin, principle, or counsellor)
    if (!isset($_SESSION['user_id']) || (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR))) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

// Helper function for error response
function handleError($message, $is_api, $redirect = 'list.php')
{
    if ($is_api) {
        sendErrorResponse($message, 400);
    }
    set_flash_message('error', $message);
    header('Location: ' . $redirect);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $school_id = $_POST['school_id'] ?? '';
    $campus_id = $_POST['campus_id'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    $fathers_name = $_POST['fathers_name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $board_id = $_POST['board_id'] ?? '';
    $medium_id = $_POST['medium_id'] ?? '';
    $group_id = $_POST['group_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $standard = $_POST['standard'] ?? '';
    $mob = $_POST['mob'] ?? '';
    $parent_mob = $_POST['parent_mob'] ?? $mob; // Default to mob if not provided
    $amob = $_POST['amob'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $aadhaar = $_POST['aadhaar'] ?? '';
    $schoolname = $_POST['schoolname'] ?? '';
    $schaddr = $_POST['schaddr'] ?? '';
    $addr = $_POST['addr'] ?? '';
    $district = $_POST['district'] ?? '';
    $fathername = $_POST['fathername'] ?? '';
    $fatheredu = $_POST['fatheredu'] ?? '';
    $ocupation = $_POST['ocupation'] ?? '';
    $ofcaddr = $_POST['ofcaddr'] ?? '';
    $gr_no = $_POST['gr_no'] ?? '';
    $religion = $_POST['religion'] ?? '';
    $caste = $_POST['caste'] ?? '';
    $hostel_required = $_POST['hostel_required'] ?? 0;
    $transport_required = $_POST['transport_required'] ?? 'No';
    $transport_months = !empty($_POST['transport_months']) ? intval($_POST['transport_months']) : null;
    // Use mobile number as password
    $password = $mob;
    $confirm_password = $mob;
    $declaration_agreed = isset($_POST['declaration_agreed']) ? 1 : 0;

    // Validate school selection
    if (empty($school_id)) {
        handleError("Please select a school!", $is_api_call, 'add.php');
    }

    // Validate campus selection
    if (empty($campus_id)) {
        handleError("Please select a campus!", $is_api_call, 'add.php');
    }

    // Validate course selection
    if (empty($course_id)) {
        handleError("Please select a course!", $is_api_call, 'add.php');
    }

    // Validate mobile number
    if (!preg_match('/^[0-9]{10}$/', $mob)) {
        handleError("Please enter a valid 10-digit mobile number!", $is_api_call);
    }

    // Validate alternate mobile if provided
    if (!empty($amob) && !preg_match('/^[0-9]{10}$/', $amob)) {
        handleError("Please enter a valid 10-digit alternate mobile number!", $is_api_call);
    }

    // Validate Aadhaar number
    if (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
        handleError("Please enter a valid 12-digit Aadhaar number!", $is_api_call);
    }

    // Check if mobile number already exists
    $checkStmt = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE mob = ?");
    $checkStmt->execute([$mob]);
    if ($checkStmt->rowCount() > 0) {
        handleError("Mobile number already registered!", $is_api_call);
    }

    // Check if Aadhaar already exists
    $checkStmt = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE aadhaar = ?");
    $checkStmt->execute([$aadhaar]);
    if ($checkStmt->rowCount() > 0) {
        handleError("Aadhaar number already registered!", $is_api_call);
    }

    // Hash the password
    $hash_password = password_hash($password, PASSWORD_DEFAULT);

    // Determine counsellor_id - if logged in user is a counsellor, assign them automatically
    $counsellor_id = null;
    if (!$is_api_call && function_exists('hasRole') && hasRole(ROLE_COUNSELLOR)) {
        $counsellor_id = $_SESSION['user_id'];
    } elseif (isset($_POST['counsellor_id'])) {
        $counsellor_id = $_POST['counsellor_id'];
    }

    // Insert student data
    $sql = "INSERT INTO tbl_gm_std_registration (
        school_id, campus_id, surname, student_name, fathers_name, dob, gender, board_id, medium_id, group_id, course_id, standard,
        mob, parent_mob, amob, email, aadhaar, schoolname, schaddr, addr, district, fathername, 
        fatheredu, ocupation, ofcaddr, gr_no, religion, caste, hostel_required, transport_required, transport_months, hash_password, password, 
        counsellor_id, declaration_agreed, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
    )";

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $school_id, $campus_id, $surname, $student_name, $fathers_name, $dob, $gender, $board_id, $medium_id, $group_id, $course_id, $standard,
            $mob, $parent_mob, $amob, $email, $aadhaar, $schoolname, $schaddr, $addr, $district, $fathername, 
            $fatheredu, $ocupation, $ofcaddr, $gr_no, $religion, $caste, $hostel_required, $transport_required, $transport_months, $hash_password, $password, 
            $counsellor_id, $declaration_agreed
        ]);

        $student_id = $conn->lastInsertId();

        // Check if this is a Direct 12th Admission
        $is_direct_12th = (isset($_POST['admission_type']) && $_POST['admission_type'] === 'direct') || floatval($standard) == 12;

        if ($is_direct_12th) {
            // 1. Confirm Admission
            $admission_letter_number = 'ADM-DIR-' . date('Y') . '-' . time() . '-' . $student_id;
            $stmt_conf = $conn->prepare("UPDATE tbl_gm_std_registration SET 
                admission_confirmed = 1, 
                admission_confirmed_by = ?, 
                admission_confirmed_date = NOW(),
                admission_letter_number = ?,
                admission_letter_generated = 1,
                status = 1 
                WHERE id = ?");
            $stmt_conf->execute([$_SESSION['user_id'] ?? null, $admission_letter_number, $student_id]);

            // 2. Generate Enrollment
            $admission_year = date('y');
            $completion_year = date('y', strtotime('+1 year'));
            $prefix = $admission_year . $completion_year;

            $stmt_seq = $conn->prepare("SELECT MAX(CAST(SUBSTRING(enrollment_no, 5) AS UNSIGNED)) as last_seq FROM tbl_enrolled_students WHERE enrollment_no LIKE ?");
            $stmt_seq->execute([$prefix . '%']);
            $last_seq = intval($stmt_seq->fetchColumn() ?: 0);
            $enrollment_no = $prefix . str_pad($last_seq + 1, 5, '0', STR_PAD_LEFT);

            $stmt_enr = $conn->prepare("INSERT INTO tbl_enrolled_students (registration_id, enrollment_no, current_term_id, enrollment_date, enrollment_status, is_active, created_at, enrolled_by) VALUES (?, ?, 1, NOW(), 'active', 1, NOW(), ?)");
            $stmt_enr->execute([$student_id, $enrollment_no, $_SESSION['user_id'] ?? null]);
            $enrollment_id = $conn->lastInsertId();

            // Link registration
            $stmt_link = $conn->prepare("UPDATE tbl_gm_std_registration SET is_enrolled = 1, enrollment_id = ?, enrollment_date = NOW() WHERE id = ?");
            $stmt_link->execute([$enrollment_id, $student_id]);

            // 3. Allocate Fees
            // Fetch Active Academic Year
            $stmt_ay = $conn->prepare("SELECT id, year_name FROM tbl_academic_years WHERE is_active = 1 LIMIT 1");
            $stmt_ay->execute();
            $ay = $stmt_ay->fetch(PDO::FETCH_ASSOC);

            if ($ay) {
                // Fetch Fee Config
                $stmt_fee = $conn->prepare("SELECT * FROM tbl_fee_config 
                    WHERE academic_year = ? AND course_id = ? AND school_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 
                    LIMIT 1");
                $stmt_fee->execute([$ay['year_name'], $course_id, $school_id, $medium_id, $group_id]);
                $fee_config = $stmt_fee->fetch(PDO::FETCH_ASSOC);

                if ($fee_config) {
                    $school_fee = floatval($fee_config['school_fee']);
                    $trust_fee = floatval($fee_config['trust_facilities_fee']);
                    $tuition_part1 = floatval($fee_config['tuition_fee_part1']) * 1.18;
                    $tuition_part2 = floatval($fee_config['tuition_fee_part2']) * 1.18;
                    $total_fees = $school_fee + $trust_fee + $tuition_part1 + $tuition_part2;

                    $stmt_alloc = $conn->prepare("INSERT INTO tbl_student_fee_allocation (student_id, fee_config_id, allocated_amount, paid_amount, pending_amount, status, academic_year, allocated_by, created_by, allocated_at) VALUES (?, ?, ?, 0, ?, 'pending', ?, ?, ?, NOW())");
                    $stmt_alloc->execute([$student_id, $fee_config['id'], $total_fees, $total_fees, $ay['year_name'], $_SESSION['user_id'] ?? null, $_SESSION['user_id'] ?? null]);

                    // Add to Pending Payments
                    $stmt_pending = $conn->prepare("INSERT INTO tbl_pending_payments (student_id, payment_type, amount, status, created_at) VALUES (?, 'total_fee', ?, 'pending', NOW())");
                    $stmt_pending->execute([$student_id, $total_fees]);
                }
            }
        }

        $conn->commit();

        // Create or link parent account automatically using parent_mob
        if (!empty($parent_mob)) {
            createParentAccount($parent_mob, $conn);
        }

        // Send notifications only if not API call (to avoid loading unnecessary dependencies)
        if (!$is_api_call) {
            // Send registration success notification
            try {
                // Fetch course name
                $courseStmt = $conn->prepare("SELECT course_name FROM tbl_courses WHERE id = ?");
                $courseStmt->execute([$course_id]);
                $course = $courseStmt->fetch();

                // Fetch group name
                $groupStmt = $conn->prepare("SELECT group_name FROM tbl_group WHERE id = ?");
                $groupStmt->execute([$group_id]);
                $group = $groupStmt->fetch();

                $full_name = trim($surname . ' ' . $student_name);

                $recipient = [
                    'name' => $full_name,
                    'email' => $email,
                    'mobile' => $mob
                ];

                // Template: registration_success_001
                // Variables: {{1}}=Name, {{2}}=Course, {{3}}=Group, {{4}}=Date, {{5}}=RegID
                $variables = [
                    'student_name' => $full_name,
                    'course_name' => $course['course_name'] ?? 'N/A',
                    'group_name' => $group['group_name'] ?? 'N/A',
                    'registration_date' => date('d-M-Y'),
                    'registration_id' => 'REG-' . str_pad($student_id, 4, '0', STR_PAD_LEFT)
                ];

                // Send registration success notification
                if (function_exists('sendNotification')) {
                    sendNotification(
                        $conn,
                        'registration_success',
                        $recipient,
                        $variables,
                        ['student_id' => $student_id]
                    );
                }
            } catch (Exception $e) {
                // Log notification error but don't fail registration
                error_log("Registration notification error: " . $e->getMessage());
            }

            // Create in-app notification for admins and counsellors
            try {
                $full_name = trim($surname . ' ' . $student_name);
                $courseStmt = $conn->prepare("SELECT course_name FROM tbl_courses WHERE id = ?");
                $courseStmt->execute([$course_id]);
                $course = $courseStmt->fetch();

                // Notify admins about new registration
                if (function_exists('notifyAdmins')) {
                    notifyAdmins(
                        'new_registration',
                        'New Student Registration',
                        "New student {$full_name} has registered for {$course['course_name']}",
                        [
                            'link' => "view.php?id={$student_id}",
                            'reference_type' => 'student',
                            'reference_id' => $student_id
                        ]
                    );
                }
            } catch (Exception $e) {
                error_log("In-app notification error: " . $e->getMessage());
            }
        }

        // Return response
        if ($is_api_call) {
            sendSuccessResponse([
                'student_id' => $student_id,
                'message' => 'Student registered successfully'
            ], 'Student registered successfully');
        }

        set_flash_message('success', "Student registered successfully!");
        header('Location: list.php');
        exit();
    } catch (PDOException $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($is_api_call) {
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
        set_flash_message('error', "Error: " . $e->getMessage());
        header('Location: add.php');
        exit();
    }
} else {
    if ($is_api_call) {
        sendErrorResponse('Method not allowed. Use POST.', 405);
    }
    header('Location: list.php');
    exit();
}
