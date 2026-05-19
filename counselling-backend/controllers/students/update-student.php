<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
/**
 * Student Update Controller
 * Handles student information updates
 * Supports both API mode (JSON response) and direct form submission mode
 */

$base_path = dirname(dirname(__DIR__));

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Start session only if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
$dbOps = new DatabaseOperations();
require_once $base_path . '/../common/helpers/fee_allocation_helper.php';

// For API calls, we may receive JSON input
if ($is_api_call) {
    $json_input = file_get_contents('php://input');
    $json_data = json_decode($json_input, true);
    if ($json_data) {
        $_POST = array_merge($_POST, $json_data);
    }
} else {
    // require_once OPERATION_FILE; // Removed to avoid duplicate class declaration
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is logged in and has appropriate role
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        set_flash_message('error', 'Unauthorized access');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Helper function for error handling
function handleUpdateError($message, $is_api, $student_id = null)
{
    if ($is_api) {
        sendErrorResponse($message, 400);
    }
    set_flash_message('error', $message);
    if ($student_id) {
        header('Location: edit-student.php?id=' . $student_id);
    } else {
        header('Location: list.php');
    }
    exit;
}

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleUpdateError('Invalid request method', $is_api_call);
}

// Get and validate student ID
if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
    handleUpdateError('Student ID is required', $is_api_call);
}

$student_id = intval($_POST['student_id']);

// Verify student exists and check authorization
try {
    $stmt = $conn->prepare("SELECT counsellor_id FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        handleUpdateError('Student not found', $is_api_call);
    }

    // If counsellor, verify they can only edit their assigned students (skip for API)
    if (!$is_api_call && function_exists('hasRole') && hasRole(ROLE_COUNSELLOR) && $student['counsellor_id'] != $_SESSION['user_id']) {
        handleUpdateError('You can only edit students assigned to you', $is_api_call);
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Verify Student for Update");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', 'Database error occurred');
    header('Location: list.php');
    exit;
}

// Get and sanitize form data
$school_id = $_POST['school_id'] ?? null;
$campus_id = $_POST['campus_id'] ?? null;
$surname = trim($_POST['surname'] ?? '');
$student_name = trim($_POST['student_name'] ?? '');
$fathers_name = trim($_POST['fathers_name'] ?? '');
$dob = $_POST['dob'] ?? null;
$gender = $_POST['gender'] ?? null;
$board_id = $_POST['board_id'] ?? null;
$medium_id = $_POST['medium_id'] ?? null;
$group_id = $_POST['group_id'] ?? null;
$course_id = $_POST['course_id'] ?? null;
$mob = trim($_POST['mob'] ?? '');
$parent_mob = trim($_POST['parent_mob'] ?? $mob);
$amob = trim($_POST['amob'] ?? '');
$email = trim($_POST['email'] ?? '');
$aadhaar = trim($_POST['aadhaar'] ?? '');
$division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : null;
$roll_no = (isset($_POST['roll_no']) && trim($_POST['roll_no']) !== '') ? trim($_POST['roll_no']) : null;
$schoolname = trim($_POST['schoolname'] ?? '');
$schaddr = trim($_POST['schaddr'] ?? '');
$addr = trim($_POST['addr'] ?? '');
$district = trim($_POST['district'] ?? '');
$fathername = trim($_POST['fathername'] ?? '');
$fatheredu = trim($_POST['fatheredu'] ?? '');
$ocupation = trim($_POST['ocupation'] ?? '');
$ofcaddr = trim($_POST['ofcaddr'] ?? '');
$gr_no = trim($_POST['gr_no'] ?? '');
$religion = trim($_POST['religion'] ?? '');
$caste = trim($_POST['caste'] ?? '');
$hostel_required = $_POST['hostel_required'] ?? 'No';
$transport_required = $_POST['transport_required'] ?? 'No';
$transport_months = !empty($_POST['transport_months']) ? intval($_POST['transport_months']) : null;
$status = isset($_POST['status']) ? intval($_POST['status']) : 1;

// Get old data before update to detect changes
try {
    $oldDataStmt = $conn->prepare("SELECT board_id, medium_id, group_id, course_id, school_id, scholarship_amount, additional_scholarship_amount, enrollment_id FROM tbl_gm_std_registration WHERE id = ?");
    $oldDataStmt->execute([$student_id]);
    $oldData = $oldDataStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $oldData = null;
}

// Validate required fields
$errors = [];

if (empty($school_id))
    $errors[] = 'School is required';
if (empty($campus_id))
    $errors[] = 'Campus is required';
if (empty($surname))
    $errors[] = 'Surname is required';
if (empty($student_name))
    $errors[] = 'Student name is required';
// Note: Fathers_name is the one from first section (student's middle name usually), 
// Fathername is the full name in parent section.
if (empty($fathers_name))
    $errors[] = "Father's name (Middle Name) is required";
if (empty($dob))
    $errors[] = 'Date of birth is required';
if (empty($gender))
    $errors[] = 'Gender is required';
if (empty($board_id))
    $errors[] = 'Board is required';
if (empty($medium_id))
    $errors[] = 'Medium is required';
if (empty($group_id))
    $errors[] = 'Group is required';
if (empty($course_id))
    $errors[] = 'Course is required';
if (empty($mob))
    $errors[] = 'Mobile number is required';
if (empty($aadhaar))
    $errors[] = 'Aadhaar number is required';
if (empty($schoolname))
    $errors[] = 'School name (10th) is required';
if (empty($schaddr))
    $errors[] = 'School address (10th) is required';
if (empty($district))
    $errors[] = 'District is required';

// Validate mobile number format
if (!empty($mob) && !preg_match('/^[0-9]{10}$/', $mob)) {
    $errors[] = 'Invalid mobile number format (must be 10 digits)';
}

if (!empty($parent_mob) && !preg_match('/^[0-9]{10}$/', $parent_mob)) {
    $errors[] = 'Invalid parent mobile number format (must be 10 digits)';
}

if (!empty($amob) && !preg_match('/^[0-9]{10}$/', $amob)) {
    $errors[] = 'Invalid alternate mobile number format (must be 10 digits)';
}

// Validate Aadhaar format
if (!empty($aadhaar) && !preg_match('/^[0-9]{12}$/', $aadhaar)) {
    $errors[] = 'Invalid Aadhaar number format (must be 12 digits)';
}

// If there are validation errors
if (!empty($errors)) {
    error_log("Update Student Validation Errors for ID $student_id: " . implode(', ', $errors));
    error_log("POST Data: " . json_encode($_POST));
    if ($is_api_call) {
        sendErrorResponse(implode(', ', $errors), 400, $errors);
    }
    set_flash_message('error', implode('<br>', $errors));
    header('Location: edit-student.php?id=' . $student_id);
    exit;
}

// Check for duplicate mobile number (excluding current student)
try {
    $checkStmt = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE mob = ? AND id != ?");
    $checkStmt->execute([$mob, $student_id]);
    if ($checkStmt->fetch()) {
        handleUpdateError('Mobile number already exists for another student', $is_api_call, $student_id);
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Check Duplicate Mobile");
    }
}

// Check for duplicate Aadhaar (excluding current student)
try {
    $checkStmt = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE aadhaar = ? AND id != ?");
    $checkStmt->execute([$aadhaar, $student_id]);
    if ($checkStmt->fetch()) {
        handleUpdateError('Aadhaar number already exists for another student', $is_api_call, $student_id);
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Check Duplicate Aadhaar");
    }
}

// Update student data
$sql = "UPDATE tbl_gm_std_registration SET 
    school_id = ?, 
    campus_id = ?, 
    surname = ?, 
    student_name = ?, 
    fathers_name = ?, 
    dob = ?, 
    gender = ?, 
    board_id = ?, 
    medium_id = ?, 
    group_id = ?, 
    course_id = ?, 
    mob = ?, 
    parent_mob = ?,
    amob = ?, 
    email = ?,
    aadhaar = ?, 
    schoolname = ?, 
    schaddr = ?, 
    addr = ?, 
    district = ?, 
    fathername = ?, 
    fatheredu = ?, 
    ocupation = ?, 
    ofcaddr = ?, 
    gr_no = ?,
    religion = ?,
    caste = ?,
    hostel_required = ?,
    transport_required = ?,
    transport_months = ?,
    status = ?,
    updated_at = NOW()
WHERE id = ?";

try {
    // Start Transaction
    error_log("Update Student - Beginning transaction for ID: $student_id");
    $conn->beginTransaction();

    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $school_id,
        $campus_id,
        $surname,
        $student_name,
        $fathers_name,
        $dob,
        $gender,
        $board_id,
        $medium_id,
        $group_id,
        $course_id,
        $mob,
        $parent_mob,
        $amob,
        $email,
        $aadhaar,
        $schoolname,
        $schaddr,
        $addr,
        $district,
        $fathername,
        $fatheredu,
        $ocupation,
        $ofcaddr,
        $gr_no,
        $religion,
        $caste,
        $hostel_required,
        $transport_required,
        $transport_months,
        $status,
        $student_id
    ]);

    if ($result) {
        error_log("Update Student - tbl_gm_std_registration updated for ID: $student_id");

        // --- NEW: Update Enrollment Details ---
        $updateEnrollStmt = $conn->prepare("UPDATE tbl_enrolled_students SET division_id = ?, roll_no = ?, updated_at = NOW() WHERE registration_id = ?");
        $updateEnrollStmt->execute([$division_id, $roll_no, $student_id]);
        error_log("Update Student - tbl_enrolled_students updated for ID: $student_id");
        // --- END Enrollment Update ---

        // Detect changes for sync
        $syncNeeded = true; // Always attempt sync if academic fields are updated for robustness

        if ($oldData) {
            // Log for debugging if needed
            // error_log("Sync Check for Student $student_id: Old=" . json_encode($oldData) . " New=" . json_encode(['board_id'=>$board_id, 'medium_id'=>$medium_id, 'group_id'=>$group_id, 'course_id'=>$course_id, 'school_id'=>$school_id]));

            // Check if values actually changed to decide on reallocation logic
            $academicChanged = (
                $oldData['board_id'] != $board_id ||
                $oldData['medium_id'] != $medium_id ||
                $oldData['group_id'] != $group_id ||
                $oldData['course_id'] != $course_id ||
                $oldData['school_id'] != $school_id
            );
        } else {
            $academicChanged = true;
        }

        if ($syncNeeded) {
            // Note: Academic fields (board, medium, group, etc) are in tbl_gm_std_registration.
            // tbl_enrolled_students only tracks division and status by default in this schema.
            // We only need to trigger fee reallocation if academic details changed.
            error_log("Update Student - Sync process started for ID: $student_id");

            // 2. Handle Fee Reallocation (if academic details actually changed)
            if ($academicChanged) {
                error_log("Update Student - Academic details changed for ID: $student_id. Handling reallocation.");
                // Check if student has an existing allocation
                $allocStmt = $conn->prepare("SELECT id, paid_amount, allocated_amount FROM tbl_student_fee_allocation WHERE student_id = ? ORDER BY id DESC LIMIT 1");
                $allocStmt->execute([$student_id]);
                $allocation = $allocStmt->fetch(PDO::FETCH_ASSOC);

                if ($allocation) {
                    $allocation_id = $allocation['id'];
                    $paid_amount = floatval($allocation['paid_amount']);

                    // Find new fee config for the updated group
                    $configStmt = $conn->prepare("SELECT id, total_fees, token_fee, number_of_installments, academic_year, school_fee, trust_facilities_fee, tuition_fee_part1, tuition_fee_part2 
                                            FROM tbl_fee_config 
                                            WHERE medium_id = ? AND group_id = ? AND course_id = ? AND school_id = ? AND is_active = 1 
                                            LIMIT 1");
                    $configStmt->execute([$medium_id, $group_id, $course_id, $school_id]);
                    $newConfig = $configStmt->fetch(PDO::FETCH_ASSOC);

                    if ($newConfig) {
                        error_log("Update Student - Found new fee config ID: " . $newConfig['id']);
                        $new_config_id = $newConfig['id'];

                        // Calculate Total based on Components + 18% GST on Tuition (Dashboard Logic)
                        $comp_school = floatval($newConfig['school_fee'] ?? 0);
                        $comp_trust = floatval($newConfig['trust_facilities_fee'] ?? 0);
                        $comp_tuition1 = floatval($newConfig['tuition_fee_part1'] ?? 0);
                        $comp_tuition2 = floatval($newConfig['tuition_fee_part2'] ?? 0);

                        $tuition1_gst = $comp_tuition1 * 1.18;
                        $tuition2_gst = $comp_tuition2 * 1.18;

                        $new_payable = $comp_school + $comp_trust + $tuition1_gst + $tuition2_gst;

                        // Recalculate pending considering scholarships
                        $scholarship = floatval($oldData['scholarship_amount'] ?? 0);
                        $additional = floatval($oldData['additional_scholarship_amount'] ?? 0);
                        // Fetch post admission discount if available
                        $stmt_disc = $conn->prepare("SELECT post_admission_discount_amount FROM tbl_enrolled_students WHERE registration_id = ? AND is_active = 1");
                        $stmt_disc->execute([$student_id]);
                        $disc_row = $stmt_disc->fetch(PDO::FETCH_ASSOC);
                        $discount = floatval($disc_row['post_admission_discount_amount'] ?? 0);

                        $total_waiver = $scholarship + $additional + $discount;
                        $net_payable = max(0, $new_payable - $total_waiver);

                        $new_pending = max(0, $net_payable - $paid_amount);
                        $overpayment = 0;
                        if ($paid_amount > $net_payable) {
                            $overpayment = $paid_amount - $net_payable;
                            $new_pending = 0;
                        }

                        // Update allocation
                        $updateAllocStmt = $conn->prepare("UPDATE tbl_student_fee_allocation SET  
                        fee_config_id = ?, 
                        allocated_amount = ?, 
                        pending_amount = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                        $updateAllocStmt->execute([$new_config_id, $new_payable, $new_pending, $allocation_id]);
                        error_log("Update Student - Allocation $allocation_id updated: Gross=$new_payable, Net Payable=$net_payable, Paid=$paid_amount, Pending=$new_pending");

                        // --- NEW: Log Group Change for Report ---
                        if ($oldData && isset($oldData['group_id']) && $oldData['group_id'] != $group_id) {
                            error_log("Update Student - Logging group change to tbl_group_change_requests for ID: $student_id");

                            // Get the true "old" total fees from the allocation before it was changed
                            $old_total_payable = floatval($allocation['allocated_amount'] ?? 0);

                            $logChangeStmt = $conn->prepare("INSERT INTO tbl_group_change_requests (
                                student_id, enrollment_id, current_group_id, requested_group_id, 
                                reason, status, request_date, 
                                current_total_fees, new_total_fees, fee_difference,
                                fees_already_paid, adjusted_pending_amount,
                                old_fee_allocation_id, new_fee_allocation_id, created_by, approved_by, approved_date
                            ) VALUES (?, ?, ?, ?, ?, 'approved', NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                            $logChangeStmt->execute([
                                $student_id,
                                $oldData['enrollment_id'] ?? 0,
                                $oldData['group_id'],
                                $group_id,
                                "Direct technical update via Admin Edit Student profile",
                                $old_total_payable,
                                $new_payable,
                                ($new_payable - $old_total_payable),
                                $paid_amount,
                                $new_pending,
                                $allocation_id,
                                $allocation_id,
                                $_SESSION['user_id'] ?? 0,
                                $_SESSION['user_id'] ?? 0
                            ]);
                        }
                        // --- END LOGGING ---

                        // Handle Overpayment Refund
                        if ($overpayment > 0) {
                            // Fetch the latest payment ID to link the refund
                            $latestPayStmt = $conn->prepare("SELECT id FROM tbl_payments WHERE student_id = ? AND status = 'paid' ORDER BY payment_date DESC, id desc LIMIT 1");
                            $latestPayStmt->execute([$student_id]);
                            $latest_payment = $latestPayStmt->fetch(PDO::FETCH_ASSOC);
                            $payment_id = $latest_payment ? $latest_payment['id'] : null;

                            if ($payment_id) {
                                $request_number = 'REF-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                                $refundStmt = $conn->prepare("INSERT INTO tbl_refund_requests (
                                    request_number, student_id, payment_id, refund_amount, refund_reason, refund_type, 
                                    request_status, requested_by, requested_by_role, created_at
                                ) VALUES (?, ?, ?, ?, ?, 'partial', 'pending', ?, 'admin', NOW())");
                                $refundStmt->execute([
                                    $request_number,
                                    $student_id,
                                    $payment_id,
                                    $overpayment,
                                    "Auto-generated due to Group Change (Fee decreased from previous config)",
                                    $_SESSION['user_id'] ?? 0
                                ]);
                                error_log("Update Student - Refund request $request_number created for ID: $student_id (linked to payment $payment_id)");
                            } else {
                                error_log("Update Student Warning - Overpayment detected but no paid record found to link refund for ID: $student_id");
                            }
                        }

                        // Re-generate installments for pending amount
                        // First delete existing pending installments
                        $delInstStmt = $conn->prepare("DELETE FROM tbl_fee_installments WHERE allocation_id = ? AND payment_status = 'pending'");
                        $delInstStmt->execute([$allocation_id]);
                    }
                }
            }
        }

        // Commit transaction if everything is successful
        $conn->commit();
        error_log("Update Student - Transaction committed for ID: $student_id");

        // Sync fee allocation to ensure scholarships are deducted from pending amount
        syncStudentFeeAllocation($conn, $student_id);

        // Create or link parent account if parent_mob changed/provided
        if (!empty($parent_mob)) {
            require_once $base_path . '/../common/helpers/parent_functions.php';
            createParentAccount($parent_mob, $conn);
        }

        // Log the update action (only for non-API)
        if (!$is_api_call) {
            try {
                $log_sql = "INSERT INTO tbl_audit_logs (user_id, action_type, description, created_at) 
                           VALUES (?, 'update_student', ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Updated student information for ID: $student_id - $surname $student_name"
                ]);
            } catch (PDOException $e) {
                if (function_exists('logDatabaseError')) {
                    logDatabaseError($e, "Log Student Update Activity");
                }
            }
        }

        // Return response
        if ($is_api_call) {
            sendSuccessResponse([
                'student_id' => $student_id
            ], 'Student information updated successfully');
        }

        set_flash_message('success', 'Student information updated successfully');

        // Redirect based on user role
        if (function_exists('hasRole') && hasRole(ROLE_COUNSELLOR)) {
            header('Location: details.php?id=' . $student_id);
        } else {
            header('Location: list.php');
        }
        exit;
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Update Student SQL Failed for ID $student_id: " . json_encode($errorInfo));
        $conn->rollBack();
        handleUpdateError('Failed to update student information: ' . ($errorInfo[2] ?? 'Unknown SQL Error'), $is_api_call, $student_id);
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log("Update Student - Transaction rolled back for ID: $student_id");
    }
    error_log("Update Student - Fatal Error for ID: $student_id: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack Trace: " . $e->getTraceAsString());

    if ($is_api_call) {
        sendErrorResponse('Error: ' . $e->getMessage(), 500, ['file' => $e->getFile(), 'line' => $e->getLine()]);
    }
    set_flash_message('error', 'Error: ' . $e->getMessage());
    header('Location: edit-student.php?id=' . $student_id);
    exit;
}


