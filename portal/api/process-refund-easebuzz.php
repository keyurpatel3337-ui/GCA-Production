<?php

/**
 * Process Refund via EaseBuzz Payment Gateway
 * Initiates refund through EaseBuzz API
 */

session_start();
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once '../globalvariable.php';
require_once '../common/easebuzz_loader.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/notification_functions.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/whatsapp_functions.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/format_helper.php';

header('Content-Type: application/json');

// Check admin access  
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$request_id = $_POST['request_id'] ?? null;
$action = $_POST['action'] ?? 'show_form'; // show_form or process

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    // Fetch request details
    $request = $dbOps->selectOne('vw_refund_requests_detailed', ['*'], ['id' => $request_id]);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Check if request is approved
    if ($request['request_status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved requests can be processed']);
        exit;
    }

    // Check if refund mode is gateway
    if ($request['refund_mode'] !== 'gateway') {
        echo json_encode(['success' => false, 'message' => 'This request uses ' . $request['refund_mode'] . ' mode, not gateway']);
        exit;
    }

    if ($action === 'show_form') {
        // Show confirmation form
        $html = generateProcessingForm($request);
        echo json_encode(['success' => true, 'html' => $html]);
    } else if ($action === 'process') {
        // Process refund via EaseBuzz
        $result = processEaseBuzzRefund($request, $conn);
        echo json_encode($result);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generateProcessingForm($request)
{
    $html = '<div class="alert alert-info">';
    $html .= '<h5><i class="bi bi-info-circle"></i> Ready to Process Refund</h5>';
    $html .= '<p>This will initiate a refund through EaseBuzz payment gateway.</p>';
    $html .= '</div>';

    $html .= '<div class="row mb-3">';
    $html .= '<div class="col-md-6">';
    $html .= '<h6>Refund Details:</h6>';
    $html .= '<table class="table table-sm table-bordered">';
    $html .= '<tr><th>Request Number:</th><td>' . htmlspecialchars($request['request_number'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Student:</th><td>' . htmlspecialchars($request['full_name'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Receipt:</th><td>' . htmlspecialchars($request['receipt_number'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Original Amount:</th><td><strong>₹' . formatIndianCurrency($request['payment_amount']) . '</strong></td></tr>';
    $html .= '<tr><th>Refund Amount:</th><td><strong class="text-danger">₹' . formatIndianCurrency($request['refund_amount']) . '</strong></td></tr>';
    $html .= '</table>';
    $html .= '</div>';

    $html .= '<div class="col-md-6">';
    $html .= '<h6>Transaction Details:</h6>';
    $html .= '<table class="table table-sm table-bordered">';
    $html .= '<tr><th>Transaction ID:</th><td>' . htmlspecialchars($request['transaction_id'] ?? 'N/A') . '</td></tr>';
    $html .= '<tr><th>Payment Date:</th><td>' . date('d-M-Y', strtotime($request['payment_date'])) . '</td></tr>';
    $html .= '<tr><th>Payment Mode:</th><td>' . ucfirst($request['payment_mode']) . '</td></tr>';
    $html .= '<tr><th>Mobile:</th><td>' . htmlspecialchars($request['student_mobile'] ?? '') . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';

    if (!$request['transaction_id']) {
        $html .= '<div class="alert alert-warning">';
        $html .= '<i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> No transaction ID found. Cannot process via EaseBuzz.';
        $html .= '</div>';
        return $html;
    }

    $html .= '<div class="alert alert-warning">';
    $html .= '<i class="bi bi-exclamation-triangle"></i> <strong>Important:</strong> ';
    $html .= 'This action cannot be undone. Please verify all details before proceeding.';
    $html .= '</div>';

    $html .= '<div class="text-end">';
    $html .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button> ';
    $html .= '<button type="button" class="btn btn-primary" onclick="confirmProcessRefund(' . $request['id'] . ')">';
    $html .= '<i class="bi bi-currency-rupee"></i> Process Refund via EaseBuzz';
    $html .= '</button>';
    $html .= '</div>';

    $html .= '<script>
        async function confirmProcessRefund(requestId) {
            if (!confirm("Are you sure you want to process this refund via EaseBuzz?\n\nThis action cannot be undone.")) return;
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = "<i class=\"bi bi-hourglass-split\"></i> Processing...";
            
            try {
                const formData = new FormData();
                formData.append("request_id", requestId);
                formData.append("action", "process");
                
                const response = await fetch("api/process-refund-easebuzz.php", {
                    method: "POST",
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, "success");
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(result.message, "danger");
                    btn.disabled = false;
                    btn.innerHTML = "<i class=\"bi bi-currency-rupee\"></i> Process Refund via EaseBuzz";
                }
            } catch (error) {
                showAlert("Error processing refund", "danger");
                btn.disabled = false;
                btn.innerHTML = "<i class=\"bi bi-currency-rupee\"></i> Process Refund via EaseBuzz";
            }
        }
    </script>';

    return $html;
}

function processEaseBuzzRefund($request, $conn)
{
    // Validate transaction ID
    if (!$request['transaction_id']) {
        return ['success' => false, 'message' => 'No transaction ID found for this payment'];
    }

    try {
        // Update status to processing
        $conn->beginTransaction();

        $update_sql = "UPDATE tbl_refund_requests SET 
            request_status = 'processing',
            processed_at = NOW(),
            processed_by = ?
            WHERE id = ?";

        $stmt = $conn->prepare($update_sql);
        $stmt->execute([$_SESSION['user_id'], $request['id']]);

        // Initialize EaseBuzz
        $merchant_key = EASEBUZZ_MERCHANT_KEY;
        $salt = EASEBUZZ_SALT;
        $env = EASEBUZZ_ENV; // 'prod' or 'test'

        $easebuzz = new Easebuzz($merchant_key, $salt, $env);

        // Prepare refund data
        $refund_data = [
            'txnid' => $request['transaction_id'],
            'refund_amount' => sprintf("%.2f", $request['refund_amount']),
            'phone' => $request['student_mobile'],
            'email' => 'student@gyanmanjari.in', // Default email as tbl_gm_std_registration has no email
            'amount' => sprintf("%.2f", $request['payment_amount'])
        ];

        // Call EaseBuzz refund API
        $refund_result = $easebuzz->refundAPI($refund_data);

        // Parse response
        $response_data = json_decode($refund_result, true);

        if ($response_data && isset($response_data['status']) && $response_data['status'] == 1) {
            // Refund initiated successfully
            $update_sql = "UPDATE tbl_refund_requests SET 
                easebuzz_refund_id = ?,
                easebuzz_status = ?,
                easebuzz_response = ?,
                request_status = 'processing'
                WHERE id = ?";

            $stmt = $conn->prepare($update_sql);
            $stmt->execute([
                $response_data['data']['refund_id'] ?? null,
                $response_data['data']['status'] ?? 'initiated',
                $refund_result,
                $request['id']
            ]);

            // tbl_refund_requests is already updated above with easebuzz_refund_id and status
            // No separate tbl_refunds tracking needed

            $conn->commit();

            // Send notification
            sendRefundProcessedNotification($request['id'], $conn);

            return [
                'success' => true,
                'message' => 'Refund initiated successfully via EaseBuzz. Refund ID: ' . ($response_data['data']['refund_id'] ?? 'N/A'),
                'refund_id' => $response_data['data']['refund_id'] ?? null
            ];
        } else {
            // Refund failed
            $error_msg = $response_data['error_desc'] ?? $response_data['msg'] ?? 'Unknown error';

            $update_sql = "UPDATE tbl_refund_requests SET 
                request_status = 'failed',
                easebuzz_response = ?,
                failure_reason = ?
                WHERE id = ?";

            $stmt = $conn->prepare($update_sql);
            $stmt->execute([
                $refund_result,
                $error_msg,
                $request['id']
            ]);

            $conn->commit();

            return [
                'success' => false,
                'message' => 'EaseBuzz refund failed: ' . $error_msg
            ];
        }
    } catch (Exception $e) {
        $conn->rollBack();

        // Update to failed status
        $update_sql = "UPDATE tbl_refund_requests SET 
            request_status = 'failed',
            failure_reason = ?
            WHERE id = ?";

        $stmt = $conn->prepare($update_sql);
        $stmt->execute([
            'Exception: ' . $e->getMessage(),
            $request['id']
        ]);

        return [
            'success' => false,
            'message' => 'Error processing refund: ' . $e->getMessage()
        ];
    }
}

function sendRefundProcessedNotification($request_id, $conn)
{
    try {
        // Fetch refund request details with student info
        $stmt = $conn->prepare("
            SELECT 
                rr.*,
                r.full_name,
                r.email,
                r.mob as mobile,
                c.course_name,
                p.amount as original_amount,
                p.receipt_no
            FROM tbl_refund_requests rr
            INNER JOIN tbl_gm_std_registration r ON rr.student_id = r.id
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_payments p ON rr.payment_id = p.id
            WHERE rr.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            return;
        }

        $recipient = [
            'name' => $request['full_name'],
            'email' => $request['email'],
            'mobile' => $request['mobile']
        ];

        $variables = [
            'student_name' => $request['full_name'],
            'request_number' => $request['request_number'],
            'refund_amount' => formatIndianCurrency($request['refund_amount']),
            'original_amount' => formatIndianCurrency($request['original_amount']),
            'receipt_no' => $request['receipt_no'],
            'course_name' => $request['course_name'] ?? 'N/A',
            'transaction_id' => $request['gateway_transaction_id'] ?? 'N/A',
            'processing_date' => date('d-M-Y', strtotime($request['processed_at'])),
            'refund_mode' => ucfirst(str_replace('_', ' ', $request['refund_mode']))
        ];

        sendNotification(
            $conn,
            'refund_processed',
            $recipient,
            $variables,
            ['student_id' => $request['student_id'], 'refund_request_id' => $request_id]
        );
    } catch (Exception $e) {
        error_log("Refund processed notification error: " . $e->getMessage());
    }
}

