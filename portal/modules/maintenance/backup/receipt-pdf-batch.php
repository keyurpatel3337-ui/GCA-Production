<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Receipt PDF Batch Backup";
$backup_dir = 'D:/portal_backups/receipt_pdfs';

// Ensure backup directory exists
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Get existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '/*.zip');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => round(filesize($file) / 1024 / 1024, 2),
            'date' => date('d-M-Y H:i', filemtime($file)),
            'timestamp' => filemtime($file)
        ];
    }
    // Sort by most recent
    usort($backups, function ($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $file_to_delete = $backup_dir . '/' . basename($_POST['file']);
    if (file_exists($file_to_delete) && unlink($file_to_delete)) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?status=deleted');
        exit;
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/maintenance/backup/receipt-pdf-batch.css">

    <div class="container-fluid">

        <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Backup deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Backup Status Dashboard -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Completed PDF Backups</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <div class="alert alert-info mb-0">No PDF backups found. Click "Create New Backup" to start.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td><i class="fas fa-file-archive text-warning"></i>
                                                    <?php echo $backup['name']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $backup['size']; ?> MB
                                                </td>
                                                <td>
                                                    <?php echo $backup['date']; ?>
                                                </td>
                                                <td>
                                                    <!-- Simple direct download link for simplicity in D: drive -->
                                                    <a href="download_backup.php?file=<?php echo urlencode($backup['name']); ?>&type=pdf"
                                                        class="btn btn-sm btn-success">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline"
                                                        onsubmit="return confirm('Delete this backup?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="file" value="<?php echo $backup['name']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Modal -->
<div class="modal fade" id="backupModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Receipt PDF Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="closeModalBtn"></button>
            </div>
            <div class="modal-body">
                <div id="backupForm">
                    <p class="text-muted">Generate batch PDFs and zip them. This may take a few minutes for large
                        volumes.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">From Date</label>
                            <input type="date" id="startDate" class="form-control"
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Date</label>
                            <input type="date" id="endDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skipExisting" checked>
                                <label class="form-check-label" for="skipExisting">Skip if already generated in this
                                    session</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="button" id="startBackupBtn" class="btn btn-primary w-100">
                                <i class="fas fa-play"></i> Start Backup Process
                            </button>
                        </div>
                    </div>
                </div>

                <div id="backupProgress" class="receipt-pdf-batch-custom-1">
                    <div class="text-center mb-3">
                        <h4 id="progressStatus">Initializing...</h4>
                        <p id="progressSubStatus" class="text-muted">Fetching receipt count...</p>
                    </div>
                    <div class="progress mb-3 receipt-pdf-batch-custom-2">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                            role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div class="list-group list-group-flush border rounded receipt-pdf-batch-custom-3"
                        id="logList">
                        <!-- Logs will appear here -->
                    </div>
                </div>
            </div>
        </div>
        </div>

<script>
    document.getElementById('startBackupBtn').addEventListener('click', function () {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const skipExisting = document.getElementById('skipExisting').checked;

        if (!startDate || !endDate) {
            alert('Please select both dates');
            return;
        }

        // Switch UI
        document.getElementById('backupForm').style.display = 'none';
        document.getElementById('backupProgress').style.display = 'block';
        document.getElementById('closeModalBtn').style.display = 'none';

        startBackupSession(startDate, endDate, skipExisting);
    });

    async function startBackupSession(start, end, skip) {
        const log = (msg, isError = false) => {
            const item = document.createElement('div');
            item.className = 'list-group-item py-1 ' + (isError ? 'text-danger' : 'text-muted');
            item.style.fontSize = '0.85rem';
            item.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            const logList = document.getElementById('logList');
            logList.insertBefore(item, logList.firstChild);
        };

        try {
            log('Starting backup initialization...');

            // Step 1: Initialize session and get total count
            const initResponse = await fetch('ajax_receipt_pdf_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=init&start_date=${start}&end_date=${end}`
            });
            const initData = await initResponse.json();

            if (!initData.success) throw new Error(initData.message);

            const total = initData.total;
            const sessionId = initData.session_id;
            const limit = 20; // Process 20 receipts per request
            let processed = 0;

            log(`Found ${total} receipts to process.`);

            if (total === 0) {
                document.getElementById('progressStatus').textContent = 'No Receipts Found';
                document.getElementById('closeModalBtn').style.display = 'block';
                return;
            }

            // Step 2: Loop chunks
            while (processed < total) {
                document.getElementById('progressStatus').textContent = `Processing Chunks...`;
                document.getElementById('progressSubStatus').textContent = `Generated ${processed} of ${total} PDFs`;

                const chunkResponse = await fetch('ajax_receipt_pdf_batch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=process&session_id=${sessionId}&offset=${processed}&limit=${limit}&start_date=${start}&end_date=${end}`
                });
                const chunkData = await chunkResponse.json();

                if (!chunkData.success) throw new Error(chunkData.message);

                processed += chunkData.processed;
                const percentage = Math.round((processed / total) * 100);

                document.getElementById('progressBar').style.width = percentage + '%';
                document.getElementById('progressBar').textContent = percentage + '%';

                log(`Processed chunk: ${processed}/${total}`);
            }

            // Step 3: Finalize (Zip)
            document.getElementById('progressStatus').textContent = 'Finishing Up...';
            document.getElementById('progressSubStatus').textContent = 'Zipping all PDFs into one file';
            log('Starting compression...');

            const finalizeResponse = await fetch('ajax_receipt_pdf_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=finalize&session_id=${sessionId}`
            });
            const finalizeData = await finalizeResponse.json();

            if (!finalizeData.success) throw new Error(finalizeData.message);

            log('Backup completed successfully!');
            document.getElementById('progressStatus').textContent = 'Backup Complete!';
            document.getElementById('progressBar').className = 'progress-bar bg-success';
            document.getElementById('progressSubStatus').textContent = 'Zip created: ' + finalizeData.filename;

            // Add download button
            const downloadBtn = document.createElement('a');
            downloadBtn.href = 'download_backup.php?file=' + encodeURIComponent(finalizeData.filename) + '&type=pdf';
            downloadBtn.className = 'btn btn-success w-100 mt-3';
            downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download Zip Now';
            document.getElementById('backupProgress').appendChild(downloadBtn);

            document.getElementById('closeModalBtn').style.display = 'block';

            // Refresh page after a delay to show in table
            setTimeout(() => location.reload(), 5000);

        } catch (error) {
            log('ERROR: ' + error.message, true);
            document.getElementById('progressStatus').textContent = 'Backup Failed';
            document.getElementById('progressBar').className = 'progress-bar bg-danger';
            document.getElementById('closeModalBtn').style.display = 'block';
        }
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
