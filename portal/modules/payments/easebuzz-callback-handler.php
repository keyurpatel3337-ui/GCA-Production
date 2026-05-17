<?php

/**
 * Unified EaseBuzz Callback Handler
 * Handles all payment responses from EaseBuzz gateway
 * Replaces easebuzz-callback.php and easebuzz-pending-callback.php
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/services/EaseBuzzService.php';

// Include necessary helpers
require_once __DIR__ . '/../../../common/helpers/error_logger.php';
require_once __DIR__ . '/../../../common/helpers/enrollment_functions.php';
require_once __DIR__ . '/../../../common/helpers/division_assignment_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/receipt_sequence_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/notification_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/whatsapp_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_allocation_helper.php';
require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/session_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$response = $_POST;
$txnid = $response['txnid'] ?? '';
$status = $response['status'] ?? '';
$amount = floatval($response['amount'] ?? 0);
$payment_id = $response['easepayid'] ?? '';
$student_id = $response['udf1'] ?? '';
$payment_type = $response['udf2'] ?? ''; // token_fee, multiple_pending_fees, school_fee, etc.
$transaction_id = $response['udf3'] ?? '';
$fee_component = $response['udf4'] ?? '';
$fee_label = $response['udf5'] ?? 'Fee Payment';
$installment_id = !empty($response['udf6']) ? intval($response['udf6']) : null;
$addedon = $response['addedon'] ?? date('Y-m-d H:i:s');
$payment_date_db = date('Y-m-d H:i:s', strtotime($addedon));
$payment_date_formatted = date('d-M-Y', strtotime($addedon));

try {
    $ebService = new EaseBuzzService();
    if (!$ebService->verifyResponse($response)) {
        throw new Exception("Hash verification failed");
    }

    if ($status !== 'success') {
        throw new Exception("Payment gateway returned status: " . $status . " - " . ($response['error_Message'] ?? ''));
    }

    // Process Successful Payment
    $dbOps = new Operation();
    $conn->beginTransaction();

    // Fetch Order by transaction_id with FOR UPDATE lock (Atomic)
    $stmt_order = $conn->prepare("SELECT * FROM tbl_payment_orders WHERE transaction_id = ? LIMIT 1 FOR UPDATE");
    $stmt_order->execute([$transaction_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $conn->rollBack();
        throw new Exception("Payment order not found for transaction: " . $transaction_id);
    }

    // Guard Clause: If order is already completed, skip processing
    if ($order['status'] === 'completed') {
        logGatewayActivity("EaseBuzz Callback - Order already processed | TxnID: $transaction_id", 'INFO');
        set_flash_message('success', "Payment already processed. Redirecting to dashboard.");
        header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
        exit;
    }

    // Double check if this transaction is already recorded in tbl_payments (Final Truth)
    $stmt_check = $conn->prepare("SELECT id FROM tbl_payments WHERE transaction_id = ? LIMIT 1");
    $stmt_check->execute([$transaction_id]);
    if ($stmt_check->fetch()) {
        logGatewayActivity("EaseBuzz Callback - Transaction already exists in tbl_payments | TxnID: $transaction_id", 'INFO');
        
        // Ensure order is also marked completed if it wasn't
        $conn->prepare("UPDATE tbl_payment_orders SET status = 'completed', completed_at = NOW() WHERE transaction_id = ? AND status != 'completed'")->execute([$transaction_id]);
        
        set_flash_message('success', "Payment already recorded. Redirecting to dashboard.");
        header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
        exit;
    }

    $created_by = 31; // Account Department (System / Online Payment)
    $receipt_numbers = [];
    $new_payment_ids = [];

    // Fetch school ID and Academic Year
    $school_id = getStudentSchoolId($conn, $student_id);
    $academic_year = getCurrentAcademicYear($conn);

    // Fetch student's current term_id from enrollment (Align with payment-save.php)
    $stmt_term = $conn->prepare("SELECT current_term_id FROM tbl_enrolled_students WHERE registration_id = ? AND is_active = 1");
    $stmt_term->execute([$student_id]);
    $term_data = $stmt_term->fetch();
    $term_id = $term_data['current_term_id'] ?? 1;

    // Fetch split data from order (This is the most reliable source)
    $split_data = [];
    if (!empty($order['split_amounts'])) {
        $split_data = json_decode($order['split_amounts'], true);
    }

    $component_breakdown = [];
    if (in_array($payment_type, ['multiple_pending_fees', 'MultipleFees'])) {
        if (!empty($_SESSION['pending_payment']['items']) && is_array($_SESSION['pending_payment']['items'])) {
            foreach ($_SESSION['pending_payment']['items'] as $item) {
                $c = $item['component'] ?? '';
                $a = floatval($item['amount'] ?? 0);
                if ($c !== '' && $a > 0) {
                    if (!isset($component_breakdown[$c])) {
                        $component_breakdown[$c] = 0;
                    }
                    $component_breakdown[$c] += $a;
                }
            }
        }

        if (empty($component_breakdown) && !empty($response['udf7'])) {
            $udf7_data = json_decode($response['udf7'], true);
            if (is_array($udf7_data)) {
                foreach ($udf7_data as $c => $a) {
                    $a = floatval($a);
                    if ($c !== '' && $a > 0) {
                        $component_breakdown[$c] = $a;
                    }
                }
            }
        }
    }

    if (in_array($payment_type, ['multiple_pending_fees', 'MultipleFees']) && !empty($component_breakdown)) {
        foreach ($component_breakdown as $component_name => $component_amount) {
            $payment_type_label = formatFeeType($component_name);

            $seq_result = getNextReceiptNumber($conn, $component_name, $school_id, null, $student_id);
            if ($seq_result['success']) {
                $r_no = $seq_result['receipt_no'];
                $receipt_numbers[] = $r_no;
            } else {
                $r_no = 'EZB-FAIL-' . date('Ymd') . '-' . substr($payment_id, -4);
                $receipt_numbers[] = $r_no;
            }

            $r_conf = getReceiptConfigForFee($conn, $component_name, $school_id) ?: 1;

            $last_payment_id = $dbOps->insert('tbl_payments', [
                'student_id' => $student_id,
                'receipt_no' => $r_no,
                'amount' => $component_amount,
                'payment_date' => $payment_date_db,
                'payment_mode' => 'online',
                'transaction_id' => $transaction_id,
                'payment_id' => $payment_id,
                'payment_type' => $payment_type_label,
                'fee_component' => $component_name,
                'term_id' => $term_id,
                'receipt_config_id' => $r_conf,
                'remarks' => $payment_type_label . ' (Online via EaseBuzz)',
                'status' => 'paid',
                'created_by' => $created_by,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $new_payment_ids[] = $last_payment_id;

            $stmt_log = $conn->prepare("INSERT INTO tbl_payment_transactions 
                (payment_id, student_id, transaction_type, amount, transaction_date, description, processed_by) 
                VALUES (?, ?, 'payment', ?, NOW(), ?, ?)");
            $stmt_log->execute([
                $last_payment_id,
                $student_id,
                $component_amount,
                "Online payment - Component: $component_name, Receipt: $r_no",
                $created_by
            ]);

            $stmt = $conn->prepare("DELETE FROM tbl_pending_payments WHERE student_id = ? AND payment_type = ?");
            $stmt->execute([$student_id, $component_name]);
        }
    } else if (!empty($split_data)) {
        // HANDLER FOR SPLIT PAYMENTS (GHSS, MST, GCA, etc.)
        foreach ($split_data as $label => $amt) {
            $fee_component = '';
            $payment_type_label = '';

            // Map Label to Fee Component (Consistent with easebuzz-webhook.php)
            // IMPROVEMENT: Priority is given to the component passed in initiation (udf4) 
            // if it matches the intended settlement entity (e.g. transport_fee belongs to MST).
            $label_upper = strtoupper($label);
            if (count($split_data) === 1 && !empty($fee_component)) {
                // If single component, trust the udf4 component if it's valid
                $payment_type_label = formatFeeType($fee_component);
            } else {
                switch ($label_upper) {
                    case 'GHSS':
                        $fee_component = 'school_fee';
                        $payment_type_label = 'School Fee';
                        break;
                    case 'MST':
                        // If udf4 is transport or hostel and label is MST, honor udf4
                        if (in_array($response['udf4'] ?? '', ['transport_fee', 'hostel_fee', 'hostel_security'])) {
                            $fee_component = $response['udf4'];
                        } else {
                            $fee_component = 'trust_facilities_fee';
                        }
                        $payment_type_label = formatFeeType($fee_component);
                        break;
                    case 'GCA':
                        $fee_component = 'tuition_fee_part2';
                        $payment_type_label = 'Tuition Fee Part 2';
                        break;
                    default:
                        $fee_component = 'other';
                        $payment_type_label = $label . ' Fee';
                }
            }

            // Get receipt number
            $seq_result = getNextReceiptNumber($conn, $fee_component, $school_id, null, $student_id);
            if ($seq_result['success']) {
                $r_no = $seq_result['receipt_no'];
                $receipt_numbers[] = $r_no;
            } else {
                $r_no = 'EZB-FAIL-' . date('Ymd') . '-' . substr($payment_id, -4);
                $receipt_numbers[] = $r_no;
            }

            $r_conf = getReceiptConfigForFee($conn, $fee_component, $school_id) ?: 1;

            // Insert into tbl_payments
            $last_payment_id = $dbOps->insert('tbl_payments', [
                'student_id' => $student_id,
                'receipt_no' => $r_no,
                'amount' => $amt,
                'payment_date' => $payment_date_db,
                'payment_mode' => 'online',
                'transaction_id' => $transaction_id,
                'payment_id' => $payment_id,
                'payment_type' => $payment_type_label,
                'fee_component' => $fee_component,
                'term_id' => $term_id,
                'receipt_config_id' => $r_conf,
                'remarks' => $payment_type_label . ' (Online via EaseBuzz)',
                'status' => 'paid',
                'created_by' => $created_by,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $new_payment_ids[] = $last_payment_id;

            // Add to transaction logs (Align with payment-save.php)
            $stmt_log = $conn->prepare("INSERT INTO tbl_payment_transactions 
                (payment_id, student_id, transaction_type, amount, transaction_date, description, processed_by) 
                VALUES (?, ?, 'payment', ?, NOW(), ?, ?)");
            $stmt_log->execute([
                $last_payment_id,
                $student_id,
                $amt,
                "Online payment - Component: $fee_component, Receipt: $r_no",
                $created_by
            ]);

            // Update pending payment
            $stmt = $conn->prepare("DELETE FROM tbl_pending_payments WHERE student_id = ? AND payment_type = ?");
            $stmt->execute([$student_id, $fee_component]);
        }

        // Auto-enroll if part 1 or token
        if ($payment_type === 'token_fee' || in_array('tuition_fee_part1', array_keys($split_data))) {
            enrollStudentAfterTokenPayment($conn, $student_id);
        }

    } else if ($payment_type === 'token_fee') {
        // LEGACY HANDLER FOR TOKEN FEE (Single)
        $seq_result = getNextReceiptNumber($conn, 'tuition_fee_part1', $school_id, null, $student_id);
        if (!$seq_result['success'])
            throw new Exception("Receipt generation failed: " . ($seq_result['error'] ?? ''));

        $receipt_no = $seq_result['receipt_no'];
        $receipt_config_id = getReceiptConfigForFee($conn, 'tuition_fee_part1', $school_id) ?: 1;

        $last_payment_id = $dbOps->insert('tbl_payments', [
            'student_id' => $student_id,
            'receipt_no' => $receipt_no,
            'amount' => $amount,
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_mode' => 'online',
            'transaction_id' => $transaction_id,
            'payment_id' => $payment_id,
            'payment_type' => 'token_fee',
            'fee_component' => 'tuition_fee_part1',
            'receipt_config_id' => $receipt_config_id,
            'remarks' => 'Token Fee',
            'status' => 'paid',
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $new_payment_ids[] = $last_payment_id;

        $stmt = $conn->prepare("DELETE FROM tbl_pending_payments WHERE student_id = ? AND payment_type = ?");
        $stmt->execute([$student_id, 'token_fee']);

        enrollStudentAfterTokenPayment($conn, $student_id);
        $receipt_numbers[] = $receipt_no;

    } else {
        // FALLBACK FOR SINGLE NON-SPLIT PAYMENTS
        $comp = $fee_component ?: $payment_type;
        $seq_result = getNextReceiptNumber($conn, $comp, $school_id, null, $student_id);
        if (!$seq_result['success'])
            throw new Exception("Receipt generation failed: " . ($seq_result['error'] ?? ''));

        $receipt_no = $seq_result['receipt_no'];
        $receipt_config_id = getReceiptConfigForFee($conn, $comp, $school_id) ?: 1;

        $last_payment_id = $dbOps->insert('tbl_payments', [
            'student_id' => $student_id,
            'receipt_no' => $receipt_no,
            'amount' => $amount,
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_mode' => 'online',
            'transaction_id' => $transaction_id,
            'payment_id' => $payment_id,
            'payment_type' => $payment_type,
            'fee_component' => $comp,
            'installment_id' => $installment_id,
            'receipt_config_id' => $receipt_config_id,
            'remarks' => $fee_label,
            'status' => 'paid',
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $new_payment_ids[] = $last_payment_id;

        if ($installment_id) {
            $stmt = $conn->prepare("UPDATE tbl_fee_installments SET status = 'paid', payment_status = 'paid', paid_amount = ?, payment_date = NOW(), receipt_no = ? WHERE id = ?");
            $stmt->execute([$amount, $receipt_no, $installment_id]);
        }

        $stmt = $conn->prepare("DELETE FROM tbl_pending_payments WHERE student_id = ? AND payment_type = ?");
        $stmt->execute([$student_id, $comp]);

        $receipt_numbers[] = $receipt_no;
    }

    $dbOps->update('tbl_payment_orders', ['status' => 'completed', 'payment_id' => $payment_id, 'completed_at' => date('Y-m-d H:i:s')], ['id' => $order['id']]);

    logGatewayActivity("Payment Success | TxnID: $txnid | Amount: $amount | PaymentID: $payment_id", 'SUCCESS', $response);

    $conn->commit();
    syncStudentFeeAllocation($conn, $student_id);
    restoreStudentSession($conn, $student_id);

    // --- Save Receipt to Backup Folder ---
    $notif_options = ['student_id' => $student_id, 'attachments' => []];
    if (!empty($new_payment_ids)) {
        try {
            require_once __DIR__ . '/../../../common/helpers/receipt_pdf_helper.php';
            $backup_folder = 'D:/Receipt Backup';
            foreach ($new_payment_ids as $p_id) {
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
            logGatewayActivity("Failed to save receipt backup for student $student_id: " . $e->getMessage(), 'WARNING');
        }
    }

    // Notifications
    try {
        $recipient = ['name' => $_SESSION['student_name'] ?? 'Student', 'email' => $_SESSION['student_email'] ?? '', 'mobile' => $_SESSION['student_mob'] ?? ''];

        if ($payment_type === 'token_fee') {
            // Template: tokenfeesuconline_21 (Name, Amount, Receipt, TxnID)
            $variables = [
                'student_name' => $recipient['name'],
                'amount' => formatIndianCurrency($amount),
                'receipt_no' => implode(', ', $receipt_numbers),
                'transaction_id' => $payment_id,
                'payment_mode' => 'Online',
                'fee_component_label' => 'Token Fee'
            ];
            $notif_type = 'token_fee_success';
        } else {
            // Template: feepaymentsuccess_002 (Name, Amount, Mode, Receipt, Date)
            $variables = [
                'student_name' => $recipient['name'],
                'amount' => formatIndianCurrency($amount),
                'payment_mode' => 'Online',
                'receipt_no' => implode(', ', $receipt_numbers),
                'payment_date' => $payment_date_formatted,
                'fee_component_label' => !empty($payment_type) ? ucwords(str_replace('_', ' ', $payment_type)) : 'Fee'
            ];
            $notif_type = 'fee_payment_success';
        }

        sendNotification($conn, $notif_type, $recipient, $variables, $notif_options);
    } catch (Exception $ne) {
        logGatewayActivity("Notification error after payment: " . $ne->getMessage(), 'WARNING', ['student_id' => $student_id]);
        error_log("Notification error: " . $ne->getMessage());
    }

    set_flash_message('success', "Payment successful! Receipt No: " . implode(', ', $receipt_numbers));
    header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction())
        $conn->rollBack();

    logGatewayActivity("EaseBuzz Callback Error: " . $e->getMessage(), 'FAILED', $response);
    error_log("EaseBuzz Callback Error: " . $e->getMessage());

    if ($student_id)
        restoreStudentSession($conn, $student_id);
    set_flash_message('error', "Payment Error: " . $e->getMessage());
    header('Location: ' . PORTAL_URL . '/modules/dashboard/student_dashboard.php');
    exit;
}
