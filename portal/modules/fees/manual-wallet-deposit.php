<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Check if user is logged in as Admin, Accountant or Principal
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT, ROLE_PRINCIPLE, ROLE_WALLET_MANAGER])) {
    header('Location: ../../login.php');
    exit;
}

$page_title = "Manual Wallet Deposit";
$page_breadcrumb = "Fees Management";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-0 p-4 d-flex align-items-center">
                    <div class="icon-box bg-success-light text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 45px; height: 45px;">
                        <i class="fas fa-wallet fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Manual Wallet Deposit</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <form id="manualDepositForm">
                        <!-- Student Search Selection -->
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold text-uppercase tracking-wider mb-2">Select
                                Student</label>
                            <div class="search-container position-relative">
                                <span class="position-absolute translate-middle-y top-50 start-0 ps-3 text-muted">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg bg-light border-0 ps-5 rounded-3"
                                    id="studentSearch" placeholder="Search by ID, Name or Mobile..." autocomplete="off">
                                <div id="searchResults"
                                    class="position-absolute w-100 shadow-lg rounded-3 mt-1 bg-white overflow-auto d-none"
                                    style="max-height: 300px; z-index: 1000;">
                                    <!-- Results will be injected here -->
                                </div>
                            </div>
                            <input type="hidden" id="selectedStudentId" name="student_id" required>
                        </div>

                        <!-- Selected Student Review -->
                        <div id="studentPreview"
                            class="d-none mb-4 p-3 rounded-4 bg-light border-start border-primary border-4">
                            <div class="d-flex align-items-center">
                                <div class="avatar-box me-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                        style="width: 50px; height: 50px; font-size: 1.2rem;">
                                        <span id="previewInitials">S</span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold text-dark" id="previewName">Student Name</h6>
                                    <div class="text-muted small">
                                        ID: <span id="previewId" class="fw-bold text-primary">#1267</span> |
                                        Class: <span id="previewClass">10-A</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle"
                                    onclick="clearSelection()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label
                                    class="form-label text-muted small fw-bold text-uppercase tracking-wider mb-2">Deposit
                                    Amount</label>
                                <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-white border-end-0 text-success"><i
                                            class="fas fa-rupee-sign"></i></span>
                                    <input type="number" class="form-control border-start-0 fw-bold fs-5" name="amount"
                                        placeholder="0.00" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label
                                    class="form-label text-muted small fw-bold text-uppercase tracking-wider mb-2">Payment
                                    Mode</label>
                                <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-white border-end-0"><i
                                            class="fas fa-money-bill-wave text-primary"></i></span>
                                    <select class="form-select border-start-0 fw-bold" name="payment_mode"
                                        id="paymentMode" required>
                                        <option value="CASH" selected>Cash</option>
                                        <option value="ONLINE">Online</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label
                                class="form-label text-muted small fw-bold text-uppercase tracking-wider mb-2">Transaction
                                Note (Optional)</label>
                            <textarea class="form-control bg-light border-0 rounded-3 p-3" rows="2" name="note"
                                placeholder="Any internal notes..."></textarea>
                        </div>

                        <div
                            class="alert alert-info border-0 shadow-none bg-info-light rounded-3 py-2 px-3 mb-4 d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div class="small">The amount will be credited instantly to the student's digital wallet.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-xl w-100 rounded-pill fw-bold shadow-lg py-3"
                            id="btnSubmit">
                            Confirm Manual Deposit <i class="fas fa-check-circle ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-light {
        background-color: rgba(46, 213, 115, 0.1) !important;
    }

    .bg-info-light {
        background-color: rgba(0, 123, 255, 0.05) !important;
    }

    .avatar-box .rounded-circle {
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    #studentSearch:focus {
        background-color: white !important;
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
    }

    .rounded-4 {
        border-radius: 1rem !important;
    }

    .search-item:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .btn-xl {
        font-size: 1.1rem;
    }
</style>

<script>
    const searchInput = document.getElementById('studentSearch');
    const searchResults = document.getElementById('searchResults');
    const preview = document.getElementById('studentPreview');
    const selectedStudentIdInput = document.getElementById('selectedStudentId');

    let searchTimeout;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('d-none');
            return;
        }

        searchTimeout = setTimeout(() => {
            // In actual implementation, this calls an API to search students
            // Mocking behavior for now
            performSearch(query);
        }, 300);
    });

    function performSearch(query) {
        // Use relative path to avoid CORS issues and environment mismatches
        fetch('api/search-students.php?query=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success' && data.data && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(student => {
                        html += `
                <div class="search-item p-3 border-bottom d-flex align-items-center" onclick="selectStudent('${student.id}', '${student.name.replace(/'/g, "\\'")}', '${student.class}', '${student.initials}')">
                    <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; font-weight: bold;">
                        ${student.initials}
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">${student.name}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">ID: #${student.id} | Class: ${student.class}</div>
                    </div>
                </div>`;
                    });
                    searchResults.innerHTML = html;
                    searchResults.classList.remove('d-none');
                } else {
                    const msg = (data && data.message) ? data.message : 'No students found';
                    searchResults.innerHTML = `<div class="p-3 text-center text-muted small">${msg}</div>`;
                    searchResults.classList.remove('d-none');
                }
            })
            .catch(err => {
                console.error('Search error:', err);
                searchResults.innerHTML = '<div class="p-3 text-center text-danger small">Error loading results</div>';
                searchResults.classList.remove('d-none');
            });
    }

    function selectStudent(id, name, className, initials) {
        selectedStudentIdInput.value = id;
        document.getElementById('previewName').innerText = name;
        document.getElementById('previewId').innerText = '#' + id;
        document.getElementById('previewClass').innerText = className;
        document.getElementById('previewInitials').innerText = initials;

        preview.classList.remove('d-none');
        searchResults.classList.add('d-none');
        searchInput.value = '';
    }

    function clearSelection() {
        selectedStudentIdInput.value = '';
        preview.classList.add('d-none');
    }

    document.getElementById('manualDepositForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!selectedStudentIdInput.value) {
            alert("Please select a student first");
            return;
        }

        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing Deposit...';

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        const mode = document.getElementById('paymentMode').value;

        if (mode === 'ONLINE') {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Initiating Online Payment...';
            // Calling Admin online topup bridge
            fetch('api/wallet-admin-actions.php?action=initiate-online-topup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: selectedStudentIdInput.value,
                    amount: formData.get('amount')
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data && data.status === 'success' && data.payment_url) {
                        alert("Redirecting to online payment gateway...");
                        window.location.href = data.payment_url;
                    } else {
                        alert((data && data.message) || 'Payment initiation failed');
                        btn.disabled = false;
                        btn.innerHTML = 'Confirm Manual Deposit <i class="fas fa-check-circle ms-2"></i>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred during online initiation');
                    btn.disabled = false;
                    btn.innerHTML = 'Confirm Manual Deposit <i class="fas fa-check-circle ms-2"></i>';
                });
        } else {
            // Calling Admin manual deposit bridge (CASH)
            fetch('api/wallet-admin-actions.php?action=manual-deposit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: selectedStudentIdInput.value,
                    amount: formData.get('amount'),
                    admin_id: '<?php echo $_SESSION['user_id']; ?>',
                    role: '<?php echo strtoupper($user_role); ?>',
                    note: formData.get('note')
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data && data.status === 'success') {
                        const rno = data.data && data.data.receipt_no ? data.data.receipt_no : (data.receipt_no || 'N/A');
                        alert("Cash Deposit Successful!\nReceipt No: " + rno);
                        location.reload();
                    } else {
                        alert((data && data.message) || 'Deposit failed');
                        btn.disabled = false;
                        btn.innerHTML = 'Confirm Manual Deposit <i class="fas fa-check-circle ms-2"></i>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred');
                    btn.disabled = false;
                    btn.innerHTML = 'Confirm Manual Deposit <i class="fas fa-check-circle ms-2"></i>';
                });
        }
    });
</script>

<?php include '../../include/footer.php'; ?>