<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once DB_CONNECT_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/receipt_sequence_helper.php';
require_once dirname(dirname(__DIR__)) . '/common/transaction_helper.php';
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';
require_once __DIR__ . '/../../../common/helpers/enrollment_functions.php';
require_once __DIR__ . '/../../../common/helpers/division_assignment_functions.php';
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;

header('Content-Type: application/json; charset=utf-8');

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Fallback to $_POST if JSON is empty (for backward compatibility)
if (empty($data)) {
    $data = $_POST;
}

// Check if user is authenticated and has appropriate role
$allowed_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ACCOUNTANT];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
    sendErrorResponse('Unauthorized access! Only accountants, admins, or principal can add payments.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log payment request
    logOfflineActivity("Payment processing started for request: " . json_encode(['student_id' => $data['student_id'] ?? null, 'amount' => $data['amount'] ?? null, 'payment_mode' => $data['payment_mode'] ?? null]), 'INFO');

    $student_id = $data['student_id'] ?? null;
    $payment_date = $data['payment_date'] ?? null;

    // Handle checkbox-based payment types (comma-separated or array)
    $payment_types = $data['payment_types'] ?? [];
    $payment_type = $data['payment_type'] ?? null; // Combined string from hidden field

    // If payment_type is a comma-separated string, use that
    if (!empty($payment_type) && is_string($payment_type)) {
        $payment_types = explode(',', $payment_type);
    }

    // Handle 'other' fee if selected
    $other_description = trim($data['other_description'] ?? '');
    $other_amount = floatval($data['other_amount'] ?? 0);

    $amount = $data['amount'] ?? null;
    $payment_mode = $data['payment_mode'] ?? null;
    $transaction_id = trim($data['transaction_id'] ?? '');
    $cheque_no = trim($data['cheque_no'] ?? '');
    $cheque_date = $data['cheque_date'] ?? null;
    $bank_name = trim($data['bank_name'] ?? '');
    $remarks = trim($data['remarks'] ?? '');
    $remarks = trim($data['remarks'] ?? '');
    $created_by = $_SESSION['user_id'];

    // Handle Discount Fields
    $discount_type = $data['discount_type'] ?? null;
    $discount_value = floatval($data['discount_value'] ?? 0);
    $discount_reason = trim($data['discount_reason'] ?? '');
    $is_without_gst = (isset($data['is_without_gst']) && $data['is_without_gst'] === true);
    // Deduction mode is now Gross (incl. GST) and goes to the main table, but without receipt
    if ($payment_mode === 'deduction') {
        $is_without_gst = false;
    }

    $apply_discount = false;
    if (!empty($discount_type) && $discount_value > 0) {
        $apply_discount = true;
    }

    // Create a readable payment type string for storage
    $fee_type_labels = [
        'school_fee' => 'School Fee',
        'trust_facilities_fee' => 'Trust Facilities Fee',
        'tuition_fee_part1' => 'Tuition Fee Part 1',
        'tuition_fee_part2' => 'Tuition Fee Part 2',
        'hostel_fee' => 'Hostel Fee',
        'hostel_security' => 'Hostel Security Deposit',
        'transport_fee' => 'Transport Fee',
        'other' => $other_description ?: 'Other'
    ];

    $payment_type_labels = [];
    foreach ($payment_types as $type) {
        $type = trim($type);
        if (isset($fee_type_labels[$type])) {
            $payment_type_labels[] = $fee_type_labels[$type];
        }
    }
    $payment_type_combined = !empty($payment_type_labels) ? implode(', ', $payment_type_labels) : 'General Payment';

    // Validation
    $errors = [];

    if (empty($student_id)) {
        $errors[] = "Please select a student.";
    }

    if (empty($payment_date)) {
        $errors[] = "Payment date is required.";
    }

    if (empty($payment_types)) {
        $errors[] = "Payment type is required.";
    }

    // Validate 'other' fee if selected
    if (in_array('other', $payment_types)) {
        if (empty($other_description)) {
            $errors[] = "Description is required for other fee.";
        }
        if ($other_amount <= 0) {
            $errors[] = "Valid amount is required for other fee.";
        }
    }

    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required.";
    }

    if (empty($payment_mode)) {
        $errors[] = "Payment mode is required.";
    }

    // Generate unique receipt number based on financial year (April to March)
    // Calculate financial year to track FY boundaries
    $current_month = intval(date('n')); // 1-12
    $current_year = intval(date('Y'));

    if ($current_month >= 4) {
        // April to December: FY is current year to next year
        $fy_start_date = $current_year . '-04-01';
    } else {
        // January to March: FY is previous year to current year
        $fy_start_date = ($current_year - 1) . '-04-01';
    }

    // Use the pre-generated transaction ID from the form, or generate a new one if empty
    if (empty($transaction_id)) {
        $transaction_id = generateUniqueTransactionID('GMI');
    }

    // Prepare list of components to save
    $components_to_save = [];

    // If payment_details is provided (new frontend logic), uses that
    if (!empty($data['payment_details']) && is_array($data['payment_details'])) {
        foreach ($data['payment_details'] as $detail) {
            if (!empty($detail['amount']) && $detail['amount'] > 0) {
                $components_to_save[] = [
                    'fee_component' => $detail['fee_component'],
                    'amount' => $detail['amount'],
                    'cheque_no' => $detail['cheque_no'] ?? null,
                    'cheque_date' => $detail['cheque_date'] ?? null,
                    'bank_name' => $detail['bank_name'] ?? null,
                    // 'remarks' can be per component if needed, but using global remarks for now
                ];
            }
        }
    } else {
        // Legacy fallback: determine fee_component from payment types
        $fee_component = null;
        $fee_component_priority = ['tuition_fee_part1', 'tuition_fee_part2', 'school_fee', 'trust_facilities_fee', 'hostel_security', 'hostel_fee', 'transport_fee'];
        foreach ($fee_component_priority as $priority_fee) {
            if (in_array($priority_fee, $payment_types)) {
                $fee_component = $priority_fee;
                break;
            }
        }
        if (!$fee_component) {
            $fee_component = !empty($payment_types) ? $payment_types[0] : 'other';
        }
        if (empty($fee_component)) {
            $fee_component = 'other';
        }

        $components_to_save[] = [
            'fee_component' => $fee_component,
            'amount' => $amount
        ];
    }

    // Block non-token-fee payments when token fee is not yet paid
    if (empty($errors) && !empty($student_id)) {
        $is_token_fee_in_payment = in_array('tuition_fee_part1', array_column($components_to_save, 'fee_component'));
        if (!$is_token_fee_in_payment) {
            $stmt_tok = $conn->prepare("SELECT token_fees_paid FROM tbl_gm_std_registration WHERE id = ?");
            $stmt_tok->execute([$student_id]);
            $tok_row = $stmt_tok->fetch(PDO::FETCH_ASSOC);
            if ($tok_row && intval($tok_row['token_fees_paid']) === 0) {
                $errors[] = "Token fee (Tuition Fee Part 1) must be paid before collecting any other fees for this student.";
            }
        }
    }

    // If no errors, save payment
    if (empty($errors)) {
        logOfflineActivity("Payment validation passed for student $student_id. Proceeding with payment record creation.", 'INFO');
        try {
            $conn->beginTransaction();
            logOfflineActivity("Database transaction started for student $student_id payment processing.", 'INFO');

            $receipt_numbers = [];
            $total_saved_amount = 0;
            $payment_ids = [];
            $receipt_payment_ids = [];

            // 11th std students (course_id 1 or 2) - receipt numbers not generated
            $stmt_course = $conn->prepare("SELECT course_id FROM tbl_gm_std_registration WHERE id = ?");
            $stmt_course->execute([$student_id]);
            $student_course_data = $stmt_course->fetch(PDO::FETCH_ASSOC);
            $skip_receipt_generation = in_array($student_course_data['course_id'] ?? 0, [1, 2]);

            foreach ($components_to_save as $comp_data) {
                $comp_name = $comp_data['fee_component'];
                $comp_amount = $comp_data['amount'];

                $school_id = getStudentSchoolId($conn, $student_id);
                $academic_year = getCurrentAcademicYear($conn);

                // Get student's current term_id from enrollment
                $stmt_term = $conn->prepare("SELECT current_term_id FROM tbl_enrolled_students WHERE registration_id = ? AND is_active = 1");
                $stmt_term->execute([$student_id]);
                $term_data = $stmt_term->fetch();
                $term_id = $term_data['current_term_id'] ?? 1;

                $receipt_no = null;
                $receipt_config_id = null;

                // hostel_cash_fee is a cash collection — never generates receipt regardless of student type
                $is_cash_fee_component = ($comp_name === 'hostel_cash_fee');
                if (!$is_without_gst && $payment_mode !== 'deduction' && !$skip_receipt_generation && !$is_cash_fee_component) {
                    // Determine receipt_config_id
                    $receipt_config_id = getReceiptConfigForFee($conn, $comp_name, $school_id);

                    // Fallback if not found (default to 1 - GCA)
                    if (!$receipt_config_id) {
                        $receipt_config_id = 1;
                        logOfflineActivity("Warning: receipt_config_id not found for $comp_name, using default (1)", 'WARNING');
                    }

                    logOfflineActivity("Generating receipt number - Student: $student_id, Fee Component: $comp_name", 'INFO');

                    $seq_result = getNextReceiptNumber($conn, $comp_name, $school_id, null, $student_id);

                    if (!$seq_result['success']) {
                        throw new Exception($seq_result['error'] ?? 'Failed to generate receipt number for ' . $comp_name);
                    }

                    $receipt_no = $seq_result['receipt_no'];
                    $receipt_numbers[] = $receipt_no;
                } elseif ($payment_mode === 'deduction') {
                    // User requested '0' for deduction receipt_no
                    $receipt_no = '0';
                } elseif ($is_without_gst) {
                    // User requested '0' for without-GST receipt_no as well
                    $receipt_no = '0';
                } elseif ($skip_receipt_generation) {
                    // Force receipt_no to '0' for course_id 1 or 2 (11th Std)
                    $receipt_no = '0';
                }

                // Insert payment record
                $target_table = "tbl_payments";

                $stmt = $conn->prepare("INSERT INTO $target_table 
                    (student_id, receipt_no, amount, payment_date, payment_mode, 
                    transaction_id, cheque_no, cheque_date, bank_name, payment_type, fee_component, term_id, receipt_config_id, remarks, 
                    status, created_by, created_at) 
                    VALUES 
                    (:student_id, :receipt_no, :amount, :payment_date, :payment_mode, 
                    :transaction_id, :cheque_no, :cheque_date, :bank_name, :payment_type, :fee_component, :term_id, :receipt_config_id, :remarks, 
                    'paid', :created_by, NOW())");

                $readable_type = isset($fee_type_labels[$comp_name]) ? $fee_type_labels[$comp_name] : $payment_type_combined;

                // Use per-component cheque details if provided
                $curr_cheque_no = $comp_data['cheque_no'] ?? $cheque_no;
                $curr_cheque_date = $comp_data['cheque_date'] ?? $cheque_date;
                $curr_bank_name = $comp_data['bank_name'] ?? $bank_name;

                $stmt->execute([
                    'student_id' => $student_id,
                    'receipt_no' => $receipt_no,
                    'amount' => $comp_amount,
                    'payment_date' => $payment_date,
                    'payment_mode' => $payment_mode,
                    'transaction_id' => $transaction_id,
                    'cheque_no' => $curr_cheque_no,
                    'cheque_date' => $curr_cheque_date,
                    'bank_name' => $curr_bank_name,
                    'payment_type' => $readable_type,
                    'fee_component' => $comp_name,
                    'term_id' => $term_id,
                    'receipt_config_id' => $receipt_config_id,
                    'remarks' => $remarks,
                    'created_by' => $created_by
                ]);

                $current_p_id = $conn->lastInsertId();
                $payment_ids[] = $current_p_id;

                // Only add to receipt IDs if a receipt number was generated
                if (!empty($receipt_no)) {
                    $receipt_payment_ids[] = $current_p_id;
                }

                $total_saved_amount += $comp_amount;

                logOfflineActivity("Payment record created: ID " . $current_p_id . ", Receipt: $receipt_no, Component: $comp_name", 'SUCCESS');

                // --- Update Pending Status Tables ---

                // 1. Update tbl_pending_payments if a matching record exists
                $stmt = $conn->prepare("UPDATE tbl_pending_payments 
                                      SET status = 'paid', updated_at = NOW() 
                                      WHERE student_id = ? AND payment_type = ? AND status = 'pending'");
                $stmt->execute([$student_id, $comp_name]);
                if ($stmt->rowCount() > 0) {
                    logInfo("Updated tbl_pending_payments status to paid for student $student_id, component $comp_name");
                }

                // 2. Synchronize Fee Allocation table
                require_once __DIR__ . '/../../../common/helpers/fee_allocation_helper.php';
                $sync_res = syncStudentFeeAllocation($conn, $student_id);
                if ($sync_res['success']) {
                    logOfflineActivity("Synchronized tbl_student_fee_allocation for student $student_id", 'INFO');
                } else {
                    logOfflineActivity("Fee allocation sync failed for student $student_id: " . $sync_res['message'], 'ERROR');
                    logError("Fee allocation sync failed for student $student_id: " . $sync_res['message']);
                }
            }

            // Response vars
            $payment_id = !empty($receipt_payment_ids) ? $receipt_payment_ids[0] : (!empty($payment_ids) ? $payment_ids[0] : 0);
            $ids_str = implode(',', $receipt_payment_ids);
            $receipt_no_str = implode(', ', $receipt_numbers);

            // Check if student should be enrolled after this payment
            // Enroll if: payment is successful AND student is not yet enrolled
            $stmt = $conn->prepare("SELECT is_enrolled, enrollment_id, token_fees_paid, admission_confirmed 
                                   FROM tbl_gm_std_registration WHERE id = ?");
            $stmt->execute([$student_id]);
            $student_status = $stmt->fetch();

            // Check if this payment includes token fee (tuition_fee_part1)
            $is_token_fee_payment = in_array('tuition_fee_part1', array_column($components_to_save, 'fee_component'));

            if (
                $student_status &&
                $is_token_fee_payment &&
                (!$student_status['is_enrolled'] || empty($student_status['enrollment_id']))
            ) {

                // If token fees weren't marked as paid, but they are paying fees now
                // OR if they are specifically paying tuition_fee_part1
                if ($student_status['token_fees_paid'] == 0 || $is_token_fee_payment) {
                    // Fetch student mobile for password setup if needed
                    $stmt = $conn->prepare("SELECT mob, aadhaar, surname, student_name, email FROM tbl_gm_std_registration WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $student_info = $stmt->fetch();

                    if ($student_info) {
                        // Set password as mobile number (hashed) - only if password not already set or token fee just paid
                        $password = password_hash($student_info['mob'], PASSWORD_DEFAULT);

                        // Calculate token amount from the payment (tuition part 1 with GST)
                        $token_amount = 0;
                        if ($is_token_fee_payment) {
                            // Find the token fee amount in our components list
                            foreach ($components_to_save as $comp) {
                                if ($comp['fee_component'] === 'tuition_fee_part1') {
                                    $token_amount = $comp['amount']; // This includes GST usually
                                    break;
                                }
                            }
                        }

                        // Update token fee fields
                        $token_payment_mode = ($payment_mode === 'online') ? 'online' : 'offline';

                        $update_fields = [
                            "token_fees_paid = 1",
                            "admission_confirmed = 1",
                            "token_payment_mode = ?",
                            "token_transaction_id = ?",
                            "updated_at = NOW()"
                        ];
                        $update_params = [$token_payment_mode, $transaction_id];

                        // Only update password and date if it's the first time token is paid
                        if ($student_status['token_fees_paid'] == 0) {
                            $update_fields[] = "token_amount = ?";
                            $update_params[] = $token_amount > 0 ? $token_amount : $amount;
                            
                            $update_fields[] = "token_payment_date = ?";
                            $update_params[] = $payment_date;
                            
                            $update_fields[] = "password = ?";
                            $update_params[] = $password;
                        }

                        $update_params[] = $student_id;
                        $sql_update = "UPDATE tbl_gm_std_registration SET " . implode(', ', $update_fields) . " WHERE id = ?";
                        $stmt = $conn->prepare($sql_update);
                        $stmt->execute($update_params);
                    }
                }

                // Enroll the student
                logOfflineActivity("Attempting to enroll student $student_id after payment.", 'INFO');
                $enrollment_result = enrollStudentAfterTokenPayment($conn, $student_id);

                if ($enrollment_result['success'] && !empty($enrollment_result['enrollment_id'])) {
                    logOfflineActivity("Student $student_id enrolled successfully. Enrollment ID: " . $enrollment_result['enrollment_id'], 'SUCCESS');
                    // Assign division and roll number automatically
                    require_once __DIR__ . '/../../common/settings_helper.php';
                    $auto_assign_enabled = getSetting($conn, 'auto_assign_division_on_enrollment', false);

                    if ($auto_assign_enabled) {
                        logOfflineActivity("Auto division assignment enabled. Attempting to assign division for enrollment ID: " . $enrollment_result['enrollment_id'], 'INFO');
                        $division_result = assignDivisionAndRollNumber($conn, $enrollment_result['enrollment_id']);
                        if (!$division_result['success']) {
                            logOfflineActivity("Division assignment failed for enrollment " . $enrollment_result['enrollment_id'] . " after manual payment: " . $division_result['message'], 'ERROR');
                            logError("Division assignment failed for enrollment " . $enrollment_result['enrollment_id'] . " after manual payment: " . $division_result['message']);
                        } else {
                            logOfflineActivity("Division assigned successfully for enrollment ID: " . $enrollment_result['enrollment_id'], 'SUCCESS');
                        }
                    }
                } else if (!$enrollment_result['success']) {
                    logOfflineActivity("Auto-enrollment after payment failed for student $student_id: " . $enrollment_result['message'], 'ERROR');
                    logError("Auto-enrollment after payment failed for student $student_id: " . $enrollment_result['message']);
                }
            }

            // Create transaction log
            $stmt = $conn->prepare("INSERT INTO tbl_payment_transactions 
                (payment_id, student_id, transaction_type, amount, transaction_date, 
                description, processed_by) 
                VALUES 
                (:payment_id, :student_id, 'payment', :amount, NOW(), 
                :description, :processed_by)");

            $description = "Payment received via " . strtoupper($payment_mode) . " - Receipt: " . $receipt_no_str;

            $stmt->execute([
                'payment_id' => $payment_id,
                'student_id' => $student_id,
                'amount' => $amount,
                'description' => $description,
                'processed_by' => $created_by
            ]);
            logOfflineActivity("Payment transaction log created for payment ID: $payment_id", 'INFO');

            $receipt_url = null;
            if (!$is_without_gst && !empty($receipt_payment_ids)) {
                if (count($receipt_payment_ids) > 1) {
                    $receipt_url = "receipt-print-pdf.php?ids=" . $ids_str;
                } else {
                    $receipt_url = "receipt-print-pdf.php?id=" . $payment_id;
                }
            }

            $conn->commit();
            logOfflineActivity("Payment transaction committed successfully for student $student_id. Receipt No: " . ($is_without_gst ? 'N/A (Without GST)' : $receipt_no_str), 'SUCCESS');

            // --- Save Receipt to Backup Folder ---
            $notif_options = ['student_id' => $student_id, 'attachments' => []];
            if (!$is_without_gst && !empty($receipt_payment_ids)) {
                try {
                    require_once __DIR__ . '/../../../common/helpers/receipt_pdf_helper.php';
                    $backup_folder = 'D:/Receipt Backup';

                    foreach ($receipt_payment_ids as $p_id) {
                        // Fetch receipt number for filename
                        $stmt_r = $conn->prepare("SELECT receipt_no FROM tbl_payments WHERE id = ?");
                        $stmt_r->execute([$p_id]);
                        $r_data = $stmt_r->fetch();

                        if ($r_data) {
                            $stmt_f = $conn->prepare("SELECT fee_component FROM tbl_payments WHERE id = ?");
                            $stmt_f->execute([$p_id]);
                            $f_data = $stmt_f->fetch();
                            $fee_comp = ($f_data['fee_component'] ?? 'other') ?: 'other';

                            $safe_fee_comp = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $fee_comp);
                            $comp_folder = $backup_folder . '/' . $safe_fee_comp;
                            if (!is_dir($comp_folder)) {
                                mkdir($comp_folder, 0777, true);
                            }

                            if (!empty($r_data['receipt_no'])) {
                                $safe_receipt_no = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $r_data['receipt_no']);
                                $filename = "Receipt_{$safe_receipt_no}_{$student_id}.pdf";
                            } else {
                                $filename = "Receipt_{$student_id}.pdf";
                            }

                            $save_path = $comp_folder . '/' . $filename;
                            $pdf_res = generateAndSaveReceiptPDF($conn, $p_id, $save_path);

                            if ($pdf_res['success'] && file_exists($save_path)) {
                                $notif_options['attachments'][] = $save_path;
                            }
                        }
                    }
                } catch (Exception $e) {
                    logError("Failed to save receipt backup for student $student_id: " . $e->getMessage());
                }
            }

            // --- Update Post-Admission Discount if applicable ---
            if ($apply_discount) {
                try {
                    // We do this in a separate try-catch block or new transaction if needed, 
                    // but since the main payment is committed, we should try to update discount now.
                    // Ideally this should be in the same transaction, but for safety of the main payment logic which is complex,
                    // I'll execute this update now.

                    logInfo("Applying post-admission discount for student $student_id: $discount_value ($discount_type)");

                    // Calculate actual amount if percentage
                    // But usually for post-admission we store the flat amount. 
                    // The frontend sends the calculated value or raw value? 
                    // Frontend 'discount_value' input seems to be the value to apply.
                    // 'discount_type' is just metadata essentially if we store the final amount.
                    // tbl_enrolled_students has 'post_admission_discount_amount' (decimal).

                    $stmt_disc = $conn->prepare("UPDATE tbl_enrolled_students 
                                               SET post_admission_discount_amount = post_admission_discount_amount + ?,
                                                   post_admission_discount_remarks = CONCAT(IFNULL(post_admission_discount_remarks, ''), ?),
                                                   updated_at = NOW()
                                               WHERE registration_id = ? AND is_active = 1");

                    // Format remark
                    $disc_remark = " | Discount: $discount_value ($discount_type) - Reason: $discount_reason (Applied on " . date('Y-m-d') . ")";
                    if (strpos($disc_remark, '|') === 0)
                        $disc_remark = substr($disc_remark, 3); // Clean leading pipe if empty

                    $stmt_disc->execute([$discount_value, $disc_remark, $student_id]);

                    logOfflineActivity("Updated post-admission discount for student $student_id", 'INFO');

                } catch (Exception $e) {
                    logError("Failed to update discount for student $student_id: " . $e->getMessage());
                    // We don't fail the request because payment was successful
                }

                // Sync allocation again to reflect discount (if sync uses discount)
                // We just committed payment, so sync was called inside loop.
                // But now we updated discount, so we should sync AGAIN.
                require_once __DIR__ . '/../../../common/helpers/fee_allocation_helper.php';
                syncStudentFeeAllocation($conn, $student_id);
            }

            // Send login credentials notification (token fee payment)
            if ($payment_mode !== 'deduction' && isset($student_info) && isset($student_status) && $student_status['token_fees_paid'] == 0) {
                try {
                    // Re-fetch student details
                    $stmt = $conn->prepare("SELECT r.*, c.course_name 
                                           FROM tbl_gm_std_registration r
                                           LEFT JOIN tbl_courses c ON r.course_id = c.id
                                           WHERE r.id = ?");
                    $stmt->execute([$student_id]);
                    $student_details = $stmt->fetch();

                    if ($student_details) {
                        $full_name = trim($student_details['surname'] . ' ' . $student_details['student_name'] . ' ' . $student_details['fathers_name']);
                        $recipient = [
                            'name' => $full_name,
                            'email' => $student_details['email'] ?? '',
                            'mobile' => $student_details['mob']
                        ];

                        $token_variables = [
                            'student_name' => $full_name,
                            'amount' => formatIndianCurrency($amount),
                            'payment_mode' => ucfirst($payment_mode),
                            'receipt_no' => implode(', ', $receipt_numbers),
                            'transaction_id' => $transaction_id,
                            'payment_date' => date('d-M-Y', strtotime($payment_date))
                        ];

                        $login_variables = [
                            'username' => $student_details['aadhaar'],
                            'password' => $student_details['mob']
                        ];

                        $notif_options = ['student_id' => $student_id];
                        if (!empty($options['attachments'])) {
                            $notif_options['attachments'] = $options['attachments'];
                        }

                        sendNotification($conn, 'token_fee_success', $recipient, $token_variables, $notif_options);
                        sendNotification($conn, 'login_credentials', $recipient, $login_variables, ['student_id' => $student_id]);
                    }
                } catch (Exception $e) {
                    logError("Payment notification error for student $student_id: " . $e->getMessage());
                }
            }

            // --- Update Hostel/Transport Requirement Flags ---
            try {
                $components_paid = array_column($components_to_save, 'fee_component');
                $update_fields = [];

                if (in_array('hostel_fee', $components_paid)) {
                    $update_fields[] = "hostel_required = 'Yes'";
                }

                if (in_array('transport_fee', $components_paid)) {
                    $update_fields[] = "transport_required = 'Yes'";
                }

                if (!empty($update_fields)) {
                    $sql_update = "UPDATE tbl_gm_std_registration SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->execute([$student_id]);
                    logOfflineActivity("Updated student requirement flags for student $student_id: " . implode(', ', $update_fields), 'INFO');

                    // After updating requirements, we should sync fees again to ensure future allocations match
                    require_once __DIR__ . '/../../../common/helpers/fee_allocation_helper.php';
                    syncStudentFeeAllocation($conn, $student_id);
                }

            } catch (Exception $e) {
                logError("Failed to update student requirement flags for student $student_id: " . $e->getMessage());
                // Don't fail the request
            }

            // Send notification for ALL payments (General Fee Payment Success)
            try {
                // Ensure we have student details if not fetched above
                if (!isset($student_details)) {
                    $stmt = $conn->prepare("SELECT r.*, c.course_name 
                                           FROM tbl_gm_std_registration r
                                           LEFT JOIN tbl_courses c ON r.course_id = c.id
                                           WHERE r.id = ?");
                    $stmt->execute([$student_id]);
                    $student_details = $stmt->fetch();
                }

                if ($student_details) {
                    $full_name = trim($student_details['surname'] . ' ' . $student_details['student_name'] . ' ' . $student_details['fathers_name']);
                    $recipient = [
                        'name' => $full_name,
                        'email' => $student_details['email'] ?? '',
                        'mobile' => $student_details['mob']
                    ];

                    $variables = [
                        'student_name' => $full_name,
                        'amount' => formatIndianCurrency($amount),
                        'payment_mode' => ucfirst($payment_mode),
                        'receipt_no' => implode(', ', $receipt_numbers),
                        'payment_date' => date('d-M-Y', strtotime($payment_date))
                    ];

                    // Check if this was ALREADY triggered as 'token_fee_success' above to avoid duplicate
                    // 'token_fee_success' is sent if student_status['token_fees_paid'] == 0 (before update)
                    // So if we just paid token fees, we sent 'token_fee_success'. 
                    // If not token fee (or already paid), we send 'fee_payment_success'.

                    $token_fee_just_paid = (isset($student_status) && $student_status['token_fees_paid'] == 0 && isset($is_token_fee_payment) && $is_token_fee_payment);

                    if ($payment_mode !== 'deduction' && !$token_fee_just_paid && !$is_without_gst) {
                        sendNotification($conn, 'fee_payment_success', $recipient, $variables, $notif_options);
                    }
                }
            } catch (Exception $e) {
                logError("General payment notification error for student $student_id: " . $e->getMessage());
            }

            sendSuccessResponse([
                'receipt_no' => $receipt_no_str,
                'receipt_numbers' => $receipt_numbers, // Also send array
                'payment_id' => $payment_id,
                'payment_ids' => $payment_ids, // Send all IDs
                'student_id' => $student_id,
                'amount' => $amount,
                'is_without_gst' => $is_without_gst,
                'receipt_url' => $receipt_url,
                'redirect_url' => $is_without_gst ? 'pending-payments.php' : null
            ], 'Payment saved successfully!' . ($is_without_gst || $payment_mode === 'deduction' ? '' : ' Receipt No: ' . $receipt_no_str));

        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            logError("Payment save failed for student $student_id. PDO Error: " . $e->getMessage());
            logDatabaseError($e, "Save Payment");
            sendErrorResponse('Failed to save payment. Please try again.', 500);
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            logError("Payment save failed for student $student_id. Error: " . $e->getMessage());
            sendErrorResponse('Failed to save payment: ' . $e->getMessage(), 500);
        }
    } else {
        logError("Payment request rejected due to validation errors: " . implode(', ', $errors));
        sendErrorResponse(implode(', ', $errors), 400);
    }
} else {
    sendErrorResponse('Invalid request method.', 405);
}
