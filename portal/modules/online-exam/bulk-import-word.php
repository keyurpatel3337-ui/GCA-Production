<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    die("Unauthorized access.");
}

$page_title = "Bulk Word Import";
$page_breadcrumb = "Bulk Word Import";
include PORTAL_INCLUDE_PATH . 'header.php';
?>
<!-- KaTeX for math rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/mhchem.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}]});"></script>

<?php
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';

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

        <!-- Stacked Layout (No Dual Panel) -->
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <!-- Card 1: Import Configuration & Upload -->
                <div class="card shadow-lg border-0 mb-4" style="border-radius: 15px;">
                    <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                        <h5 class="card-title mb-0 text-white"><i class="fas fa-file-import mr-2"></i> Configure Metadata & Select Word File</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">Set standard/subject details, then upload your `.docx` file to preview and import questions.</p>
                        
                        <!-- Metadata Fields Grid -->
                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label class="form-label font-weight-bold">Standard</label>
                                <select id="bulk_std" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                    <option value="">Select Standard</option>
                                    <option value="11">11th</option>
                                    <option value="12">12th</option>
                                    <option value="13">Reneet</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label class="form-label font-weight-bold">Group</label>
                                <select id="bulk_grp" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                    <option value="">Select Group</option>
                                    <?php
                                    $groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($groups as $g): ?>
                                        <option value="<?php echo $g['id']; ?>"><?php echo $g['group_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label class="form-label font-weight-bold">Subject</label>
                                <select id="bulk_sub" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                    <option value="">Select Subject</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label class="form-label font-weight-bold">Chapter (Optional)</label>
                                <select id="bulk_ch" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                    <option value="">Select Chapter</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label class="form-label font-weight-bold">Topic (Optional)</label>
                                <select id="bulk_tp" class="form-control custom-select shadow-sm" style="border-radius: 8px;">
                                    <option value="">Select Topic</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Upload Zone -->
                        <div class="form-group mb-0">
                            <label class="form-label font-weight-bold text-primary">Upload Word File</label>
                            <div class="upload-box p-5 border-dashed rounded text-center bg-light" id="drop-zone" style="border: 2px dashed #007bff; cursor: pointer; border-radius: 12px !important;">
                                <i class="fas fa-file-word fa-4x text-primary mb-3"></i>
                                <h5 class="mb-1 text-dark font-weight-bold">Drag and drop or click to upload `.docx`</h5>
                                <span class="text-muted small">Max file size: 10MB</span>
                                <input type="file" id="word-upload-input" accept=".docx" class="d-none">
                            </div>
                        </div>

                        <!-- Progress Section -->
                        <div id="bulk-progress-wrap" class="mt-4" style="display:none;">
                            <div class="progress mb-2" style="height: 12px; border-radius: 6px;">
                                <div id="bulk-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                            </div>
                            <p id="bulk-progress-text" class="small text-center text-muted font-weight-bold mb-0">Processing...</p>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Spacious Full-Width Preview Panel (Flowing below) -->
                <div class="card shadow-lg border-0 mb-4" id="preview-card" style="border-radius: 15px; display: none;">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                        <h5 class="card-title mb-0 text-white"><i class="fas fa-eye mr-2"></i> Preview & Review Questions</h5>
                        <button id="bulk-import-btn" class="btn btn-success px-4 font-weight-bold shadow-sm" disabled onclick="submitBulkImport()" style="border-radius: 8px;">
                            <i class="fas fa-upload mr-2"></i> Import to Question Bank
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="preview-container" style="max-height: 800px; overflow-y: auto;">
                            <!-- Dynamically loaded preview list -->
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

<style>
    .upload-box:hover { background-color: #e9ecef!important; }
    .preview-row { border-bottom: 1px solid #eee; padding: 15px; transition: background 0.2s; }
    .preview-row:hover { background-color: #f8f9fa; }
    .preview-row img { max-width: 250px; border-radius: 5px; margin: 10px 0; border: 1px solid #ddd; }
    .option-box { font-size: 0.9rem; color: #555; background: #fff; border: 1px solid #eee; border-radius: 5px; padding: 5px 10px; margin-bottom: 5px; }
    .border-dashed { border-style: dashed !important; }
</style>

<script>
let parsedQuestions = [];

document.addEventListener('DOMContentLoaded', function() {
    const stdSel = document.getElementById('bulk_std');
    const subSel = document.getElementById('bulk_sub');
    const chSel  = document.getElementById('bulk_ch');
    const tpSel  = document.getElementById('bulk_tp');
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('word-upload-input');

    // --- Dynamic Dropdowns via AJAX ---
    stdSel.addEventListener('change', async function() {
        const val = this.value;
        subSel.innerHTML = '<option value="">Select Subject</option>';
        if (val) {
            try {
                const resp = await fetch('ajax/get-subjects.php?standard_id=' + val);
                const subjects = await resp.json();
                subjects.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id; opt.innerText = s.subject_name;
                    subSel.appendChild(opt);
                });
            } catch (e) {
                console.error('Error fetching subjects:', e);
            }
        }
        subSel.dispatchEvent(new Event('change'));
    });

    subSel.addEventListener('change', async function() {
        const val = this.value;
        chSel.innerHTML = '<option value="">Select Chapter</option>';
        if (val) {
            try {
                const resp = await fetch('ajax/get-chapters.php?subject_id=' + val);
                const chapters = await resp.json();
                chapters.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.chpid; opt.innerText = c.chapter;
                    chSel.appendChild(opt);
                });
            } catch (e) {
                console.error('Error fetching chapters:', e);
            }
        }
        chSel.dispatchEvent(new Event('change'));
    });

    chSel.addEventListener('change', async function() {
        const val = this.value;
        tpSel.innerHTML = '<option value="">Select Topic</option>';
        if (val) {
            try {
                const resp = await fetch('ajax/get-topics.php?chapter_id=' + val);
                const topics = await resp.json();
                topics.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id; opt.innerText = t.topic_name;
                    tpSel.appendChild(opt);
                });
            } catch (e) {
                console.error('Error fetching topics:', e);
            }
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
        const previewCard  = document.getElementById('preview-card');
        const previewCont  = document.getElementById('preview-container');
        const importBtn    = document.getElementById('bulk-import-btn');

        progressWrap.style.display = 'block';
        progressBar.style.width = '20%';
        progressText.textContent = 'Uploading and Parsing Word file...';
        
        if (previewCard) previewCard.style.display = 'none';
        if (previewCont) previewCont.innerHTML = '';
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
                
                // Show preview card and populate it
                if (previewCard) previewCard.style.display = 'block';
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
        if (!previewCont) return;
        if (!questions.length) {
            previewCont.innerHTML = '<div class="p-5 text-center">No questions found in file.</div>';
            return;
        }

        let html = '<div class="p-3 bg-light border-bottom font-weight-bold"><i class="fas fa-list text-primary mr-2"></i>Found ' + questions.length + ' questions:</div>';
        questions.forEach((q, i) => {
            html += `
            <div class="preview-row p-3 mb-3 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="badge bg-primary text-white mr-3 px-2 py-1">Q${i+1}</span>
                    <div class="flex-grow-1">
                        <div class="question-text mb-3 ql-editor p-0" style="min-height: auto; font-size: 1.05rem;">
                            <div class="mb-2"><span class="badge bg-primary text-white mr-1">EN</span> ${q.question_text}</div>
                            ${q.question_text_guj ? `<div class="text-secondary"><span class="badge bg-warning text-dark mr-1">GU</span> ${q.question_text_guj}</div>` : ''}
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="option-box ql-editor p-2 border rounded" style="min-height: auto; background: #fafafa;">
                                    <div><strong class="text-primary">A (EN):</strong> ${q.option_a || '---'}</div>
                                    ${q.option_a_guj ? `<div class="mt-1 text-muted"><strong class="text-warning">A (GU):</strong> ${q.option_a_guj}</div>` : ''}
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="option-box ql-editor p-2 border rounded" style="min-height: auto; background: #fafafa;">
                                    <div><strong class="text-primary">B (EN):</strong> ${q.option_b || '---'}</div>
                                    ${q.option_b_guj ? `<div class="mt-1 text-muted"><strong class="text-warning">B (GU):</strong> ${q.option_b_guj}</div>` : ''}
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="option-box ql-editor p-2 border rounded" style="min-height: auto; background: #fafafa;">
                                    <div><strong class="text-primary">C (EN):</strong> ${q.option_c || '---'}</div>
                                    ${q.option_c_guj ? `<div class="mt-1 text-muted"><strong class="text-warning">C (GU):</strong> ${q.option_c_guj}</div>` : ''}
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="option-box ql-editor p-2 border rounded" style="min-height: auto; background: #fafafa;">
                                    <div><strong class="text-primary">D (EN):</strong> ${q.option_d || '---'}</div>
                                    ${q.option_d_guj ? `<div class="mt-1 text-muted"><strong class="text-warning">D (GU):</strong> ${q.option_d_guj}</div>` : ''}
                                </div>
                            </div>
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

    function resetPreview() {
        const previewCard = document.getElementById('preview-card');
        if (previewCard) previewCard.style.display = 'none';
        const previewCont = document.getElementById('preview-container');
        if (previewCont) previewCont.innerHTML = '';
        const progressWrap = document.getElementById('bulk-progress-wrap');
        if (progressWrap) progressWrap.style.display = 'none';
        const importBtn = document.getElementById('bulk-import-btn');
        if (importBtn) importBtn.disabled = true;
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
                group_id: document.getElementById('bulk_grp').value,
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
