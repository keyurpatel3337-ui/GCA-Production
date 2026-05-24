<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$page_title = "Manage Exams | OES";

$f_std = isset($_GET['standard_id']) ? (int)$_GET['standard_id'] : 0;
$f_sub = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    $conn->beginTransaction();
    try {
        $conn->prepare("DELETE FROM tbl_oes_exam_questions WHERE exam_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM tbl_oes_exams WHERE id = ?")->execute([$id]);
        $conn->commit();
        header("Location: manage-exams.php?msg=deleted");
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: manage-exams.php?msg=error");
    }
    exit();
}

// Fetch Exams with Filtering
$where = " WHERE 1=1 ";
$params = [];

if ($f_std > 0) {
    $where .= " AND e.standard_id = ? ";
    $params[] = $f_std;
}

if ($f_sub > 0) {
    $where .= " AND EXISTS (
        SELECT 1 FROM tbl_oes_exam_questions eq 
        JOIN tbl_oes_questions q ON eq.question_id = q.id 
        WHERE eq.exam_id = e.id AND q.subject_id = ?
    ) ";
    $params[] = $f_sub;
}

$query = "SELECT e.*, s.stdtext 
          FROM tbl_oes_exams e 
          LEFT JOIN standard s ON e.standard_id = s.stdid 
          $where
          ORDER BY e.created_at ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Standards for filter
$standards = $conn->query("SELECT stdid, stdtext FROM standard ORDER BY stdtext ASC")->fetchAll(PDO::FETCH_ASSOC);

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            
            <!-- Filters -->
            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <form method="GET" class="row align-items-end g-3">
                        <div class="col-md-4">
                            <label class="small font-weight-bold text-muted mb-2">Filter by Standard</label>
                            <select name="standard_id" id="filter_standard" class="form-control" style="border-radius: 10px;" onchange="loadSubjects(this.value)">
                                <option value="">-- All Standards --</option>
                                <?php foreach($standards as $std): ?>
                                    <option value="<?= $std['stdid'] ?>" <?= $f_std == $std['stdid'] ? 'selected' : '' ?>><?= htmlspecialchars($std['stdtext']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold text-muted mb-2">Filter by Subject</label>
                            <select name="subject_id" id="filter_subject" class="form-control" style="border-radius: 10px;">
                                <option value="">-- All Subjects --</option>
                                <!-- Populated via AJAX -->
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-dark px-4 flex-grow-1" style="border-radius: 10px;">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                            <a href="manage-exams.php" class="btn btn-light px-3 border" style="border-radius: 10px;" title="Reset">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
                <i class="fas fa-check-circle mr-2"></i> Exam deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'exam_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px; background-color: #d1e7dd; color: #0f5132;">
                <i class="fas fa-check-circle mr-2"></i> Exam updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'exam_created'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px; background-color: #d1e7dd; color: #0f5132;">
                <i class="fas fa-check-circle mr-2"></i> Exam created successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_GET['msg'])): ?>
            <script>
                // Instantly clean msg parameter from URL address bar
                if (window.history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace(/[?&]msg=[^&]+/, '').replace(/^&/, '?');
                    window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
                }
            </script>
            <?php endif; ?>

            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-alt mr-2 text-primary"></i> Generated Question Papers</h5>
                    <a href="exam-setup.php" class="btn btn-primary shadow-sm px-4" style="border-radius: 12px; font-weight: 600;">
                        <i class="fas fa-plus mr-2" style="font-size: 0.75rem;"></i> Create New Exam
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 border-0 rounded-start">Exam Title</th>
                                    <th class="py-3 border-0">Standard</th>
                                    <th class="py-3 border-0">Marks / Time</th>
                                    <th class="py-3 border-0">Schedule</th>
                                    <th class="py-3 border-0">Status</th>
                                    <th class="pe-4 py-3 border-0 rounded-end text-end">Downloads</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($exams)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-file-invoice fa-3x mb-3 text-light"></i>
                                            <h5>No Exams Found</h5>
                                            <p class="small">Start by creating an exam or generating one from a template.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($exams as $e): ?>
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3">
                                            <div class="font-weight-bold text-dark"><?= htmlspecialchars($e['title']) ?></div>
                                            <div class="small text-muted"><?= date('d M Y, h:i A', strtotime($e['created_at'])) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($e['stdtext'] ?: 'N/A') ?></span></td>
                                        <td>
                                            <div class="font-weight-bold"><?= $e['total_marks'] ?> Marks</div>
                                            <div class="small text-muted"><?= $e['duration_mins'] ?> Mins</div>
                                        </td>
                                        <td>
                                            <div class="small"><b>Start:</b> <?= date('d M, h:i A', strtotime($e['start_time'])) ?></div>
                                            <div class="small text-muted"><b>End:</b> <?= date('d M, h:i A', strtotime($e['end_time'])) ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $now = time();
                                            $start = strtotime($e['start_time']);
                                            $end = strtotime($e['end_time']);
                                            
                                            if ($now < $start) echo '<span class="badge bg-warning text-dark">Scheduled</span>';
                                            elseif ($now > $end) echo '<span class="badge bg-secondary">Expired</span>';
                                            else echo '<span class="badge bg-success">Live</span>';
                                            ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="btn-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                                <a href="view-student-attempts.php?exam_id=<?= $e['id'] ?>" class="btn btn-sm btn-white border-right text-info font-weight-bold" title="View Results">
                                                    <i class="fas fa-poll"></i> Results
                                                </a>
                                                <a href="export-pdf-paper.php?exam_id=<?= $e['id'] ?>" target="_blank" class="btn btn-sm btn-white border-right" title="Print PDF">
                                                    <i class="fas fa-file-pdf text-danger"></i> PDF
                                                </a>
                                                <a href="export-word-paper.php?exam_id=<?= $e['id'] ?>" class="btn btn-sm btn-white border-right" title="Download Word">
                                                    <i class="fas fa-file-word text-primary"></i> Word
                                                </a>
                                                <a href="edit-exam.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-white border-right text-warning" title="Edit Exam">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-white text-danger" onclick="deleteExam(<?= $e['id'] ?>)" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function deleteExam(id) {
    if (confirm('Are you sure you want to delete this exam and its question paper?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

async function loadSubjects(standardId) {
    const subjectSelect = document.getElementById('filter_subject');
    const currentSubjectId = "<?= $f_sub ?>";
    
    subjectSelect.innerHTML = '<option value="">-- Loading... --</option>';
    
    try {
        const response = await fetch(`ajax/get-subjects.php?standard_id=${standardId}`);
        const subjects = await response.json();
        
        subjectSelect.innerHTML = '<option value="">-- All Subjects --</option>';
        subjects.forEach(sub => {
            const selected = sub.id == currentSubjectId ? 'selected' : '';
            subjectSelect.innerHTML += `<option value="${sub.id}" ${selected}>${sub.subject_name}</option>`;
        });
    } catch (error) {
        console.error('Error fetching subjects:', error);
        subjectSelect.innerHTML = '<option value="">-- Error --</option>';
    }
}

// Initialize subjects on page load if standard is selected
document.addEventListener('DOMContentLoaded', () => {
    const stdId = document.getElementById('filter_standard').value;
    if (stdId) {
        loadSubjects(stdId);
    }
});
</script>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
