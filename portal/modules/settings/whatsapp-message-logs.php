<?php

/**
 * WhatsApp Message Logs
 * Path: portal/modules/settings/whatsapp-message-logs.php
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once PAGINATION_FILE;
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

<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">WhatsApp Message Logs</h1>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item active">WhatsApp Logs</li>
                    </ol>
                </div>
                <div class="col-sm-6 text-end">
                    <button class="btn btn-warning text-dark me-2" id="syncBtn">
                        <i class="fas fa-sync-alt"></i> Sync Delivery Status
                    </button>
                    <button class="btn btn-success" id="exportBtn">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Stats Cards -->
            <div class="row mb-3 gx-3" id="statsRow">
                <div class="col-md-5th col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 bg-light h-100">
                        <div class="card-body p-3 text-center">
                            <div class="text-muted small mb-1 uppercase fw-bold">Total Messages</div>
                            <h3 class="mb-0" id="statTotal">-</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-5th col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 border-start border-info border-3 h-100">
                        <div class="card-body p-3 text-center">
                            <div class="text-muted small mb-1 uppercase fw-bold text-info">Sent</div>
                            <h3 class="mb-0 text-info" id="statSent">-</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-5th col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 border-start border-success border-3 h-100 text-success">
                        <div class="card-body p-3 text-center">
                            <div class="text-muted small mb-1 uppercase fw-bold">Delivered</div>
                            <h3 class="mb-0" id="statDelivered">-</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-5th col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 border-start border-primary border-3 h-100 text-primary">
                        <div class="card-body p-3 text-center">
                            <div class="text-muted small mb-1 uppercase fw-bold">Read</div>
                            <h3 class="mb-0" id="statRead">-</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-5th col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 border-start border-danger border-3 h-100 text-danger">
                        <div class="card-body p-3 text-center">
                            <div class="text-muted small mb-1 uppercase fw-bold">Failed</div>
                            <h3 class="mb-0" id="statFailed">-</h3>
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
                                <option value="read">Read</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted">Template</label>
                            <select name="template_id" id="filterTemplate" class="form-select">
                                <option value="">All Templates</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small text-muted">Search Number/ID</label>
                            <input type="text" name="search" class="form-control" placeholder="Phone / Message ID">
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
                                    <th>Template</th>
                                    <th>Message Preview</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody id="logsList">
                                <tr>
                                    <td colspan="6" class="text-center p-5">
                                        <div class="spinner-border text-primary"></div>
                                        <div class="mt-2 text-muted">Loading logs...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-top-0" id="paginationRow">
                        <!-- Pagination will be rendered here via JS -->
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="logModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Message Details</h5>
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

        async function loadData() {
            try {
                // Load templates for filter
                const templateResponse = await apiClient.get('settings/whatsapp-templates');
                let options = '<option value="">All Templates</option>';
                if (templateResponse.data && templateResponse.data.templates) {
                    templateResponse.data.templates.forEach(t => {
                        options += `<option value="${t.id}">${t.template_name}</option>`;
                    });
                }
                $('#filterTemplate').html(options);

                loadLogs(1);
            } catch (error) {
                console.error('Data load failed', error);
            }
        }

        let currentFilters = {};

        async function loadLogs(page = 1) {
            // Update current filters state
            currentFilters = {
                date_from: $('input[name="date_from"]').val() || '<?= $date_from ?>',
                date_to: $('input[name="date_to"]').val() || '<?= $date_to ?>',
                page: page
            };

            // Add other non-empty filters
            const status = $('select[name="status"]').val();
            const template_id = $('select[name="template_id"]').val();
            const search = $('input[name="search"]').val();

            if (status) currentFilters.status = status;
            if (template_id) currentFilters.template_id = template_id;
            if (search) currentFilters.search = search;

            try {
                const response = await apiClient.get('settings/whatsapp-logs', currentFilters);

                const logs = response.data?.logs || [];
                const stats = response.data?.stats || {};

                // Update Stats with null safety
                $('#statTotal').text(stats.total || 0);
                $('#statSent').text(stats.sent || 0);
                $('#statDelivered').text(stats.delivered || 0);
                $('#statRead').text(stats.read_count || 0);
                $('#statFailed').text(stats.failed || 0);

                // Update Table
                let html = '';
                if (logs.length === 0) {
                    html = '<tr><td colspan="6" class="text-center p-4">No logs found</td></tr>';
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
                                <div class="fw-bold"><i class="fab fa-whatsapp text-success me-1"></i>${l.recipient_number}</div>
                                <div class="tiny text-muted uppercase">Provider: ${l.provider_name || 'BhashSMS'}</div>
                            </td>
                            <td>
                                <div class="small fw-bold">${l.template_name || 'N/A'}</div>
                                <div class="tiny text-muted uppercase">${l.category || ''}</div>
                            </td>
                            <td>
                                <div class="small text-muted text-truncate" style="max-width: 300px;">
                                    ${l.message_content || l.body_text || ''}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-${statusClass} uppercase tiny">${l.status}</span>
                                ${l.error_message ? `<div class="tiny text-danger mt-1">${l.error_message}</div>` : ''}
                            </td>
                            <td class="small">${time}</td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary view-btn" data-id="${l.id}">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    `;
                    });
                }
                $('#logsList').html(html);

                // Update Pagination
                if (response.data?.pagination) {
                    renderPaginationUI(response.data.pagination);
                }
            } catch (error) {
                $('#logsList').html(`<tr><td colspan="6" class="text-center text-danger p-4">Error: ${error.message}</td></tr>`);
                $('#paginationRow').html('');
            }
        }

        function renderPaginationUI(pagination) {
            const { current_page, total_pages, total_records, limit } = pagination;

            if (total_records === 0) {
                $('#paginationRow').html('');
                return;
            }

            const start = ((current_page - 1) * limit) + 1;
            const end = Math.min(current_page * limit, total_records);

            let html = `
            <div class="d-flex flex-wrap justify-content-between align-items-center w-100 gap-3">
                <div class="text-muted small">
                    Showing <strong>${start}</strong> to <strong>${end}</strong> of <strong>${total_records.toLocaleString()}</strong> entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
        `;

            // Previous button
            html += `
            <li class="page-item ${current_page <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="javascript:void(0)" data-page="${current_page - 1}">Previous</a>
            </li>
        `;

            // Page numbers (simplified version of PHP logic)
            const showPages = 2;
            let rangeStart = Math.max(1, current_page - showPages);
            let rangeEnd = Math.min(total_pages, current_page + showPages);

            if (rangeStart > 1) {
                html += `<li class="page-item"><a class="page-link" href="javascript:void(0)" data-page="1">1</a></li>`;
                if (rangeStart > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }

            for (let i = rangeStart; i <= rangeEnd; i++) {
                html += `
                <li class="page-item ${i === current_page ? 'active shadow-sm' : ''}">
                    <a class="page-link" href="javascript:void(0)" data-page="${i}">${i}</a>
                </li>
            `;
            }

            if (rangeEnd < total_pages) {
                if (rangeEnd < total_pages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                html += `<li class="page-item"><a class="page-link" href="javascript:void(0)" data-page="${total_pages}">${total_pages}</a></li>`;
            }

            // Next button
            html += `
            <li class="page-item ${current_page >= total_pages ? 'disabled' : ''}">
                <a class="page-link" href="javascript:void(0)" data-page="${current_page + 1}">Next</a>
            </li>
        `;

            html += `
                    </ul>
                </nav>
            </div>
        `;

            $('#paginationRow').html(html);

            // Bind clicks
            $('#paginationRow .page-link').off('click').on('click', function (e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && !$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
                    loadLogs(page);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        }

        function getStatusClass(status) {
            switch (status) {
                case 'read':
                    return 'primary';
                case 'delivered':
                    return 'success';
                case 'sent':
                    return 'info';
                case 'failed':
                    return 'danger';
                default:
                    return 'secondary';
            }
        }

        $(document).ready(function () {
            loadData();

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadLogs();
            });

            $(document).on('click', '.view-btn', async function () {
                const id = $(this).data('id');
                $('#logDetailsBody').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>');
                $('#logModal').modal('show');

                try {
                    const response = await apiClient.get('settings/whatsapp-log-details', {
                        id
                    });
                    const l = response.data;

                    let apiResp = l.api_response || '{}';
                    try {
                        const parsed = typeof apiResp === 'string' ? JSON.parse(apiResp) : apiResp;
                        apiResp = JSON.stringify(parsed, null, 2);
                    } catch (e) {
                        // Keep original if not JSON
                    }

                    let detailHtml = `
                    <div class="table-responsive">
                        <table class="table table-bordered small">
                            <tr><th class="bg-light" width="30%">Message ID</th><td>${l.message_id || 'N/A'}</td></tr>
                            <tr><th class="bg-light">Recipient</th><td><i class="fab fa-whatsapp text-success me-1"></i>${l.recipient_number}</td></tr>
                            <tr><th class="bg-light">Provider</th><td>${l.provider_name || 'BhashSMS'}</td></tr>
                            <tr><th class="bg-light">Template</th><td>${l.template_name || 'N/A'} (${l.category || ''})</td></tr>
                            <tr><th class="bg-light">Content</th><td style="white-space: pre-wrap;">${l.message_content || ''}</td></tr>
                            <tr><th class="bg-light">Variables</th><td><code>${l.variables || '[]'}</code></td></tr>
                            <tr><th class="bg-light">Status</th><td><span class="badge bg-${getStatusClass(l.status)}">${l.status}</span></td></tr>
                            ${l.error_message ? `<tr><th class="bg-light">Error</th><td class="text-danger">${l.error_message}</td></tr>` : ''}
                            <tr><th class="bg-light">Sent At</th><td>${l.created_at}</td></tr>
                            <tr><th class="bg-light">Delivered At</th><td>${l.delivered_at || 'Not yet'}</td></tr>
                            <tr><th class="bg-light">Read At</th><td>${l.read_at || 'Not yet'}</td></tr>
                        </table>
                    </div>
                    <div class="mt-3">
                        <label class="small text-muted fw-bold">API Response:</label>
                        <pre class="bg-light p-2 small border rounded" style="max-height: 200px; overflow-y: auto;">${apiResp}</pre>
                    </div>
                `;
                    $('#logDetailsBody').html(detailHtml);
                } catch (error) {
                    $('#logDetailsBody').html(`<div class="alert alert-danger">Failed to load details: ${error.message}</div>`);
                }
            });

            $('#exportBtn').on('click', function () {
                TableUtils.exportToExcel('logsTable', 'whatsapp_logs', {
                    removeFirstColumn: false,
                    removeLastColumn: true
                });
            });

            $('#syncBtn').on('click', async function () {
                const btn = $(this);
                const originalHtml = btn.html();

                btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Syncing...').prop('disabled', true);

                try {
                    const response = await apiClient.get('settings/whatsapp-logs-sync', { limit: 100 });
                    if (response.success) {
                        alert(`Sync completed. Checked: ${response.data.checked}, Updated: ${response.data.updated}`);
                        loadLogs(); // Reload table
                    } else {
                        alert(`Sync failed: ${response.message || 'Unknown error'}`);
                    }
                } catch (error) {
                    alert(`Error syncing logs: ${error.message}`);
                } finally {
                    btn.html(originalHtml).prop('disabled', false);
                }
            });
        });
    </script>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/settings/whatsapp-message-logs.php.css">