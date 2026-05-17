<?php

/**
 * Get Refund Request Details API
 * Returns detailed information about a refund request
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$request_id = $_POST['id'] ?? null;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    // Fetch refund request details
    $stmt = $conn->prepare("SELECT * FROM vw_refund_requests_detailed WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Check access permission
    if (isset($_SESSION['student_id'])) {
        if ($request['student_id'] != $_SESSION['student_id']) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }

    // Build HTML content
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary mb-3">Request Information</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Request Number:</strong></td>
                    <td>' . htmlspecialchars($request['request_number'] ?? '') . '</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>' . getStatusBadge($request['request_status']) . '</td>
                </tr>
                <tr>
                    <td><strong>Requested On:</strong></td>
                    <td>' . date('d-M-Y h:i A', strtotime($request['requested_at'])) . '</td>
                </tr>
                <tr>
                    <td><strong>Days Pending:</strong></td>
                    <td>' . $request['days_pending'] . ' days</td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-primary mb-3">Payment Information</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Receipt Number:</strong></td>
                    <td>' . htmlspecialchars($request['receipt_number'] ?? '') . '</td>
                </tr>
                <tr>
                    <td><strong>Payment Amount:</strong></td>
                    <td>₹' . formatIndianCurrency($request['payment_amount']) . '</td>
                </tr>
                <tr>
                    <td><strong>Refund Amount:</strong></td>
                    <td><strong class="text-success">₹' . formatIndianCurrency($request['refund_amount']) . '</strong></td>
                </tr>
                <tr>
                    <td><strong>Refund Type:</strong></td>
                    <td>' . ucfirst($request['refund_type']) . '</td>
                </tr>
            </table>
        </div>
    </div>
    
    <hr>
    
    <h6 class="text-primary mb-2">Refund Reason</h6>
    <div class="alert alert-light">
        ' . nl2br(htmlspecialchars($request['refund_reason'] ?? '')) . '
    </div>';

    // Timeline
    $html .= '
    <h6 class="text-primary mb-3">Request Timeline</h6>
    <div class="refund-timeline">';

    // Requested
    $html .= '
        <div class="refund-timeline-item active">
            <strong>Requested</strong><br>
            <small>' . date('d-M-Y h:i A', strtotime($request['requested_at'])) . '</small><br>
            <small class="text-muted">By: ' . htmlspecialchars($request['requested_by_name'] ?? '') . '</small>
        </div>';

    // Reviewed
    if ($request['reviewed_at']) {
        $html .= '
        <div class="refund-timeline-item active">
            <strong>Reviewed</strong><br>
            <small>' . date('d-M-Y h:i A', strtotime($request['reviewed_at'])) . '</small><br>
            <small class="text-muted">By: ' . htmlspecialchars($request['reviewed_by_name'] ?? '') . '</small>';
        if ($request['review_remarks']) {
            $html .= '<br><small class="text-muted">Remarks: ' . htmlspecialchars($request['review_remarks'] ?? '') . '</small>';
        }
        $html .= '</div>';
    }

    // Approved
    if ($request['approved_at']) {
        $html .= '
        <div class="refund-timeline-item active">
            <strong>Approved</strong><br>
            <small>' . date('d-M-Y h:i A', strtotime($request['approved_at'])) . '</small><br>
            <small class="text-muted">By: ' . htmlspecialchars($request['approved_by_name'] ?? '') . '</small>';
        if ($request['approval_remarks']) {
            $html .= '<br><small class="text-muted">Remarks: ' . htmlspecialchars($request['approval_remarks'] ?? '') . '</small>';
        }
        $html .= '</div>';
    }

    // Rejected
    if ($request['rejected_at']) {
        $html .= '
        <div class="refund-timeline-item">
            <strong class="text-danger">Rejected</strong><br>
            <small>' . date('d-M-Y h:i A', strtotime($request['rejected_at'])) . '</small><br>
            <small class="text-muted">By: ' . htmlspecialchars($request['rejected_by_name'] ?? '') . '</small>';
        if ($request['rejection_reason']) {
            $html .= '<br><small class="text-danger">Reason: ' . htmlspecialchars($request['rejection_reason'] ?? '') . '</small>';
        }
        $html .= '</div>';
    }

    // Processing/Completed
    if ($request['request_status'] === 'processing' || $request['request_status'] === 'completed') {
        $status_label = ucfirst($request['request_status']);
        $html .= '
        <div class="refund-timeline-item active">
            <strong>' . $status_label . '</strong><br>';
        if ($request['easebuzz_refund_id']) {
            $html .= '<small class="text-muted">EaseBuzz ID: ' . htmlspecialchars($request['easebuzz_refund_id'] ?? '') . '</small>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';

    // Bank details if applicable
    if ($request['refund_mode'] === 'bank_transfer' && $request['bank_account_number']) {
        $html .= '
        <hr>
        <h6 class="text-primary mb-2">Bank Account Details</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <td><strong>Account Number:</strong></td>
                <td>' . htmlspecialchars($request['bank_account_number'] ?? '') . '</td>
            </tr>
            <tr>
                <td><strong>IFSC Code:</strong></td>
                <td>' . htmlspecialchars($request['bank_ifsc'] ?? '') . '</td>
            </tr>
            <tr>
                <td><strong>Account Holder:</strong></td>
                <td>' . htmlspecialchars($request['bank_account_holder'] ?? '') . '</td>
            </tr>
            <tr>
                <td><strong>Bank Name:</strong></td>
                <td>' . htmlspecialchars($request['bank_name'] ?? '') . '</td>
            </tr>
        </table>';
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'data' => $request
    ]);
} catch (Exception $e) {
    logError("Get Refund Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading details']);
}

function getStatusBadge($status)
{
    $badges = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'under_review' => '<span class="badge bg-info">Under Review</span>',
        'approved' => '<span class="badge bg-success">Approved</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'processing' => '<span class="badge bg-primary">Processing</span>',
        'completed' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Completed</span>',
        'failed' => '<span class="badge bg-danger">Failed</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

