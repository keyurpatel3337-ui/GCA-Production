/**
 * OES Question Editor - Main Logic
 * Modernized Multi-Modal Editor with Math, Chem, Draw, OCR, and Bulk Support
 */

let editors = {};
let activeEditor = null;
let currentRange = null;
let canvas = null; // Fabric.js
let cropper = null; // Cropper.js

// ---- Multi-Language State (EN / HI / GU) ----
const LANG_CONFIG = {
    en: { ocr: 'eng', voice: 'en-IN', label: 'EN', flag: '🇬🇧' },
    hi: { ocr: 'hin', voice: 'hi-IN', label: 'HI', flag: '🇮🇳' },
    gu: { ocr: 'guj', voice: 'gu-IN', label: 'GU', flag: '🏛️' },
};
const LANG_ORDER = ['en', 'hi', 'gu'];
let currentLang = 'en';

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'block';
        if (id === 'drawModal' && canvas) {
            setTimeout(() => { canvas.calcOffset(); }, 100);
        }
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
}

function cycleLang() {
    const idx = LANG_ORDER.indexOf(currentLang);
    currentLang = LANG_ORDER[(idx + 1) % LANG_ORDER.length];
    const cfg = LANG_CONFIG[currentLang];
    const btn = document.getElementById('lang-toggle-btn');
    if (btn) btn.innerHTML = cfg.flag + ' ' + cfg.label;
    console.log('[OES] Language switched to:', currentLang, cfg);
}

// ---- Custom Image Resize Module for Quill 2.x (Zero-Dependency) ----
class CustomImageResize {
    constructor(quill, options = {}) {
        this.quill = quill;
        this.options = options;
        this.img = null;
        this.overlay = null;
        this.handle = null;
        this.isUpdatingSize = false;
        
        // Listen for clicks on images
        this.quill.root.addEventListener('click', this.handleClick.bind(this), false);
        
        // Hide overlay on click outside or text change
        document.addEventListener('mousedown', this.handleDocMouseDown.bind(this), false);
        this.quill.on('text-change', () => {
            if (this.isUpdatingSize) return;
            this.hide();
        });
    }

    handleClick(evt) {
        if (evt.target && evt.target.tagName === 'IMG') {
            this.show(evt.target);
        } else {
            this.hide();
        }
    }

    handleDocMouseDown(evt) {
        if (!this.overlay) return;
        if (evt.target === this.img || evt.target === this.handle || this.overlay.contains(evt.target)) {
            return;
        }
        this.hide();
    }

    show(img) {
        if (this.img === img) return;
        this.hide();
        this.img = img;
        this.createOverlay();
    }

    hide() {
        if (this.overlay) {
            if (this.overlay.parentNode) {
                this.overlay.parentNode.removeChild(this.overlay);
            }
            this.overlay = null;
        }
        this.img = null;
        this.handle = null;
    }

    createOverlay() {
        const rect = this.img.getBoundingClientRect();
        const scrollX = window.scrollX || window.pageXOffset;
        const scrollY = window.scrollY || window.pageYOffset;

        this.overlay = document.createElement('div');
        this.overlay.className = 'ql-image-resizer-overlay';
        Object.assign(this.overlay.style, {
            position: 'absolute',
            top: `${rect.top + scrollY}px`,
            left: `${rect.left + scrollX}px`,
            width: `${rect.width}px`,
            height: `${rect.height}px`,
            border: '1px solid #0d6efd',
            pointerEvents: 'none',
            zIndex: '10000',
            boxSizing: 'border-box'
        });

        // Add resize handles (bottom-right only for simplicity, or 4 corners)
        this.handle = document.createElement('div');
        Object.assign(this.handle.style, {
            position: 'absolute',
            right: '-5px',
            bottom: '-5px',
            width: '10px',
            height: '10px',
            backgroundColor: '#0d6efd',
            cursor: 'nwse-resize',
            pointerEvents: 'auto',
            zIndex: '10001'
        });
        
        this.handle.addEventListener('mousedown', this.startResize.bind(this), false);
        this.overlay.appendChild(this.handle);

        // Add Toolbar (Align/Delete/Manual Adjustment)
        const toolbar = document.createElement('div');
        Object.assign(toolbar.style, {
            position: 'absolute',
            top: '-38px',
            left: '50%',
            transform: 'translateX(-50%)',
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            backgroundColor: '#fff',
            padding: '4px 8px',
            borderRadius: '6px',
            border: '1px solid #ced4da',
            boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
            pointerEvents: 'auto',
            zIndex: '10002'
        });

        // Manual Width Adjustment
        const wLabel = document.createElement('span');
        wLabel.innerText = 'W:';
        wLabel.style.fontSize = '12px';
        wLabel.style.fontWeight = 'bold';
        wLabel.style.color = '#495057';
        toolbar.appendChild(wLabel);

        const wInput = document.createElement('input');
        wInput.type = 'number';
        wInput.value = Math.round(rect.width || this.img.clientWidth || 100);
        Object.assign(wInput.style, {
            width: '55px',
            height: '22px',
            border: '1px solid #ced4da',
            borderRadius: '4px',
            fontSize: '11px',
            textAlign: 'center',
            padding: '2px'
        });
        toolbar.appendChild(wInput);

        // Manual Height Adjustment
        const hLabel = document.createElement('span');
        hLabel.innerText = 'H:';
        hLabel.style.fontSize = '12px';
        hLabel.style.fontWeight = 'bold';
        hLabel.style.color = '#495057';
        toolbar.appendChild(hLabel);

        const hInput = document.createElement('input');
        hInput.type = 'number';
        hInput.value = Math.round(rect.height || this.img.clientHeight || 100);
        Object.assign(hInput.style, {
            width: '55px',
            height: '22px',
            border: '1px solid #ced4da',
            borderRadius: '4px',
            fontSize: '11px',
            textAlign: 'center',
            padding: '2px'
        });
        toolbar.appendChild(hInput);

        // Aspect ratio lock logic
        const initialWidth = parseFloat(wInput.value) || 100;
        const initialHeight = parseFloat(hInput.value) || 100;
        const ratio = initialWidth / initialHeight;

        const updateImageSize = (newWidth, newHeight) => {
            if (!this.img || !this.overlay) return;
            
            this.isUpdatingSize = true;
            try {
                // 1. Update DOM styles directly
                this.img.style.width = `${newWidth}px`;
                this.img.style.height = `${newHeight}px`;
                this.img.setAttribute('width', `${newWidth}`);
                this.img.setAttribute('height', `${newHeight}`);
                this.img.setAttribute('style', `width: ${newWidth}px; height: ${newHeight}px;`);
                
                // 2. Format Blot
                const blot = Quill.find(this.img);
                if (blot && typeof blot.format === 'function') {
                    try {
                        blot.format('width', `${newWidth}px`);
                        blot.format('height', `${newHeight}px`);
                        blot.format('style', `width: ${newWidth}px; height: ${newHeight}px;`);
                    } catch (e) {
                        console.warn('[OES Resizer] Format error:', e);
                    }
                }
                this.quill.update();

                // 3. Update overlay dimensions in real-time
                if (this.img) {
                    const newRect = this.img.getBoundingClientRect();
                    Object.assign(this.overlay.style, {
                        width: `${newRect.width}px`,
                        height: `${newRect.height}px`
                    });
                }
            } finally {
                this.isUpdatingSize = false;
            }
        };

        wInput.addEventListener('input', (e) => {
            e.stopPropagation();
            const newWidth = Math.max(10, parseInt(wInput.value) || 0);
            const newHeight = Math.round(newWidth / ratio);
            hInput.value = newHeight;
            updateImageSize(newWidth, newHeight);
        });

        hInput.addEventListener('input', (e) => {
            e.stopPropagation();
            const newHeight = Math.max(10, parseInt(hInput.value) || 0);
            const newWidth = Math.round(newHeight * ratio);
            wInput.value = newWidth;
            updateImageSize(newWidth, newHeight);
        });

        // Vertical divider
        const divider = document.createElement('div');
        Object.assign(divider.style, {
            width: '1px',
            height: '16px',
            backgroundColor: '#dee2e6',
            margin: '0 4px'
        });
        toolbar.appendChild(divider);

        const actions = [
            { icon: '<i class="fas fa-align-left"></i>', click: () => this.align('left') },
            { icon: '<i class="fas fa-align-center"></i>', click: () => this.align('center') },
            { icon: '<i class="fas fa-align-right"></i>', click: () => this.align('right') },
            { icon: '<i class="fas fa-trash text-danger"></i>', click: () => this.deleteImg() }
        ];

        actions.forEach(a => {
            const btn = document.createElement('button');
            btn.innerHTML = a.icon;
            btn.type = 'button';
            Object.assign(btn.style, {
                border: 'none',
                background: 'none',
                padding: '2px 4px',
                cursor: 'pointer',
                fontSize: '12px'
            });
            btn.onclick = (e) => { e.preventDefault(); e.stopPropagation(); a.click(); };
            toolbar.appendChild(btn);
        });

        this.overlay.appendChild(toolbar);
        document.body.appendChild(this.overlay);
    }

    startResize(evt) {
        evt.preventDefault();
        evt.stopPropagation();
        
        const getX = (e) => {
            if (e.clientX !== undefined) return e.clientX;
            if (e.touches && e.touches.length > 0) return e.touches[0].clientX;
            return 0;
        };
        
        const startX = getX(evt);
        const rect = this.img.getBoundingClientRect();
        const startWidth = rect.width || this.img.clientWidth || 100;
        const startHeight = rect.height || this.img.clientHeight || 100;
        
        let ratio = startWidth / startHeight;
        if (isNaN(ratio) || !isFinite(ratio) || ratio <= 0) {
            ratio = 1.0;
        }
        
        const blot = Quill.find(this.img);
        let finalWidth = startWidth;
        let finalHeight = startHeight;

        const onMouseMove = (moveEvt) => {
            if (!this.img || !this.overlay) return;
            const currentX = getX(moveEvt);
            const deltaX = currentX - startX;
            finalWidth = Math.max(30, startWidth + deltaX);
            finalHeight = finalWidth / ratio;
            
            // 1. Only modify the DOM directly during drag to avoid triggering Quill text-change events
            this.img.style.width = `${finalWidth}px`;
            this.img.style.height = `${finalHeight}px`;
            this.img.setAttribute('width', `${finalWidth}`);
            this.img.setAttribute('height', `${finalHeight}`);
            this.img.setAttribute('style', `width: ${finalWidth}px; height: ${finalHeight}px;`);
            
            // 2. Update overlay positioning in real-time
            const newRect = this.img.getBoundingClientRect();
            const scrollX = window.scrollX || window.pageXOffset;
            const scrollY = window.scrollY || window.pageYOffset;
            Object.assign(this.overlay.style, {
                top: `${newRect.top + scrollY}px`,
                left: `${newRect.left + scrollX}px`,
                width: `${newRect.width}px`,
                height: `${newRect.height}px`
            });
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            
            this.isUpdatingSize = true;
            try {
                // 3. Persist the final dimensions back into Quill's parchment blot ONCE on mouse up
                if (this.img && blot) {
                    try {
                        if (typeof blot.format === 'function') {
                            blot.format('width', `${finalWidth}px`);
                            blot.format('height', `${finalHeight}px`);
                            blot.format('style', `width: ${finalWidth}px; height: ${finalHeight}px;`);
                        }
                    } catch (err) {
                        console.warn('[OES Resizer] Final formatting failed:', err);
                    }
                }
                this.quill.update(); // Sync quill content
            } finally {
                this.isUpdatingSize = false;
            }
        };

        document.addEventListener('mousemove', onMouseMove, false);
        document.addEventListener('mouseup', onMouseUp, false);
    }

    align(type) {
        if (!this.img) return;
        const parent = this.img.parentNode;
        if (type === 'left') parent.style.textAlign = 'left';
        else if (type === 'center') parent.style.textAlign = 'center';
        else if (type === 'right') parent.style.textAlign = 'right';
        this.quill.update();
        this.show(this.img); // Refresh overlay position
    }

    deleteImg() {
        if (!this.img) return;
        const blot = Quill.find(this.img);
        if (blot) blot.deleteAt(0);
        this.hide();
    }
}

// Register Module
Quill.register('modules/imageResize', CustomImageResize);

function configureQuill() {
    const FontClass = Quill.import('attributors/class/font');
    FontClass.whitelist = ['serif', 'monospace', 'outfit', 'roboto', 'inter'];
    Quill.register(FontClass, true);

    const Size = Quill.import('attributors/style/size');
    Size.whitelist = ['8px', '10px', '12px', '14px', '16px', '18px', '20px', '24px', '30px', '36px', '48px', '60px', '72px'];
    Quill.register(Size, true);

    // Custom Image blot to support inline width, height, and style attributes from resizing
    const ImageBlot = Quill.import('formats/image');
    
    // Direct base class override as a foolproof fallback
    if (ImageBlot) {
        ImageBlot.sanitize = function(url) {
            if (!url) return 'about:blank';
            const trimUrl = url.trim();
            if (trimUrl.toLowerCase().startsWith('javascript:')) {
                return 'about:blank';
            }
            return trimUrl;
        };
    }

    class CustomImageBlot extends ImageBlot {
        static blotName = 'image';
        static tagName = 'IMG';

        static sanitize(url) {
            if (!url) return 'about:blank';
            const trimUrl = url.trim();
            if (trimUrl.toLowerCase().startsWith('javascript:')) {
                return 'about:blank';
            }
            return trimUrl;
        }

        static create(value) {
            const node = super.create(value);
            if (typeof value === 'string') {
                node.setAttribute('src', typeof this.sanitize === 'function' ? this.sanitize(value) : value);
            } else if (typeof value === 'object') {
                if (value.src) node.setAttribute('src', typeof this.sanitize === 'function' ? this.sanitize(value.src) : value.src);
                if (value.width) node.setAttribute('width', value.width);
                if (value.height) node.setAttribute('height', value.height);
                if (value.style) node.setAttribute('style', value.style);
            }
            return node;
        }
        static formats(node) {
            const formats = {};
            if (node.hasAttribute('src')) formats.src = node.getAttribute('src');
            if (node.hasAttribute('width')) formats.width = node.getAttribute('width');
            if (node.hasAttribute('height')) formats.height = node.getAttribute('height');
            if (node.hasAttribute('style')) formats.style = node.getAttribute('style');
            return formats;
        }
        format(name, value) {
            if (['width', 'height', 'style'].indexOf(name) > -1) {
                if (value) {
                    this.domNode.setAttribute(name, value);
                } else {
                    this.domNode.removeAttribute(name);
                }
            } else {
                super.format(name, value);
            }
        }
    }
    Quill.register('formats/image', CustomImageBlot, true);
    Quill.register(CustomImageBlot, true);
}

// ---- Icon Helpers ----
const L = {
    sigma: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 4H6l6 8-6 8h12"/></svg>',
    flask: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 3h6m-6 0v6l-4 9a1 1 0 0 0 .9 1.45h12.2A1 1 0 0 0 19 18L15 9V3"/><path d="M6.5 13.5h11"/></svg>',
    pencil: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
    scanText: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 7V5a2 2 0 0 1 2-2h2m10 0h2a2 2 0 0 1 2 2v2m0 10v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2m5-11h8m-8 4h6m-6 4h4"/></svg>',
    fileImport: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M12 12v8m-3-3 3 3 3-3"/></svg>',
    table: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>',
    rowAdd: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 10h18M3 14h18M8 6V4m0 16v-2m8-14v-2m0 18v-2"/></svg>',
    colAdd: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 3v18M10 3v18M6 8H4m0 4H4m0 4H4m16-8h-2m0 4h-2m0 4h-2"/></svg>',
    rowDel: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18M3 14h18"/><line x1="9" y1="6" x2="15" y2="18"/><line x1="15" y1="6" x2="9" y2="18"/></svg>',
    colDel: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v18M10 3v18"/><line x1="6" y1="9" x2="18" y2="15"/><line x1="18" y1="9" x2="6" y2="15"/></svg>',
    lang: '<svg class="lucide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m5 8 6 6m-7 0 7-7 3 3M2 12h2m16 0h2m-5-7v2m0 14v-2m-7.5-10h15"/></svg>',
};

function injectIcons() {
    document.querySelectorAll('.ql-math').forEach(b => { b.innerHTML = L.sigma; b.title = 'Math Equation (MathLive)'; });
    document.querySelectorAll('.ql-chem').forEach(b => { b.innerHTML = L.flask; b.title = 'Chemistry Molecule (Ketcher)'; });
    document.querySelectorAll('.ql-draw').forEach(b => { b.innerHTML = L.pencil; b.title = 'Drawing Board (Fabric.js)'; });
    document.querySelectorAll('.ql-ocr').forEach(b => { b.innerHTML = L.scanText; b.title = 'OCR: Image to Text (Tesseract)'; });
    document.querySelectorAll('.ql-import').forEach(b => { b.innerHTML = L.fileImport; b.title = 'Import Doc/Excel'; });
    document.querySelectorAll('.ql-table').forEach(b => { b.innerHTML = L.table; b.title = 'Insert Table'; });
    document.querySelectorAll('.ql-table-insert-row').forEach(b => { b.innerHTML = L.rowAdd; b.title = 'Insert Row Below'; });
    document.querySelectorAll('.ql-table-insert-column').forEach(b => { b.innerHTML = L.colAdd; b.title = 'Insert Column Right'; });
    document.querySelectorAll('.ql-table-delete-row').forEach(b => { b.innerHTML = L.rowDel; b.title = 'Delete Row'; b.style.color = '#ea4335'; });
    document.querySelectorAll('.ql-table-delete-column').forEach(b => { b.innerHTML = L.colDel; b.title = 'Delete Column'; b.style.color = '#ea4335'; });

    // Language toggle — inject ONLY on first toolbar (main editor)
    const firstToolbar = document.querySelector('.ql-toolbar');
    if (firstToolbar && !firstToolbar.querySelector('.oes-lang-btn')) {
        const wrap = document.createElement('span');
        wrap.className = 'ql-formats';
        wrap.innerHTML = '<button class="oes-lang-btn" id="lang-toggle-btn" type="button" title="Switch OCR Language (EN → HI → GU)">🇬🇧 EN</button>';
        firstToolbar.appendChild(wrap);
        document.getElementById('lang-toggle-btn').addEventListener('click', cycleLang);
    }
}

// ---- Question Type & Marks Logic ----
function updateQuestionTypeUI() {
    const select = document.getElementById('question_type_select');
    const bulkSection = document.getElementById('descriptive_bulk_section');
    const realIdInput = document.getElementById('real_question_type_id');
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption || selectedOption.value === '') {
        document.getElementById('mcq_section').style.display = 'none';
        bulkSection.style.display = 'none';
        document.getElementById('main-editor-card').style.display = 'block';
        document.getElementById('solution-card').style.display = 'block';
        document.getElementById('question-body-label').textContent = 'Question Body';
        realIdInput.value = '';
        return;
    }

    const typeName = (selectedOption.getAttribute('data-type') || selectedOption.text.trim()).toLowerCase();

    if (typeName === 'mcq') {
        document.getElementById('mcq_section').style.setProperty('display', 'block', 'important');
        bulkSection.style.display = 'none';
        document.getElementById('main-editor-card').style.display = 'block';
        document.getElementById('solution-card').style.display = 'block';
        document.getElementById('question-body-label').textContent = 'Question Body';
        realIdInput.value = '1'; 
        const marks = selectedOption.getAttribute('data-marks');
        const neg = selectedOption.getAttribute('data-neg');
        if (marks !== null) document.getElementById('marks_input').value = marks;
        if (neg !== null) document.getElementById('negative_marks_input').value = neg;
    } else if (typeName === 'descriptive') {
        document.getElementById('mcq_section').style.display = 'none';
        bulkSection.style.display = 'block';
        document.getElementById('main-editor-card').style.display = 'none';
        document.getElementById('solution-card').style.display = 'none';
        document.getElementById('question-body-label').textContent = 'Question (Descriptive Mode)';
        realIdInput.value = 'descriptive';
    }
}

// ---- AJAX Cascading Dropdowns ----
async function updateSubjects() {
    const standardId = document.getElementById('standard_id_select').value;
    const subjectSelect = document.getElementById('subject_id_select');
    const currentSubjectId = subjectSelect.value;
    subjectSelect.innerHTML = '<option value="">Loading Subjects...</option>';

    try {
        const response = await fetch(`ajax/get-subjects.php?standard_id=${standardId}`);
        const data = await response.json();
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        data.forEach(sub => {
            const option = document.createElement('option');
            option.value = sub.id;
            option.textContent = sub.subject_name;
            if (sub.id == currentSubjectId) option.selected = true;
            subjectSelect.appendChild(option);
        });
        await updateChapters();
    } catch (err) {
        console.error('Error fetching subjects:', err);
        subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        await updateChapters();
    }
}

async function updateChapters() {
    const subjectId = document.getElementById('subject_id_select').value;
    const chapterSelect = document.getElementById('chapter_id_select');
    const topicSelect = document.getElementById('topic_id_select');

    chapterSelect.innerHTML = '<option value="">Loading Chapters...</option>';
    topicSelect.innerHTML = '<option value="">Select Chapter First</option>';

    if (!subjectId) {
        chapterSelect.innerHTML = '<option value="">Select Subject First</option>';
        return;
    }

    try {
        const response = await fetch(`ajax/get-chapters.php?subject_id=${subjectId}`);
        const data = await response.json();
        chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
        data.forEach(ch => {
            const option = document.createElement('option');
            option.value = ch.chpid;
            option.textContent = ch.chapter;
            chapterSelect.appendChild(option);
        });
    } catch (err) {
        console.error('Error fetching chapters:', err);
        chapterSelect.innerHTML = '<option value="">Error loading chapters</option>';
    }
}

async function updateTopics() {
    const chapterId = document.getElementById('chapter_id_select').value;
    const topicSelect = document.getElementById('topic_id_select');
    topicSelect.innerHTML = '<option value="">Loading Topics...</option>';

    if (!chapterId) {
        topicSelect.innerHTML = '<option value="">Select Chapter First</option>';
        return;
    }

    try {
        const response = await fetch(`ajax/get-topics.php?chapter_id=${chapterId}`);
        const data = await response.json();
        topicSelect.innerHTML = '<option value="">Select Topic</option>';
        data.forEach(t => {
            const option = document.createElement('option');
            option.value = t.id;
            option.textContent = t.topic_name;
            topicSelect.appendChild(option);
        });
    } catch (err) {
        console.error('Error fetching topics:', err);
        topicSelect.innerHTML = '<option value="">Error loading topics</option>';
    }
}

// ---- Math / Draw / Chem Modal Logics ----
function initModalHandlers() {
    // Math
    const mathField = document.getElementById('math-input');
    const confirmMathBtn = document.getElementById('confirm-math-btn');
    if (confirmMathBtn) {
        confirmMathBtn.addEventListener('click', () => {
            let latex = mathField.value;
            if (latex && activeEditor && currentRange) {
                // Clean degree characters (e.g., ° or ^° or other lookalikes) to standard LaTeX ^\circ for KaTeX compatibility
                latex = latex.replace(/\^?[°º˚◦]/g, '^\\circ');
                
                activeEditor.insertEmbed(currentRange.index, 'formula', latex);
                activeEditor.setSelection(currentRange.index + 1);
                closeModal('mathModal');
                mathField.value = '';
            }
        });
    }

    // Drawing (Fabric.js)
    const drawCanvas = document.getElementById('drawing-canvas');
    if (drawCanvas && typeof fabric !== 'undefined') {
        canvas = new fabric.Canvas('drawing-canvas', {
            isDrawingMode: true,
            width: 850,
            height: 400
        });
        canvas.freeDrawingBrush.width = 3;
        canvas.freeDrawingBrush.color = '#000000';
        clearCanvas();
    }

    const confirmDrawBtn = document.getElementById('confirm-draw-btn');
    if (confirmDrawBtn) {
        confirmDrawBtn.addEventListener('click', () => {
            const dataUrl = canvas.toDataURL({ format: 'png', multiplier: 2 });
            if (activeEditor && currentRange) {
                activeEditor.insertEmbed(currentRange.index, 'image', dataUrl);
                activeEditor.setSelection(currentRange.index + 1);
                closeModal('drawModal');
                clearCanvas();
            }
        });
    }

    // Ketcher (Chem)
    const confirmChemBtn = document.getElementById('confirm-chem-btn');
    if (confirmChemBtn) {
        confirmChemBtn.addEventListener('click', async () => {
            console.log('[OES] Confirm Chem Clicked');
            const ketcherFrame = document.getElementById('ketcher-frame');
            if (!ketcherFrame) { console.error('Ketcher frame not found'); return; }

            try {
                const ketcher = ketcherFrame.contentWindow.ketcher;
                if (!ketcher) {
                    alert("Ketcher is still loading or not available. Please wait a moment.");
                    return;
                }

                if (!activeEditor) activeEditor = editors.main;
                if (!currentRange) {
                    currentRange = { index: activeEditor.getLength() - 1, length: 0 };
                }

                console.log('[OES] Requesting Molfile from Ketcher...');
                const molfile = await ketcher.getMolfile();
                if (!molfile || molfile.trim().length < 10) {
                    alert("Please draw something first.");
                    return;
                }

                console.log('[OES] Generating PNG image from Ketcher...');
                const imageData = await ketcher.generateImage(molfile, { outputFormat: 'png' });
                
                const img = new Image();
                img.onload = function () {
                    console.log('[OES] Image Loaded Successfully (Size:', img.width, 'x', img.height, ')');
                    const tempCanvas = document.createElement('canvas');
                    const scale = 2;
                    tempCanvas.width = img.width * scale;
                    tempCanvas.height = img.height * scale;
                    
                    const ctx = tempCanvas.getContext('2d');
                    ctx.fillStyle = "white";
                    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                    ctx.drawImage(img, 0, 0, tempCanvas.width, tempCanvas.height);
                    
                    const finalDataUrl = tempCanvas.toDataURL("image/png");
                    activeEditor.insertEmbed(currentRange.index, 'image', finalDataUrl);
                    activeEditor.setSelection(currentRange.index + 1);
                    
                    closeModal('chemModal');
                };

                img.onerror = (e) => { 
                    console.error('[OES] Image Load Error. Data type:', typeof imageData);
                    if (typeof imageData === 'string') console.error('[OES] Data start:', imageData.substring(0, 100));
                    alert("Failed to process molecule image. The format returned by Ketcher was not recognized."); 
                };
                
                // Handle different return types (Blob vs String vs DataURL)
                let handled = false;
                if (imageData && (imageData instanceof Blob || (imageData.constructor && imageData.constructor.name === 'Blob'))) {
                    // Use FileReader instead of createObjectURL to bypass "blob:" CSP restriction
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(imageData);
                    handled = true;
                } else if (typeof imageData === 'string') {
                    if (imageData.startsWith('data:image')) {
                        img.src = imageData;
                    } else if (imageData.includes('<svg')) {
                        img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(imageData)));
                    } else {
                        img.src = imageData;
                    }
                    handled = true;
                }

                if (!handled) {
                    const typeInfo = typeof imageData;
                    const constructorInfo = imageData && imageData.constructor ? imageData.constructor.name : 'N/A';
                    console.error('[OES] Unknown Ketcher format:', typeInfo, constructorInfo, imageData);
                    alert("Ketcher returned an unknown format: " + typeInfo + " (" + constructorInfo + "). Please try again or draw a simpler molecule.");
                }
                
            } catch (err) {
                console.error('[OES] Ketcher Error:', err);
                alert("Molecule capture failed. Error: " + err.message);
            }
        });
    }
}

function clearCanvas() {
    if (canvas) {
        canvas.clear();
        canvas.setBackgroundColor('#ffffff', canvas.renderAll.bind(canvas));
    }
}

// ---- Preview & Print ----
function getQuestionData() {
    const typeSelect = document.getElementById('question_type_select');
    const selectedType = typeSelect.options[typeSelect.selectedIndex].getAttribute('data-type') || 'mcq';

    let data = {
        type: selectedType,
        question_text: editors.main.root.innerHTML,
        explanation: editors.explanation.root.innerHTML,
        difficulty: document.getElementsByName('difficulty')[0].value,
        subject: document.getElementsByName('subject_id')[0].options[document.getElementsByName('subject_id')[0].selectedIndex].text
    };

    if (selectedType === 'mcq') {
        data.options = {
            A: editors.a.root.innerHTML,
            B: editors.b.root.innerHTML,
            C: editors.c.root.innerHTML,
            D: editors.d.root.innerHTML
        };
        data.correct_answer = document.getElementsByName('correct_option')[0].value;
    } else {
        data.is_descriptive = true;
        data.questions = [];
        for (let i = 1; i <= 5; i++) {
            data.questions.push({
                marks: i,
                text: editors['desc-q' + i].root.innerHTML,
                solution: editors['desc-s' + i].root.innerHTML
            });
        }
    }
    return data;
}

function renderQuestionHTML(data, isForPrint = false) {
    let html = `
        <div class="preview-card" style="${isForPrint ? 'padding: 20px;' : ''}">
            <div class="d-flex justify-content-between mb-3 small text-muted">
                <span>Subject: <strong>${data.subject}</strong></span>
                <span>Difficulty: <strong>${data.difficulty}</strong></span>
            </div>
            <div class="question-text mb-4" style="font-size: 1.1rem; line-height: 1.6;">
                ${data.question_text}
            </div>
    `;

    if (data.type === 'mcq') {
        html += `<div class="options-container row g-3">`;
        for (const [key, value] of Object.entries(data.options)) {
            const isCorrect = key === data.correct_answer;
            html += `
                <div class="col-md-6 mb-3">
                    <div class="p-3 border rounded ${isCorrect && !isForPrint ? 'border-success bg-light' : ''}" style="position: relative;">
                        <span class="badge ${isCorrect ? 'bg-success' : 'bg-secondary'} mr-2" style="position: absolute; left: -10px; top: -10px;">Option ${key}</span>
                        <div class="mt-2">${value}</div>
                    </div>
                </div>
            `;
        }
        html += `</div>`;
    } else if (data.is_descriptive) {
        html += `<div class="descriptive-container">`;
        data.questions.forEach(q => {
            if (q.text.trim() !== '<p><br></p>') {
                html += `
                    <div class="mb-4 p-3 border-left border-info bg-light rounded shadow-sm">
                        <h6 class="font-weight-bold text-info">${q.marks} Mark Question</h6>
                        <div class="mb-2">${q.text}</div>
                        <hr>
                        <div class="small text-muted"><strong>Solution:</strong> ${q.solution}</div>
                    </div>
                `;
            }
        });
        html += `</div>`;
    }

    if (data.explanation && data.explanation.trim() !== '<p><br></p>') {
        html += `
            <div class="mt-4 p-3 border-top bg-light rounded">
                <h6 class="font-weight-bold text-primary"><i class="fas fa-info-circle mr-2"></i> Explanation / Solution</h6>
                <div class="small">${data.explanation}</div>
            </div>
        `;
    }

    html += `</div>`;
    return html;
}

function previewQuestion() {
    const data = getQuestionData();
    const html = renderQuestionHTML(data);
    document.getElementById('preview-content').innerHTML = html;
    openModal('previewModal');
}

function printQuestion() {
    const data = getQuestionData();
    const html = renderQuestionHTML(data, true);
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print Question</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    body { padding: 40px; font-family: 'Inter', sans-serif; }
                    .preview-card { max-width: 800px; margin: 0 auto; }
                    @media print { .no-print { display: none; } }
                </style>
            </head>
            <body>
                <div class="no-print mb-4 text-center">
                    <button onclick="window.print()" class="btn btn-primary px-4 py-2">Confirm Print</button>
                    <p class="text-muted small mt-2">Select "Save as PDF" in the print destination to download as PDF.</p>
                </div>
                ${html}
            </body>
        </html>
    `);
    printWindow.document.close();
}

// ---- OCR & Import Logic ----
function triggerOCR() { document.getElementById('ocr-input').click(); }
function triggerDocImport() { document.getElementById('doc-import-input').click(); }

function initFileHandlers() {
    const ocrInput = document.getElementById('ocr-input');
    const ocrPreviewImg = document.getElementById('ocr-preview-img');
    const docImportInput = document.getElementById('doc-import-input');

    if (ocrInput) {
        ocrInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (event) => {
                ocrPreviewImg.src = event.target.result;
                openModal('ocrModal');
                if (cropper) cropper.destroy();
                cropper = new Cropper(ocrPreviewImg, { viewMode: 1, autoCropArea: 0.8 });
            };
            reader.readAsDataURL(file);
        });
    }

    document.getElementById('start-ocr-btn').addEventListener('click', async () => {
        if (!cropper) return;
        const overlay = document.getElementById('ocr-processing-overlay');
        overlay.classList.add('active');

        const rawCanvas = cropper.getCroppedCanvas({ width: 1200 });
        const processedCanvas = preprocessForOCR(rawCanvas);
        const processedImage = processedCanvas.toDataURL('image/png');

        const loadingIndex = currentRange ? currentRange.index : 0;
        activeEditor.insertText(loadingIndex, "[Scanning Text...]", { italic: true, color: 'blue' });

        const ocrLang = LANG_CONFIG[currentLang].ocr;
        try {
            const result = await Tesseract.recognize(processedImage, ocrLang);
            activeEditor.deleteText(loadingIndex, 17);
            activeEditor.insertText(loadingIndex, result.data.text.trim());
            closeModal('ocrModal');
        } catch (err) {
            activeEditor.deleteText(loadingIndex, 17);
            alert('Scan failed: ' + err.message);
        } finally {
            overlay.classList.remove('active');
            ocrInput.value = '';
        }
    });

    if (docImportInput) {
        docImportInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (event) => {
                const arrayBuffer = event.target.result;
                const fileName = file.name.toLowerCase();
                const loadingIndex = currentRange ? currentRange.index : 0;
                activeEditor.insertText(loadingIndex, "[Importing...]", { italic: true, color: 'blue' });

                try {
                    if (fileName.endsWith('.docx')) {
                        const result = await mammoth.convertToHtml({ arrayBuffer });
                        activeEditor.deleteText(loadingIndex, 14);
                        activeEditor.clipboard.dangerouslyPasteHTML(loadingIndex, result.value);
                    } else if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls') || fileName.endsWith('.csv')) {
                        const workbook = XLSX.read(arrayBuffer, { type: 'array' });
                        const htmlString = XLSX.utils.sheet_to_html(workbook.Sheets[workbook.SheetNames[0]]);
                        activeEditor.deleteText(loadingIndex, 14);
                        activeEditor.clipboard.dangerouslyPasteHTML(loadingIndex, htmlString);
                    }
                } catch (err) {
                    activeEditor.deleteText(loadingIndex, 14);
                    alert("Import failed.");
                }
                docImportInput.value = "";
            };
            reader.readAsArrayBuffer(file);
        });
    }
}

function preprocessForOCR(src) {
    const out = document.createElement('canvas');
    out.width = src.width; out.height = src.height;
    const ctx = out.getContext('2d');
    ctx.drawImage(src, 0, 0);
    const imgData = ctx.getImageData(0, 0, out.width, out.height);
    const d = imgData.data;
    let totalLum = 0;
    for (let i = 0; i < d.length; i += 4) {
        const lum = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
        totalLum += lum;
    }
    const avgLum = totalLum / (d.length / 4);
    const invert = avgLum < 100;
    for (let i = 0; i < d.length; i += 4) {
        let lum = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
        if (invert) lum = 255 - lum;
        const val = lum > 128 ? 255 : 0;
        d[i] = d[i+1] = d[i+2] = val;
    }
    ctx.putImageData(imgData, 0, 0);
    return out;
}

// ---- Main Initialization Function ----
function initOesEditor(initialData = null) {
    console.log('[OES] Initializing Editor...');
    
    // 1. ATTACH SUBMIT HANDLER FIRST (CRITICAL FOR DATA PERSISTENCE)
    const form = document.getElementById('question-form');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault(); // Pause form submission for robust async diagnostic logging
            console.log('[OES] Form submit triggered. Syncing editors...');
            
            // Fail-safe: Ensure correct question_type_id hidden input value based on select and visibility
            const typeSelect = document.getElementById('question_type_select');
            const realIdInput = document.getElementById('real_question_type_id');
            const mcqSection = document.getElementById('mcq_section');
            
            if (realIdInput) {
                if (typeSelect && typeSelect.value === '1') {
                    realIdInput.value = '1';
                } else if (mcqSection && window.getComputedStyle(mcqSection).display !== 'none') {
                    realIdInput.value = '1';
                } else if (typeSelect && typeSelect.value === 'descriptive') {
                    realIdInput.value = 'descriptive';
                }
                console.log('[OES Submit] Determined question_type_id hidden value:', realIdInput.value);
            }

            // Sync editors one-by-one with individual try-catch blocks to prevent cascading failures
            const syncEditor = (eid, targetInputId) => {
                try {
                    const targetInput = document.getElementById(targetInputId);
                    if (editors[eid] && targetInput) {
                        targetInput.value = editors[eid].root.innerHTML;
                        console.log(`[OES Submit] Synced ${eid} -> ${targetInputId}`);
                    }
                } catch (err) {
                    console.error(`[OES Submit] Failed to sync editor '${eid}':`, err);
                }
            };

            syncEditor('main', 'question_text');
            syncEditor('a', 'option_a');
            syncEditor('b', 'option_b');
            syncEditor('c', 'option_c');
            syncEditor('d', 'option_d');
            syncEditor('explanation', 'explanation');

            for (let i = 1; i <= 5; i++) {
                syncEditor(`desc-q${i}`, `desc_question_${i}`);
                syncEditor(`desc-s${i}`, `desc_solution_${i}`);
            }

            // Debug: Log client-side HTML state immediately on submit and wait for network completion
            try {
                const submitDebugData = {};
                const textareaValues = {};
                const idsToLog = [
                    'main', 'a', 'b', 'c', 'd', 'explanation',
                    'desc-q1', 'desc-s1', 'desc-q2', 'desc-s2',
                    'desc-q3', 'desc-s3', 'desc-q4', 'desc-s4',
                    'desc-q5', 'desc-s5'
                ];
                
                idsToLog.forEach(eid => {
                    if (editors[eid]) {
                        submitDebugData[eid] = editors[eid].root.innerHTML;
                        const targetId = eid === 'main' ? 'question_text' :
                                         eid === 'explanation' ? 'explanation' :
                                         eid.startsWith('desc-q') ? `desc_question_${eid.substring(6)}` :
                                         eid.startsWith('desc-s') ? `desc_solution_${eid.substring(6)}` :
                                         `option_${eid}`;
                        const inputEl = document.getElementById(targetId);
                        if (inputEl) {
                            textareaValues[targetId] = inputEl.value;
                        }
                    }
                });

                await fetch('ajax/log-editor-debug.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        event: 'FORM_SUBMIT_SYNC', 
                        data: {
                            editors: submitDebugData,
                            textareas: textareaValues
                        }
                    })
                });
            } catch (err) {
                console.error('Debug logging error:', err);
            }

            console.log('[OES] Sync completed successfully. Submitting form...');
            form.submit(); // Resume form submission programmatically
        });
    }

    // 2. PROCEED WITH UI INITIALIZATION
    try {
        configureQuill();
        
        const editorIds = [
            'main', 'a', 'b', 'c', 'd', 'explanation',
            'desc-q1', 'desc-s1', 'desc-q2', 'desc-s2',
            'desc-q3', 'desc-s3', 'desc-q4', 'desc-s4',
            'desc-q5', 'desc-s5'
        ];
        
        const toolbarOptions = [
            [{ 'font': ['', 'serif', 'monospace', 'outfit', 'roboto', 'inter'] }],
            [{ 'size': ['8px', '10px', '12px', '14px', '16px', '18px', '20px', '24px', '30px', '36px', '48px', '60px', '72px'] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'script': 'sub' }, { 'script': 'super' }],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            ['link', 'image', 'formula'],
            ['math', 'chem', 'draw', 'ocr', 'import'],
            ['table', 'table-insert-row', 'table-insert-column', 'table-delete-row', 'table-delete-column'],
            ['clean']
        ];

        editorIds.forEach(id => {
            const el = document.querySelector(`#editor-${id}`);
            if (!el) return;

            editors[id] = new Quill(`#editor-${id}`, {
                theme: 'snow',
                modules: {
                    table: true,
                    imageResize: {},
                    toolbar: {
                        container: toolbarOptions,
                        handlers: {
                            'math': () => openModal('mathModal'),
                            'chem': () => openModal('chemModal'),
                            'draw': () => openModal('drawModal'),
                            'ocr': () => triggerOCR(),
                            'import': () => triggerDocImport(),
                            'formula': () => openModal('mathModal'),
                            'table': function () { this.quill.getModule('table').insertTable(2, 3); },
                            'table-insert-row': function () { this.quill.getModule('table').insertRowBelow(); },
                            'table-insert-column': function () { this.quill.getModule('table').insertColumnRight(); },
                            'table-delete-row': function () { this.quill.getModule('table').deleteRow(); },
                            'table-delete-column': function () { this.quill.getModule('table').deleteColumn(); }
                        }
                    }
                },
                placeholder: `Type ${id === 'main' ? 'question' : id} here...`
            });

            // Custom image matcher to prevent Quill from stripping local relative/absolute image paths
            const customImageMatcher = (node, delta) => {
                const src = node.getAttribute('src');
                if (src) {
                    const cleanSrc = src.trim();
                    if (!cleanSrc.toLowerCase().startsWith('javascript:')) {
                        const Delta = Quill.import('delta') || Quill.import('core/delta');
                        const attributes = {};
                        if (node.getAttribute('width')) attributes.width = node.getAttribute('width');
                        if (node.getAttribute('height')) attributes.height = node.getAttribute('height');
                        if (node.getAttribute('style')) attributes.style = node.getAttribute('style');
                        
                        return new Delta().insert({ image: cleanSrc }, attributes);
                    }
                }
                return delta;
            };
            editors[id].clipboard.addMatcher('IMG', customImageMatcher);
            editors[id].clipboard.addMatcher('img', customImageMatcher);

            // Function to sync this specific editor to its hidden input/textarea
            const syncEditorToInput = () => {
                const targetId = id === 'main' ? 'question_text' :
                                 id === 'explanation' ? 'explanation' :
                                 id.startsWith('desc-q') ? `desc_question_${id.substring(6)}` :
                                 id.startsWith('desc-s') ? `desc_solution_${id.substring(6)}` :
                                 `option_${id}`;
                const targetInput = document.getElementById(targetId);
                if (targetInput && editors[id]) {
                    targetInput.value = editors[id].root.innerHTML;
                }
            };

            // Pre-fill if data provided
            if (initialData && initialData[id]) {
                editors[id].clipboard.dangerouslyPasteHTML(initialData[id]);
            }
            
            // Sync immediately on load so the hidden textareas have values
            syncEditorToInput();

            // Real-time synchronization on every keystroke/change (Foolproof!)
            editors[id].on('text-change', syncEditorToInput);

            editors[id].on('selection-change', (range) => {
                if (range) {
                    activeEditor = editors[id];
                    currentRange = range;
                }
            });
        });

        activeEditor = editors.main;
        
        injectIcons();
        initModalHandlers();
        initFileHandlers();

        // Move OES modals to body for better responsiveness/z-index
        document.querySelectorAll('.oes-modal').forEach(m => document.body.appendChild(m));
        
        // Global Event Listeners
        const qTypeSelect = document.getElementById('question_type_select');
        if (qTypeSelect) qTypeSelect.addEventListener('change', updateQuestionTypeUI);
        
        const stdSelect = document.getElementById('standard_id_select');
        if (stdSelect) stdSelect.addEventListener('change', updateSubjects);
        
        const subSelect = document.getElementById('subject_id_select');
        if (subSelect) subSelect.addEventListener('change', updateChapters);
        
        const chSelect = document.getElementById('chapter_id_select');
        if (chSelect) chSelect.addEventListener('change', updateTopics);
        
        // Fill basic metadata if provided
        if (initialData) {
            const diffSelect = document.getElementsByName('difficulty')[0];
            if (diffSelect && initialData.difficulty) diffSelect.value = initialData.difficulty;

            const marksInput = document.getElementById('marks_input');
            if (marksInput && initialData.marks) marksInput.value = initialData.marks;

            const negInput = document.getElementById('negative_marks_input');
            if (negInput && initialData.negative_marks) negInput.value = initialData.negative_marks;

            const videoInput = document.getElementsByName('video_solution_url')[0];
            if (videoInput && initialData.video_solution_url) videoInput.value = initialData.video_solution_url;
            
            const correctSel = document.getElementsByName('correct_option')[0];
            if (correctSel && initialData.correct_option) correctSel.value = initialData.correct_option;
        }
        
        // Debug: Log client-side HTML state immediately on load
        try {
            const loadDebugData = {};
            editorIds.forEach(eid => {
                if (editors[eid]) loadDebugData[eid] = editors[eid].root.innerHTML;
            });
            fetch('ajax/log-editor-debug.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event: 'INITIAL_LOAD_COMPLETE', data: loadDebugData })
            });
        } catch (e) {
            console.error('Debug logging error:', e);
        }
        
        console.log('[OES] Editor ready.');
    } catch (err) {
        console.error('[OES] Initialization Error:', err);
    }
}
