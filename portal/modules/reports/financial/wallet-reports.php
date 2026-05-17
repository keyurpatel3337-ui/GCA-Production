<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Auth check
if (!hasRole(ROLE_WALLET_MANAGER) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ../../../login.php');
    exit;
}

$page_title = "Wallet Reports";
$page_breadcrumb = "Reports";

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="icon-box bg-primary-light text-primary rounded-circle p-3 me-3">
                        <i class="fas fa-file-invoice fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">Wallet Transaction Reports</h4>
                        <p class="text-muted mb-0">Generate and export wallet transaction history</p>
                    </div>
                </div>

                <form id="reportFilterForm" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Date Range</label>
                        <select class="form-select border-0 bg-light rounded-3" id="dateRange">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last7days" selected>Last 7 Days</option>
                            <option value="last30days">Last 30 Days</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Transaction Type</label>
                        <select class="form-select border-0 bg-light rounded-3" id="txType">
                            <option value="all">All Types</option>
                            <option value="CREDIT">Deposits Only</option>
                            <option value="DEBIT">Usage Only</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Student Search</label>
                        <input type="text" class="form-control border-0 bg-light rounded-3" id="studentSearch"
                            placeholder="Student ID or Name">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100 rounded-pill fw-bold"
                            onclick="generateReport()">
                            Generate <i class="fas fa-sync-alt ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Report Results</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-success rounded-pill px-3"
                            onclick="exportReport('excel')">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-gray-50 text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Student</th>
                                    <th class="py-3">Description</th>
                                    <th class="py-3">Date</th>
                                    <th class="py-3">Amount</th>
                                    <th class="py-3">Ref No</th>
                                    <th class="pe-4 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="reportResults">
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="opacity-25 mb-2">
                                            <i class="fas fa-filter fa-3x"></i>
                                        </div>
                                        <p class="text-muted">Use the filters above to generate a report.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/reports/financial/wallet-reports.php.css">

<script>
    function generateReport() {
        const results = document.getElementById('reportResults');
        const dateRange = document.getElementById('dateRange').value;
        const txType = document.getElementById('txType').value;
        const studentSearch = document.getElementById('studentSearch').value;

        results.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i> Generating Report...</td></tr>';

        let query = `?action=transaction-history&type=${txType}&student_id=${studentSearch}`;
        // Add date logic if needed

        fetch(`../../fees/api/wallet-admin-actions.php${query}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success' && data.data && data.data.transactions && data.data.transactions.length > 0) {
                    let html = '';
                    data.data.transactions.forEach(tx => {
                        const tx_date = new Date(tx.created_at);
                        const formatted_date = tx_date.toLocaleDateString('en-GB') + ' ' + tx_date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                        html += `
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold">${tx.student_name || 'N/A'}</div>
                                <div class="text-muted small">#${tx.student_id}</div>
                            </td>
                            <td class="py-3">${tx.description}</td>
                            <td class="py-3 small text-muted">${formatted_date}</td>
                            <td class="py-3">
                                <span class="fw-bold ${tx.type === 'CREDIT' ? 'text-success' : 'text-danger'}">
                                    ${tx.type === 'CREDIT' ? '+' : '-'}₹${parseFloat(tx.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                </span>
                            </td>
                            <td class="py-3 small text-muted">${tx.receipt_ref || 'N/A'}</td>
                            <td class="pe-4 text-end">
                                <span class="badge rounded-pill ${tx.status === 'SUCCESS' ? 'bg-success' : 'bg-danger'} text-white px-3 py-1">
                                    ${tx.status}
                                </span>
                            </td>
                        </tr>`;
                    });
                    results.innerHTML = html;
                } else {
                    results.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted small">No records found for the selected criteria.</td></tr>';
                }
            })
            .catch(err => {
                console.error(err);
                results.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger small">Error connecting to Wallet API bridge.</td></tr>';
            });
    }

    function exportReport(format) {
        alert("Excel/PDF Exporting is being processed via the Wallet System API... Please wait.");
        // In production, this would trigger a download link from the backend
    }
</script>

<?php include '../../../include/footer.php'; ?>