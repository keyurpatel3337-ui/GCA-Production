<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../student-portal/student-login.php');
    exit;
}

$payment_id = $_POST['payment_id'] ?? '';
$amount = $_POST['amount'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>

<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2>Payment Successful!</h2>
            <p class="mb-0">Your token fee has been paid successfully</p>
        </div>

        <div class="success-body">
            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                <strong>Account Activated:</strong> You can now access your student portal with full features.
            </div>

            <div class="payment-details">
                <h5 class="mb-3">Payment Details</h5>

                <div class="detail-row">
                    <span class="detail-label">Payment ID:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($payment_id ?? ''); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Amount Paid:</span>
                    <span class="detail-value">₹<?php echo formatIndianCurrency($amount); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Payment Type:</span>
                    <span class="detail-value">Token Fee</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Payment Date:</span>
                    <span class="detail-value"><?php echo date('d M Y, h:i A'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value text-success"><i class="fas fa-check-circle"></i> Confirmed</span>
                </div>
            </div>

            <a href="../modules/dashboard/student_dashboard.php" class="btn btn-dashboard">
                <i class="fas fa-home"></i> Go to Dashboard
            </a>

            <p class="text-center mt-3 text-muted small">
                <i class="fas fa-receipt"></i> A payment receipt has been generated for your records
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent back button after successful payment
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>

</html>