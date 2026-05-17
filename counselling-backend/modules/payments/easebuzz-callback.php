<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../../common/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../../common/helpers/enrollment_functions.php';
require_once __DIR__ . '/../../../common/helpers/division_assignment_functions.php';
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;
require_once '../../easebuzz-lib/easebuzz_payment_gateway.php';
require_once __DIR__ . '/../../../common/helpers/receipt_sequence_helper.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// EaseBuzz sends POST data on callback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $response = $_POST;
    $txnid = $response['txnid'] ?? '';
    $status = $response['status'] ?? '';
    $amount = $response['amount'] ?? '';
    $payment_id = $response['easepayid'] ?? '';
    $student_id = $response['udf1'] ?? '';
    $payment_type = $response['udf2'] ?? 'token_fee'; // Dynamic: token_fee, school_fee, trust_facilities_fee, etc.
    $transaction_id = $response['udf3'] ?? '';
    $installment_id = !empty($response['udf4']) ? intval($response['udf4']) : null; // Installment ID if applicable

    // Fetch EaseBuzz configuration
    $config = $dbOps->selectOne('tbl_payment_gateway_config', ['*'], ['gateway_name' => 'easebuzz', 'is_active' => 1]);

    if (!$config) {
        echo json_encode(['status' => 'error', 'message' => 'Payment gateway configuration not found']);
        exit;
    }

    $merchant_key = $config['api_key'];
    $merchant_salt = $config['api_secret'];
    $api_url = $config['api_url'] ?? '';
    $env = $config['environment'] ?? 'prod';

    logPaymentDebug("Callback Received", ['txnid' => $txnid, 'status' => $status, 'env' => $env]);

    // Initialize EaseBuzz with official library and verify response
    $easebuzz = new Easebuzz($merchant_key, $merchant_salt, $env);

    // Verify response using official library method
    $verification_result = json_decode($easebuzz->easebuzzResponse($response), true);

    if ($verification_result && $verification_result['status'] == 1) {
        // Hash verified successfully

        if ($status === 'success') {
            // Payment successful
            try {
                // Fetch order details
                $order = $dbOps->selectOne('tbl_payment_orders', ['*'], ['gateway_order_id' => $txnid]);

                if (!$order) {
                    throw new Exception("Order not found");
                }

                $transaction_id = $order['transaction_id'];

                $conn->beginTransaction();

                // Use system user ID (1) for student payments as students aren't in tbl_users
                $created_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

                // CRITICAL: Use the actual amount paid from gateway response
                $paid_amount = floatval($amount);

                // Process based on payment type
                $school_id = getStudentSchoolId($conn, $student_id);
                // Fetch student's current term_id from enrollment (Align with payment-save.php)
                $stmt_term = $conn->prepare("SELECT current_term_id FROM tbl_enrolled_students WHERE registration_id = ? AND is_active = 1");
                $stmt_term->execute([$student_id]);
                $term_data = $stmt_term->fetch();
                $term_id = $term_data['current_term_id'] ?? 1;

                if ($payment_type === 'token_fee') {
                    // TOKEN FEE PAYMENT PROCESSING
                    // For token fee, the entire amount goes to tuition_fee_part1 (GCA receipt)
                    // This is tuition + 18% GST combined
                    $tuition_part1_with_gst = $paid_amount;


                    // Calculate financial year for receipt numbering
                    $payment_year = intval(date('Y'));
                    $payment_month = intval(date('n')); // 1-12

                    if ($payment_month >= 4) {
                        $fy_start = $payment_year;
                    } else {
                        $fy_start = $payment_year - 1;
                    }
                    $fy_start_date = $fy_start . '-04-01';
                    $date_prefix = date('Ymd');

                    // Token Fee Payment: Generate SINGLE GCA Receipt for Tuition Fee (with 18% GST)
                    // Get next sequential receipt number using new sequence system
                    $school_id = getStudentSchoolId($conn, $student_id);
                    $academic_year = getCurrentAcademicYear($conn);
                    $seq_result = getNextReceiptNumber($conn, 'tuition_fee_part1', $school_id, $academic_year, $student_id);

                    if (!$seq_result['success']) {
                        throw new Exception('Failed to generate receipt number: ' . ($seq_result['error'] ?? 'Unknown error'));
                    }

                    $receipt_no = $seq_result['receipt_no'];

                    $stmt = $conn->prepare("INSERT INTO tbl_payments 
                                           (student_id, receipt_no, amount, payment_date, payment_mode, 
                                            transaction_id, payment_id, payment_type, fee_component, term_id, remarks, 
                                            status, created_by, created_at) 
                                           VALUES 
                                           (?, ?, ?, NOW(), 'online', ?, ?, 'token_fee', 'tuition_fee_part1', ?, 'Token Fee - Tuition Fee Part 1 (incl. 18% GST) - Payment via EaseBuzz', 'paid', ?, NOW())");
                    $stmt->execute([$student_id, $receipt_no, $tuition_part1_with_gst, $transaction_id, $payment_id, $term_id, $created_by]);

                    // Add to transaction logs (Align with payment-save.php)
                    $stmt_log = $conn->prepare("INSERT INTO tbl_payment_transactions 
                        (payment_id, student_id, transaction_type, amount, transaction_date, description, processed_by) 
                        VALUES (?, ?, 'payment', ?, NOW(), ?, ?)");
                    $last_payment_id = $conn->lastInsertId();
                    $stmt_log->execute([
                        $last_payment_id,
                        $student_id,
                        $tuition_part1_with_gst,
                        "Online payment - Token Fee (Tuition Part 1), Receipt: $receipt_no",
                        $created_by
                    ]);

                    // Update student record - store our transaction_id, not EaseBuzz payment_id
                    $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                                           SET token_fees_paid = 1,
                                               token_payment_date = NOW(),
                                               token_transaction_id = ?,
                                               token_payment_id = ?,
                                               token_amount = ?,
                                               updated_at = NOW()
                                           WHERE id = ?");
                    $stmt->execute([$transaction_id, $payment_id, $paid_amount, $student_id]);

                    // Update pending payment
                    $stmt = $conn->prepare("UPDATE tbl_pending_payments 
                                           SET status = 'completed',
                                               gateway_response = ?,
                                               updated_at = NOW()
                                           WHERE student_id = ? AND payment_type = 'token_fee'");
                    $stmt->execute([json_encode($_POST), $student_id]);

                    // Enroll student and assign fees
                    $enrollment_result = enrollStudentAfterTokenPayment($conn, $student_id);

                    if ($enrollment_result['success'] && !empty($enrollment_result['enrollment_id'])) {
                        // Assign division and roll number automatically (if enabled in settings)
                        require_once '../../common/settings_helper.php';
                        $auto_assign_enabled = getSetting($conn, 'auto_assign_division_on_enrollment', false);

                        if ($auto_assign_enabled) {
                            $division_result = assignDivisionAndRollNumber($conn, $enrollment_result['enrollment_id']);
                            if (!$division_result['success']) {
                                logError("Division assignment failed for student $student_id after callback payment: " . $division_result['message']);
                            }
                        }
                    }

                    $receipt_numbers = [$receipt_no];
                } else {
                    // OTHER FEE PAYMENTS (School Fee, Trust Fee, Tuition Part 2, Hostel, Transport)
                    // Map payment_type to fee_component
                    $fee_component_map = [
                        'school_fee' => 'school_fee',
                        'trust_facilities_fee' => 'trust_facilities_fee',
                        'tuition_fee_part2' => 'tuition_fee_part2',
                        'hostel_fee' => 'hostel_fee',
                        'transport_fee' => 'transport_fee'
                    ];
                    $fee_component = $fee_component_map[$payment_type] ?? $payment_type;

                    // Generate receipt number based on fee component
                    $receipt_prefixes = [
                        'school_fee' => 'MST',
                        'trust_facilities_fee' => 'MST',
                        'tuition_fee_part2' => 'GCA',
                        'hostel_fee' => 'HST',
                        'transport_fee' => 'TRP'
                    ];
                    $receipt_prefix = $receipt_prefixes[$fee_component] ?? 'FEE';

                    // Get next sequential receipt number for this fee component
                    $school_id = getStudentSchoolId($conn, $student_id);
                    $academic_year = getCurrentAcademicYear($conn);
                    $seq_result = getNextReceiptNumber($conn, $fee_component, $school_id, $academic_year, $student_id);

                    if (!$seq_result['success']) {
                        throw new Exception('Failed to generate receipt number: ' . ($seq_result['error'] ?? 'Unknown error'));
                    }

                    $receipt_no = $seq_result['receipt_no'];

                    // Fee label for remarks
                    $fee_labels = [
                        'school_fee' => 'School Fee',
                        'trust_facilities_fee' => 'Trust Facilities Fee',
                        'tuition_fee_part2' => 'Tuition Fee Part 2',
                        'hostel_fee' => 'Hostel Fee',
                        'transport_fee' => 'Transport Fee'
                    ];
                    $fee_label = $fee_labels[$fee_component] ?? 'Fee Payment';

                    $remarks = $fee_label . ' - Payment via EaseBuzz';
                    if ($installment_id) {
                        $remarks .= ' (Installment)';
                    }

                    $stmt = $conn->prepare("INSERT INTO tbl_payments 
                                           (student_id, receipt_no, amount, payment_date, payment_mode, 
                                            transaction_id, payment_id, payment_type, fee_component, 
                                            installment_id, term_id, remarks, status, created_by, created_at) 
                                           VALUES 
                                           (?, ?, ?, NOW(), 'online', ?, ?, ?, ?, ?, ?, ?, 'paid', ?, NOW())");
                    $stmt->execute([
                        $student_id,
                        $receipt_no,
                        $paid_amount,
                        $transaction_id,
                        $payment_id,
                        $payment_type,
                        $fee_component,
                        $installment_id,
                        $term_id,
                        $remarks,
                        $created_by
                    ]);

                    // Add to transaction logs (Align with payment-save.php)
                    $stmt_log = $conn->prepare("INSERT INTO tbl_payment_transactions 
                        (payment_id, student_id, transaction_type, amount, transaction_date, description, processed_by) 
                        VALUES (?, ?, 'payment', ?, NOW(), ?, ?)");
                    $last_payment_id = $conn->lastInsertId();
                    $stmt_log->execute([
                        $last_payment_id,
                        $student_id,
                        $paid_amount,
                        "Online payment - Component: $fee_component, Receipt: $receipt_no",
                        $created_by
                    ]);

                    // Update installment status if applicable
                    if ($installment_id) {
                        $stmt = $conn->prepare("UPDATE tbl_fee_installments 
                                               SET status = 'paid',
                                                   paid_amount = ?,
                                                   payment_date = NOW(),
                                                   transaction_id = ?,
                                                   updated_at = NOW()
                                               WHERE id = ?");
                        $stmt->execute([$paid_amount, $transaction_id, $installment_id]);
                    }

                    // Update student fee status
                    $update_field = $fee_component . '_paid';
                    $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                                           SET {$update_field} = 1,
                                               updated_at = NOW()
                                           WHERE id = ?");
                    $stmt->execute([$student_id]);

                    $receipt_numbers = [$receipt_no];
                }


                // Update order status (common for all payment types)
                $stmt = $conn->prepare("UPDATE tbl_payment_orders 
                                       SET status = 'completed', 
                                           payment_id = ?,
                                           completed_at = NOW()
                                       WHERE id = ?");
                $stmt->execute([$payment_id, $order['id']]);

                $conn->commit();

                // Send notifications after successful payment
                try {
                    // Fetch student details for notification
                    $stmt = $conn->prepare("SELECT r.*, 
                                           CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) as full_name,
                                           c.course_name 
                                           FROM tbl_gm_std_registration r
                                           LEFT JOIN tbl_courses c ON r.course_id = c.id
                                           WHERE r.id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();

                    if ($student) {
                        $recipient = [
                            'name' => $student['full_name'],
                            'email' => $student['email'] ?? 'noemail@example.com',
                            'mobile' => $student['mob'] ?? ''
                        ];

                        // Use appropriate template based on payment type
                        if ($payment_type === 'token_fee') {
                            $notification_template = 'token_fee_success';
                            $variables = [
                                'student_name' => $student['full_name'],
                                'amount' => formatIndianCurrency($amount),
                                'receipt_no' => implode(', ', $receipt_numbers ?? ['N/A']),
                                'transaction_id' => $transaction_id
                            ];
                        } else {
                            $notification_template = 'fee_payment_success';
                            $variables = [
                                'student_name' => $student['full_name'],
                                'amount' => formatIndianCurrency($amount),
                                'payment_mode' => 'Online',
                                'receipt_no' => implode(', ', $receipt_numbers ?? ['N/A']),
                                'payment_date' => date('d-M-Y')
                            ];
                        }

                        sendNotification(
                            $conn,
                            $notification_template,
                            $recipient,
                            $variables,
                            ['student_id' => $student_id]
                        );
                    }
                } catch (Exception $e) {
                    // Log notification error but don't fail the payment
                    logPaymentError("Notification", $txnid, $e->getMessage());
                }

                // Restore complete student session for redirect (fetch student details)
                $stmt_student = $conn->prepare("SELECT id, surname, student_name, fathers_name, mob, aadhaar 
                                                FROM tbl_gm_std_registration 
                                                WHERE id = ?");
                $stmt_student->execute([$student_id]);
                $student_data = $stmt_student->fetch();

                if ($student_data) {
                    $_SESSION['student_id'] = $student_data['id'];
                    $_SESSION['student_name'] = trim($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . ($student_data['fathers_name'] ?? ''));
                    $_SESSION['student_mobile'] = $student_data['mob'];
                    $_SESSION['student_aadhaar'] = $student_data['aadhaar'];
                    $_SESSION['user_role'] = 'student';
                    $_SESSION['is_student_login'] = true;
                    $_SESSION['login_time'] = time();
                    $_SESSION['token_payment_verified'] = true;
                }

                // Dynamic success message based on payment type
                $receipt_list = implode(', ', $receipt_numbers ?? ['N/A']);
                if ($payment_type === 'token_fee') {
                    set_flash_message('success', "Payment successful! Your token fee has been paid. Receipt No: " . $receipt_list);
                } else {
                    $fee_labels = [
                        'school_fee' => 'School Fee',
                        'trust_facilities_fee' => 'Trust Facilities Fee',
                        'tuition_fee_part2' => 'Tuition Fee Part 2',
                        'hostel_fee' => 'Hostel Fee',
                        'transport_fee' => 'Transport Fee'
                    ];
                    $fee_name = $fee_labels[$payment_type] ?? 'Fee';
                    set_flash_message('success', "Payment successful! Your {$fee_name} has been paid. Receipt No: " . $receipt_list);
                }

                header('Location: ../student-portal/my-fees.php');
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                logPaymentError("Processing", $txnid, $e->getMessage(), ['payment_type' => $payment_type, 'amount' => $amount]);

                // Restore complete student session for redirect
                if ($student_id) {
                    try {
                        $stmt_student = $conn->prepare("SELECT id, surname, student_name, fathers_name, mob, aadhaar 
                                                        FROM tbl_gm_std_registration 
                                                        WHERE id = ?");
                        $stmt_student->execute([$student_id]);
                        $student_data = $stmt_student->fetch();

                        if ($student_data) {
                            $_SESSION['student_id'] = $student_data['id'];
                            $_SESSION['student_name'] = trim($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . ($student_data['fathers_name'] ?? ''));
                            $_SESSION['student_mobile'] = $student_data['mob'];
                            $_SESSION['student_aadhaar'] = $student_data['aadhaar'];
                            $_SESSION['user_role'] = 'student';
                            $_SESSION['is_student_login'] = true;
                            $_SESSION['login_time'] = time();
                        }
                    } catch (Exception $ex) {
                        // If session restoration fails, just set minimal session
                        $_SESSION['student_id'] = $student_id;
                        $_SESSION['user_role'] = 'student';
                        $_SESSION['is_student_login'] = true;
                    }
                }

                set_flash_message('error', "Error processing payment. Contact support with payment ID: " . $payment_id);
                header('Location: ../student-portal/my-fees.php');
                exit;
            }
        } else {
            // Payment failed or cancelled
            $error_msg = $_POST['error_Message'] ?? 'Payment failed';
            logPaymentError("Payment Failed", $txnid, $error_msg, ['status' => $status]);

            // Restore complete student session for redirect
            if ($student_id) {
                try {
                    $stmt_student = $conn->prepare("SELECT id, surname, student_name, fathers_name, mob, aadhaar 
                                                    FROM tbl_gm_std_registration 
                                                    WHERE id = ?");
                    $stmt_student->execute([$student_id]);
                    $student_data = $stmt_student->fetch();

                    if ($student_data) {
                        $_SESSION['student_id'] = $student_data['id'];
                        $_SESSION['student_name'] = trim($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . ($student_data['fathers_name'] ?? ''));
                        $_SESSION['student_mobile'] = $student_data['mob'];
                        $_SESSION['student_aadhaar'] = $student_data['aadhaar'];
                        $_SESSION['user_role'] = 'student';
                        $_SESSION['is_student_login'] = true;
                        $_SESSION['login_time'] = time();
                    }
                } catch (Exception $ex) {
                    // If session restoration fails, just set minimal session
                    $_SESSION['student_id'] = $student_id;
                    $_SESSION['user_role'] = 'student';
                    $_SESSION['is_student_login'] = true;
                }
            }

            set_flash_message('error', "Payment " . $status . ": " . htmlspecialchars($error_msg ?? ''));
            header('Location: ../student-portal/my-fees.php');
            exit;
        }
    } else {
        // Hash verification failed
        logPaymentError("Hash Verification Failed", $txnid, "Response hash mismatch");

        // Restore complete student session for redirect
        if ($student_id) {
            try {
                $stmt_student = $conn->prepare("SELECT id, surname, student_name, fathers_name, mob, aadhaar 
                                                FROM tbl_gm_std_registration 
                                                WHERE id = ?");
                $stmt_student->execute([$student_id]);
                $student_data = $stmt_student->fetch();

                if ($student_data) {
                    $_SESSION['student_id'] = $student_data['id'];
                    $_SESSION['student_name'] = trim($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . ($student_data['fathers_name'] ?? ''));
                    $_SESSION['student_mobile'] = $student_data['mob'];
                    $_SESSION['student_aadhaar'] = $student_data['aadhaar'];
                    $_SESSION['user_role'] = 'student';
                    $_SESSION['is_student_login'] = true;
                    $_SESSION['login_time'] = time();
                }
            } catch (Exception $ex) {
                // If session restoration fails, just set minimal session
                $_SESSION['student_id'] = $student_id;
                $_SESSION['user_role'] = 'student';
                $_SESSION['is_student_login'] = true;
            }
        }

        set_flash_message('error', "Payment verification failed. Please contact support.");
        header('Location: ../student-portal/my-fees.php');
        exit;
    }
} else {
    // Invalid callback - try to restore session if student_id available
    $student_id = $_GET['udf1'] ?? $_POST['udf1'] ?? null;

    if ($student_id) {
        try {
            $stmt_student = $conn->prepare("SELECT id, surname, student_name, fathers_name, mob, aadhaar 
                                            FROM tbl_gm_std_registration 
                                            WHERE id = ?");
            $stmt_student->execute([$student_id]);
            $student_data = $stmt_student->fetch();

            if ($student_data) {
                $_SESSION['student_id'] = $student_data['id'];
                $_SESSION['student_name'] = trim($student_data['surname'] . ' ' . $student_data['student_name'] . ' ' . ($student_data['fathers_name'] ?? ''));
                $_SESSION['student_mobile'] = $student_data['mob'];
                $_SESSION['student_aadhaar'] = $student_data['aadhaar'];
                $_SESSION['user_role'] = 'student';
                $_SESSION['is_student_login'] = true;
                $_SESSION['login_time'] = time();
            }
        } catch (Exception $ex) {
            // If session restoration fails, just set minimal session
            $_SESSION['student_id'] = $student_id;
            $_SESSION['user_role'] = 'student';
            $_SESSION['is_student_login'] = true;
        }
    }

    set_flash_message('error', "Invalid payment response");
    header('Location: ../student-portal/my-fees.php');
    exit;
}