<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Access Control
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

// Fetch Exam Data
$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if (!$exam_id) die("Invalid Exam ID");

try {
    $stmt = $conn->prepare("SELECT e.*, s.stdtext FROM tbl_oes_exams e LEFT JOIN standard s ON e.standard_id = s.stdid WHERE e.id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam) die("Exam not found.");
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = "Paper Preview: " . $exam['title'];
include PORTAL_INCLUDE_PATH . 'header.php';
?>

<!-- Load PDF.js (Local same-origin assets to bypass CSP Blob Worker restriction) -->
<script src="<?= PORTAL_URL ?>/assets/js/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= PORTAL_URL ?>/assets/js/pdf.worker.min.js';
</script>

<style>
    .pdf-page-wrapper {
        margin: 0 auto 20px auto;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        background: #fff;
        display: block;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .pdf-page-wrapper:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
    }
    #pdf-view-viewport::-webkit-scrollbar {
        width: 10px;
    }
    #pdf-view-viewport::-webkit-scrollbar-track {
        background: #2d2d2d;
    }
    #pdf-view-viewport::-webkit-scrollbar-thumb {
        background: #555;
        border-radius: 5px;
    }
    #pdf-view-viewport::-webkit-scrollbar-thumb:hover {
        background: #888;
    }
    .preview-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    .preview-card {
        background: #525659;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        border: 1px solid #333;
    }
    .preview-header {
        background: #2d2d2d;
        color: #fff;
        padding: 15px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #live-preview-iframe {
        width: 100%;
        height: calc(100vh - 250px);
        border: none;
        display: block;
        background: #fff;
    }
    .info-bar {
        background: #fff;
        border-radius: 12px;
        padding: 15px 25px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .btn-print {
        background: #dc3545;
        color: #fff;
        font-weight: 700;
        border-radius: 10px;
        padding: 12px 25px;
        transition: all 0.2s;
        border: none;
        text-decoration: none !important;
    }
    .btn-print:hover {
        background: #c82333;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        color: #fff;
    }
    .status-badge {
        font-size: 0.75rem;
        background: #e8f5e9;
        color: #2e7d32;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 700;
    }
</style>

<?php include PORTAL_INCLUDE_PATH . 'navbar.php'; ?>
<?php include PORTAL_INCLUDE_PATH . 'sidebar.php'; ?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 font-weight-bold">Paper <span class="text-primary">Preview</span> Studio</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="manage-exams.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Exams
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content pt-3">
        <div class="container-fluid preview-container">
            
            <div class="info-bar">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark"><?= htmlspecialchars($exam['title']) ?></h5>
                    <div class="d-flex align-items-center gap-3">
                        <span class="small text-muted"><i class="fas fa-graduation-cap mr-1"></i> <?= htmlspecialchars($exam['stdtext'] ?: 'N/A') ?></span>
                        <span class="small text-muted"><i class="fas fa-star mr-1"></i> <?= $exam['total_marks'] ?> Marks</span>
                        <span class="status-badge"><i class="fas fa-magic mr-1"></i> AI Optimized Layout</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="btn-group shadow-sm" role="group" style="border-radius: 10px; overflow: hidden; border: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-sm btn-primary px-3 font-weight-bold" id="btn-col-1" onclick="changeCols(1)">
                            <i class="fas fa-stop mr-1"></i> Single
                        </button>
                        <button type="button" class="btn btn-sm btn-white px-3 font-weight-bold" id="btn-col-2" onclick="changeCols(2)">
                            <i class="fas fa-columns mr-1"></i> Double
                        </button>
                    </div>
                    <a href="export-word-paper.php?exam_id=<?= $exam_id ?>" id="word-export-btn" class="btn btn-outline-primary shadow-sm" style="border-radius: 10px; font-weight: 600;">
                        <i class="fas fa-file-word mr-2"></i> Word
                    </a>
                    <a href="#" id="final-print-btn" target="_blank" class="btn btn-print shadow-sm">
                        <i class="fas fa-print mr-2"></i> Print Paper
                    </a>
                </div>
            </div>

            <div class="preview-card">
                <div class="preview-header py-2">
                    <span class="small font-weight-bold text-uppercase letter-spacing-1 d-none d-md-inline-block">
                        <i class="fas fa-eye mr-2"></i> Live PDF View (Professional Standard)
                    </span>
                    <div class="d-flex align-items-center gap-3 w-100 w-sm-auto justify-content-between justify-content-md-end">
                        <!-- Custom toolbar for PDF controls -->
                        <div class="d-flex align-items-center gap-2">
                            <div class="btn-group shadow-sm" role="group" style="border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15);">
                                <button type="button" class="btn btn-sm btn-dark text-white px-2" onclick="zoomOut()" title="Zoom Out" style="background: #1e1e1e; border: none;">
                                    <i class="fas fa-search-minus"></i>
                                </button>
                                <span class="input-group-text bg-dark text-white border-0 small px-3 font-weight-bold" id="zoom-percent" style="font-size: 0.8rem; background: #2b2b2b !important;">100%</span>
                                <button type="button" class="btn btn-sm btn-dark text-white px-2" onclick="zoomIn()" title="Zoom In" style="background: #1e1e1e; border: none;">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-dark text-white px-2" onclick="zoomFit()" title="Fit to Width" style="background: #1e1e1e; border: none;">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            
                            <!-- Floating Page indicator -->
                            <span class="badge bg-dark border border-secondary text-white px-3 py-2 font-weight-bold" id="page-indicator" style="font-size: 0.8rem; background: #1e1e1e !important;">
                                Page 1 of 1
                            </span>
                        </div>

                        <div id="load-status" class="small text-white-50"><i class="fas fa-sync fa-spin mr-2"></i>Loading Preview...</div>
                    </div>
                </div>
                <div class="card-body p-0" id="pdf-view-viewport" style="background: #3c4043; overflow-y: auto; height: calc(100vh - 250px); text-align: center; position: relative;">
                    <div id="pdf-pages-container" style="display: inline-block; padding: 25px 0;"></div>
                </div>
            </div>

            <p class="text-center text-muted small mt-4">
                <i class="fas fa-info-circle mr-1"></i> The layout above is automatically optimized for academic standards (A4, 2-Column, 12pt Serif).
            </p>
        </div>
    </div>
</main>

<script>
    let currentCols = 1;
    let pdfDoc = null;
    let currentZoom = 1.0;
    let currentPdfUrl = '';

    // Future-proof Password Protection Settings
    let enablePasswordProtection = false; // Change to true in the future to enable password prompting
    let defaultPdfPassword = ''; // Optional default preset password

    // Update active UI buttons for column selection
    function updateColButtons(num) {
        const btn1 = document.getElementById('btn-col-1');
        const btn2 = document.getElementById('btn-col-2');
        if (!btn1 || !btn2) return;
        
        if (num === 1) {
            btn1.classList.replace('btn-white', 'btn-primary');
            btn2.classList.replace('btn-primary', 'btn-white');
        } else {
            btn2.classList.replace('btn-white', 'btn-primary');
            btn1.classList.replace('btn-primary', 'btn-white');
        }
    }

    async function changeCols(num) {
        currentCols = num;
        updateColButtons(num);
        
        const printBtn = document.getElementById('final-print-btn');
        const wordBtn = document.getElementById('word-export-btn');
        
        const pdfUrl = `generate-pdf-paper.php?exam_id=<?= $exam_id ?>&cols=${num}`;
        printBtn.href = pdfUrl;
        if (wordBtn) {
            wordBtn.href = `export-word-paper.php?exam_id=<?= $exam_id ?>&cols=${num}`;
        }
        
        // Re-render PDF in custom canvas viewer
        await renderPDF(pdfUrl);
    }

    async function renderPDF(url) {
        currentPdfUrl = url;
        const container = document.getElementById('pdf-pages-container');
        const status = document.getElementById('load-status');
        
        status.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i>Loading Preview...';
        container.innerHTML = '';
        
        try {
            const loadingParams = { url: url };
            if (enablePasswordProtection && defaultPdfPassword) {
                loadingParams.password = defaultPdfPassword;
            }
            
            const loadingTask = pdfjsLib.getDocument(loadingParams);
            
            // Password handler callback
            loadingTask.onPassword = function(callback, reason) {
                if (enablePasswordProtection) {
                    Swal.fire({
                        title: 'Password Required',
                        text: reason === 2 ? 'Incorrect password. Try again:' : 'This exam paper is encrypted. Enter password:',
                        input: 'password',
                        inputPlaceholder: 'Enter PDF Password',
                        showCancelButton: true,
                        confirmButtonText: 'Decrypt',
                        confirmButtonColor: '#dc3545',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            callback(result.value);
                        } else {
                            callback('');
                            status.innerHTML = '<i class="fas fa-lock text-warning mr-2"></i> Decryption Required';
                        }
                    });
                } else {
                    callback('');
                }
            };
            
            pdfDoc = await loadingTask.promise;
            
            status.innerHTML = '<i class="fas fa-check-circle text-success mr-2"></i> Ready to Print';
            document.getElementById('page-indicator').textContent = `Page 1 of ${pdfDoc.numPages}`;
            
            // Sequential rendering of pages for consistent layout order
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                await renderPage(pageNum);
            }
            
            setupScrollSpy();
            
        } catch (error) {
            console.error("PDF.js loading exception: ", error);
            status.innerHTML = '<i class="fas fa-exclamation-triangle text-danger mr-2"></i> Failed to Load';
        }
    }

    async function renderPage(pageNum) {
        const page = await pdfDoc.getPage(pageNum);
        const container = document.getElementById('pdf-pages-container');
        
        const pageWrapper = document.createElement('div');
        pageWrapper.className = 'pdf-page-wrapper';
        pageWrapper.dataset.page = pageNum;
        
        const canvas = document.createElement('canvas');
        pageWrapper.appendChild(canvas);
        container.appendChild(pageWrapper);
        
        const ctx = canvas.getContext('2d');
        let viewport = page.getViewport({ scale: currentZoom });
        
        if (currentZoom === 'fit') {
            const parentWidth = document.getElementById('pdf-view-viewport').clientWidth - 60;
            const originalWidth = page.getViewport({ scale: 1.0 }).width;
            const scale = parentWidth / originalWidth;
            viewport = page.getViewport({ scale: scale });
        }
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        pageWrapper.style.width = viewport.width + 'px';
        pageWrapper.style.height = viewport.height + 'px';
        
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        
        await page.render(renderContext).promise;
    }

    async function updateZoom(newZoom) {
        currentZoom = newZoom;
        
        const percentSpan = document.getElementById('zoom-percent');
        if (newZoom === 'fit') {
            percentSpan.textContent = 'Fit';
        } else {
            percentSpan.textContent = Math.round(newZoom * 100) + '%';
        }
        
        if (!pdfDoc) return;
        
        const status = document.getElementById('load-status');
        status.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i>Scaling...';
        
        const container = document.getElementById('pdf-pages-container');
        container.innerHTML = '';
        
        for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
            await renderPage(pageNum);
        }
        
        status.innerHTML = '<i class="fas fa-check-circle text-success mr-2"></i> Scaled';
    }

    function zoomIn() {
        if (currentZoom === 'fit') currentZoom = 1.0;
        if (currentZoom < 3.0) {
            updateZoom(currentZoom + 0.15);
        }
    }

    function zoomOut() {
        if (currentZoom === 'fit') currentZoom = 1.0;
        if (currentZoom > 0.5) {
            updateZoom(currentZoom - 0.15);
        }
    }

    function zoomFit() {
        updateZoom('fit');
    }

    function setupScrollSpy() {
        const viewport = document.getElementById('pdf-view-viewport');
        const indicator = document.getElementById('page-indicator');
        if (!viewport || !indicator) return;
        
        viewport.addEventListener('scroll', () => {
            const wrappers = document.querySelectorAll('.pdf-page-wrapper');
            const parentRect = viewport.getBoundingClientRect();
            
            let activePage = 1;
            
            wrappers.forEach(wrapper => {
                const rect = wrapper.getBoundingClientRect();
                
                // Check if page centers inside viewport
                const overlapTop = Math.max(rect.top, parentRect.top);
                const overlapBottom = Math.min(rect.bottom, parentRect.bottom);
                const visibleHeight = Math.max(0, overlapBottom - overlapTop);
                
                if (visibleHeight > (wrapper.clientHeight / 3)) {
                    activePage = parseInt(wrapper.dataset.page);
                }
            });
            
            if (pdfDoc) {
                indicator.textContent = `Page ${activePage} of ${pdfDoc.numPages}`;
            }
        });
    }

    // Initial load
    window.onload = () => changeCols(1);
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>