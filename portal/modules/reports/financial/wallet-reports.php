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
                    <div class="col-md-3" id="reportTypeContainer">
                        <label class="form-label small fw-bold text-uppercase text-primary">Report Type</label>
                        <select class="form-select border-0 bg-light rounded-3 fw-semibold text-primary" id="reportType" onchange="handleReportTypeChange()">
                            <option value="tx_history" selected>Transaction History Ledger</option>
                            <option value="day_book">Daily Day Book (Reconciliation)</option>
                            <option value="deposit_summary">Deposit Summary (Cash vs PG)</option>
                            <option value="merchant_settlement">Merchant Settlement Report</option>
                            <option value="low_balance">Low Balance & Spending Audit</option>
                            <option value="refund_dispute">Refund & Dispute Logs</option>
                            <option value="gateway_pipeline">Gateway Settlement Pipeline</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="dateRangeContainer">
                        <label class="form-label small fw-bold text-uppercase">Date Range</label>
                        <select class="form-select border-0 bg-light rounded-3" id="dateRange" onchange="generateReport()">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last7days" selected>Last 7 Days</option>
                            <option value="last30days">Last 30 Days</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="txTypeContainer">
                        <label class="form-label small fw-bold text-uppercase">Transaction Type</label>
                        <select class="form-select border-0 bg-light rounded-3" id="txType" onchange="generateReport()">
                            <option value="all">All Types</option>
                            <option value="CREDIT">Deposits Only</option>
                            <option value="DEBIT">Usage Only</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="studentSearchContainer">
                        <label class="form-label small fw-bold text-uppercase">Student Search</label>
                        <input type="text" class="form-control border-0 bg-light rounded-3" id="studentSearch"
                            placeholder="Student ID or Name" onkeyup="if(event.key === 'Enter') generateReport()">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm"
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
                            <thead class="bg-gray-50 text-muted small text-uppercase" id="reportHeaders">
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

<style>
    .bg-primary-light {
        background-color: rgba(0, 123, 255, 0.1);
    }

    .bg-gray-50 {
        background-color: #fdfdfd !important;
    }
</style>

<script>
    function handleReportTypeChange() {
        const reportType = document.getElementById('reportType').value;
        const dateRangeContainer = document.getElementById('dateRangeContainer');
        const txTypeContainer = document.getElementById('txTypeContainer');
        const studentSearchContainer = document.getElementById('studentSearchContainer');

        if (reportType === 'tx_history') {
            dateRangeContainer.style.display = 'block';
            txTypeContainer.style.display = 'block';
            studentSearchContainer.style.display = 'block';
        } else if (reportType === 'low_balance') {
            dateRangeContainer.style.display = 'none';
            txTypeContainer.style.display = 'none';
            studentSearchContainer.style.display = 'none';
        } else {
            dateRangeContainer.style.display = 'block';
            txTypeContainer.style.display = 'none';
            studentSearchContainer.style.display = 'none';
        }
        
        generateReport();
    }

    function generateReport() {
        const results = document.getElementById('reportResults');
        const headers = document.getElementById('reportHeaders');
        const reportType = document.getElementById('reportType').value;
        const dateRange = document.getElementById('dateRange').value;
        const txType = document.getElementById('txType').value;
        const studentSearch = document.getElementById('studentSearch').value;

        results.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i> Generating Report...</td></tr>';

        let start_date = '';
        let end_date = '';
        const today = new Date();
        
        if (dateRange === 'today') {
            start_date = today.toISOString().split('T')[0];
            end_date = start_date;
        } else if (dateRange === 'yesterday') {
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            start_date = yesterday.toISOString().split('T')[0];
            end_date = start_date;
        } else if (dateRange === 'last7days') {
            const last7 = new Date();
            last7.setDate(today.getDate() - 7);
            start_date = last7.toISOString().split('T')[0];
            end_date = today.toISOString().split('T')[0];
        } else if (dateRange === 'last30days') {
            const last30 = new Date();
            last30.setDate(today.getDate() - 30);
            start_date = last30.toISOString().split('T')[0];
            end_date = today.toISOString().split('T')[0];
        }

        if (reportType === 'tx_history') {
            headers.innerHTML = `
                <tr>
                    <th class="ps-4 py-3">Student</th>
                    <th class="py-3">Description</th>
                    <th class="py-3">Date</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Ref No</th>
                    <th class="pe-4 text-end">Status</th>
                </tr>
            `;

            let query = `?action=transaction-history&type=${txType}&student_id=${studentSearch}&start_date=${start_date}&end_date=${end_date}`;
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
        } else {
            let query = `?action=generate-report&report_type=${reportType}&start_date=${start_date}&end_date=${end_date}&student_search=${studentSearch}`;
            fetch(`../../fees/api/wallet-admin-actions.php${query}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.status === 'success' && data.data && data.data.length > 0) {
                        let html = '';
                        const rows = data.data;

                        if (reportType === 'day_book') {
                            headers.innerHTML = `
                                <tr>
                                    <th class="ps-4 py-3">Report Date</th>
                                    <th class="py-3">Total Additions (Deposits)</th>
                                    <th class="py-3">Total Purchases (Debits)</th>
                                    <th class="py-3">Total Refunds</th>
                                    <th class="py-3">Tx Count</th>
                                    <th class="pe-4 text-end">Net Position</th>
                                </tr>
                            `;
                            rows.forEach(r => {
                                const net = parseFloat(r.total_credits) - parseFloat(r.total_debits) + parseFloat(r.total_refunds);
                                html += `
                                <tr>
                                    <td class="ps-4 py-3 fw-bold">${new Date(r.report_date).toLocaleDateString('en-GB')}</td>
                                    <td class="py-3 text-success fw-semibold">+₹${parseFloat(r.total_credits).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="py-3 text-danger fw-semibold">-₹${parseFloat(r.total_debits).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="py-3 text-warning fw-semibold">+₹${parseFloat(r.total_refunds).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="py-3 small text-muted">${r.tx_count} transactions</td>
                                    <td class="pe-4 text-end">
                                        <span class="fw-bold ${net >= 0 ? 'text-success' : 'text-danger'}">
                                            ${net >= 0 ? '+' : ''}₹${net.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                        </span>
                                    </td>
                                </tr>`;
                            });
                        } else if (reportType === 'deposit_summary') {
                            headers.innerHTML = `
                                <tr>
                                    <th class="ps-4 py-3">Deposit Mode</th>
                                    <th class="py-3">Total Deposit Count</th>
                                    <th class="py-3">Average Recharge Value</th>
                                    <th class="pe-4 text-end">Total Volume</th>
                                </tr>
                            `;
                            rows.forEach(r => {
                                html += `
                                <tr>
                                    <td class="ps-4 py-3"><span class="badge bg-light text-dark border p-2 rounded-3">${r.deposit_mode}</span></td>
                                    <td class="py-3 fw-semibold text-muted">${r.total_count} times</td>
                                    <td class="py-3 fw-semibold">₹${parseFloat(r.avg_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="pe-4 text-end text-success fw-bold">₹${parseFloat(r.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                </tr>`;
                            });
                        } else if (reportType === 'merchant_settlement') {
                            headers.innerHTML = `
                                <tr>
                                    <th class="ps-4 py-3">Merchant ID</th>
                                    <th class="py-3">Total Sales Count</th>
                                    <th class="py-3">First Sales Activity</th>
                                    <th class="py-3">Last Sales Activity</th>
                                    <th class="pe-4 text-end">Total Sales Volume</th>
                                </tr>
                            `;
                            rows.forEach(r => {
                                html += `
                                <tr>
                                    <td class="ps-4 py-3"><span class="badge bg-primary text-white px-3 py-2 rounded-pill">${r.merchant_id}</span></td>
                                    <td class="py-3 fw-semibold text-muted">${r.sales_count} sales</td>
                                    <td class="py-3 small text-muted">${new Date(r.first_sale).toLocaleDateString('en-GB')}</td>
                                    <td class="py-3 small text-muted">${new Date(r.last_sale).toLocaleDateString('en-GB')}</td>
                                    <td class="pe-4 text-end text-danger fw-bold">₹${parseFloat(r.total_sales_volume).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                </tr>`;
                            });
                        } else if (reportType === 'low_balance') {
                            headers.innerHTML = `
                                <tr>
                                    <th class="ps-4 py-3">Student Name</th>
                                    <th class="py-3">ID</th>
                                    <th class="py-3">Daily Spending Limit</th>
                                    <th class="py-3">Current Balance</th>
                                    <th class="pe-4 text-end">Account Status</th>
                                </tr>
                            `;
                            rows.forEach(r => {
                                html += `
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold">${r.student_name || 'N/A'}</div>
                                    </td>
                                    <td class="py-3 small text-muted">#${r.student_id}</td>
                                    <td class="py-3 fw-semibold">₹${parseFloat(r.daily_limit).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="py-3 text-danger fw-bold">₹${parseFloat(r.current_balance).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="pe-4 text-end">
                                        <span class="badge rounded-pill bg-warning text-dark px-3 py-1">
                                            LOW BALANCE
                                        </span>
                                    </td>
                                </tr>`;
                            });
                        } else if (reportType === 'refund_dispute') {
                            headers.innerHTML = `
                                <tr>
                                    <th class="ps-4 py-3">Student Name</th>
                                    <th class="py-3">Original Receipt Reference</th>
                                    <th class="py-3">Refund Reason</th>
                                    <th class="py-3">Refund Date</th>
                                    <th class="pe-4 text-end">Refunded Amount</th>
                                </tr>
                            `;
                            rows.forEach(r => {
                                html += `
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold">${r.student_name || 'N/A'}</div>
                                        <div class="text-muted small">#${r.student_id}</div>
                                    </td>
                                    <td class="py-3 small text-muted">${r.original_ref || 'N/A'}</td>
                                    <td class="py-3 fw-semibold text-muted">${r.refund_reason}</td>
                                    <td class="py-3 small text-muted">${new Date(r.refund_date).toLocaleDateString('en-GB') + ' ' + new Date(r.refund_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                                    <td class="pe-4 text-end text-success fw-bold">₹${parseFloat(r.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                </tr>`;
                            });
                        } else if (reportType === 'gateway_pipeline') {
                            headers.innerHTML = `
                                <tr>
                                    <th class="ps-4 py-3">Student Name</th>
                                    <th class="py-3">Gateway Transaction Reference</th>
                                    <th class="py-3">Top-up Value</th>
                                    <th class="py-3">Timestamp</th>
                                    <th class="pe-4 text-end">Gateway Status</th>
                                </tr>
                            `;
                            rows.forEach(r => {
                                const status_badge = r.status === 'SUCCESS' ? 'bg-success' : (r.status === 'FAILED' ? 'bg-danger' : 'bg-warning text-dark');
                                html += `
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold">${r.student_name || 'N/A'}</div>
                                        <div class="text-muted small">#${r.student_id}</div>
                                    </td>
                                    <td class="py-3 small text-muted">${r.gateway_ref || 'N/A'}</td>
                                    <td class="py-3 fw-bold text-success">₹${parseFloat(r.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                    <td class="py-3 small text-muted">${new Date(r.created_at).toLocaleDateString('en-GB') + ' ' + new Date(r.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                                    <td class="pe-4 text-end">
                                        <span class="badge rounded-pill ${status_badge} text-white px-3 py-1">
                                            ${r.status}
                                        </span>
                                    </td>
                                </tr>`;
                            });
                        }

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
    }

    function exportReport(format) {
        alert("Excel/PDF Exporting is being processed via the Wallet System API... Please wait.");
    }

    document.addEventListener('DOMContentLoaded', function() {
        handleReportTypeChange();
    });
</script>

<?php include '../../../include/footer.php'; ?>