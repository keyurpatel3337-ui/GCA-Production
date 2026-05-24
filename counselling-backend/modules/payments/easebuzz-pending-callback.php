<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once __DIR__ . '/../../../common/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/receipt_sequence_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once '../../easebuzz-lib/easebuzz_payment_gateway.php';

// EaseBuzz sends POST data on callback for pending fee payments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $response = $_POST;
    $txnid = $response['txnid'] ?? '';
    $status = $response['status'] ?? '';
    $amount = $response['amount'] ?? '';
    $payment_id = $response['easepayid'] ?? '';
    $student_id = $response['udf1'] ?? '';
    $payment_type = $response['udf2'] ?? '';
    $transaction_id = $response['udf3'] ?? '';
    $fee_component = $response['udf4'] ?? '';
    $fee_label = $response['udf5'] ?? '';
    $installment_id = isset($response['udf6']) && $response['udf6'] !== '' ? intval($response['udf6']) : null;

    // Fetch EaseBuzz configuration
    $config = $dbOps->selectOne('tbl_payment_gateway_config', ['*'], ['gateway_name' => 'easebuzz', 'is_active' => 1]);

    if (!$config) {
        set_flash_message('error', "Payment gateway configuration not found");
        header('Location: ../student-portal/my-fees.php');
        exit;
    }

    $merchant_key = $config['api_key'];
    $merchant_salt = $config['api_secret'];
    $env = $config['environment'] ?? 'test';

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

                // Use the actual amount paid from gateway response
                $paid_amount = floatval($amount);

                // Generate sequential receipt number using new sequence system
                $school_id = getStudentSchoolId($conn, $student_id);
                $academic_year = getCurrentAcademicYear($conn);
                $seq_result = getNextReceiptNumber($conn, $fee_component, $school_id, null, $student_id);

                if (!$seq_result['success']) {
                    throw new Exception('Failed to generate receipt number: ' . ($seq_result['error'] ?? 'Unknown error'));
                }

                $receipt_no = $seq_result['receipt_no'];

                // Insert payment record
                $stmt = $conn->prepare("INSERT INTO tbl_payments 
                                       (student_id, receipt_no, amount, payment_date, payment_mode, transaction_id, payment_id, payment_type, fee_component, installment_id, remarks, status, created_by, created_at) 
                                       VALUES 
                                       (?, ?, ?, NOW(), 'online', ?, ?, ?, ?, ?, ?, 'paid', ?, NOW())");
                $stmt->execute([
                    $student_id,
                    $receipt_no,
                    $paid_amount,
                    $transaction_id,
                    $payment_id,
                    $payment_type,
                    $fee_component,
                    $installment_id,
                    $fee_label . ' - Payment via EaseBuzz',
                    $created_by
                ]);

                // If this is an installment payment, update installment status
                if ($installment_id) {
                    $dbOps->update('tbl_fee_installments', [
                        'payment_status' => 'paid',
                        'paid_amount' => $paid_amount,
                        'payment_date' => 'NOW()'
                    ], ['id' => $installment_id]);
                }

                // Update order status
                $dbOps->update('tbl_payment_orders', [
                    'status' => 'completed',
                    'updated_at' => 'NOW()'
                ], ['gateway_order_id' => $txnid]);

                // Delete pending payment record if exists
                $dbOps->delete('tbl_pending_payments', ['student_id' => $student_id, 'payment_type' => $fee_component]);

                $conn->commit();

                // Restore student session for redirect
                $_SESSION['student_id'] = $student_id;
                $_SESSION['user_role'] = 'student';
                $_SESSION['is_student_login'] = true;

                // Send notifications
                $student_data = $dbOps->selectOne('tbl_gm_std_registration', ['student_name as name', 'mob', 'email'], ['id' => $student_id]);

                if ($student_data) {
                    $s_first_name = !empty($student_data['name']) ? trim($student_data['name']) : 'Student';
                    // Send payment confirmation notifications
                    try {
                        $notification_data = [
                            'student_name' => $s_first_name,
                            'fee_type' => $fee_label,
                            'amount' => '₹' . formatIndianCurrency($paid_amount),
                            'receipt_no' => $receipt_no,
                            'payment_date' => date('d-M-Y'),
                            'transaction_id' => $transaction_id
                        ];

                        // Use the combined notification helper which now handles WhatsApp-to-Email fallback
                        if (function_exists('sendNotification')) {
                            sendNotification($conn, 'fee_payment_success', [
                                'name' => $s_first_name,
                                'mobile' => $student_data['mob'],
                                'email' => $student_data['email']
                            ], $notification_data);
                        } else {
                            // Fallback to direct email if sendNotification is not available
                            if (function_exists('sendEmailTemplate') && !empty($student_data['email'])) {
                                sendEmailTemplate($conn, 'email_token_fee_success', $student_data['email'], $s_first_name, $notification_data);
                            }
                        }
                    } catch (Exception $notif_error) {
                        // Log notification error but don't fail the payment
                        logError("Notification error: " . $notif_error->getMessage());
                    }
                }

                set_flash_message('success', "Payment successful! Your " . $fee_label . " has been paid. Receipt No: " . $receipt_no);
                header('Location: ../student-portal/my-fees.php');
                exit;
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                logError("Pending fee payment callback error: " . $e->getMessage());
                set_flash_message('error', "Error processing payment. Please contact support with transaction ID: " . $transaction_id);
                header('Location: ../student-portal/my-fees.php');
                exit;
            }
        } else {
            // Payment failed or cancelled
            try {
                $dbOps->update('tbl_payment_orders', [
                    'status' => 'failed',
                    'updated_at' => 'NOW()'
                ], ['gateway_order_id' => $txnid]);
            } catch (Exception $e) {
                logError("Error updating failed payment order: " . $e->getMessage());
            }

            // Restore student session for redirect
            if ($student_id) {
                $_SESSION['student_id'] = $student_id;
                $_SESSION['user_role'] = 'student';
                $_SESSION['is_student_login'] = true;
            }

            set_flash_message('error', "Payment " . $status . ". Please try again.");
            header('Location: ../student-portal/my-fees.php');
            exit;
        }
    } else {
        // Hash verification failed
        logError("Hash verification failed for transaction: " . $txnid);

        // Restore student session for redirect
        if ($student_id) {
            $_SESSION['student_id'] = $student_id;
            $_SESSION['user_role'] = 'student';
            $_SESSION['is_student_login'] = true;
        }

        set_flash_message('error', "Payment verification failed. Please contact support.");
        header('Location: ../student-portal/my-fees.php');
        exit;
    }
} else {
    // Not a POST request - try to restore session if student_id available
    $student_id = $_GET['udf1'] ?? $_POST['udf1'] ?? null;

    if ($student_id) {
        $_SESSION['student_id'] = $student_id;
        $_SESSION['user_role'] = 'student';
        $_SESSION['is_student_login'] = true;
    }

    set_flash_message('error', "Invalid payment callback");
    header('Location: ../student-portal/my-fees.php');
    exit;
}
