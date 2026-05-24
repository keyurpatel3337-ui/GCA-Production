<?php

/**
 * Get Refund Request Details for Admin
 * Returns detailed HTML view of refund request
 */
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

// Check admin access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$request_id = $_GET['id'] ?? null;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    // Fetch detailed request information
    $stmt = $conn->prepare("SELECT * FROM vw_refund_requests_detailed WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Generate HTML
    $html = generateRefundDetailsHTML($request);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'data' => $request
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generateRefundDetailsHTML($req)
{
    $status_badge = getStatusBadge($req['request_status']);

    $html = '<div class="row">';

    // Left Column - Request Information
    $html .= '<div class="col-md-6">';
    $html .= '<div class="card mb-3">';
    $html .= '<div class="card-header bg-primary text-white"><h6 class="mb-0">Request Information</h6></div>';
    $html .= '<div class="card-body">';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><th>Request Number:</th><td><strong>' . htmlspecialchars($req['request_number'] ?? '') . '</strong></td></tr>';
    $html .= '<tr><th>Status:</th><td>' . $status_badge . '</td></tr>';
    $html .= '<tr><th>Requested On:</th><td>' . date('d-M-Y h:i A', strtotime($req['requested_at'])) . '</td></tr>';
    $html .= '<tr><th>Requested By:</th><td>' . ucfirst($req['requested_by_role']) . '</td></tr>';
    $html .= '<tr><th>Days Pending:</th><td><strong>' . $req['days_pending'] . '</strong> days</td></tr>';
    $html .= '</table>';
    $html .= '</div></div>';

    // Student Information
    $html .= '<div class="card mb-3">';
    $html .= '<div class="card-header bg-info text-white"><h6 class="mb-0">Student Information</h6></div>';
    $html .= '<div class="card-body">';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><th>Name:</th><td>' . htmlspecialchars($req['full_name'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Mobile:</th><td>' . htmlspecialchars($req['student_mobile'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Enrollment:</th><td>' . htmlspecialchars($req['enrollment_number'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Standard:</th><td>' . htmlspecialchars($req['course_name'] ?? '') . '</td></tr>';
    $html .= '</table>';
    $html .= '</div></div>';

    // Payment Information
    $html .= '<div class="card mb-3">';
    $html .= '<div class="card-header bg-success text-white"><h6 class="mb-0">Payment Information</h6></div>';
    $html .= '<div class="card-body">';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><th>Receipt Number:</th><td>' . htmlspecialchars($req['receipt_number'] ?? '') . '</td></tr>';
    $html .= '<tr><th>Payment Amount:</th><td><strong>₹' . formatIndianCurrency($req['payment_amount']) . '</strong></td></tr>';
    $html .= '<tr><th>Refund Amount:</th><td><strong class="text-danger">₹' . formatIndianCurrency($req['refund_amount']) . '</strong></td></tr>';
    $html .= '<tr><th>Refund Type:</th><td><span class="badge bg-secondary">' . ucfirst($req['refund_type']) . '</span></td></tr>';
    $html .= '<tr><th>Payment Date:</th><td>' . date('d-M-Y', strtotime($req['payment_date'])) . '</td></tr>';
    $html .= '<tr><th>Payment Mode:</th><td>' . ucfirst($req['payment_mode']) . '</td></tr>';
    if ($req['transaction_id']) {
        $html .= '<tr><th>Transaction ID:</th><td>' . htmlspecialchars($req['transaction_id'] ?? '') . '</td></tr>';
    }
    $html .= '</table>';
    $html .= '</div></div>';

    $html .= '</div>'; // End left column

    // Right Column - Workflow & Details
    $html .= '<div class="col-md-6">';

    // Refund Reason
    $html .= '<div class="card mb-3">';
    $html .= '<div class="card-header bg-warning"><h6 class="mb-0">Refund Reason</h6></div>';
    $html .= '<div class="card-body">';
    $html .= '<p>' . nl2br(htmlspecialchars($req['refund_reason'] ?? '')) . '</p>';
    $html .= '</div></div>';

    // Timeline
    $html .= '<div class="card mb-3">';
    $html .= '<div class="card-header bg-dark text-white"><h6 class="mb-0">Timeline</h6></div>';
    $html .= '<div class="card-body">';
    $html .= generateTimeline($req);
    $html .= '</div></div>';

    // Bank Details (if applicable)
    if ($req['refund_mode'] === 'bank_transfer' && $req['bank_account_number']) {
        $html .= '<div class="card mb-3">';
        $html .= '<div class="card-header bg-secondary text-white"><h6 class="mb-0">Bank Details</h6></div>';
        $html .= '<div class="card-body">';
        $html .= '<table class="table table-sm">';
        $html .= '<tr><th>Account Holder:</th><td>' . htmlspecialchars($req['bank_account_holder'] ?? '') . '</td></tr>';
        $html .= '<tr><th>Account Number:</th><td>' . htmlspecialchars($req['bank_account_number'] ?? '') . '</td></tr>';
        $html .= '<tr><th>IFSC Code:</th><td>' . htmlspecialchars($req['bank_ifsc'] ?? '') . '</td></tr>';
        $html .= '<tr><th>Bank Name:</th><td>' . htmlspecialchars($req['bank_name'] ?? '') . '</td></tr>';
        $html .= '</table>';
        $html .= '</div></div>';
    }

    // EaseBuzz Details (if processed via gateway)
    if ($req['easebuzz_refund_id']) {
        $html .= '<div class="card mb-3">';
        $html .= '<div class="card-header bg-primary text-white"><h6 class="mb-0">EaseBuzz Refund Details</h6></div>';
        $html .= '<div class="card-body">';
        $html .= '<table class="table table-sm">';
        $html .= '<tr><th>Refund ID:</th><td>' . htmlspecialchars($req['easebuzz_refund_id'] ?? '') . '</td></tr>';
        $html .= '<tr><th>Status:</th><td>' . htmlspecialchars($req['easebuzz_status'] ?? '') . '</td></tr>';
        if ($req['easebuzz_response']) {
            $response = json_decode($req['easebuzz_response'], true);
            if (isset($response['bank_ref_num'])) {
                $html .= '<tr><th>Bank Ref:</th><td>' . htmlspecialchars($response['bank_ref_num'] ?? '') . '</td></tr>';
            }
        }
        $html .= '</table>';
        $html .= '</div></div>';
    }

    // Supporting Documents
    if ($req['supporting_documents']) {
        $documents = json_decode($req['supporting_documents'], true);
        if ($documents && count($documents) > 0) {
            $html .= '<div class="card mb-3">';
            $html .= '<div class="card-header"><h6 class="mb-0">Supporting Documents</h6></div>';
            $html .= '<div class="card-body">';
            $html .= '<ul class="list-unstyled">';
            foreach ($documents as $doc) {
                $html .= '<li><a href="' . $doc . '" target="_blank"><i class="bi bi-file-earmark-pdf"></i> View Document</a></li>';
            }
            $html .= '</ul>';
            $html .= '</div></div>';
        }
    }

    $html .= '</div>'; // End right column
    $html .= '</div>'; // End row

    return $html;
}

function generateTimeline($req)
{
    $html = '<div class="timeline">';

    // Requested
    $html .= '<div class="timeline-item active">';
    $html .= '<div class="timeline-marker bg-primary"></div>';
    $html .= '<div class="timeline-content">';
    $html .= '<h6>Requested</h6>';
    $html .= '<small>' . date('d-M-Y h:i A', strtotime($req['requested_at'])) . '</small>';
    $html .= '<p class="mb-0">By: ' . ucfirst($req['requested_by_role']) . '</p>';
    $html .= '</div></div>';

    // Reviewed
    if ($req['reviewed_at']) {
        $html .= '<div class="timeline-item active">';
        $html .= '<div class="timeline-marker bg-info"></div>';
        $html .= '<div class="timeline-content">';
        $html .= '<h6>Under Review</h6>';
        $html .= '<small>' . date('d-M-Y h:i A', strtotime($req['reviewed_at'])) . '</small>';
        if ($req['review_remarks']) {
            $html .= '<p class="mb-0"><em>' . htmlspecialchars($req['review_remarks'] ?? '') . '</em></p>';
        }
        $html .= '</div></div>';
    }

    // Approved or Rejected
    if ($req['approved_at']) {
        $html .= '<div class="timeline-item active">';
        $html .= '<div class="timeline-marker bg-success"></div>';
        $html .= '<div class="timeline-content">';
        $html .= '<h6>Approved</h6>';
        $html .= '<small>' . date('d-M-Y h:i A', strtotime($req['approved_at'])) . '</small>';
        if ($req['approval_remarks']) {
            $html .= '<p class="mb-0"><em>' . htmlspecialchars($req['approval_remarks'] ?? '') . '</em></p>';
        }
        $html .= '</div></div>';
    } elseif ($req['rejected_at']) {
        $html .= '<div class="timeline-item active">';
        $html .= '<div class="timeline-marker bg-danger"></div>';
        $html .= '<div class="timeline-content">';
        $html .= '<h6>Rejected</h6>';
        $html .= '<small>' . date('d-M-Y h:i A', strtotime($req['rejected_at'])) . '</small>';
        $html .= '<p class="mb-0 text-danger"><em>' . htmlspecialchars($req['rejection_reason'] ?? '') . '</em></p>';
        $html .= '</div></div>';
    }

    // Processing/Completed
    if (in_array($req['request_status'], ['processing', 'completed'])) {
        $is_completed = $req['request_status'] === 'completed';
        $html .= '<div class="timeline-item active">';
        $html .= '<div class="timeline-marker bg-' . ($is_completed ? 'success' : 'primary') . '"></div>';
        $html .= '<div class="timeline-content">';
        $html .= '<h6>' . ($is_completed ? 'Completed' : 'Processing') . '</h6>';
        $html .= '</div></div>';
    }

    $html .= '</div>';

    $html .= '<style>
        .timeline { position: relative; padding-left: 30px; }
        .timeline-item { position: relative; padding-bottom: 20px; }
        .timeline-marker { position: absolute; left: -30px; width: 12px; height: 12px; border-radius: 50%; }
        .timeline-item:not(:last-child)::before {
            content: "";
            position: absolute;
            left: -24px;
            top: 12px;
            width: 2px;
            height: calc(100% - 12px);
            background: #dee2e6;
        }
        .timeline-item.active .timeline-marker { box-shadow: 0 0 0 4px rgba(0,123,255,0.2); }
    </style>';

    return $html;
}

function getStatusBadge($status)
{
    $badges = [
        'pending' => '<span class="badge bg-warning"><i class="bi bi-clock"></i> Pending</span>',
        'under_review' => '<span class="badge bg-info"><i class="bi bi-search"></i> Under Review</span>',
        'approved' => '<span class="badge bg-success"><i class="bi bi-check"></i> Approved</span>',
        'rejected' => '<span class="badge bg-danger"><i class="bi bi-x"></i> Rejected</span>',
        'processing' => '<span class="badge bg-primary"><i class="bi bi-hourglass-split"></i> Processing</span>',
        'completed' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Completed</span>',
        'failed' => '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Failed</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}
