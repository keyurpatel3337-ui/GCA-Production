c:\Users\Administrator\AppData\Local\Packages\MicrosoftWindows.Client.Core_cw5n1h2txyewy\TempState\ScreenClip\{3C5F12A7-BE77-49C6-AE12-5854EB3FFC56}.png<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    die("Unauthorized access.");
}

$page_title = "Bulk Word Import";
$page_breadcrumb = "Bulk Word Import";
include PORTAL_INCLUDE_PATH . 'header.php';
?>
<!-- OES Custom CSS -->
<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/online-exam.css">
<!-- KaTeX for math rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/mhchem.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}]});"></script>

<?php
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';

// Fetch lists for global dropdowns
$standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdid ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-3"><strong>Bulk</strong> Question Import (Word)</h1>
                <a href="question-bank.php" class="btn btn-outline-secondary shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Question Bank
                </a>
            </div>
        </div>

        <!-- Import Configuration Card -->
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
                    <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                        <h5 class="card-title mb-0 text-white">1. Configure Metadata</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-4">Set global values for all questions in this file.</p>
                        
                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold">Standard</label>
                            <select id="bulk_std" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                <option value="">Select Standard</option>
                                <?php foreach ($standards as $s): ?>
                                    <option value="<?php echo $s['stdid']; ?>"><?php echo $s['stdtext']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold">Subject</label>
                            <select id="bulk_sub" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                <option value="">Select Subject</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold">Chapter (Optional)</label>
                            <select id="bulk_ch" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                <option value="">Select Chapter</option>
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label font-weight-bold">Topic (Optional)</label>
                            <select id="bulk_tp" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                <option value="">Select Topic</option>
                            </select>
                        </div>

                        <hr>

                        <div class="form-group mt-4">
                            <label class="form-label font-weight-bold text-primary">2. Select Word File</label>
                            <div class="upload-box p-4 rounded text-center bg-light" id="drop-zone">
                                <i class="fas fa-file-word fa-3x text-primary mb-3"></i>
                                <p class="mb-0 text-dark font-weight-bold">Click to Upload .docx</p>
                                <span class="text-muted small">Max file size: 10MB</span>
                                <input type="file" id="word-upload-input" accept=".docx" class="d-none">
                            </div>
                        </div>

                        <div id="bulk-progress-wrap" class="mt-4" style="display:none;">
                            <div class="progress mb-2" style="height: 10px; border-radius: 5px;">
                                <div id="bulk-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                            <p id="bulk-progress-text" class="small text-center text-muted mb-0">Processing...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                        <h5 class="card-title mb-0 text-white">Preview & Import</h5>
                        <button id="bulk-import-btn" class="btn btn-success btn-sm px-4" disabled onclick="submitBulkImport()">
                            <i class="fas fa-upload mr-2"></i> Import to Question Bank
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="preview-container" style="min-height: 500px; max-height: 700px; overflow-y: auto;">
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                                <h5>Upload a file to see preview</h5>
                                <p class="small px-5">The system will parse the document and show you exactly what will be imported.</p>
                                <div class="mt-4 p-3 border rounded d-inline-block bg-white shadow-sm">
                                    <p class="small text-primary fw-bold">Path Verification Check:</p>
                                    <img src="../../../uploads/oes/test_path_marker.png" alt="Test Image" onerror="this.parentElement.style.display='none'" style="width: 50px;">
                                    <p class="x-small text-muted mt-2">If you see an icon above, paths are working.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
            </div>
        </div>
    </div>
        </div>
    </div>
</main>

<script>
// Data from server (constants.php would provide this if needed, but we fetch via AJAX)
const allSubjects = <?php 
    $sub_all = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0 ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($sub_all); 
?>;
const allChapters = <?php 
    $ch_all = $conn->query("SELECT chpid, subid, chapter FROM chapters WHERE activated = 1 AND is_deleted = 0 ORDER BY chapter ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($ch_all); 
?>;
const allTopics = <?php 
    $tp_all = $conn->query("SELECT id, subject_id, chapter_id, topic_name_english FROM tbl_topics WHERE activated = 1 AND is_deleted = 0 ORDER BY topic_name_english ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($tp_all); 
?>;

let parsedQuestions = [];

document.addEventListener('DOMContentLoaded', function() {
    const stdSel = document.getElementById('bulk_std');
    const subSel = document.getElementById('bulk_sub');
    const chSel  = document.getElementById('bulk_ch');
    const tpSel  = document.getElementById('bulk_tp');
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('word-upload-input');

    // --- Dynamic Dropdowns ---
    stdSel.addEventListener('change', function() {
        const val = this.value;
        subSel.innerHTML = '<option value="">Select Subject</option>';
        if (val) {
            allSubjects.filter(s => s.standard_id == val).forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id; opt.innerText = s.subject_name;
                subSel.appendChild(opt);
            });
        }
        subSel.dispatchEvent(new Event('change'));
    });

    subSel.addEventListener('change', function() {
        const val = this.value;
        chSel.innerHTML = '<option value="">Select Chapter</option>';
        if (val) {
            allChapters.filter(c => c.subid == val).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.chpid; opt.innerText = c.chapter;
                chSel.appendChild(opt);
            });
        }
        chSel.dispatchEvent(new Event('change'));
    });

    chSel.addEventListener('change', function() {
        const val = this.value;
        const subId = subSel.value;
        tpSel.innerHTML = '<option value="">Select Topic</option>';
        if (val) {
            allTopics.filter(t => t.chapter_id == val || (t.subject_id == subId && !t.chapter_id)).forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id; opt.innerText = t.topic_name_english;
                tpSel.appendChild(opt);
            });
        }
    });

    // --- File Upload Handling ---
    dropZone.addEventListener('click', () => fileInput.click());
    
    fileInput.addEventListener('change', function(e) {
        if (!this.files.length) return;
        handleFileUpload(this.files[0]);
    });

    function autoFillMetadata(questions) {
        if (!questions || !questions.length) return;
        
        const firstQ = questions.find(q => q.standard || q.subject || q.chapter || q.topic) || questions[0];
        
        let msgFound = [];
        let msgNotFound = [];
        
        // Helper to find and select option by text (forgiving match)
        function selectOptionByText(selectEl, text) {
            if (!text) return false;
            
            let norm = t => t.replace(/[^a-z0-9]/gi, '').toLowerCase();
            let textNorm = norm(text);
            
            let matched = Array.from(selectEl.options).find(opt => {
                if (!opt.value) return false; // skip placeholder
                return norm(opt.text) === textNorm || norm(opt.value) === textNorm;
            });
            
            if (matched && matched.value) {
                selectEl.value = matched.value;
                selectEl.dispatchEvent(new Event('change'));
                return true;
            }
            return false;
        }
        
        // Standard
        if (firstQ.standard) {
            if (selectOptionByText(document.getElementById('bulk_std'), firstQ.standard)) {
                msgFound.push('Standard');
            } else {
                msgNotFound.push('Standard');
            }
        }
        
        // Subject
        if (firstQ.subject) {
            if (selectOptionByText(document.getElementById('bulk_sub'), firstQ.subject)) {
                msgFound.push('Subject');
            } else {
                msgNotFound.push('Subject');
            }
        }
        
        // Chapter
        if (firstQ.chapter) {
            if (selectOptionByText(document.getElementById('bulk_ch'), firstQ.chapter)) {
                msgFound.push('Chapter');
            } else {
                msgNotFound.push('Chapter');
            }
        }
        
        // Topic
        if (firstQ.topic) {
            if (selectOptionByText(document.getElementById('bulk_tp'), firstQ.topic)) {
                msgFound.push('Topic');
            } else {
                msgNotFound.push('Topic');
            }
        }
        
        // Display feedback message
        let hintWrap = document.getElementById('bulk-auto-fill-msg');
        if (!hintWrap) {
            hintWrap = document.createElement('div');
            hintWrap.id = 'bulk-auto-fill-msg';
            hintWrap.style.fontSize = '0.85rem';
            hintWrap.style.marginBottom = '15px';
            hintWrap.style.padding = '10px 15px';
            hintWrap.style.borderRadius = '8px';
            document.getElementById('drop-zone').parentNode.insertBefore(hintWrap, document.getElementById('drop-zone'));
        }
        
        if (msgFound.length > 0 || msgNotFound.length > 0) {
            let html = '';
            if (msgFound.length > 0) {
                html += `<div style="color:#1565c0; font-weight:600;"><i class="fas fa-magic mr-1"></i> Auto-filled from file: ${msgFound.join(', ')}</div>`;
            }
            if (msgNotFound.length > 0) {
                html += `<div style="color:#e65100; font-weight:600; margin-top:4px;"><i class="fas fa-exclamation-triangle mr-1"></i> Could not map: ${msgNotFound.join(', ')}. Please select manually.</div>`;
            }
            hintWrap.innerHTML = html;
            hintWrap.style.display = 'block';
            hintWrap.style.background = msgNotFound.length > 0 ? '#fff3e0' : '#e3f2fd';
            hintWrap.style.border = '1px solid ' + (msgNotFound.length > 0 ? '#ffcc80' : '#bbdefb');
        } else {
            hintWrap.style.display = 'none';
        }
    }

    function handleFileUpload(file) {
        let hintWrap = document.getElementById('bulk-auto-fill-msg');
        if (hintWrap) hintWrap.style.display = 'none';

        const formData = new FormData();
        formData.append('word_file', file);

        const progressWrap = document.getElementById('bulk-progress-wrap');
        const progressBar  = document.getElementById('bulk-progress-bar');
        const progressText = document.getElementById('bulk-progress-text');
        const previewCont  = document.getElementById('preview-container');
        const importBtn    = document.getElementById('bulk-import-btn');

        progressWrap.style.display = 'block';
        progressBar.style.width = '20%';
        progressText.textContent = 'Uploading and Parsing Word file...';
        previewCont.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Reading document structure...</p></div>';
        importBtn.disabled = true;

        fetch('ajax/import-word-server.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            progressBar.style.width = '100%';
            progressText.textContent = 'Parsing complete!';
            
            if (data.success) {
                autoFillMetadata(data.questions);
                parsedQuestions = data.questions;
                renderPreview(data.questions);
                importBtn.disabled = false;
            } else {
                alert('Word parse error: ' + data.message);
                resetPreview();
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Failed to process file.');
            resetPreview();
        });
    }

    function renderPreview(questions) {
        const previewCont = document.getElementById('preview-container');
        if (!questions.length) {
            previewCont.innerHTML = '<div class="p-5 text-center">No questions found in file.</div>';
            return;
        }

        let html = '<div class="p-3 bg-light border-bottom font-weight-bold">Found ' + questions.length + ' questions:</div>';
        questions.forEach((q, i) => {
            html += `
            <div class="preview-row">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="badge badge-primary mr-2">Q${i+1}</span>
                    <div class="flex-grow-1">
                        <div class="question-text mb-3 ql-editor p-0" style="min-height: auto;">${q.question_text}</div>
                        <div class="row">
                            <div class="col-md-6"><div class="option-box ql-editor p-1" style="min-height: auto;"><strong>A:</strong> ${q.option_a || '---'}</div></div>
                            <div class="col-md-6"><div class="option-box ql-editor p-1" style="min-height: auto;"><strong>B:</strong> ${q.option_b || '---'}</div></div>
                            <div class="col-md-6"><div class="option-box ql-editor p-1" style="min-height: auto;"><strong>C:</strong> ${q.option_c || '---'}</div></div>
                            <div class="col-md-6"><div class="option-box ql-editor p-1" style="min-height: auto;"><strong>D:</strong> ${q.option_d || '---'}</div></div>
                        </div>
                    </div>
                </div>
            </div>`;
        });
        previewCont.innerHTML = html;
        
        // Render Math
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(previewCont, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},
                    {left: '$', right: '$', display: false}
                ],
                throwOnError: false
            });
        }
    }

    function renderTableView(questions) {
        const tableCard = document.getElementById('table-view-card');
        const tableBody = document.getElementById('table-view-body');
        
        if (!questions.length) {
            tableCard.style.display = 'none';
            return;
        }

        tableCard.style.display = 'block';
        let html = '';
        questions.forEach((q, i) => {
            html += `
            <tr>
                <td class="text-center font-weight-bold">${i+1}</td>
                <td>${q.standard || '---'}</td>
                <td>${q.group_name || '---'}</td>
                <td>${q.subject || '---'}</td>
                <td>${q.chapter || '---'}</td>
                <td>${q.topic || '---'}</td>
                <td style="min-width: 300px; max-width: 500px; white-space: normal;">${q.question_text}</td>
                <td style="max-width: 200px; white-space: normal;">${q.option_a || '---'}</td>
                <td style="max-width: 200px; white-space: normal;">${q.option_b || '---'}</td>
                <td style="max-width: 200px; white-space: normal;">${q.option_c || '---'}</td>
                <td style="max-width: 200px; white-space: normal;">${q.option_d || '---'}</td>
                <td class="text-center font-weight-bold text-success">${q.correct_option || '---'}</td>
                <td class="text-center">${q.question_type || 'MCQ'}</td>
                <td class="text-center">${q.marks || '1'}</td>
                <td class="text-center">${q.order_no || (i+1)}</td>
            </tr>`;
        });
        tableBody.innerHTML = html;
        
        // Render Math in Table too
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(tableBody, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},
                    {left: '$', right: '$', display: false}
                ],
                throwOnError: false
            });
        }
    }

    function resetPreview() {
        document.getElementById('preview-container').innerHTML = '';
        document.getElementById('table-view-card').style.display = 'none';
        document.getElementById('bulk-progress-wrap').style.display = 'none';
    }

    window.submitBulkImport = async function() {
        if (!parsedQuestions.length) return;

        const std = document.getElementById('bulk_std').value;
        const sub = document.getElementById('bulk_sub').value;
        
        if (!std || !sub) {
            alert('Please select Standard and Subject first.');
            return;
        }

        const btn = document.getElementById('bulk-import-btn');
        const progressBar = document.getElementById('bulk-progress-bar');
        const progressText = document.getElementById('bulk-progress-text');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Importing...';
        progressBar.style.width = '50%';
        progressText.textContent = 'Saving to database...';

        const payload = {
            questions: parsedQuestions,
            global_metadata: {
                standard_id: std,
                subject_id: sub,
                chapter_id: document.getElementById('bulk_ch').value,
                topic_id: document.getElementById('bulk_tp').value
            }
        };

        try {
            const resp = await fetch('save_bulk_questions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            
            if (result.success) {
                progressBar.style.width = '100%';
                progressText.textContent = 'Success!';
                alert('Successfully imported ' + result.imported + ' questions!');
                window.location.href = 'question-bank.php';
            } else {
                alert('Import failed: ' + result.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload mr-2"></i> Import to Question Bank';
            }
        } catch (e) {
            console.error(e);
            alert('Error during import.');
            btn.disabled = false;
        }
    };
});
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
