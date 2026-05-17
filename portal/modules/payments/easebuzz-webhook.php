<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * EaseBuzz Transaction Webhook Handler
 * Receives real-time payment notifications from EaseBuzz
 * URL: https://yourdomain.com/[your-project]/portal/modules/payments/easebuzz-webhook.php
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
if (!isset($dbOps)) {
    $dbOps = new Operation($conn);
}
require_once __DIR__ . '/../../../common/helpers/error_logger.php';
require_once __DIR__ . '/../../../common/helpers/enrollment_functions.php';
require_once __DIR__ . '/../../../common/helpers/division_assignment_functions.php';
require_once __DIR__ . '/../../../common/helpers/receipt_sequence_helper.php';
require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';
require_once __DIR__ . '/../../common/easebuzz_loader.php';

/**
 * Enhanced Logging (Old System Style)
 */
function logWebhookData($message, $data = null, $status = 'WEBHOOK')
{
    logGatewayActivity($message, $status, $data);
}

// Get raw POST data
$webhook_data = $_POST;

if (empty($webhook_data)) {
    logWebhookData("EaseBuzz Webhook - No POST data received", null, 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

// Extract parameters
$txnid = $webhook_data['txnid'] ?? '';
$status = $webhook_data['status'] ?? '';
$amount = $webhook_data['amount'] ?? '';
$payment_id = $webhook_data['easepayid'] ?? '';
$hash = $webhook_data['hash'] ?? '';
$student_id = $webhook_data['udf1'] ?? '';
$payment_type = $webhook_data['udf2'] ?? '';
$transaction_id = $webhook_data['udf3'] ?? '';
$installment_id = $webhook_data['udf4'] ?? ''; // Installment ID if present
$addedon = $webhook_data['addedon'] ?? date('Y-m-d H:i:s');
$payment_date_db = date('Y-m-d H:i:s', strtotime($addedon));
$payment_date_formatted = date('d-M-Y', strtotime($addedon));

logWebhookData("EaseBuzz Webhook Incoming | TxnID: $txnid | Status: $status | AddedOn: $addedon", $webhook_data);

// Validate required fields
if (empty($txnid) || empty($status) || empty($hash)) {
    logWebhookData("EaseBuzz Webhook - Missing required fields", $webhook_data, 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

try {
    require_once __DIR__ . '/../../../common/config/app-config.php';
    $config = getPaymentGatewayConfig('easebuzz');

    if (!$config) {
        logWebhookData("EaseBuzz Webhook - Gateway configuration not found", null, 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Config not found']);
        exit;
    }

    $salt = $config['api_secret'];
    $key = $config['api_key'];

    // --- REPLICATE OLD SYSTEM HASH LOGIC ---
    // SALT|status|udf10|udf9|udf8|udf7|udf6|udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key
    $hashString = $salt . '|' .
        ($webhook_data['status'] ?? '') . '|' .
        ($webhook_data['udf10'] ?? '') . '|' .
        ($webhook_data['udf9'] ?? '') . '|' .
        ($webhook_data['udf8'] ?? '') . '|' .
        ($webhook_data['udf7'] ?? '') . '|' .
        ($webhook_data['udf6'] ?? '') . '|' .
        ($webhook_data['udf5'] ?? '') . '|' .
        ($webhook_data['udf4'] ?? '') . '|' .
        ($webhook_data['udf3'] ?? '') . '|' .
        ($webhook_data['udf2'] ?? '') . '|' .
        ($webhook_data['udf1'] ?? '') . '|' .
        ($webhook_data['email'] ?? '') . '|' .
        ($webhook_data['firstname'] ?? '') . '|' .
        ($webhook_data['productinfo'] ?? '') . '|' .
        ($webhook_data['amount'] ?? '') . '|' .
        ($webhook_data['txnid'] ?? '') . '|' .
        ($webhook_data['key'] ?? $key);

    $calculatedHash = hash('sha512', $hashString);

    if (!hash_equals($calculatedHash, $hash)) {
        logWebhookData("Hash verification failed", ['received' => $hash, 'calculated' => $calculatedHash], 'FAILED');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Hash mismatch']);
        exit;
    }

    logWebhookData("Hash verification successful", null, 'INFO');

    // --- PREFERENCE LOGIC: Wait for Callback ---
    // We wait 2 seconds to give the browser-side callback preference to finish first
    sleep(2);

    // --- IDEMPOTENCY CHECK (Final Truth) ---
    // Start transaction early for locking
    $conn->beginTransaction();

    // Check if transaction is already in tbl_payments
    $stmt_check = $conn->prepare("SELECT id FROM tbl_payments WHERE transaction_id = ? LIMIT 1");
    $stmt_check->execute([$transaction_id ?: $txnid]);
    if ($stmt_check->fetch()) {
        $conn->rollBack();
        logWebhookData("Webhook Skip - Transaction already processed into tbl_payments", null, 'INFO');
        
        // Fix Order status if it was stuck
        $conn->prepare("UPDATE tbl_payment_orders SET status = 'completed', completed_at = NOW() WHERE (transaction_id = ? OR gateway_order_id = ?) AND status != 'completed'")
             ->execute([$transaction_id ?: $txnid, $txnid]);
             
        echo json_encode(['status' => 'success', 'message' => 'Already processed']);
        exit;
    }

    // Fetch Order Info with FOR UPDATE lock
    $stmt_order = $conn->prepare("SELECT id, status, split_amounts, component_breakdown FROM tbl_payment_orders WHERE (transaction_id = ? OR gateway_order_id = ?) LIMIT 1 FOR UPDATE");
    $stmt_order->execute([$transaction_id ?: $txnid, $txnid]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $conn->rollBack();
        logWebhookData("Order not found for txnid: $txnid / $transaction_id", null, 'ERROR');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    if ($order['status'] === 'completed') {
        $conn->rollBack();
        logWebhookData("Webhook Skip - Order already marked completed", null, 'INFO');
        echo json_encode(['status' => 'success', 'message' => 'Already processed']);
        exit;
    }

    // Process based on status
    if ($status === 'success' || $status === 'completed') {
        // Payment successful - Transaction already started above
        try {
            // --- PROCESS SPLIT PAYMENTS ---
            // Fetch split data from order
            $split_data = [];
            if (!empty($order['split_amounts'])) {
                $split_data = json_decode($order['split_amounts'], true);
            }

            $component_breakdown = [];
            if (in_array($payment_type, ['multiple_pending_fees', 'MultipleFees']) && !empty($order['component_breakdown'])) {
                $breakdown_data = json_decode($order['component_breakdown'], true);
                if (is_array($breakdown_data)) {
                    foreach ($breakdown_data as $component_name => $component_amount) {
                        $component_amount = floatval($component_amount);
                        if (!empty($component_name) && $component_amount > 0) {
                            $component_breakdown[$component_name] = $component_amount;
                        }
                    }
                }
            }

            $school_id = getStudentSchoolId($conn, $student_id);
            $payment_ids = [];
            $receipt_numbers = [];

            if ($payment_type === 'multiple_pending_fees' && !empty($component_breakdown)) {
                foreach ($component_breakdown as $component_name => $component_amount) {
                    $payment_type_label = formatFeeType($component_name);

                    // Get official receipt number
                    $seq_result = getNextReceiptNumber($conn, $component_name, $school_id, null, $student_id);
                    if ($seq_result['success']) {
                        $receipt_no = $seq_result['receipt_no'];
                        $receipt_numbers[] = $receipt_no;
                    } else {
                        $receipt_no = 'EZB-ERR-' . date('Ymd') . '-' . substr($payment_id, -4);
                        $receipt_numbers[] = $receipt_no;
                    }

                    // Get correct receipt config ID
                    $receipt_config_id = getReceiptConfigForFee($conn, $component_name, $school_id);

                    $stmt_ins = $conn->prepare("INSERT INTO tbl_payments 
                                           (student_id, receipt_no, amount, payment_date, payment_mode, 
                                            transaction_id, payment_id, payment_type, fee_component, receipt_config_id, remarks, 
                                            status, created_by, created_at) 
                                           VALUES 
                                           (?, ?, ?, ?, 'online', ?, ?, ?, ?, ?, 'Payment via EaseBuzz Webhook', 'paid', 31, NOW())");
                    $stmt_ins->execute([$student_id, $receipt_no, $component_amount, $payment_date_db, $transaction_id, $payment_id, $payment_type_label, $component_name, $receipt_config_id]);
                    $payment_ids[] = $conn->lastInsertId();

                    // Update tbl_pending_payments
                    $stmt_pend = $conn->prepare("UPDATE tbl_pending_payments SET status = 'completed', updated_at = NOW() WHERE student_id = ? AND payment_type = ? AND status = 'pending'");
                    $stmt_pend->execute([$student_id, $component_name]);
                }
            } else if (!empty($split_data)) {
                foreach ($split_data as $label => $amt) {
                    // Map Label to Fee Component
                    $fee_component = '';
                    $payment_type_label = '';

                    switch (strtoupper($label)) {
                        case 'GHSS':
                            $fee_component = 'school_fee';
                            $payment_type_label = 'School Fee';
                            break;
                        case 'MST':
                            if (in_array($webhook_data['udf4'] ?? '', ['transport_fee', 'hostel_fee', 'hostel_security'])) {
                                $fee_component = $webhook_data['udf4'];
                                $payment_type_label = ucwords(str_replace('_', ' ', $fee_component));
                            } else {
                                $fee_component = 'trust_facilities_fee';
                                $payment_type_label = 'Trust Facilities Fee';
                            }
                            break;
                        case 'GCA':
                            $fee_component = 'tuition_fee_part2';
                            $payment_type_label = 'Tuition Fee Part 2';
                            break;
                        default:
                            $fee_component = 'other';
                            $payment_type_label = $label . ' Fee';
                    }

                    // Get official receipt number
                    $seq_result = getNextReceiptNumber($conn, $fee_component, $school_id, null, $student_id);
                    if ($seq_result['success']) {
                        $receipt_no = $seq_result['receipt_no'];
                        $receipt_numbers[] = $receipt_no;
                    } else {
                        $receipt_no = 'EZB-ERR-' . date('Ymd') . '-' . substr($payment_id, -4);
                        $receipt_numbers[] = $receipt_no;
                    }

                    // Get correct receipt config ID
                    $receipt_config_id = getReceiptConfigForFee($conn, $fee_component, $school_id);

                    $stmt_ins = $conn->prepare("INSERT INTO tbl_payments 
                                           (student_id, receipt_no, amount, payment_date, payment_mode, 
                                            transaction_id, payment_id, payment_type, fee_component, receipt_config_id, remarks, 
                                            status, created_by, created_at) 
                                           VALUES 
                                           (?, ?, ?, ?, 'online', ?, ?, ?, ?, ?, 'Payment via EaseBuzz Webhook', 'paid', 31, NOW())");
                    $stmt_ins->execute([$student_id, $receipt_no, $amt, $payment_date_db, $transaction_id, $payment_id, $payment_type_label, $fee_component, $receipt_config_id]);
                    $payment_ids[] = $conn->lastInsertId();

                    // Update tbl_pending_payments
                    $stmt_pend = $conn->prepare("UPDATE tbl_pending_payments SET status = 'completed', updated_at = NOW() WHERE student_id = ? AND payment_type = ? AND status = 'pending'");
                    $stmt_pend->execute([$student_id, $fee_component]);
                }
            } else {
                // Fallback for non-split payments
                $seq_result = getNextReceiptNumber($conn, $payment_type, $school_id, null, $student_id);
                $receipt_no = $seq_result['success'] ? $seq_result['receipt_no'] : 'EZB-' . date('Ymd') . '-' . $student_id;

                // Get correct receipt config ID
                $receipt_config_id = getReceiptConfigForFee($conn, $payment_type, $school_id);

                $stmt = $conn->prepare("INSERT INTO tbl_payments 
                                       (student_id, receipt_no, amount, payment_date, payment_mode, 
                                        transaction_id, payment_id, payment_type, fee_component, receipt_config_id, remarks, 
                                        status, created_by, created_at) 
                                       VALUES 
                                       (?, ?, ?, ?, 'online', ?, ?, ?, ?, ?, 'Payment via EaseBuzz Webhook', 'paid', 31, NOW())");
                $stmt->execute([$student_id, $receipt_no, $amount, $payment_date_db, $transaction_id, $payment_id, $payment_type, $payment_type, $receipt_config_id]);
                $payment_ids[] = $conn->lastInsertId();
                $receipt_numbers[] = $receipt_no;

                $stmt = $conn->prepare("UPDATE tbl_pending_payments 
                                       SET status = 'completed', gateway_response = ?, updated_at = NOW()
                                       WHERE student_id = ? AND payment_type = ? AND status = 'pending'");
                $stmt->execute([json_encode($webhook_data), $student_id, $payment_type]);
            }

            // Update order status
            $stmt = $conn->prepare("UPDATE tbl_payment_orders 
                                   SET status = 'completed', 
                                       payment_id = ?,
                                       completed_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([$payment_id, $order['id']]);

            // Auto-enroll student
            if ($payment_type === 'token_fee' || in_array('tuition_fee_part1', array_column($split_data, 'fee_component'))) {
                $enrollment_result = enrollStudentAfterTokenPayment($conn, $student_id);
                if ($enrollment_result['success'] && !empty($enrollment_result['enrollment_id'])) {
                    assignDivisionAndRollNumber($conn, $enrollment_result['enrollment_id']);
                }
            }

            // Sync Fee Allocation
            require_once __DIR__ . '/../../../common/helpers/fee_allocation_helper.php';
            syncStudentFeeAllocation($conn, $student_id);

            // --- Save Receipt to Backup Folder & Prepare Attachments ---
            $notif_options = ['student_id' => $student_id, 'attachments' => []];
            if (!empty($payment_ids)) {
                try {
                    require_once __DIR__ . '/../../../common/helpers/receipt_pdf_helper.php';
                    require_once __DIR__ . '/../../../common/helpers/notification_functions.php';
                    $backup_folder = 'D:/Receipt Backup';

                    foreach ($payment_ids as $p_id) {
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

                    // Send Notifications
                    $stmt_std = $conn->prepare("SELECT student_name, email, mob FROM tbl_gm_std_registration WHERE id = ?");
                    $stmt_std->execute([$student_id]);
                    $student = $stmt_std->fetch();

                    if ($student && !empty($student['email'])) {
                        $recipient = ['name' => $student['student_name'], 'email' => $student['email'], 'mobile' => $student['mob']];
                        if ($payment_type === 'token_fee') {
                            // Template: tokenfeesuconline_21 (Name, Amount, Receipt, TxnID)
                            $variables = [
                                'student_name' => $student['student_name'],
                                'amount' => formatIndianCurrency($amount),
                                'receipt_no' => implode(', ', $receipt_numbers),
                                'transaction_id' => $txnid,
                                'payment_mode' => 'Online',
                                'fee_component_label' => 'Token Fee'
                            ];
                        } else {
                            // Template: feepaymentsuccess_002 (Name, Amount, Mode, Receipt, Date)
                            $variables = [
                                'student_name' => $student['student_name'],
                                'amount' => formatIndianCurrency($amount),
                                'payment_mode' => 'Online',
                                'receipt_no' => implode(', ', $receipt_numbers),
                                'payment_date' => $payment_date_formatted,
                                'fee_component_label' => !empty($payment_type) ? ucwords(str_replace('_', ' ', $payment_type)) : 'Fee'
                            ];
                        }

                        sendNotification($conn, $payment_type === 'token_fee' ? 'token_fee_success' : 'fee_payment_success', $recipient, $variables, $notif_options);
                    }
                } catch (Exception $e) {
                    logGatewayActivity("Failed to process receipt/notification for student $student_id: " . $e->getMessage(), 'WARNING');
                }
            }

            if ($conn->inTransaction()) {
                $conn->commit();
            }

            logGatewayActivity("Webhook Payment Success | TxnID: $txnid | Amount: $amount", 'SUCCESS', $webhook_data);
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            logGatewayActivity("EaseBuzz Webhook - Error processing payment: " . $e->getMessage(), 'ERROR', ['txnid' => $txnid]);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error processing payment']);
        }
    } elseif ($status === 'failure' || $status === 'failed') {
        // Payment failed
        $stmt = $conn->prepare("UPDATE tbl_payment_orders 
                               SET status = 'failed'
                               WHERE id = ?");
        $stmt->execute([$order['id']]);

        $stmt = $conn->prepare("UPDATE tbl_pending_payments 
                               SET status = 'failed',
                                   gateway_response = ?,
                                   updated_at = NOW()
                               WHERE student_id = ? AND payment_type = 'token_fee' AND status = 'pending'");
        $stmt->execute([json_encode($webhook_data), $student_id]);

        logGatewayActivity("EaseBuzz Webhook - Payment failed | TxnID: $txnid | Status: $status", 'FAILED', $webhook_data);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment failure recorded']);
    } else {
        // Other status (pending, cancelled, etc.)
        logError("EaseBuzz Webhook - Unhandled status: $status | TxnID: $txnid");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Status noted']);
    }
} catch (PDOException $e) {
    logGatewayActivity("EaseBuzz Webhook - Database error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
} catch (Exception $e) {
    logGatewayActivity("EaseBuzz Webhook - Server error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
