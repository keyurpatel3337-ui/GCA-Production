<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Admission Confirmation Save Controller
 * Supports both API mode (JSON response) and direct form submission mode
 */

$base_path = dirname(dirname(__DIR__));

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Helper functions for API responses
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('sendErrorResponse')) {
    function sendErrorResponse($message, $statusCode = 400, $details = null)
    {
        $response = [
            'success' => false,
            'error' => $message
        ];
        if ($details !== null) {
            $response['details'] = $details;
        }
        sendJsonResponse($response, $statusCode);
    }
}

if (!function_exists('sendSuccessResponse')) {
    function sendSuccessResponse($data = null, $message = null)
    {
        $response = ['success' => true];
        if ($message !== null) {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }
        sendJsonResponse($response);
    }
}

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/fee_allocation_helper.php';
require_once $base_path . '/../common/helpers/format_helper.php';

$dbOps = new DatabaseOperations();

// For API calls, we may receive JSON input
if ($is_api_call) {
    $json_input = file_get_contents('php://input');
    $json_data = json_decode($json_input, true);
    if ($json_data) {
        $_POST = array_merge($_POST, $json_data);
    }
} else {
    // Prevent globalvariable.php from redirecting

    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Temporarily disable notification functions due to missing PHPMailer dependency
    // require_once $base_path . '/../common/helpers/notification_functions.php';
    // require_once $base_path . '/../common/helpers/whatsapp_functions.php';
    // require_once $base_path . '/../common/services/NotificationService.php';
    // Manual session check
    if (!isset($_SESSION['user_id'])) {
        set_flash_message('error', 'Please login to continue');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    // Check if user is Counsellor, Principal, or Super Admin
    if (!hasAnyRole([ROLE_COUNSELLOR, ROLE_PRINCIPLE, ROLE_SUPER_ADMIN])) {
        set_flash_message('error', 'Unauthorized access');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_api_call) {
        sendErrorResponse('Invalid request method', 405);
    }
    set_flash_message('error', "Invalid request method");
    header('Location: ../students/list.php');
    exit;
}

$student_id = $_POST['student_id'] ?? null;
$scholarship_rule_id = $_POST['scholarship_rule_id'] ?? null;
$scholarship_percentage = $_POST['scholarship_percentage'] ?? 0;
$scholarship_amount = $_POST['scholarship_amount'] ?? 0;
$additional_scholarship_type = $_POST['additional_scholarship_type'] ?? null;
$additional_scholarship_value = $_POST['additional_scholarship_value'] ?? 0;
$additional_scholarship_amount = $_POST['additional_scholarship_amount'] ?? 0;
$additional_scholarship_remarks = trim($_POST['additional_scholarship_remarks'] ?? '');
$payment_mode = $_POST['payment_mode'] ?? 'offline';
$remarks = trim($_POST['remarks'] ?? '');
$hostel_required = $_POST['hostel_required'] ?? 'No';
$transport_required = $_POST['transport_required'] ?? 'No';
$counsellor_id = $_SESSION['user_id'] ?? null;

// Validation
if (empty($student_id)) {
    if ($is_api_call) {
        sendErrorResponse('Student ID is required', 400);
    }
    set_flash_message('error', "Student ID is required");
    header('Location: ../students/list.php');
    exit;
}

try {
    logOfflineActivity("Fetching student information for admission confirm - Student ID: $student_id, Counsellor ID: $counsellor_id", 'INFO');

    // Verify student (for API, skip counsellor check)
    if ($is_api_call) {
        $student = $dbOps->customSelectOne(
            "SELECT s.*, c.course_name 
            FROM tbl_gm_std_registration s
            LEFT JOIN tbl_courses c ON s.course_id = c.id
            WHERE s.id = ?",
            [$student_id]
        );
    } else {
        $stmt = $conn->prepare("SELECT s.*, c.course_name 
                               FROM tbl_gm_std_registration s
                               LEFT JOIN tbl_courses c ON s.course_id = c.id
                               WHERE s.id = ? AND s.counsellor_id = ?");
        $stmt->execute([$student_id, $counsellor_id]);
        $student = $stmt->fetch();
    }

    if (!$student) {
        logError("Student not found or not assigned to counsellor - Student ID: $student_id, Counsellor ID: $counsellor_id");
        if ($is_api_call) {
            sendErrorResponse('Student not found or not assigned to you', 404);
        }
        set_flash_message('error', "Student not found or not assigned to you");
        header('Location: ../students/list.php');
        exit;
    }

    logOfflineActivity("Student information fetched successfully - Student: {$student['student_name']}, Course ID: {$student['course_id']}, Medium ID: {$student['medium_id']}, Group ID: {$student['group_id']}", 'INFO');

    // Check if already confirmed
    if ($student['admission_confirmed']) {
        if ($is_api_call) {
            sendErrorResponse('Admission already confirmed for this student', 400);
        }
        set_flash_message('error', "Admission already confirmed for this student");
        header('Location: admission-confirm.php?id=' . $student_id);
        exit;
    }

    // Fetch fee configuration to get token amount
    $token_amount = 0;
    $school_fee = 0;
    $trust_facilities_fee = 0;
    $tuition_fee_part1 = 0;
    try {
        logOfflineActivity("Fetching fee configuration - Course ID: {$student['course_id']}, Medium ID: {$student['medium_id']}, Group ID: {$student['group_id']}", 'INFO');

        $stmt_fee = $conn->prepare("SELECT school_fee, trust_facilities_fee, tuition_fee_part1 FROM tbl_fee_config 
                                    WHERE course_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 
                                    LIMIT 1");
        $stmt_fee->execute([$student['course_id'], $student['medium_id'], $student['group_id']]);
        $fee_config = $stmt_fee->fetch();

        if ($fee_config) {
            $school_fee = floatval($fee_config['school_fee']);
            $trust_facilities_fee = floatval($fee_config['trust_facilities_fee']);
            $tuition_fee_part1 = floatval($fee_config['tuition_fee_part1']);

            // Calculate token amount: Only Tuition Part 1 (with 18% GST)
            $gst_part1 = $tuition_fee_part1 * 0.18;
            $token_amount = $tuition_fee_part1 + $gst_part1;

            logOfflineActivity("Fee configuration fetched successfully - School Fee: ₹{$school_fee}, Trust Fee: ₹{$trust_facilities_fee}, Tuition Part 1: ₹{$tuition_fee_part1}, Token Amount (with GST): ₹{$token_amount}", 'INFO');
        } else {
            logError("No fee configuration found for Course ID: {$student['course_id']}, Medium ID: {$student['medium_id']}, Group ID: {$student['group_id']}");
        }
    } catch (PDOException $e) {
        logError("Error fetching fee configuration - Student ID: $student_id, Course: {$student['course_id']}, Medium: {$student['medium_id']}, Group: {$student['group_id']} - Error: " . $e->getMessage());
        if (!$is_api_call && function_exists('logDatabaseError')) {
            logDatabaseError($e, "Fetch Fee Configuration for Token Amount");
        }
    }

    // Calculate final scholarship amount based on type
    // NOTE: These amounts should INCLUDE 18% GST (calculated in frontend)
    // Frontend calculates: base discount amount + (base * 18% GST) = total with GST
    // Example: Base ₹5,600 + GST ₹1,008 = ₹6,608 (this ₹6,608 is what gets saved)
    $final_scholarship_amount = floatval($scholarship_amount);
    $final_scholarship_percentage = floatval($scholarship_percentage);
    $final_additional_amount = floatval($additional_scholarship_amount);
    $final_additional_value = floatval($additional_scholarship_value);

    // Generate admission letter number
    $admission_letter_number = 'ADM-' . date('Y') . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT);

    $conn->beginTransaction();

    // Update student record with admission confirmation
    $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                           SET admission_confirmed = 1,
                               admission_confirmed_by = ?,
                               admission_confirmed_date = NOW(),
                               scholarship_rule_id = ?,
                               scholarship_amount = ?,
                               scholarship_percentage = ?,
                               additional_scholarship_type = ?,
                               additional_scholarship_value = ?,
                               additional_scholarship_amount = ?,
                               additional_scholarship_remarks = ?,
                               admission_letter_number = ?,
                               admission_letter_generated = 1,
                               hostel_required = ?,
                               transport_required = ?,
                               updated_at = NOW()
                           WHERE id = ?");

    $stmt->execute([
        $counsellor_id,
        $scholarship_rule_id ?: null,
        $final_scholarship_amount,
        $final_scholarship_percentage,
        $additional_scholarship_type ?: null,
        $final_additional_value > 0 ? $final_additional_value : null,
        $final_additional_amount > 0 ? $final_additional_amount : null,
        !empty($additional_scholarship_remarks) ? $additional_scholarship_remarks : null,
        $admission_letter_number,
        $hostel_required,
        $transport_required,
        $student_id
    ]);

    // Assigned Token Fee as Pending Payment if configured
    if ($token_amount > 0) {
        $chk = $conn->prepare("SELECT id FROM tbl_pending_payments WHERE student_id = ? AND payment_type = 'token_fee'");
        $chk->execute([$student_id]);
        if (!$chk->fetch()) {
            $stmt = $conn->prepare("INSERT INTO tbl_pending_payments 
                                   (student_id, payment_type, amount, payment_gateway, transaction_id, status, created_at) 
                                   VALUES (?, 'token_fee', ?, 'offline', '', 'pending', NOW())");
            $stmt->execute([$student_id, $token_amount]);
        }
    }

    // Add Hostel Fee if required
    if (strtolower($hostel_required) === 'yes') {
        // Fetch hostel fee from hostel_fee_settings for current academic year
        try {
            logOfflineActivity("Fetching hostel fee configuration - Student ID: $student_id, Academic Year ID: {$student['academic_year_id']}, Gender: {$student['gender']}", 'INFO');

            $hostel_fee_query = "SELECT boys_hostel_fee, girls_hostel_fee, gst_applicable, gst_rate, security_deposit 
                                 FROM tbl_hostel_fee_settings 
                                 WHERE academic_year_id = ? AND is_active = 1 
                                 LIMIT 1";
            $hostel_stmt = $conn->prepare($hostel_fee_query);
            $hostel_stmt->execute([$student['academic_year_id']]);
            $hostel_config = $hostel_stmt->fetch();

            if ($hostel_config) {
                $hostel_fee_amount = ($student['gender'] === 'Male') ? $hostel_config['boys_hostel_fee'] : $hostel_config['girls_hostel_fee'];

                // Apply GST if applicable
                if ($hostel_config['gst_applicable'] == 1 && $hostel_config['gst_rate'] > 0) {
                    $hostel_gst = $hostel_fee_amount * ($hostel_config['gst_rate'] / 100);
                    $hostel_fee_amount += $hostel_gst;
                    logOfflineActivity("Hostel fee GST applied - Base: ₹{$hostel_config['boys_hostel_fee']}, GST Rate: {$hostel_config['gst_rate']}%, Final: ₹{$hostel_fee_amount}", 'INFO');
                }

                // Check if hostel fee already exists
                $chk_hostel = $conn->prepare("SELECT id FROM tbl_pending_payments WHERE student_id = ? AND payment_type = 'hostel_fee'");
                $chk_hostel->execute([$student_id]);
                if (!$chk_hostel->fetch() && $hostel_fee_amount > 0) {
                    $stmt_hostel = $conn->prepare("INSERT INTO tbl_pending_payments 
                                                   (student_id, payment_type, amount, payment_gateway, transaction_id, status, created_at) 
                                                   VALUES (?, 'hostel_fee', ?, 'offline', '', 'pending', NOW())");
                    $stmt_hostel->execute([$student_id, $hostel_fee_amount]);
                    logOfflineActivity("Hostel fee added to pending payments - Student ID: $student_id, Amount: ₹{$hostel_fee_amount}", 'INFO');
                } else {
                    logOfflineActivity("Hostel fee not added - Already exists or amount is 0 - Student ID: $student_id", 'INFO');
                }
            } else {
                logError("No hostel fee configuration found for Academic Year ID: {$student['academic_year_id']}");
            }
        } catch (PDOException $e) {
            logError("Error adding hostel fee - Student ID: $student_id, Academic Year: {$student['academic_year_id']} - Error: " . $e->getMessage());
            if (!$is_api_call && function_exists('logDatabaseError')) {
                logDatabaseError($e, "Add Hostel Fee");
            }
        }
    }

    // Add Transport Fee if required  
    if (strtolower($transport_required) === 'yes') {
        // Fetch transport fee (monthly rate) from transport_fee_settings for current academic year
        try {
            logOfflineActivity("Fetching transport fee configuration - Student ID: $student_id, Academic Year ID: {$student['academic_year_id']}", 'INFO');

            $transport_fee_query = "SELECT transport_fee, gst_rate, term1_months, term2_months, annual_months 
                                    FROM tbl_transport_fee_settings 
                                    WHERE academic_year_id = ? AND course_id = ? AND is_active = 1 
                                    LIMIT 1";
            $transport_stmt = $conn->prepare($transport_fee_query);
            $transport_stmt->execute([$student['academic_year_id'], $student['course_id']]);
            $transport_config = $transport_stmt->fetch();

            if (!$transport_config) {
                // Fallback to global setting for this AY
                $transport_fee_query = "SELECT transport_fee, gst_rate, term1_months, term2_months, annual_months 
                                        FROM tbl_transport_fee_settings 
                                        WHERE academic_year_id = ? AND (course_id IS NULL OR course_id = 0) AND is_active = 1 
                                        LIMIT 1";
                $transport_stmt = $conn->prepare($transport_fee_query);
                $transport_stmt->execute([$student['academic_year_id']]);
                $transport_config = $transport_stmt->fetch();
            }

            if ($transport_config) {
                $monthly_rate = floatval($transport_config['transport_fee']);
                $gst_rate = floatval($transport_config['gst_rate']);
                $timeline = $transport_config['collection_timeline'] ?? 'Term-wise';
                $t1_m = intval($transport_config['term1_months'] ?? 7);
                $t2_m = intval($transport_config['term2_months'] ?? 6);
                $an_m = intval($transport_config['annual_months'] ?? 12);
                $course_id = intval($student['course_id']);

                // Calculations based on Standard & Timeline
                $allocations = [];
                if ($timeline === 'Term-wise') {
                    if ($course_id == 1 || $course_id == 2) {
                        // 11th - Term Wise
                        $allocations[] = ['label' => "Transport Fee (Term 1 - $t1_m Months)", 'months' => $t1_m];
                        $allocations[] = ['label' => "Transport Fee (Term 2 - $t2_m Months)", 'months' => $t2_m];
                    } else {
                        // Default / 12th - Annual
                        $allocations[] = ['label' => "Transport Fee (Annual - $an_m Months)", 'months' => $an_m];
                    }
                } elseif ($timeline === 'Annually') {
                    $allocations[] = ['label' => "Transport Fee (Annual)", 'months' => $an_m];
                } elseif ($timeline === 'Half-Yearly') {
                    $half_annual = $an_m / 2;
                    $allocations[] = ['label' => "Transport Fee (Half-Yearly 1)", 'months' => $half_annual];
                    $allocations[] = ['label' => "Transport Fee (Half-Yearly 2)", 'months' => $half_annual];
                } elseif ($timeline === 'Quarterly') {
                    // Split into terms for allocation
                    $allocations[] = ['label' => "Transport Fee (Term 1 Portion)", 'months' => $t1_m];
                    $allocations[] = ['label' => "Transport Fee (Term 2 Portion)", 'months' => $t2_m];
                } elseif ($timeline === 'Monthly') {
                    $allocations[] = ['label' => "Transport Fee (Term 1 Portion)", 'months' => $t1_m];
                    $allocations[] = ['label' => "Transport Fee (Term 2 Portion)", 'months' => $t2_m];
                }

                foreach ($allocations as $alloc) {
                    $base_amount = $monthly_rate * $alloc['months'];
                    $final_amount = $base_amount;

                    if ($gst_rate > 0) {
                        $final_amount += ($base_amount * ($gst_rate / 100));
                    }

                    // Check if this specific transport fee component already exists to avoid duplicates
                    $chk_transport = $conn->prepare("SELECT id FROM tbl_pending_payments WHERE student_id = ? AND payment_type = 'transport_fee' AND amount = ?");
                    $chk_transport->execute([$student_id, $final_amount]);
                    
                    if (!$chk_transport->fetch() && $final_amount > 0) {
                        $stmt_transport = $conn->prepare("INSERT INTO tbl_pending_payments 
                                                         (student_id, payment_type, amount, payment_gateway, transaction_id, status, created_at) 
                                                         VALUES (?, 'transport_fee', ?, 'offline', '', 'pending', NOW())");
                        $stmt_transport->execute([$student_id, $final_amount]);
                        logOfflineActivity("Transport fee added: {$alloc['label']} - Amount: ₹{$final_amount}", 'INFO');
                    }
                }
            } else {
                logError("No transport fee configuration found for Academic Year ID: {$student['academic_year_id']}");
            }
        } catch (PDOException $e) {
            logError("Error adding transport fee - Student ID: $student_id - Error: " . $e->getMessage());
            if (!$is_api_call && function_exists('logDatabaseError')) {
                logDatabaseError($e, "Add Transport Fee");
            }
        }
    }

    $conn->commit();

    // Automatically create fee allocation record if missing and synchronize
    ensureFeeAllocation($conn, $student_id);

    // Send notifications only for non-API calls
    if (!$is_api_call) {
        // Send admission confirmation notification
        try {
            $full_name = trim($student['surname'] . ' ' . $student['student_name']);
            $email = $student['email'] ?? '';

            $recipient = [
                'name' => $full_name,
                'email' => $email,
                'mobile' => $student['mob']
            ];

            $variables = [
                'student_name' => $full_name,
                'course_name' => $student['course_name'] ?? 'N/A',
                'admission_letter_number' => $admission_letter_number,
                'token_amount' => formatIndianCurrency($token_amount),
                'payment_mode' => $payment_mode === 'online' ? 'Online' : 'Offline',
                'aadhaar' => $student['aadhaar'],
                'password' => $student['mob'],
                'confirmation_date' => date('d-M-Y')
            ];

            if (function_exists('sendNotification')) {
                sendNotification(
                    $conn,
                    'admission_confirmed',
                    $recipient,
                    $variables,
                    ['student_id' => $student_id]
                );
            }
        } catch (Exception $e) {
            error_log("Admission confirmation notification error: " . $e->getMessage());
        }

        // Create in-app notification for the student
        try {
            $full_name = trim($student['surname'] . ' ' . $student['student_name']);

            if (function_exists('createInAppNotification')) {
                createInAppNotification(
                    'student',
                    $student_id,
                    'admission_confirmed',
                    'Admission Confirmed!',
                    "Congratulations! Your admission has been confirmed. Admission Letter: {$admission_letter_number}",
                    [
                        'link' => "../student-portal/dashboard.php",
                        'priority' => 'high',
                        'reference_type' => 'admission',
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
            'admission_letter_number' => $admission_letter_number,
            'token_amount' => $token_amount,
            'payment_mode' => $payment_mode
        ], 'Admission confirmed successfully');
    }

    // Set success message based on payment mode
    if ($payment_mode === 'online') {
        set_flash_message('success', "Admission confirmed successfully! Admission Letter No: " . $admission_letter_number .
            ". Student can now login to the portal using their Aadhaar and Mobile number to pay token fee online.");
    } else {
        set_flash_message('success', "Admission confirmed successfully! Admission Letter No: " . $admission_letter_number .
            ". Student should visit accounts department for token fee payment.");
    }

    header('Location: admission-letter.php?id=' . $student_id);
    exit;
} catch (PDOException $e) {
    $conn->rollBack();
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Confirm Admission");
    }
    if ($is_api_call) {
        sendErrorResponse('Error confirming admission: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', "Error confirming admission. Please try again.");
    header('Location: admission-confirm.php?id=' . $student_id);
    exit;
}
