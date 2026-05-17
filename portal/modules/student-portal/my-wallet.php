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

// Use the globally defined $user_id from globalvariable.php
// $user_id is already set correctly for students in globalvariable.php
$student_name = $_SESSION['student_name'] ?? 'Student';

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

    // Disable SSL for local dev
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error)
        return ['status' => 'error', 'message' => $error];
    return json_decode($response, true);
}

// Fetch Wallet Balance
$balance_data = callWalletAPI('/balance/check.php', 'GET', ['student_id' => $user_id]);
$balance = ($balance_data && isset($balance_data['status']) && $balance_data['status'] === 'success') ? $balance_data['data']['balance'] : 0.00;

// Fetch Transaction History
$history_data = callWalletAPI('/transaction/history.php', 'GET', ['student_id' => $user_id]);
$transactions = ($history_data && isset($history_data['status']) && $history_data['status'] === 'success') ? $history_data['data']['transactions'] ?? [] : [];

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
    <div class="row g-4">
        <!-- Wallet Balance Card -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-primary text-white card-wallet">
                <div class="card-body p-4 position-relative" style="min-height: 220px;">
                    <div class="position-absolute top-0 end-0 p-4 opacity-10">
                        <i class="fas fa-wallet fa-8x"></i>
                    </div>
                    <div class="position-relative">
                        <h6 class="text-uppercase opacity-75 mb-2 fw-bold tracking-wider" style="font-size: 0.8rem;">
                            Current Balance</h6>
                        <h1 class="display-3 fw-bold mb-4 font-outfit" id="wallet-balance-amount">
                            ₹<?php echo formatIndianCurrency($balance); ?></h1>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-white btn-lg px-4 rounded-pill shadow-sm fw-bold fs-6"
                                data-bs-toggle="modal" data-bs-target="#topupModal">
                                <i class="fas fa-plus-circle me-1 text-primary"></i> Add Funds
                            </button>
                            <button type="button" class="btn btn-outline-white btn-lg px-4 rounded-pill fw-bold fs-6"
                                data-bs-toggle="modal" data-bs-target="#pinModal">
                                <i class="fas fa-key me-1"></i> Security PIN
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PIN Setup Alert -->
            <?php
            // In a real scenario, we'd check if PIN is set in the DB
            $pin_set = true; // Placeholder
            if (!$pin_set): ?>
                <div
                    class="alert alert-warning border-0 shadow-sm rounded-4 p-3 d-flex align-items-center mb-4 bg-warning-light">
                    <div class="icon-box bg-warning text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">Secure Your Wallet</h6>
                        <p class="small text-muted mb-0">You must set a 4-digit PIN before making purchases.</p>
                    </div>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 ms-2 fw-bold" data-bs-toggle="modal"
                        data-bs-target="#pinModal">Set Now</button>
                </div>
            <?php endif; ?>

            <!-- Quick View Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 border-top border-primary border-4">
                <h6 class="fw-bold mb-4 text-primary d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i> Account Details
                </h6>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Account Status</span>
                        <span
                            class="badge bg-success-light text-success border-0 rounded-pill px-3 py-1 fw-bold">Active</span>
                    </div>
                    <div class="divider border-bottom opacity-50"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Student Name</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($student_name ?? ''); ?></span>
                    </div>
                    <div class="divider border-bottom opacity-50"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Student ID</span>
                        <span class="fw-bold text-dark">#<?php echo htmlspecialchars($user_id ?? ''); ?></span>
                    </div>
                    <div class="divider border-bottom opacity-50"></div>
                    <div class="text-center mt-2">
                        <a href="<?php echo BASE_URL; ?>/portal/website/digital-wallet-policy.php" target="_blank" class="text-primary small fw-bold text-decoration-none">
                            <i class="fas fa-file-contract me-1"></i> View Wallet Policy & Terms
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Transaction History -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">Transaction History</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle" type="button"
                            data-bs-toggle="dropdown">
                            All transactions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">All</a></li>
                            <li><a class="dropdown-item" href="#">Deposits</a></li>
                            <li><a class="dropdown-item" href="#">Purchases</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-gray-50 text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 border-0">Description</th>
                                    <th class="py-3 border-0">Date & Time</th>
                                    <th class="py-3 border-0">Amount</th>
                                    <th class="pe-4 text-end py-3 border-0">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="opacity-25 mb-3">
                                                <i class="fas fa-receipt fa-4x mb-2 text-primary"></i>
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
                                                        <i
                                                            class="fas <?php echo $tx_type === 'CREDIT' ? 'fa-plus' : 'fa-shopping-bag'; ?>"></i>
                                                    </div>
                                                    <div class="text-truncate" style="max-width: 200px;">
                                                        <h6 class="mb-0 fw-bold text-dark small">
                                                            <?php echo htmlspecialchars($tx['description'] ?? 'Transaction'); ?>
                                                        </h6>
                                                        <small class="text-muted text-uppercase tracking-tighter"
                                                            style="font-size: 0.7rem;">Ref:
                                                            <?php echo htmlspecialchars($tx['receipt_ref'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="small fw-bold text-dark"><?php echo date('d M, Y', $tx_date); ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?php echo date('h:i A', $tx_date); ?>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <span
                                                    class="fw-bold fs-6 <?php echo $tx_type === 'CREDIT' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $tx_type === 'CREDIT' ? '+' : '-'; ?>₹<?php echo formatIndianCurrency($tx_amount); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 text-end py-3">
                                                <?php if (($tx['status'] ?? 'SUCCESS') === 'SUCCESS'): ?>
                                                    <span class="badge rounded-pill bg-success text-white px-3 py-1">Success</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-danger text-white px-3 py-1">Failed</span>
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

<!-- Modals & Other Components -->
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
                        <label class="form-label text-muted small fw-bold text-uppercase tracking-wider mb-2">Select
                            Amount to recharge</label>
                        <div class="input-group input-group-lg border rounded-4 overflow-hidden bg-light p-1">
                            <span class="input-group-text bg-transparent border-0 fs-3">₹</span>
                            <input type="number"
                                class="form-control border-0 bg-transparent fs-2 fw-bold text-center no-spinner"
                                id="topupAmount" placeholder="0.00" min="10" step="1" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-4"><button type="button"
                                class="btn btn-outline-primary btn-sm w-100 rounded-lg py-2 fw-bold"
                                onclick="setTopupAmount(500)">+ 500</button></div>
                        <div class="col-4"><button type="button"
                                class="btn btn-outline-primary btn-sm w-100 rounded-lg py-2 fw-bold"
                                onclick="setTopupAmount(1000)">+ 1000</button></div>
                        <div class="col-4"><button type="button"
                                class="btn btn-outline-primary btn-sm w-100 rounded-lg py-2 fw-bold"
                                onclick="setTopupAmount(2000)">+ 2000</button></div>
                    </div>

                    <p class="text-muted small text-center mb-4">You will be redirected to the secure payment gateway to
                        complete your transaction.</p>

                    <button type="submit" class="btn btn-primary btn-xl w-100 rounded-pill fw-bold shadow-lg"
                        id="btn-recharge">
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
                <p class="text-muted small mb-4">This 4-digit PIN will be required at school shops and canteen. Do not
                    share it with anyone.</p>

                <form id="pinForm">
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <input type="password"
                            class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric"
                            required>
                        <input type="password"
                            class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric"
                            required>
                        <input type="password"
                            class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric"
                            required>
                        <input type="password"
                            class="form-control text-center bg-gray-100 border-0 rounded-4 fs-1 fw-bold font-monospace"
                            maxlength="1" style="width: 65px; height: 75px;" pattern="[0-9]*" inputmode="numeric"
                            required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-xl w-100 rounded-pill fw-bold shadow-lg mt-2">
                        Update Secure PIN
                    </button>
                    <button type="button" class="btn btn-link text-muted small mt-3 text-decoration-none"
                        data-bs-dismiss="modal">I'll do it later</button>
                </form>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>assets/css/modules/student-portal/my-wallet.css">

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

    // Auto-focus logic for PIN inputs
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
</script>

<?php include '../../include/footer.php'; ?>