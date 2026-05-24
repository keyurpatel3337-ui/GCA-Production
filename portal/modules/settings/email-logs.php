<?php

/**
 * Email Message Logs
 * Path: portal/modules/settings/email-logs.php
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once '../../include/header.php';
require_once '../../include/navbar.php';
require_once '../../include/sidebar.php';

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$date_from = date('Y-m-d', strtotime('-7 days'));
$date_to = date('Y-m-d');
?>

<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">

        </div>
        <div class="col-sm-6 text-end">
            <button class="btn btn-success" id="exportBtn">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>
</div>


<div class="app-content">
    <div class="container-fluid">

        <!-- Stats Cards -->
        <div class="row mb-3" id="statsRow">
            <div class="col-md col-6 mb-2">
                <div class="card shadow-sm border-0 bg-light">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small">Total</div>
                        <h4 class="mb-0" id="statTotal">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md col-6 mb-2">
                <div class="card shadow-sm border-0 border-start border-primary border-3">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small">Sent</div>
                        <h4 class="mb-0 text-primary" id="statSent">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md col-6 mb-2">
                <div class="card shadow-sm border-0 border-start border-success border-3">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small">Delivered</div>
                        <h4 class="mb-0 text-success" id="statDelivered">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md col-6 mb-2">
                <div class="card shadow-sm border-0 border-start border-danger border-3">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small">Failed</div>
                        <h4 class="mb-0 text-danger" id="statFailed">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md col-6 mb-2">
                <div class="card shadow-sm border-0 border-start border-warning border-3">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small">Bounced</div>
                        <h4 class="mb-0 text-warning" id="statBounced">-</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-2">
                        <label class="small text-muted">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="sent">Sent</option>
                            <option value="delivered">Delivered</option>
                            <option value="failed">Failed</option>
                            <option value="bounced">Bounced</option>
                            <option value="queued">Queued</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted">Recipient Type</label>
                        <select name="recipient_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted">Search (Email / Name / Subject)</label>
                        <input type="text" name="search" class="form-control" placeholder="Search...">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="logsTable">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Recipient</th>
                                <th>Subject / Template</th>
                                <th>Status</th>
                                <th>Sent Time</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="logsList">
                            <tr>
                                <td colspan="5" class="text-center p-5">
                                    <div class="spinner-border text-primary"></div>
                                    <div class="mt-2 text-muted">Loading logs...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Email Log Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsBody">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include SheetJS for modern Excel exports -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>
<?php require_once '../../include/footer.php'; ?>

<script src="../../assets/js/api-client.js"></script>
<script>
    const apiClient = new CounsellingAPI();

    async function loadLogs() {
        const filters = $('#filterForm').serialize();
        try {
            const response = await apiClient.get('settings/email-logs', filters);
            const {
                logs,
                stats
            } = response.data;

            // Update Stats
            $('#statTotal').text(stats.total || 0);
            $('#statSent').text(stats.sent || 0);
            $('#statDelivered').text(stats.delivered || 0);
            $('#statFailed').text(stats.failed || 0);
            $('#statBounced').text(stats.bounced || 0);

            // Update Table
            let html = '';
            if (logs.length === 0) {
                html = '<tr><td colspan="5" class="text-center p-4">No logs found</td></tr>';
            } else {
                logs.forEach(l => {
                    const statusClass = getStatusClass(l.status);
                    const time = new Date(l.created_at).toLocaleString('en-IN', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    html += `
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold">${l.recipient_name || 'N/A'}</div>
                                <div class="tiny text-muted">${l.recipient_email}</div>
                                <span class="badge bg-light text-dark tiny">${l.recipient_type || 'N/A'}</span>
                            </td>
                            <td>
                                <div class="small fw-bold text-truncate css-email-logs-c0dc2c">${l.subject}</div>
                                <div class="tiny text-muted uppercase">Template: ${l.template_code || 'Custom'}</div>
                            </td>
                            <td>
                                <span class="badge bg-${statusClass} uppercase tiny">${l.status}</span>
                                ${l.error_message ? `<div class="tiny text-danger mt-1 text-truncate css-email-logs-7ecc66" title="${l.error_message}">${l.error_message}</div>` : ''}
                            </td>
                            <td class="small">${time}</td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-secondary view-btn" data-id="${l.id}">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#logsList').html(html);
        } catch (error) {
            $('#logsList').html(`<tr><td colspan="5" class="text-center text-danger p-4">Error: ${error.message}</td></tr>`);
        }
    }

    function getStatusClass(status) {
        switch (status) {
            case 'delivered':
                return 'success';
            case 'sent':
                return 'primary';
            case 'failed':
                return 'danger';
            case 'bounced':
                return 'warning';
            case 'queued':
                return 'secondary';
            default:
                return 'light text-dark';
        }
    }

    $(document).ready(function () {
        loadLogs();

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            loadLogs();
        });

        $(document).on('click', '.view-btn', async function () {
            const id = $(this).data('id');
            $('#logDetailsBody').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>');
            $('#logModal').modal('show');

            try {
                const response = await apiClient.get('settings/email-log-details', {
                    id
                });
                const l = response.data;

                let detailsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold border-bottom pb-2">Information</h6>
                            <table class="table table-sm small">
                                <tr><th width="35%">Recipient</th><td>${l.recipient_name} (${l.recipient_email})</td></tr>
                                <tr><th>Type</th><td>${l.recipient_type}</td></tr>
                                <tr><th>Subject</th><td>${l.subject}</td></tr>
                                <tr><th>Template</th><td>${l.template_name || 'Custom'} (${l.template_code || ''})</td></tr>
                                <tr><th>SMTP Config</th><td>${l.smtp_config_name || 'Default'}</td></tr>
                                <tr><th>Status</th><td><span class="badge bg-${getStatusClass(l.status)}">${l.status}</span></td></tr>
                                <tr><th>Created</th><td>${l.created_at}</td></tr>
                                <tr><th>Sent At</th><td>${l.sent_at || '-'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold border-bottom pb-2">Technical Bits</h6>
                            ${l.error_message ? `<div class="alert alert-danger p-2 small"><strong>Error:</strong> ${l.error_message}</div>` : ''}
                            <div class="small mb-1"><strong>Variables Used:</strong></div>
                            <pre class="bg-light p-2 tiny border rounded css-email-logs-6500ac">${l.variables_used || '{}'}</pre>
                            <div class="small mb-1"><strong>SMTP Response:</strong></div>
                            <pre class="bg-light p-2 tiny border rounded css-email-logs-6500ac">${l.smtp_response || 'No response recorded'}</pre>
                        </div>
                        <div class="col-md-12 mt-3">
                            <h6 class="fw-bold border-bottom pb-2">Email Content Preview</h6>
                            <div class="border rounded p-3 bg-white css-email-logs-f4d939">
                                ${l.html_body}
                            </div>
                        </div>
                    </div>
                `;
                $('#logDetailsBody').html(detailsHtml);
            } catch (error) {
                $('#logDetailsBody').html(`<div class="alert alert-danger">Failed to load details: ${error.message}</div>`);
            }
        });

        $('#exportBtn').on('click', function () {
            TableUtils.exportToExcel('logsTable', 'email_logs');
        });
    });
</script>

