<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Restrict access to logged-in students and parents
if (!hasAnyRole([ROLE_STUDENT]) && !(isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = $user_id;
$material_id = intval($_GET['id'] ?? 0);

// Fetch material details
$stmt = $conn->prepare("
    SELECT m.*, s.subject_name 
    FROM tbl_academic_materials m
    JOIN tbl_subjects s ON m.subject_id = s.id
    WHERE m.id = ?
");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    die("Study Material not found.");
}

// Handle AJAX actions (Bookmarking, Note-taking, Doubt-solving)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $page_number = intval($_POST['page_number'] ?? 1);

    try {
        if ($action === 'toggle_bookmark') {
            // Check if already bookmarked
            $chk = $conn->prepare("SELECT id FROM tbl_student_material_bookmarks WHERE student_id = ? AND material_id = ? AND page_number = ?");
            $chk->execute([$student_id, $material_id, $page_number]);
            if ($chk->fetch()) {
                $del = $conn->prepare("DELETE FROM tbl_student_material_bookmarks WHERE student_id = ? AND material_id = ? AND page_number = ?");
                $del->execute([$student_id, $material_id, $page_number]);
                echo json_encode(['success' => true, 'is_bookmarked' => false, 'message' => 'Bookmark removed.']);
            } else {
                $ins = $conn->prepare("INSERT INTO tbl_student_material_bookmarks (student_id, material_id, page_number) VALUES (?, ?, ?)");
                $ins->execute([$student_id, $material_id, $page_number]);
                echo json_encode(['success' => true, 'is_bookmarked' => true, 'message' => 'Page bookmarked!']);
            }
            exit;
        }

        if ($action === 'save_note') {
            $note_text = trim($_POST['note_text'] ?? '');
            if (empty($note_text)) {
                $del = $conn->prepare("DELETE FROM tbl_student_material_notes WHERE student_id = ? AND material_id = ? AND page_number = ?");
                $del->execute([$student_id, $material_id, $page_number]);
                echo json_encode(['success' => true, 'message' => 'Note cleared.']);
            } else {
                $ups = $conn->prepare("
                    INSERT INTO tbl_student_material_notes (student_id, material_id, page_number, note_text) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE note_text = ?, updated_at = NOW()
                ");
                $ups->execute([$student_id, $material_id, $page_number, $note_text, $note_text]);
                echo json_encode(['success' => true, 'message' => 'Note saved.']);
            }
            exit;
        }

        if ($action === 'submit_doubt') {
            $doubt_text = trim($_POST['doubt_text'] ?? '');
            if (empty($doubt_text)) {
                echo json_encode(['success' => false, 'message' => 'Doubt query cannot be empty.']);
                exit;
            }

            $ins = $conn->prepare("
                INSERT INTO tbl_student_material_doubts (student_id, material_id, page_number, doubt_text) 
                VALUES (?, ?, ?, ?)
            ");
            $ins->execute([$student_id, $material_id, $page_number, $doubt_text]);
            echo json_encode(['success' => true, 'message' => 'Doubt flagged and sent to teacher!']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch all bookmarked pages for this material
$b_stmt = $conn->prepare("SELECT page_number FROM tbl_student_material_bookmarks WHERE student_id = ? AND material_id = ?");
$b_stmt->execute([$student_id, $material_id]);
$bookmarks = $b_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all notes page-by-page
$n_stmt = $conn->prepare("SELECT page_number, note_text FROM tbl_student_material_notes WHERE student_id = ? AND material_id = ?");
$n_stmt->execute([$student_id, $material_id]);
$notes = $n_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch all doubts
$d_stmt = $conn->prepare("SELECT page_number, doubt_text, status, reply_text FROM tbl_student_material_doubts WHERE student_id = ? AND material_id = ? ORDER BY id DESC");
$d_stmt->execute([$student_id, $material_id]);
$doubts = $d_stmt->fetchAll();

$page_title = htmlspecialchars($material['title']) . ' | E-Reader';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Include Mozilla PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    // Configure worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
</script>

<style>
    .kindle-container {
        display: flex;
        gap: 20px;
        min-height: calc(100vh - 120px);
    }
    .kindle-viewer {
        flex: 1;
        background: #faf7f2; /* Kindle paperwhite sepia texture */
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        min-width: 0;
    }
    .kindle-sidebar {
        width: 320px;
        flex-shrink: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }
    .pdf-canvas-wrapper {
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        background: #ffffff;
        max-width: 100%;
        margin-top: 15px;
        position: relative;
    }
    .btn-reader {
        transition: all 0.2s ease;
        border-radius: 50px;
    }
    .btn-reader:hover {
        transform: scale(1.05);
    }
    .kindle-tab-btn {
        font-weight: 600;
        border: none;
        background: transparent;
        padding: 10px;
        border-bottom: 2px solid transparent;
        color: #64748b;
        flex: 1;
        transition: all 0.2s ease;
    }
    .kindle-tab-btn.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
    }
</style>

<div class="container-fluid py-3">
    <!-- Header Back Navigation -->
    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <div class="d-flex align-items-center gap-3">
            <a href="materials-list.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> Library
            </a>
            <div>
                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($material['title']) ?></h5>
                <span class="badge bg-secondary small"><?= htmlspecialchars($material['subject_name']) ?></span>
            </div>
        </div>
    </div>

    <!-- E-Reader Layout -->
    <div class="kindle-container">
        <!-- 1. Central E-Reader Container -->
        <div class="kindle-viewer">
            <!-- Reader Bar Controls -->
            <div class="d-flex align-items-center justify-content-between w-100 border-bottom pb-3">
                <!-- Navigation -->
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light btn-reader border" onclick="prevPage()"><i class="fas fa-chevron-left"></i> Prev</button>
                    <span class="fw-bold px-2">Page <span id="page-num">1</span> / <span id="page-count">---</span></span>
                    <button class="btn btn-light btn-reader border" onclick="nextPage()">Next <i class="fas fa-chevron-right"></i></button>
                </div>

                <!-- Tools -->
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-warning btn-reader px-3" id="bookmarkBtn" onclick="toggleBookmark()">
                        <i class="far fa-star me-1"></i> Bookmark
                    </button>
                    <button class="btn btn-outline-danger btn-reader px-3" onclick="openDoubtModal()">
                        <i class="fas fa-question-circle me-1"></i> Mark Doubt
                    </button>
                </div>
            </div>

            <!-- PDF Page Renderer Canvas -->
            <div class="pdf-canvas-wrapper">
                <div id="loading-spinner" class="position-absolute top-50 start-50 translate-middle text-center py-5">
                    <i class="fas fa-circle-notch fa-spin fa-3x text-primary mb-2"></i>
                    <div class="small text-muted fw-bold">Kindle Paper E-Reader loading...</div>
                </div>
                <canvas id="pdf-render"></canvas>
            </div>
        </div>

        <!-- 2. Kindle Interactive Side Drawer (Notes & Doubts Log) -->
        <div class="kindle-sidebar">
            <div class="d-flex border-bottom text-center">
                <button class="kindle-tab-btn active" onclick="switchSidebarTab('notes')"><i class="fas fa-edit me-1"></i> Page Notes</button>
                <button class="kindle-tab-btn" onclick="switchSidebarTab('doubts')"><i class="fas fa-comments me-1"></i> Doubts Log</button>
            </div>

            <div class="flex-grow-1 p-3 overflow-auto" id="sidebar-tab-content" style="max-height: 480px;">
                <!-- Tab: Notes -->
                <div id="notes-tab-pane">
                    <label class="form-label small fw-bold text-muted mb-2">Write Notes for Page <span class="current-page-num">1</span></label>
                    <textarea id="page-notes-textarea" class="form-control mb-3" rows="6" placeholder="Write page highlights, tags, or summaries here..." oninput="autoSaveNote(this.value)"></textarea>
                    <div class="d-flex align-items-center justify-content-between text-muted small">
                        <span id="save-status"><i class="fas fa-check-circle text-success me-1"></i> Saved</span>
                        <span>Autosaves instantly</span>
                    </div>
                </div>

                <!-- Tab: Doubts -->
                <div id="doubts-tab-pane" style="display: none;">
                    <label class="form-label small fw-bold text-muted mb-2">Your flagged page doubts</label>
                    <div id="doubts-log-list" class="d-flex flex-column gap-3">
                        <?php if (empty($doubts)): ?>
                            <div class="text-muted small text-center py-4">No doubts flagged yet.</div>
                        <?php else: ?>
                            <?php foreach ($doubts as $d): ?>
                                <div class="p-2 border rounded bg-light">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="badge bg-secondary small">Page <?= $d['page_number'] ?></span>
                                        <?php if ($d['status'] === 'Resolved'): ?>
                                            <span class="badge bg-success small">Resolved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark small">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small fw-bold text-dark"><?= htmlspecialchars($d['doubt_text']) ?></div>
                                    <?php if (!empty($d['reply_text'])): ?>
                                        <div class="mt-2 p-1 border-top small text-primary" style="background-color: #f0f4ff;">
                                            <strong>Teacher reply:</strong> <?= htmlspecialchars($d['reply_text']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Flag Doubt on specific page -->
<div class="modal fade" id="flagDoubtModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow rounded-3 border-0">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-question-circle me-2"></i> Ask Doubt on Page <span class="current-page-num">1</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="flagDoubtForm">
                <div class="modal-body p-4">
                    <p class="text-muted small">Your query will be shared directly with the subject teacher mapped to this class, specifying that it relates to page <strong class="current-page-num">1</strong>.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Explain your doubt <span class="text-danger">*</span></label>
                        <textarea name="doubt_text" class="form-control" rows="4" placeholder="What is unclear about this page? Explain in detail..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger text-white px-4 rounded-pill"><i class="fas fa-paper-plane me-1"></i> Send doubt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const url = '<?= BASE_URL . "/" . $material["file_path"] ?>';
    const bookmarks = <?= json_encode($bookmarks) ?>;
    const pageNotes = <?= json_encode($notes) ?>;
    const materialId = <?= $material_id ?>;

    let pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        pageNumPending = null,
        scale = 1.0,
        canvas = document.getElementById('pdf-render'),
        ctx = canvas.getContext('2d');

    // Render Page
    function renderPage(num) {
        pageRendering = true;
        document.getElementById('loading-spinner').style.display = 'block';

        // Update Page display indicators
        document.getElementById('page-num').textContent = num;
        document.querySelectorAll('.current-page-num').forEach(el => el.textContent = num);

        // Fetch page notes
        const noteArea = document.getElementById('page-notes-textarea');
        noteArea.value = pageNotes[num] || '';

        // Update Bookmark button state
        const bBtn = document.getElementById('bookmarkBtn');
        if (bookmarks.includes(num)) {
            bBtn.className = 'btn btn-warning btn-reader px-3';
            bBtn.innerHTML = '<i class="fas fa-star me-1 text-white"></i> Bookmarked';
        } else {
            bBtn.className = 'btn btn-outline-warning btn-reader px-3';
            bBtn.innerHTML = '<i class="far fa-star me-1"></i> Bookmark';
        }

        // Render PDF page to canvas
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale: scale });
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            const renderTask = page.render(renderContext);

            renderTask.promise.then(() => {
                pageRendering = false;
                document.getElementById('loading-spinner').style.display = 'none';
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        });
    }

    function queueRenderPage(num) {
        if (pageRendering) {
            pageNumPending = num;
        } else {
            renderPage(num);
        }
    }

    function prevPage() {
        if (pageNum <= 1) return;
        pageNum--;
        queueRenderPage(pageNum);
    }

    function nextPage() {
        if (pageNum >= pdfDoc.numPages) return;
        pageNum++;
        queueRenderPage(pageNum);
    }

    // Load PDF
    pdfjsLib.getDocument(url).promise.then(pdfDoc_ => {
        pdfDoc = pdfDoc_;
        document.getElementById('page-count').textContent = pdfDoc.numPages;
        renderPage(pageNum);
    });

    // Toggle Bookmarking via AJAX
    function toggleBookmark() {
        $.post(location.href, { action: 'toggle_bookmark', page_number: pageNum })
            .done(res => {
                if (res.success) {
                    if (res.is_bookmarked) {
                        if (!bookmarks.includes(pageNum)) bookmarks.push(pageNum);
                    } else {
                        const idx = bookmarks.indexOf(pageNum);
                        if (idx > -1) bookmarks.splice(idx, 1);
                    }
                    renderPage(pageNum);
                    showToast('success', 'Allocated!', res.message);
                } else {
                    showToast('error', 'Error!', res.message);
                }
            });
    }

    // Auto-Save Note (Debounced/Throttled)
    let saveTimeout = null;
    function autoSaveNote(val) {
        document.getElementById('save-status').innerHTML = '<i class="fas fa-spinner fa-spin me-1 text-primary"></i> Typing...';
        
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            $.post(location.href, { action: 'save_note', page_number: pageNum, note_text: val })
                .done(res => {
                    if (res.success) {
                        pageNotes[pageNum] = val;
                        document.getElementById('save-status').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Saved';
                    } else {
                        document.getElementById('save-status').innerHTML = '<i class="fas fa-exclamation-circle text-danger me-1"></i> Save Failed';
                    }
                });
        }, 1000);
    }

    // Doubt Flagging Modal Trigger & Submit
    function openDoubtModal() {
        $('#flagDoubtModal').modal('show');
    }

    document.getElementById('flagDoubtForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const area = this.querySelector('textarea');
        const text = area.value;
        const submitBtn = this.querySelector('button[type="submit"]');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

        $.post(location.href, { action: 'submit_doubt', page_number: pageNum, doubt_text: text })
            .done(res => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send doubt';
                
                if (res.success) {
                    $('#flagDoubtModal').modal('hide');
                    area.value = '';
                    showToast('success', 'Sent!', res.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error!', res.message);
                }
            });
    });

    // Sidebar Tab Switching
    function switchSidebarTab(tabName) {
        document.querySelectorAll('.kindle-tab-btn').forEach(btn => btn.classList.remove('active'));
        if (tabName === 'notes') {
            document.querySelector('.kindle-tab-btn:nth-child(1)').classList.add('active');
            document.getElementById('notes-tab-pane').style.display = 'block';
            document.getElementById('doubts-tab-pane').style.display = 'none';
        } else {
            document.querySelector('.kindle-tab-btn:nth-child(2)').classList.add('active');
            document.getElementById('notes-tab-pane').style.display = 'none';
            document.getElementById('doubts-tab-pane').style.display = 'block';
        }
    }

    // Mobile & Tablet Touch Swipe Gestures support
    let touchstartX = 0;
    let touchendX = 0;
    const swipeThreshold = 60; // minimum swipe distance (pixels) to trigger page change
    const renderCanvas = document.getElementById('pdf-render');

    renderCanvas.addEventListener('touchstart', function(event) {
        touchstartX = event.changedTouches[0].screenX;
    }, { passive: true });

    renderCanvas.addEventListener('touchend', function(event) {
        touchendX = event.changedTouches[0].screenX;
        handleSwipeGesture();
    }, { passive: true });

    function handleSwipeGesture() {
        const diffX = touchendX - touchstartX;
        if (Math.abs(diffX) > swipeThreshold) {
            if (diffX < 0) {
                nextPage(); // Swipe Left -> Next Page
            } else {
                prevPage(); // Swipe Right -> Prev Page
            }
        }
    }
</script>

<?php include '../../include/footer.php'; ?>
