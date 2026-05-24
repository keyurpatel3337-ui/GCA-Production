<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(__DIR__, 4) . '/common/helpers/format_helper.php';
// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Receipt Reports Backup";
$backup_dir = 'D:/portal_backups/receipt_reports';
$message = '';
$message_type = '';

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'generate') {
        $report_type = $_POST['report_type'] ?? 'daily';
        $report_date = $_POST['report_date'] ?? date('Y-m-d');
        $target_dir = $backup_dir . '/' . $report_type;

        // Create directory if not exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        try {
            // Build query based on report type
            if ($report_type === 'daily') {
                $where = "DATE(p.payment_date) = :date";
                $params = ['date' => $report_date];
                $filename_date = date('Y-m-d', strtotime($report_date));
            } elseif ($report_type === 'monthly') {
                $where = "YEAR(p.payment_date) = YEAR(:date) AND MONTH(p.payment_date) = MONTH(:date)";
                $params = ['date' => $report_date];
                $filename_date = date('Y-m', strtotime($report_date));
            } else {
                $where = "YEAR(p.payment_date) = YEAR(:date)";
                $params = ['date' => $report_date];
                $filename_date = date('Y', strtotime($report_date));
            }

            $sql = "SELECT 
                        p.receipt_no,
                        p.payment_date,
                        CONCAT(s.surname, ' ', s.student_name, ' ', IFNULL(s.fathers_name, '')) as student_name,
                        e.roll_no,
                        s.standard,
                        p.payment_type,
                        p.payment_mode,
                        p.amount,
                        p.transaction_id
                    FROM tbl_payments p
                    LEFT JOIN tbl_gm_std_registration s ON p.student_id = s.id
                    LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id AND e.is_active = 1
                    WHERE {$where}
                    ORDER BY p.payment_date DESC";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($data)) {
                $message = "No receipts found for the selected period.";
                $message_type = 'warning';
            } else {
                // Generate Excel file
                require_once dirname(__DIR__, 4) . '/portal/vendor/autoload.php';
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Receipt Report');

                $headers = ['Receipt No', 'Date', 'Student Name', 'Roll No', 'Standard', 'Fee Type', 'Payment Mode', 'Amount', 'Transaction ID'];
                $col = 'A';
                foreach ($headers as $h) {
                    $sheet->setCellValue($col . '1', $h);
                    $sheet->getStyle($col . '1')->getFont()->setBold(true);
                    $col++;
                }

                $rowNum = 2;
                $total = 0;
                foreach ($data as $row) {
                    $sheet->setCellValue('A' . $rowNum, $row['receipt_no']);
                    $sheet->setCellValue('B' . $rowNum, date('d-M-Y', strtotime($row['payment_date'])));
                    $sheet->setCellValue('C' . $rowNum, $row['student_name']);
                    $sheet->setCellValue('D' . $rowNum, $row['roll_no']);
                    $sheet->setCellValue('E' . $rowNum, $row['standard']);
                    $sheet->setCellValue('F' . $rowNum, $row['payment_type']);
                    $sheet->setCellValue('G' . $rowNum, $row['payment_mode']);
                    $sheet->setCellValue('H' . $rowNum, round($row['amount']));
                    $sheet->setCellValue('I' . $rowNum, $row['transaction_id']);
                    $total += $row['amount'];
                    $rowNum++;
                }

                // Total row
                $sheet->setCellValue('G' . $rowNum, 'TOTAL:');
                $sheet->getStyle('G' . $rowNum)->getFont()->setBold(true);
                $sheet->setCellValue('H' . $rowNum, round($total));
                $sheet->getStyle('H' . $rowNum)->getFont()->setBold(true);

                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = 'Receipt_Report_' . ucfirst($report_type) . '_' . $filename_date . '.xlsx';
                $filepath = $target_dir . '/' . $filename;

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save($filepath);

                $message = "Report generated successfully: {$filename} (" . count($data) . " receipts, Total: ₹" . formatIndianCurrency($total) . ")";
                $message_type = 'success';
                logError("Receipt report generated: {$filename} by user ID: " . ($_SESSION['user_id'] ?? 0), 'INFO');

                // Trigger PDF Backup if requested
                if (isset($_POST['include_pdf_backup']) && $_POST['include_pdf_backup'] == '1') {
                    $trigger_pdf_backup = true;
                    $pdf_report_type = $report_type;
                    $pdf_report_date = $report_date;
                }
            }

        } catch (Exception $e) {
            $message = "Report generation failed: " . $e->getMessage();
            $message_type = 'danger';
            logError("Receipt report failed: " . $e->getMessage());
        }

        if ($_POST['action'] === 'delete' && isset($_POST['file'])) {
            $file_to_delete = $backup_dir . '/' . $_POST['type'] . '/' . basename($_POST['file']);
            if (file_exists($file_to_delete) && unlink($file_to_delete)) {
                $message = "Report deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to delete report.";
                $message_type = 'danger';
            }
        }

        if ($_POST['action'] === 'download' && isset($_POST['file'])) {
            $file_to_download = $backup_dir . '/' . $_POST['type'] . '/' . basename($_POST['file']);
            if (file_exists($file_to_download)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . basename($file_to_download) . '"');
                header('Content-Length: ' . filesize($file_to_download));
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($file_to_download);
                exit;
            } else {
                $message = "File not found.";
                $message_type = 'danger';
            }
        }
    }
}

// Get existing reports
$reports = ['daily' => [], 'monthly' => [], 'yearly' => []];
foreach (['daily', 'monthly', 'yearly'] as $type) {
    $type_dir = $backup_dir . '/' . $type;
    if (is_dir($type_dir)) {
        $files = glob($type_dir . '/*.xlsx');
        foreach ($files as $file) {
            $reports[$type][] = [
                'name' => basename($file),
                'size' => round(filesize($file) / 1024, 2),
                'date' => date('d-M-Y H:i', filemtime($file))
            ];
        }
        usort($reports[$type], function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



<div class="container-fluid">

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Generate Report -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle"></i> Generate New Report</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="generate">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select" id="reportType">
                        <option value="daily">Daily</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="report_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check pt-1">
                        <input class="form-check-input" type="checkbox" name="include_pdf_backup" value="1"
                            id="includePdfBackup">
                        <label class="form-check-label" for="includePdfBackup">
                            Include PDF Backup
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-file-excel"></i> Generate Report
                    </button>
                </div>
            </form>

            <!-- PDF Backup Progress (Hidden by default) -->
            <div id="pdfBackupProgress" class="mt-4 border-top pt-4 css-receipt-reports-224b51">
                <div class="text-center mb-3">
                    <h4><i class="fas fa-file-pdf text-danger"></i> Generating Receipt PDFs...</h4>
                    <p id="progressSubStatus" class="text-muted">Initializing...</p>
                </div>
                <div class="progress mb-3 css-receipt-reports-bd56ab">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success css-receipt-reports-759002"
                        role="progressbar">0%</div>
                </div>
                <div class="list-group list-group-flush border rounded css-receipt-reports-7da9cc"
                    id="logList">
                </div>
                <div id="finalizeMessage" class="mt-3 css-receipt-reports-224b51"></div>
            </div>
        </div>
    </div>

    <!-- Existing Reports -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#daily">Daily <span
                            class="badge bg-primary">
                            <?php echo count($reports['daily']); ?>
                        </span></a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#monthly">Monthly <span
                            class="badge bg-success">
                            <?php echo count($reports['monthly']); ?>
                        </span></a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#yearly">Yearly <span
                            class="badge bg-warning">
                            <?php echo count($reports['yearly']); ?>
                        </span></a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <?php foreach (['daily', 'monthly', 'yearly'] as $type): ?>
                    <div class="tab-pane fade <?php echo $type === 'daily' ? 'show active' : ''; ?>"
                        id="<?php echo $type; ?>">
                        <?php if (empty($reports[$type])): ?>
                            <div class="alert alert-info">No
                                <?php echo $type; ?> reports found.
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
                                        <?php foreach ($reports[$type] as $report): ?>
                                            <tr>
                                                <td><i class="fas fa-file-excel text-success"></i>
                                                    <?php echo $report['name']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $report['size']; ?> KB
                                                </td>
                                                <td>
                                                    <?php echo $report['date']; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="download">
                                                        <input type="hidden" name="type" value="<?php echo $type; ?>">
                                                        <input type="hidden" name="file" value="<?php echo $report['name']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this report?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="type" value="<?php echo $type; ?>">
                                                        <input type="hidden" name="file" value="<?php echo $report['name']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
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
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
    <?php if (isset($trigger_pdf_backup) && $trigger_pdf_backup): ?>
        document.addEventListener('DOMContentLoaded', function () {
            startPdfBackup('<?php echo $pdf_report_type; ?>', '<?php echo $pdf_report_date; ?>');
        });
    <?php endif; ?>

    async function startPdfBackup(type, date) {
        const log = (msg, isError = false) => {
            const item = document.createElement('div');
            item.className = 'list-group-item py-1 ' + (isError ? 'text-danger' : 'text-muted');
            item.style.fontSize = '0.85rem';
            item.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            const logList = document.getElementById('logList');
            if (logList) logList.insertBefore(item, logList.firstChild);
        };

        const progressDiv = document.getElementById('pdfBackupProgress');
        if (progressDiv) progressDiv.style.display = 'block';

        try {
            log('Starting PDF backup for ' + type + ' report...');

            // Step 1: Initialize
            const initResponse = await fetch('ajax_receipt_pdf_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=init&report_type=${type}&report_date=${date}`
            });
            const initData = await initResponse.json();
            if (!initData.success) throw new Error(initData.message);

            const total = initData.total;
            const sessionId = initData.session_id;
            const limit = 20;
            let processed = 0;

            if (total === 0) {
                log('No receipts found for PDF generation.');
                if (document.getElementById('progressSubStatus')) document.getElementById('progressSubStatus').textContent = 'Completed (No receipts)';
                return;
            }

            // Step 2: Loop chunks
            while (processed < total) {
                if (document.getElementById('progressSubStatus')) document.getElementById('progressSubStatus').textContent = `Generated ${processed} of ${total} PDFs`;

                const chunkResponse = await fetch('ajax_receipt_pdf_batch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=process&session_id=${sessionId}&offset=${processed}&limit=${limit}&report_type=${type}&report_date=${date}`
                });
                const chunkData = await chunkResponse.json();
                if (!chunkData.success) throw new Error(chunkData.message);

                processed += chunkData.processed;
                const percentage = Math.round((processed / total) * 100);
                const progressBar = document.getElementById('progressBar');
                if (progressBar) {
                    progressBar.style.width = percentage + '%';
                    progressBar.textContent = percentage + '%';
                }
                log(`Processed: ${processed}/${total}`);
            }

            // Step 3: Finalize
            log('Zipping PDFs...');
            const finalizeResponse = await fetch('ajax_receipt_pdf_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=finalize&session_id=${sessionId}`
            });
            const finalizeData = await finalizeResponse.json();
            if (!finalizeData.success) throw new Error(finalizeData.message);

            log('PDF Backup completed!');
            const finMsg = document.getElementById('finalizeMessage');
            if (finMsg) {
                finMsg.style.display = 'block';
                finMsg.innerHTML = `<div class="alert alert-success mt-3">
                <strong>PDF Backup Ready:</strong> ${finalizeData.filename} 
                <a href="download_backup.php?file=${encodeURIComponent(finalizeData.filename)}&type=pdf" class="btn btn-sm btn-success ms-2">
                    <i class="fas fa-download"></i> Download Zip
                </a>
            </div>`;
            }
            if (document.getElementById('progressSubStatus')) document.getElementById('progressSubStatus').textContent = 'Completed!';

        } catch (error) {
            log('ERROR: ' + error.message, true);
            if (document.getElementById('progressSubStatus')) document.getElementById('progressSubStatus').textContent = 'Failed';
            const progressBar = document.getElementById('progressBar');
            if (progressBar) progressBar.className = 'progress-bar bg-danger';
        }
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>