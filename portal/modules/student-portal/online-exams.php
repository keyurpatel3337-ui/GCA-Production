<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: " . PORTAL_URL . "/student-login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$page_title = "My Online Exams";

// Fetch student standard and division using registration ID
$std_stmt = $conn->prepare("
    SELECT r.course_id, r.standard as reg_standard_num, r.medium_id, r.group_id, e.division_id, s.stdid, s.stdnumber 
    FROM tbl_gm_std_registration r
    LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id AND e.is_active = 1
    LEFT JOIN standard s ON s.stdnumber = r.standard AND (
        (r.standard = 13)
        OR (r.medium_id = 1 AND s.stdtext LIKE '%Gujarati%')
        OR (r.medium_id = 2 AND s.stdtext LIKE '%English%')
    )
    WHERE r.id = ?
");
$std_stmt->execute([$student_id]);
$student = $std_stmt->fetch();

$course_id = $student ? ($student['course_id'] ?? 0) : 0;
$standard_id = $student ? ($student['stdid'] ?? 0) : 0;
$std_number = $student ? ($student['stdnumber'] ?? 0) : 0;
$group_id = $student ? ($student['group_id'] ?? null) : null;
$division_id = $student ? ($student['division_id'] ?? null) : null;

// Fetch all active subjects for the student's standard
$subj_stmt = $conn->prepare("
    SELECT id, subject_name 
    FROM tbl_subjects 
    WHERE standard_id = ? 
    AND activated = 1 
    AND is_deleted = 0 
    ORDER BY subject_name ASC
");
$subj_stmt->execute([$standard_id]);
$practice_subjects = $subj_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent self-practice tests
$sp_stmt = $conn->prepare("
    SELECT e.id, e.title, e.total_marks, e.created_at, se.status as attempt_status, se.total_score
    FROM tbl_oes_exams e
    JOIN tbl_oes_student_exams se ON e.id = se.exam_id
    WHERE e.student_id = ?
    ORDER BY se.start_timestamp DESC
    LIMIT 5
");
$sp_stmt->execute([$student_id]);
$self_practices = $sp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available official scheduled exams (excluding self-practice exams)
$sql = "SELECT e.*, 
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE (e.standard_id = ? OR e.standard_id IS NULL) 
        AND (e.group_id = ? OR e.group_id IS NULL)
        AND (e.division_id = ? OR e.division_id IS NULL)
        AND e.exam_mode = 'Practice'
        AND e.status IN ('Scheduled', 'Live')
        AND e.end_time >= NOW()
        AND e.student_id IS NULL
        ORDER BY e.start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id, $standard_id, $group_id, $division_id]);
$exams = $stmt->fetchAll();

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<div class="container-fluid py-4">
    <!-- Scoped Styling for Premium Web App Aesthetics -->
    <style>
        .self-practice-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .self-practice-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .self-practice-header {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;
            padding: 1.5rem;
            border: none;
        }
        .self-practice-header h4 {
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .form-select-custom, .form-input-custom {
            height: 48px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.95rem;
            font-weight: 500;
            color: #334155;
            padding: 0 1rem;
            transition: all 0.2s ease;
        }
        .form-select-custom:focus, .form-input-custom:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
            outline: none;
        }
        .btn-generate-test {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;
            color: white !important;
            height: 48px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.025em;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
            transition: all 0.2s ease;
        }
        .btn-generate-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 148, 136, 0.35);
        }
        .btn-generate-test:active {
            transform: translateY(0);
        }
        .history-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            background: #ffffff;
        }
        .history-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s ease;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-item:hover {
            background-color: #f8fafc;
        }
        .history-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
        }
        .history-meta {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 4px;
        }
        .official-exam-card {
            border: none;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            border-left: 5px solid #6366f1;
        }
        .official-exam-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.1);
        }
        .official-exam-card.live {
            border-left-color: #10b981;
        }
        .official-exam-card.upcoming {
            border-left-color: #f59e0b;
        }
        .exam-info-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .pulse-live {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 1.5s infinite;
        }
        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        .empty-state-card {
            text-align: center;
            padding: 4rem 2rem;
            background: #ffffff;
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
            color: #64748b;
        }
    </style>

    <!-- Header Section -->
    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h1 class="h2 font-weight-extrabold text-dark mb-1" style="font-family: 'Inter', sans-serif; font-weight: 800;">
                <i class="fas fa-graduation-cap text-teal mr-2" style="color: #0d9488;"></i>Online Practice Center
            </h1>
            <p class="text-muted mb-0">Harness your skills through dynamic practice tests and structured scheduled exams.</p>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- LEFT COLUMN: Practice Generator & History -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <!-- 1. Beautiful Self-Practice Generator Form -->
            <div class="card self-practice-card mb-4">
                <div class="card-header self-practice-header d-flex align-items-center">
                    <div class="mr-3 text-white">
                        <i class="fas fa-rocket fa-2x"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-white font-weight-bold">Self-Practice Builder</h4>
                        <small class="text-white-50">Generate custom practice tests instantly</small>
                    </div>
                </div>
                <div class="card-body p-4 bg-white">
                    <form id="selfPracticeForm">
                        <!-- Subject Field -->
                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold text-dark mb-1">Select Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" id="subject_id" class="form-control form-select-custom" required onchange="loadPracticeChapters(this.value)">
                                <option value="">-- Choose Subject --</option>
                                <?php foreach ($practice_subjects as $subj): ?>
                                    <option value="<?php echo $subj['id']; ?>"><?php echo htmlspecialchars($subj['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Chapter Field -->
                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold text-dark mb-1">Select Chapter (Optional)</label>
                            <select name="chapter_id" id="chapter_id" class="form-control form-select-custom" disabled>
                                <option value="">All Chapters (Select Subject First)</option>
                            </select>
                        </div>

                        <!-- Difficulty Field -->
                        <div class="form-group mb-3">
                            <label class="form-label font-weight-bold text-dark mb-1">Difficulty Level</label>
                            <select name="difficulty" id="difficulty" class="form-control form-select-custom">
                                <option value="">Any Difficulty</option>
                                <option value="A">Easy</option>
                                <option value="B">Medium (Level B)</option>
                                <option value="C">Medium (Level C)</option>
                                <option value="D">Medium (Level D)</option>
                                <option value="E">Hard</option>
                            </select>
                        </div>

                        <!-- Question Count Field -->
                        <div class="form-group mb-4">
                            <label class="form-label font-weight-bold text-dark mb-1">Number of Questions</label>
                            <select name="question_count" id="question_count" class="form-control form-select-custom">
                                <option value="5">5 Questions</option>
                                <option value="10" selected>10 Questions</option>
                                <option value="15">15 Questions</option>
                                <option value="20">20 Questions</option>
                                <option value="25">25 Questions</option>
                                <option value="30">30 Questions</option>
                            </select>
                        </div>

                        <!-- Action Button -->
                        <button type="submit" class="btn btn-generate-test btn-block d-flex align-items-center justify-content-center">
                            <i class="fas fa-magic mr-2 animate-bounce"></i> Create & Launch Practice Test
                        </button>
                    </form>
                </div>
            </div>

            <!-- 2. Recent Self-Practice Attempts History -->
            <div class="card history-card">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-history mr-2 text-teal" style="color: #0d9488;"></i>Recent Attempts
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($self_practices) > 0): ?>
                        <div class="history-list">
                            <?php foreach ($self_practices as $sp): ?>
                                <div class="history-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="history-title">
                                            <?php echo htmlspecialchars($sp['title']); ?>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($sp['attempt_status'] === 'Submitted'): ?>
                                                <span class="badge badge-success px-2 py-1" style="border-radius: 6px;">Completed</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning px-2 py-1 text-dark" style="border-radius: 6px;">Ongoing</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="history-meta mt-2">
                                        <span>
                                            <i class="far fa-calendar-alt mr-1 text-muted"></i>
                                            <?php echo date('M j, Y - h:i A', strtotime($sp['created_at'])); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-star mr-1 text-muted" style="color: #eab308 !important;"></i>
                                            <?php 
                                            if ($sp['attempt_status'] === 'Submitted') {
                                                echo '<b>' . $sp['total_score'] . '</b> / ' . $sp['total_marks'] . ' Marks';
                                            } else {
                                                echo $sp['total_marks'] . ' Marks';
                                            }
                                            ?>
                                        </span>
                                        <?php if ($sp['attempt_status'] !== 'Submitted'): ?>
                                            <a href="take-exam.php?id=<?php echo $sp['id']; ?>" class="btn btn-xs btn-outline-teal ml-auto" style="border-radius: 6px; padding: 2px 8px; font-size: 0.72rem; color: #0d9488; border-color: #0d9488;">
                                                Resume <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-folder-open fa-2x mb-2 text-light" style="color: #e2e8f0 !important;"></i><br>
                            <span class="small font-weight-bold text-slate-400">No recent practice attempts found.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Official Scheduled Practice Exams -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <h4 class="font-weight-bold text-dark mb-3" style="font-family: 'Inter', sans-serif; letter-spacing: -0.01em;">
                <i class="fas fa-calendar-alt text-primary mr-2" style="color: #6366f1 !important;"></i>Official Scheduled Exams
            </h4>

            <?php if (count($exams) > 0): ?>
                <?php foreach ($exams as $e): ?>
                    <?php 
                    $is_submitted = ($e['attempt_status'] === 'Submitted');
                    $is_live = (strtotime($e['start_time']) <= time() && strtotime($e['end_time']) >= time());
                    
                    if ($is_submitted) {
                        $card_class = '';
                        $status_label = 'Submitted';
                        $status_badge_class = 'badge-secondary';
                    } else if ($is_live) {
                        $card_class = 'live';
                        $status_label = '<span class="pulse-live mr-1"></span> LIVE NOW';
                        $status_badge_class = 'badge-success';
                    } else {
                        $card_class = 'upcoming';
                        $status_label = 'Upcoming';
                        $status_badge_class = 'badge-warning text-dark';
                    }
                    ?>
                    <div class="card official-exam-card <?php echo $card_class; ?>">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap: 10px;">
                                <div>
                                    <h5 class="font-weight-bold text-dark mb-1" style="font-size: 1.15rem;"><?php echo htmlspecialchars($e['title']); ?></h5>
                                    <p class="text-muted mb-0 small" style="max-width: 450px;"><?php echo htmlspecialchars($e['description'] ?: 'No instructions provided.'); ?></p>
                                </div>
                                <span class="badge <?php echo $status_badge_class; ?> px-3 py-2 font-weight-bold d-flex align-items-center" style="border-radius: 8px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.02em;">
                                    <?php echo $status_label; ?>
                                </span>
                            </div>

                            <hr class="my-3" style="border-color: #f1f5f9;">

                            <div class="row align-items-center">
                                <div class="col-md-8 col-sm-12 mb-3 mb-md-0">
                                    <div class="d-flex flex-wrap" style="gap: 15px;">
                                        <div class="exam-info-pill">
                                            <i class="far fa-clock"></i> <?php echo $e['duration_mins']; ?> Mins
                                        </div>
                                        <div class="exam-info-pill">
                                            <i class="fas fa-star" style="color: #eab308 !important;"></i> <?php echo $e['total_marks']; ?> Marks
                                        </div>
                                        <div class="exam-info-pill">
                                            <i class="far fa-calendar-alt text-primary"></i> 
                                            Starts: <?php echo date('M j, Y - h:i A', strtotime($e['start_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-12 text-md-right">
                                    <?php if ($e['attempt_status'] === 'Submitted'): ?>
                                        <button disabled class="btn btn-secondary btn-md font-weight-bold px-4" style="border-radius: 10px; opacity: 0.8;">
                                            <i class="fas fa-check-double mr-1"></i> Completed
                                        </button>
                                    <?php elseif ($is_live): ?>
                                        <a href="take-exam.php?id=<?php echo $e['id']; ?>" class="btn font-weight-bold px-4 py-2 text-white" style="border-radius: 10px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);">
                                            Start Exam <i class="fas fa-arrow-right ml-2"></i>
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="btn btn-light btn-md font-weight-bold px-4" style="border-radius: 10px; color: #94a3b8 !important;" title="Locked until exam start time">
                                            <i class="fas fa-lock mr-2"></i> Upcoming
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-card">
                    <i class="fas fa-calendar-times fa-3x mb-3 text-slate-300" style="color: #cbd5e1 !important;"></i>
                    <h5 class="font-weight-bold text-dark">No Active Scheduled Exams</h5>
                    <p class="text-muted mb-0 small">There are no official scheduled practice tests assigned to you at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SweetAlert2 & custom dynamic scripts -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function loadPracticeChapters(subjectId) {
    const chapterSelect = $('#chapter_id');
    chapterSelect.prop('disabled', true).html('<option value="">Loading chapters...</option>');
    
    if (!subjectId) {
        chapterSelect.html('<option value="">All Chapters (Select Subject First)</option>');
        return;
    }
    
    $.ajax({
        url: '../online-exam/ajax/get-chapters.php',
        type: 'GET',
        data: { subject_id: subjectId },
        dataType: 'json',
        success: function(data) {
            let html = '<option value="">All Chapters (Optional)</option>';
            if (data && data.length > 0) {
                data.forEach(function(ch) {
                    html += `<option value="${ch.chpid}">Chapter: ${ch.chapter}</option>`;
                });
                chapterSelect.prop('disabled', false);
            } else {
                html = '<option value="">No chapters found</option>';
            }
            chapterSelect.html(html);
        },
        error: function() {
            chapterSelect.html('<option value="">Error loading chapters</option>');
        }
    });
}

$(document).ready(function() {
    $('#selfPracticeForm').on('submit', function(e) {
        e.preventDefault();
        
        const subjectId = $('#subject_id').val();
        if(!subjectId) {
            Swal.fire({
                icon: 'error',
                title: 'Subject Required',
                text: 'Please select a valid subject first.'
            });
            return;
        }

        // Show a premium loading modal while compiling practice test questions
        Swal.fire({
            title: 'Generating Custom Test',
            text: 'Selecting premium random questions matching your filters...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = $(this).serialize();

        $.ajax({
            url: 'ajax/generate-self-practice.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Success! Show premium SweetAlert2 animation before launching
                    Swal.fire({
                        icon: 'success',
                        title: 'Test Created Successfully!',
                        text: 'Redirecting you to the secure fullscreen exam terminal...',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        window.location.href = 'take-exam.php?id=' + response.exam_id;
                    });
                } else {
                    // Show descriptive error message from the backend generator
                    Swal.fire({
                        icon: 'warning',
                        title: 'Generation Failed',
                        text: response.message || 'An unexpected error occurred during test generation.'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'System Error',
                    text: 'Unable to communicate with the exam server. Please check your connection and try again.'
                });
            }
        });
    });
});
</script>

<?php 
include PORTAL_INCLUDE_PATH . 'footer.php'; 
?>
