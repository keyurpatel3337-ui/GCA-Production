<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Check if student is logged in
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: ../../login.php');
    exit;
}

$student_name = $_SESSION['student_name'] ?? 'Student';
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? '';

// Function to call Wallet API
function callWalletAPI($endpoint, $method = 'GET', $data = [])
{
    if (!defined('WALLET_API_URL')) {
        return ['status' => 'error', 'message' => 'Wallet API URL not defined'];
    }

    $url = WALLET_API_URL . $endpoint;
    $ch = curl_init();

    if ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
        ]);
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
        ]);
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error)
        return ['status' => 'error', 'message' => $error];

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'message' => 'Invalid Wallet API Response Format',
            'raw_response' => $response
        ];
    }
    return $decoded;
}

// Fetch student's enrollment number from registration ID ($user_id)
$enrollment_no = '';
try {
    if (!isset($conn)) {
        require_once dirname(dirname(dirname(__DIR__))) . '/common/db_connect.php';
    }
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT enrollment_no FROM tbl_enrolled_students WHERE registration_id = ?");
        $stmt->execute([$user_id]);
        $enrollment_no = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Fallback or ignore
}
$wallet_student_id = !empty($enrollment_no) ? $enrollment_no : $user_id;

// Fetch Wallet Balance
$balance_data = callWalletAPI('/balance/check.php', 'GET', ['student_id' => $wallet_student_id]);
$balance = ($balance_data && isset($balance_data['status']) && $balance_data['status'] === 'success') ? $balance_data['data']['balance'] : 0.00;

// Fetch Transaction History
$history_data = callWalletAPI('/transaction/history.php', 'GET', ['student_id' => $wallet_student_id]);
$transactions = ($history_data && isset($history_data['status']) && $history_data['status'] === 'success') ? $history_data['data']['transactions'] ?? [] : [];

// Fetch Daily Limit and Account Status from wallet database
$daily_limit = 1000.00; // Default fallback
$wallet_status = 'ACTIVE'; // Default fallback
try {
    $wallet_conn = new PDO(
        "mysql:host=" . EXT_DB_HOST . ";dbname=student_wallet;charset=utf8mb4",
        EXT_DB_USER,
        EXT_DB_PASS
    );
    $wallet_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $wallet_conn->prepare("SELECT daily_limit, status FROM wallet_accounts WHERE student_id = ?");
    $stmt->execute([$wallet_student_id]);
    $wallet_account = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($wallet_account) {
        if (isset($wallet_account['daily_limit'])) {
            $daily_limit = floatval($wallet_account['daily_limit']);
        }
        if (!empty($wallet_account['status'])) {
            $wallet_status = strtoupper($wallet_account['status']);
        }
    }
} catch (PDOException $e) {
    // Keep default fallbacks if connection fails
}

$page_title = "Digital Wallet";
$page_breadcrumb = "Digital Wallet";

include '../../include/header.php';
?>
<script type="text/javascript">
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, null, window.location.href);
    };
</script>
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <!-- Welcome Header banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-banner p-4 rounded-4 shadow-sm text-white position-relative overflow-hidden mb-2">
                <div class="position-absolute top-0 end-0 opacity-10 p-3">
                    <i class="fas fa-wallet fa-6x"></i>
                </div>
                <div class="position-relative">
                    <span class="badge bg-white-translucent text-white rounded-pill px-3 py-1 mb-2 fw-semibold tracking-wider text-uppercase" style="font-size: 0.75rem;">Student Portal</span>
                    <h2 class="fw-bold mb-1 font-outfit">My Digital Wallet</h2>
                    <p class="text-white-50 mb-0">Secure real-time wallet for transactions at the GCA Canteen, Bookstore, and Library.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Digital Visa Card, Action Hub, and Details -->
        <div class="col-xl-5 col-lg-6">
            
            <!-- Sleek Digital Pass Card -->
            <div class="student-digital-card rounded-4 p-4 text-white shadow-lg mb-4 position-relative overflow-hidden">
                <div class="card-mesh-1"></div>
                <div class="card-mesh-2"></div>
                
                <div class="position-relative d-flex flex-column justify-content-between h-100" style="min-height: 240px;">
                    <!-- Card Top Header -->
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-uppercase tracking-wider opacity-75 small" style="font-size: 0.75rem;">Digital Pass</span>
                        <i class="fas fa-wifi rotate-90 opacity-75 fa-lg"></i>
                    </div>
                    
                    <!-- Card Chip -->
                    <div class="card-chip my-2">
                        <div class="chip-line"></div>
                        <div class="chip-line"></div>
                        <div class="chip-line"></div>
                        <div class="chip-line"></div>
                    </div>
                    
                    <!-- Balance Numerals -->
                    <div>
                        <span class="text-white-50 small text-uppercase tracking-wider d-block mb-1" style="font-size: 0.65rem;">Available Balance</span>
                        <h2 class="display-5 fw-bold font-outfit mb-0" id="wallet-balance-amount">
                            ₹<?php echo formatIndianCurrency($balance); ?>
                        </h2>
                    </div>
                    
                    <!-- Card Footer Details -->
                    <div class="d-flex justify-content-between align-items-end mt-3">
                        <div>
                            <span class="text-white-50 small text-uppercase tracking-wider d-block" style="font-size: 0.65rem;">Card Holder</span>
                            <span class="fw-bold font-outfit text-truncate d-block" style="max-width: 220px; font-size: 0.95rem;"><?php echo htmlspecialchars($student_name ?? ''); ?></span>
                        </div>
                        <div class="text-end">
                            <span class="text-white-50 small text-uppercase tracking-wider d-block" style="font-size: 0.65rem;">Enrollment No</span>
                            <span class="fw-semibold font-monospace" style="font-size: 0.9rem;"><?php echo htmlspecialchars($wallet_student_id ?? ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PIN alert inside left col if PIN is not set -->
            <?php
            $pin_set = true; // Default placeholder, check if setup is needed
            if (!$pin_set): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 d-flex align-items-center mb-4 bg-warning-light animate-pulse">
                    <div class="icon-box bg-warning text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">Secure Your Wallet</h6>
                        <p class="small text-muted mb-0">Set your 4-digit Transaction Security PIN.</p>
                    </div>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 ms-2 fw-bold" data-bs-toggle="modal"
                        data-bs-target="#pinModal">Set PIN</button>
                </div>
            <?php endif; ?>

            <!-- Interactive Action Panel -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                    <i class="fas fa-rocket me-2 text-primary"></i> Wallet Quick Controls
                </h6>
                <div class="row g-3">
                    <div class="col-6">
                        <button type="button" class="btn btn-light btn-action-card w-100 rounded-3 p-3 text-center border-0"
                            data-bs-toggle="modal" data-bs-target="#topupModal">
                            <i class="fas fa-plus-circle fa-2x mb-2 text-success d-block"></i>
                            <span class="fw-bold text-dark small d-block">Add Funds</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-light btn-action-card w-100 rounded-3 p-3 text-center border-0"
                            data-bs-toggle="modal" data-bs-target="#pinModal">
                            <i class="fas fa-key fa-2x mb-2 text-primary d-block"></i>
                            <span class="fw-bold text-dark small d-block">Security PIN</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Account Details Metadata -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 border-top border-primary border-4">
                <h6 class="fw-bold mb-4 text-primary d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i> Account Insights
                </h6>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Account Integrity</span>
                        <span class="badge bg-success-light text-success border-0 rounded-pill px-3 py-1 fw-bold">VERIFIED</span>
                    </div>
                    <div class="divider border-bottom opacity-50"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Daily Spending Cap</span>
                        <span class="fw-bold text-dark">₹<?php echo formatIndianCurrency($daily_limit); ?></span>
                    </div>
                    <div class="divider border-bottom opacity-50"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Canteen Access Status</span>
                        <?php
                        $status_badge_class = 'bg-success-light text-success';
                        if ($wallet_status === 'BLOCKED') {
                            $status_badge_class = 'bg-danger-light text-danger';
                        } elseif ($wallet_status === 'SUSPENDED') {
                            $status_badge_class = 'bg-warning-light text-warning';
                        }
                        ?>
                        <span class="badge <?php echo $status_badge_class; ?> rounded-pill px-2 py-1 small fw-bold"><?php echo htmlspecialchars($wallet_status); ?></span>
                    </div>
                    <div class="divider border-bottom opacity-50"></div>
                    <div class="text-center mt-2">
                        <a href="<?php echo BASE_URL; ?>/portal/website/digital-wallet-policy.php" target="_blank" class="text-primary small fw-bold text-decoration-none">
                            <i class="fas fa-file-contract me-1"></i> View Digital Wallet policy & limits
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Side: Transaction Ledger -->
        <div class="col-xl-7 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">Transaction Ledger</h5>
                    <span class="badge bg-light text-muted border px-3 py-2 rounded-pill font-monospace" style="font-size: 0.75rem;">
                        <?php echo count($transactions); ?> Records Found
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-gray-50 text-muted small text-uppercase sticky-top top-0">
                                <tr>
                                    <th class="ps-4 py-3 border-0">Description</th>
                                    <th class="py-3 border-0">Timestamp</th>
                                    <th class="py-3 border-0">Amount</th>
                                    <th class="py-3 border-0">Status</th>
                                    <th class="pe-4 text-end py-3 border-0">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="opacity-25 mb-3">
                                                <i class="fas fa-receipt fa-4x mb-2 text-primary animate-bounce"></i>
                                            </div>
                                            <h6 class="text-muted fw-bold">No transactions found yet</h6>
                                            <p class="text-muted small mb-0">Your wallet transactions will appear here.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <?php
                                        $tx_type = $tx['tx_type'] ?? 'DEBIT';
                                        $tx_amount = $tx['amount'] ?? 0;
                                        $tx_date = !empty($tx['created_at']) ? strtotime($tx['created_at']) : time();
                                        ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-box <?php echo $tx_type === 'CREDIT' ? 'bg-success-light text-success' : 'bg-danger-light text-danger'; ?> rounded-circle d-flex align-items-center justify-content-center me-3"
                                                         style="width: 40px; height: 40px; min-width: 40px;">
                                                        <i class="fas <?php echo $tx_type === 'CREDIT' ? 'fa-plus' : 'fa-shopping-bag'; ?>"></i>
                                                    </div>
                                                    <div class="text-wrap" style="max-width: 320px; word-break: break-word;">
                                                        <h6 class="mb-0 fw-bold text-dark small text-wrap">
                                                            <?php echo htmlspecialchars($tx['description'] ?? 'Transaction'); ?>
                                                        </h6>
                                                        <small class="text-muted text-uppercase tracking-tighter d-block mt-1" style="font-size: 0.7rem;">
                                                            Ref: <?php echo htmlspecialchars($tx['receipt_ref'] ?? $tx['reference_id'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="small fw-bold text-dark"><?php echo date('d M, Y', $tx_date); ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?php echo date('h:i A', $tx_date); ?>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <span class="fw-bold fs-6 <?php echo $tx_type === 'CREDIT' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $tx_type === 'CREDIT' ? '+' : '-'; ?>₹<?php echo formatIndianCurrency($tx_amount); ?>
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <?php if (($tx['status'] ?? 'SUCCESS') === 'SUCCESS'): ?>
                                                    <span class="badge rounded-pill bg-success text-white px-3 py-1">Success</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-danger text-white px-3 py-1">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-4 text-end py-3">
                                                <?php if (!empty($tx['items']) && $tx['items'] !== '[]'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 font-outfit show-items-btn animate-hover-shift" 
                                                            data-items="<?php echo htmlspecialchars($tx['items'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-ref="<?php echo htmlspecialchars($tx['receipt_ref'] ?? $tx['reference_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-desc="<?php echo htmlspecialchars($tx['description'] ?? 'Transaction', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-amount="₹<?php echo formatIndianCurrency($tx_amount); ?>"
                                                            data-date="<?php echo date('d M, Y h:i A', $tx_date); ?>">
                                                        <i class="fas fa-list me-1"></i> Details
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Funds Modal -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light p-4 border-0">
                <h5 class="fw-bold modal-title font-outfit text-dark">Add Money to Wallet</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="topupForm">
                    <div class="mb-4 text-center">
                        <label class="form-label text-muted small fw-bold text-uppercase tracking-wider mb-2">Recharge Amount (INR)</label>
                        <div class="input-group input-group-lg border rounded-4 overflow-hidden bg-light p-1">
                            <span class="input-group-text bg-transparent border-0 fs-3">₹</span>
                            <input type="number" class="form-control border-0 bg-transparent fs-2 fw-bold text-center no-spinner font-outfit"
                                id="topupAmount" placeholder="0" min="10" step="1" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2 fw-bold" onclick="setTopupAmount(500)">+ 500</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2 fw-bold" onclick="setTopupAmount(1000)">+ 1000</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2 fw-bold" onclick="setTopupAmount(2000)">+ 2000</button>
                        </div>
                    </div>

                    <p class="text-muted small text-center mb-4">You will be redirected to the secure payment gateway to complete your transaction.</p>

                    <button type="submit" class="btn btn-primary btn-xl w-100 rounded-pill fw-bold shadow-lg" id="btn-recharge">
                        Recharge Wallet Now <i class="fas fa-chevron-right ms-2 fs-6"></i>
                    </button>
                    <div class="text-center mt-3">
                        <i class="fab fa-cc-visa fa-lg me-2 text-muted"></i>
                        <i class="fab fa-cc-mastercard fa-lg me-2 text-muted"></i>
                        <i class="fab fa-cc-amazon-pay fa-lg text-muted"></i>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Security PIN Modal -->
<div class="modal fade" id="pinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light p-4 border-0">
                <h5 class="fw-bold modal-title font-outfit text-dark">Security PIN Management</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="icon-box bg-primary-light text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center"
                    style="width: 70px; height: 70px;">
                    <i class="fas fa-lock fa-2x"></i>
                </div>
                <h6 class="fw-bold mb-2">Create Transaction PIN</h6>
                <p class="text-muted small mb-4">This 4-digit PIN will be required at school shops and canteen. Do not share it with anyone.</p>

                <form id="pinForm">
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <input type="password" class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric" required>
                        <input type="password" class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric" required>
                        <input type="password" class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric" required>
                        <input type="password" class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-xl w-100 rounded-pill fw-bold shadow-lg mt-2">
                        Update Secure PIN
                    </button>
                    <button type="button" class="btn btn-link text-muted small mt-3 text-decoration-none" data-bs-dismiss="modal">I'll do it later</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Items Modal -->
<div class="modal fade" id="itemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light p-4 border-0">
                <h5 class="fw-bold modal-title font-outfit text-dark" id="itemsModalTitle">Transaction Details</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Transaction Overview Summary -->
                <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded-3 bg-light">
                    <div>
                        <span class="text-muted small text-uppercase tracking-wider d-block" style="font-size: 0.65rem;">Transaction / Ref</span>
                        <strong class="text-dark font-outfit" id="modalTxDesc">Purchase</strong>
                        <span class="text-muted small d-block" id="modalTxRef" style="font-size: 0.75rem;">Ref: </span>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small text-uppercase tracking-wider d-block" style="font-size: 0.65rem;">Total Amount</span>
                        <strong class="text-danger font-outfit fs-5 d-block" id="modalTxAmount">₹0.00</strong>
                        <span class="text-muted small d-block" id="modalTxDate" style="font-size: 0.7rem;">Date</span>
                    </div>
                </div>

                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center">
                    <i class="fas fa-shopping-cart me-2 text-primary"></i> Purchased Items
                </h6>
                <div class="table-responsive rounded-3 border mb-0">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3 py-2 border-0">Item Name</th>
                                <th class="py-2 border-0 text-center">Qty</th>
                                <th class="py-2 border-0 text-end">Price</th>
                                <th class="pe-3 py-2 border-0 text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsTableBody">
                            <!-- Items inserted dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3 border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap');

    .font-outfit {
        font-family: 'Outfit', sans-serif !important;
    }

    .welcome-banner {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Breathtaking Visa-style Pass Card styling */
    .student-digital-card {
        background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25) !important;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .student-digital-card:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: 0 25px 45px rgba(0, 0, 0, 0.35) !important;
        border-color: rgba(255, 255, 255, 0.25);
    }

    .card-mesh-1 {
        position: absolute;
        width: 180px;
        height: 180px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 123, 255, 0.35) 0%, rgba(0, 0, 0, 0) 70%);
        top: -60px;
        right: -40px;
        filter: blur(25px);
        pointer-events: none;
    }

    .card-mesh-2 {
        position: absolute;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(46, 213, 115, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
        bottom: -70px;
        left: -50px;
        filter: blur(25px);
        pointer-events: none;
    }

    .card-chip {
        width: 46px;
        height: 34px;
        background: linear-gradient(135deg, #f1c40f 0%, #d4ac0d 100%);
        border-radius: 5px;
        padding: 4px;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: inset 0 1px 3px rgba(255, 255, 255, 0.5);
    }

    .chip-line {
        border: 1px solid rgba(0, 0, 0, 0.12);
        border-radius: 1px;
    }

    .rotate-90 {
        transform: rotate(90deg);
    }

    .bg-white-translucent {
        background-color: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(4px);
    }

    .btn-action-card {
        background-color: #f8f9fa;
        transition: all 0.3s ease;
        border: 1px solid transparent !important;
    }

    .btn-action-card:hover {
        transform: translateY(-4px);
        background-color: #ffffff;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
        border-color: rgba(0, 123, 255, 0.25) !important;
    }

    .bg-success-light {
        background-color: rgba(46, 213, 115, 0.1) !important;
    }

    .bg-danger-light {
        background-color: rgba(255, 71, 87, 0.1) !important;
    }

    .bg-warning-light {
        background-color: rgba(255, 159, 67, 0.1) !important;
    }

    .bg-primary-light {
        background-color: rgba(0, 123, 255, 0.1) !important;
    }

    .bg-gray-100 {
        background-color: #f8f9fa !important;
    }

    .bg-gray-50 {
        background-color: #fdfdfd !important;
    }

    .tracking-wider {
        letter-spacing: 0.08em;
    }

    .tracking-tighter {
        letter-spacing: -0.01em;
    }

    .rounded-4 {
        border-radius: 1.25rem !important;
    }

    .rounded-lg {
        border-radius: 0.75rem !important;
    }

    .btn-xl {
        padding: 0.85rem 1.5rem;
        font-size: 1.05rem;
    }

    .no-spinner::-webkit-outer-spin-button,
    .no-spinner::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    #pinForm input:focus {
        background-color: #fff !important;
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
        border: 1px solid #007bff !important;
    }

    /* Animations */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .7; }
    }
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<script>
    function setTopupAmount(amt) {
        document.getElementById('topupAmount').value = amt;
    }

    document.getElementById('topupForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-recharge');
        const originalText = btn.innerHTML;
        const amount = document.getElementById('topupAmount').value;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Initializing...';

        fetch('api/wallet-actions.php?action=topup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount: amount })
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success' && data.data && data.data.url) {
                    window.location.href = data.data.url;
                } else {
                    console.error('Wallet API Error:', data);
                    alert(data.message || 'Failed to initiate payment. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                alert('An error occurred while connecting to the server. Please check the console for details.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    });

    // PIN Input auto-focus sequence
    const pins = document.querySelectorAll('#pinForm input');
    pins.forEach((pin, idx) => {
        pin.addEventListener('keyup', (e) => {
            if (e.key >= 0 && e.key <= 9) {
                if (idx < 3) pins[idx + 1].focus();
            } else if (e.key === 'Backspace') {
                if (idx > 0) pins[idx - 1].focus();
            }
        });

        pin.addEventListener('input', (e) => {
            if (e.target.value.length > 1) {
                e.target.value = e.target.value.charAt(0);
            }
        });
    });

    document.getElementById('pinForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const pin = Array.from(pins).map(p => p.value).join('');

        if (pin.length < 4) {
            alert("Please enter a 4-digit PIN");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';

        fetch('api/wallet-actions.php?action=update-pin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pin: pin })
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success') {
                    alert("Security PIN Updated Successfully!");
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update PIN');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Update Secure PIN';
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred. Please check your connection.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Update Secure PIN';
            });
    });

    // Handle view transaction items details click
    let itemsModal = null;
    
    document.querySelectorAll('.show-items-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!itemsModal) {
                itemsModal = new bootstrap.Modal(document.getElementById('itemsModal'));
            }
            const rawItems = this.getAttribute('data-items');
            const ref = this.getAttribute('data-ref');
            const desc = this.getAttribute('data-desc');
            const amount = this.getAttribute('data-amount');
            const date = this.getAttribute('data-date');
            
            document.getElementById('modalTxDesc').textContent = desc;
            document.getElementById('modalTxRef').textContent = 'Ref: ' + ref;
            document.getElementById('modalTxAmount').textContent = amount;
            document.getElementById('modalTxDate').textContent = date;
            
            const tbody = document.getElementById('modalItemsTableBody');
            tbody.innerHTML = '';
            
            try {
                let items = JSON.parse(rawItems);
                let isRefund = false;
                
                // If it is a refund structure, it has standard format: {"original_tx_id": "...", "refunded_items": [...]}
                if (items && !Array.isArray(items) && items.refunded_items) {
                    items = items.refunded_items;
                    isRefund = true;
                }
                
                if (Array.isArray(items) && items.length > 0) {
                    items.forEach(item => {
                        const name = item.name || 'Unnamed Item';
                        const qty = parseInt(item.qty || item.quantity || 1);
                        const price = parseFloat(item.price || 0);
                        const total = qty * price;
                        
                        const tr = document.createElement('tr');
                        if (isRefund) {
                            tr.innerHTML = `
                                <td class="ps-3 py-2 fw-semibold text-danger">${escapeHtml(name)} <span class="badge bg-danger-light text-danger ms-1" style="font-size: 0.65rem;">Refunded</span></td>
                                <td class="py-2 text-center text-muted">${qty}</td>
                                <td class="py-2 text-end text-muted">-</td>
                                <td class="pe-3 py-2 text-end fw-bold text-danger">-</td>
                            `;
                        } else {
                            tr.innerHTML = `
                                <td class="ps-3 py-2 fw-semibold text-dark">${escapeHtml(name)}</td>
                                <td class="py-2 text-center text-muted">${qty}</td>
                                <td class="py-2 text-end text-muted">₹${price.toFixed(2)}</td>
                                <td class="pe-3 py-2 text-end fw-bold text-dark">₹${total.toFixed(2)}</td>
                            `;
                        }
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">No items specified.</td></tr>`;
                }
            } catch (e) {
                console.error('Failed to parse items JSON:', e);
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">Failed to load itemized details.</td></tr>`;
            }
            
            itemsModal.show();
        });
    });

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
</script>

<?php include '../../include/footer.php'; ?>