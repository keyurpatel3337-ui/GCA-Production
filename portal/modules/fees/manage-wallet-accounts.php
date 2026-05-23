<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Auth check: Only Super Admin, Principal, and Wallet Manager
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_WALLET_MANAGER])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "Manage Wallet Accounts";
$page_breadcrumb = "Wallet System";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <!-- Top summary cards for immediate audit awareness -->
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-uppercase tracking-wider small text-muted mb-1 fw-bold">Total Accounts</h6>
                        <h2 class="fw-bold mb-0 text-dark" id="stat-total-accounts">0</h2>
                    </div>
                    <div class="icon-box bg-soft-primary text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; background-color: rgba(0, 123, 255, 0.08);">
                        <i class="fas fa-users fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-uppercase tracking-wider small text-muted mb-1 fw-bold">Total Wallet Funds</h6>
                        <h2 class="fw-bold mb-0 text-dark" id="stat-total-funds">₹0.00</h2>
                    </div>
                    <div class="icon-box bg-soft-success text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; background-color: rgba(46, 213, 115, 0.08);">
                        <i class="fas fa-wallet fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-uppercase tracking-wider small text-muted mb-1 fw-bold">Flagged / Blocked</h6>
                        <h2 class="fw-bold mb-0 text-danger" id="stat-flagged-accounts">0</h2>
                    </div>
                    <div class="icon-box bg-soft-danger text-danger rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; background-color: rgba(255, 71, 87, 0.08);">
                        <i class="fas fa-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Accounts Database Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark">Wallet Accounts Registry</h5>
                <p class="text-muted small mb-0">Monitor student account states, adjust daily transaction ceilings, and manage security parameters.</p>
            </div>
            <!-- Search & Actions -->
            <div class="d-flex align-items-center gap-2">
                <div class="input-group search-input-group" style="width: 320px;">
                    <span class="input-group-text bg-light border-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" id="search-bar" class="form-control bg-light border-0" placeholder="Search by student name or ID..." style="outline: none; box-shadow: none;">
                </div>
                <button onclick="loadAccounts()" class="btn btn-outline-primary border-0 rounded-3 p-2 px-3" title="Refresh registry data">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 custom-accounts-table">
                    <thead class="bg-light text-muted uppercase small">
                        <tr>
                            <th class="ps-4">Student & Account Details</th>
                            <th>Current Balance</th>
                            <th>Daily Spending Limit</th>
                            <th>Security Status</th>
                            <th class="pe-4 text-end">Control Operations</th>
                        </tr>
                    </thead>
                    <tbody id="accounts-table-body">
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Accessing student wallet registry, please wait...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adjust Daily Limit -->
<div class="modal fade" id="adjustLimitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0">Adjust Spending Ceiling</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="limit-form">
                    <input type="hidden" id="limit-student-id">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Student Account</label>
                        <input type="text" id="limit-student-name" class="form-control border rounded-3 p-3 bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-semibold">Daily Spending Limit (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">₹</span>
                            <input type="number" id="limit-value" class="form-control border border-start-0 rounded-end-3 p-3 fw-bold text-dark" min="0" step="50" required>
                        </div>
                        <small class="text-muted mt-2 d-block">Set the maximum cumulative amount this account can spend in a single day at POS outlets.</small>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4">Update Ceiling</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Toggle Security Status -->
<div class="modal fade" id="securityStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0">Modify Account Lock Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="status-form">
                    <input type="hidden" id="status-student-id">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Student Account</label>
                        <input type="text" id="status-student-name" class="form-control border rounded-3 p-3 bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-semibold">Security State Selection</label>
                        <select id="status-value" class="form-select border rounded-3 p-3 fw-bold text-dark" required>
                            <option value="ACTIVE">ACTIVE (Full terminal operations allowed)</option>
                            <option value="BLOCKED">BLOCKED (Temporary POS block)</option>
                            <option value="SUSPENDED">SUSPENDED (Indefinite administrative lock)</option>
                        </select>
                        <small class="text-muted mt-2 d-block">Blocked or Suspended accounts cannot make purchases at canteen or vending terminals.</small>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4">Apply Status Lock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .search-input-group {
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 10px;
        overflow: hidden;
    }
    .custom-accounts-table th {
        font-weight: 600;
        padding-top: 15px;
        padding-bottom: 15px;
        letter-spacing: 0.05em;
    }
    .custom-accounts-table td {
        padding-top: 18px;
        padding-bottom: 18px;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .avatar-char {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: rgba(0, 123, 255, 0.08);
        color: #007bff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
    }
    .badge-status-active {
        background-color: rgba(46, 213, 115, 0.1) !important;
        color: #2ed573 !important;
    }
    .badge-status-blocked {
        background-color: rgba(255, 71, 87, 0.1) !important;
        color: #ff4757 !important;
    }
    .badge-status-suspended {
        background-color: rgba(255, 165, 2, 0.1) !important;
        color: #ffa502 !important;
    }
</style>

<!-- SweetAlert2 integration -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let accountsData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();

    // Event listener for search filtering
    document.getElementById('search-bar').addEventListener('input', function() {
        renderAccounts(this.value);
    });

    // Form handlers
    document.getElementById('limit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        updateLimit();
    });

    document.getElementById('status-form').addEventListener('submit', function(e) {
        e.preventDefault();
        updateStatus();
    });
});

function loadAccounts() {
    const tableBody = document.getElementById('accounts-table-body');
    tableBody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                Accessing student wallet registry, please wait...
            </td>
        </tr>
    `;

    fetch('api/wallet-admin-actions.php?action=list-accounts')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                accountsData = res.data;
                renderAccounts();
                calculateStats();
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-5 text-danger">
                            <i class="fas fa-exclamation-circle fs-5 me-2"></i> ${res.message || 'Failed to load registry.'}
                        </td>
                    </tr>
                `;
            }
        })
        .catch(err => {
            console.error(err);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-danger">
                        <i class="fas fa-wifi fs-5 me-2"></i> Failed to connect to server.
                    </td>
                </tr>
            `;
        });
}

function renderAccounts(filter = '') {
    const tableBody = document.getElementById('accounts-table-body');
    
    // Filter locally based on search
    const filtered = accountsData.filter(acc => {
        if (!filter) return true;
        const search = filter.toLowerCase();
        return acc.student_name.toLowerCase().includes(search) || 
               acc.student_id.toLowerCase().includes(search);
    });

    if (filtered.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-5 text-muted">
                    <i class="fas fa-folder-open fs-4 d-block mb-2 opacity-50"></i>
                    No wallet accounts matched your criteria.
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = '';
    filtered.forEach(acc => {
        const initial = acc.student_name.trim().charAt(0).toUpperCase() || 'S';
        const currentBalance = parseFloat(acc.current_balance).toFixed(2);
        const dailyLimit = parseFloat(acc.daily_limit).toFixed(2);
        
        let badgeClass = 'badge-status-active';
        if (acc.status === 'BLOCKED') badgeClass = 'badge-status-blocked';
        else if (acc.status === 'SUSPENDED') badgeClass = 'badge-status-suspended';

        tableBody.innerHTML += `
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-char shadow-xs">${initial}</div>
                        <div>
                            <div class="fw-bold text-dark">${escapeHTML(acc.student_name)}</div>
                            <div class="text-muted small">ID: ${escapeHTML(acc.student_id)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="fw-bold font-monospace text-dark">₹${currentBalance}</div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold text-muted font-monospace">₹${dailyLimit}</span>
                        <button onclick="openLimitModal('${acc.student_id}', '${escapeJS(acc.student_name)}', ${acc.daily_limit})" 
                                class="btn btn-sm btn-light border p-1 px-2 rounded-2 text-primary" title="Edit spending ceiling">
                            <i class="fas fa-edit small"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge ${badgeClass} p-2 px-3 rounded-pill fw-bold text-uppercase" style="font-size: 0.72rem;">${acc.status}</span>
                        <button onclick="openStatusModal('${acc.student_id}', '${escapeJS(acc.student_name)}', '${acc.status}')"
                                class="btn btn-sm btn-light border p-1 px-2 rounded-2 text-secondary" title="Change state locks">
                            <i class="fas fa-lock small"></i>
                        </button>
                    </div>
                </td>
                <td class="pe-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <button onclick="resetPin('${acc.student_id}', '${escapeJS(acc.student_name)}')" 
                                class="btn btn-sm btn-outline-danger border-0 rounded-3 px-3 py-1.5 fw-semibold d-flex align-items-center gap-2" 
                                style="font-size: 0.8rem;" title="Wipe PIN credentials">
                            <i class="fas fa-key small"></i> Reset PIN
                        </button>
                        <a href="../reports/financial/wallet-reports.php?report_type=transaction_history&student_search=${acc.student_id}" 
                           class="btn btn-sm btn-outline-primary border-0 rounded-3 px-3 py-1.5 fw-semibold d-flex align-items-center gap-2" 
                           style="font-size: 0.8rem;" title="View individual statement log">
                            <i class="fas fa-file-invoice small"></i> Ledger
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });
}

function calculateStats() {
    const total = accountsData.length;
    const totalFunds = accountsData.reduce((sum, acc) => sum + parseFloat(acc.current_balance), 0);
    const flagged = accountsData.filter(acc => acc.status !== 'ACTIVE').length;

    document.getElementById('stat-total-accounts').innerText = total;
    document.getElementById('stat-total-funds').innerText = '₹' + totalFunds.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('stat-flagged-accounts').innerText = flagged;
}

// Modal open helpers
function openLimitModal(sid, sname, limit) {
    document.getElementById('limit-student-id').value = sid;
    document.getElementById('limit-student-name').value = sname;
    document.getElementById('limit-value').value = limit;
    
    const modal = new bootstrap.Modal(document.getElementById('adjustLimitModal'));
    modal.show();
}

function openStatusModal(sid, sname, status) {
    document.getElementById('status-student-id').value = sid;
    document.getElementById('status-student-name').value = sname;
    document.getElementById('status-value').value = status;
    
    const modal = new bootstrap.Modal(document.getElementById('securityStatusModal'));
    modal.show();
}

// Form submissions / Operations
function updateLimit() {
    const student_id = document.getElementById('limit-student-id').value;
    const daily_limit = document.getElementById('limit-value').value;

    Swal.fire({
        title: 'Updating Limit...',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('api/wallet-admin-actions.php?action=update-limit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id, daily_limit })
    })
    .then(data => {
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('adjustLimitModal')).hide();
            Swal.fire({
                icon: 'success',
                title: 'Spending Limit Updated',
                text: data.message,
                confirmButtonColor: '#007bff'
            });
            loadAccounts();
        } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: data.message });
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to update limit.' });
    });
}

function updateStatus() {
    const student_id = document.getElementById('status-student-id').value;
    const status = document.getElementById('status-value').value;

    Swal.fire({
        title: 'Updating Security locks...',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('api/wallet-admin-actions.php?action=update-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id, status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('securityStatusModal')).hide();
            Swal.fire({
                icon: 'success',
                title: 'Security State Applied',
                text: data.message,
                confirmButtonColor: '#007bff'
            });
            loadAccounts();
        } else {
            Swal.fire({ icon: 'error', title: 'Lock Modification Failed', text: data.message });
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to update account locks.' });
    });
}

function resetPin(student_id, student_name) {
    Swal.fire({
        title: 'Wipe Transaction PIN?',
        text: `Are you sure you want to completely erase the transaction PIN credentials for ${student_name}? The student will need to establish a new PIN at their portal before making purchases.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Wipe PIN!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Erasing Credentials...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('api/wallet-admin-actions.php?action=reset-pin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ student_id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'PIN Erased',
                        text: data.message,
                        confirmButtonColor: '#007bff'
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Reset Failed', text: data.message });
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to reset PIN.' });
            });
        }
    });
}

// Escaping utilities for security & robustness
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}

function escapeJS(str) {
    if (!str) return '';
    return str.replace(/['"\\]/g, '\\$&');
}
</script>

<?php include '../../include/footer.php'; ?>
