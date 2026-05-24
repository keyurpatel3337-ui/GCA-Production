<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Counsellor or Principle
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = "Create Counselling Session" ;
$page_breadcrumb = "Session -";

// DEBUG: Log POST data
error_log("=== CREATE SESSION DEBUG ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Session user_id: " . $user_id);

// Get student_id from POST only
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
error_log("Extracted student_id: " . $student_id);

// Initialize student variable
$student = null;

// Verify student is assigned to this counsellor
if ($student_id > 0 && !isset($_POST['session_date'])) {
    error_log("Loading student info for ID: " . $student_id);
    try {
        $op = new Operation();

        // Build WHERE clause based on user role
        $where = ['id' => $student_id];

        // Only counsellors need the counsellor_id filter
        if (hasRole(ROLE_COUNSELLOR)) {
            $where['counsellor_id'] = $user_id;
        }

        $student_result = $op->select(
            'tbl_gm_std_registration',
            ['id', 'surname', 'student_name', 'fathers_name', 'mob', 'counsellor_id'],
            $where
        );

        $student = !empty($student_result) ? $student_result[0] : null;
        error_log("Student found: " . ($student ? "YES" : "NO"));

        // Build full name
        if ($student) {
            $student['full_name'] = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name']);
            error_log("Student full name: " . $student['full_name']);
        }

        if (!$student) {
            error_log("Student not found - redirecting");
            set_flash_message('error', "Student not found or not assigned to you");
            header('Location: list.php');
            exit;
        } else {
            error_log("Student loaded successfully - will display form with student info");
        }
    } catch (Exception $e) {
        error_log("Exception loading student: " . $e->getMessage());
        logDatabaseError($e, "Fetch Student for Session");
        set_flash_message('error', "Error loading student information");
        header('Location: list.php');
        exit;
    }
} else {
    error_log("Condition not met - student_id: $student_id, has session_date: " . (isset($_POST['session_date']) ? 'YES' : 'NO'));
}

// Handle form submission (only if all required session fields are present)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['session_date']) && isset($_POST['session_notes'])) {
    $student_id = intval($_POST['student_id']);
    $session_date = trim($_POST['session_date']);
    $session_duration = intval($_POST['session_duration']);
    $session_topic = trim($_POST['session_topic'] ?? 'General');
    $session_notes = trim($_POST['session_notes']);
    $recommendations = trim($_POST['recommendations'] ?? '');
    $status = trim($_POST['status'] ?? 'completed');
    $follow_up_required = isset($_POST['follow_up_required']) ? 1 : 0;
    $follow_up_date = !empty($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : null;

    // Validate required fields
    if (empty($student_id) || empty($session_date) || empty($session_notes)) {
        set_flash_message('error', "Please fill all required fields");
    } else {
        try {
            $op = new Operation();

            // Verify student exists and is assigned (for counsellors only)
            $where = ['id' => $student_id];
            if (hasRole(ROLE_COUNSELLOR)) {
                $where['counsellor_id'] = $user_id;
            }
            $student_check = $op->selectOne('tbl_gm_std_registration', ['*'], $where);
            if (!$student_check) {
                set_flash_message('error', "Invalid student selection");
                header('Location: list.php');
                exit;
            }

            // Insert session record
            $op->insert('tbl_sessions', [
                'student_id' => $student_id,
                'counsellor_id' => $user_id,
                'session_date' => $session_date,
                'session_duration' => $session_duration,
                'session_topic' => $session_topic,
                'session_notes' => $session_notes,
                'recommendations' => $recommendations,
                'status' => $status,
                'follow_up_required' => $follow_up_required,
                'follow_up_date' => $follow_up_date,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            set_flash_message('success', "Counselling session recorded successfully!");
            header("Location: sessions.php");
            exit;
        } catch (Exception $e) {
            logDatabaseError($e, "Create Counselling Session");
            set_flash_message('error', "Error saving session: " . $e->getMessage());
        }
    }
}

// Get all students assigned to this counsellor for dropdown
try {
    $op = new Operation();
    $students = $op->select(
        'tbl_gm_std_registration',
        ['id', 'surname', 'student_name', 'fathers_name'],
        ['counsellor_id' => $user_id],
        'surname, student_name'
    );

    // Build full name for each student
    foreach ($students as &$stud) {
        $stud['full_name'] = trim($stud['surname'] . ' ' . $stud['student_name'] . ' ' . $stud['fathers_name']);
    }
    unset($stud);
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Assigned Students");
    $students = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                <i class="fas fa-exclamation-triangle"></i> <?php echo gca_safe_html($_SESSION['error_msg']);
                ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title text-uppercase fw-bold"><i class="fas fa-comments me-2"></i> Record
                            Counselling Session</h3>
                    </div>
                    <form method="POST" action="">
                        <div class="card-body">
                            <?php
                            error_log("Form render - student_id: " . $student_id);
                            error_log("Form render - isset student: " . (isset($student) ? 'YES' : 'NO'));
                            if (isset($student)) {
                                error_log("Form render - student data: " . print_r($student, true));
                            }
                            ?>
                            <?php if ($student_id > 0 && isset($student) && $student !== null): ?>
                                <!-- Display selected student (read-only) -->
                                <div class="form-group">
                                    <label>Student</label>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="mb-2">
                                                <i class="fas fa-user-circle text-primary"></i>
                                                <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                                            </h5>
                                            <p class="mb-0">
                                                <i class="fas fa-phone text-success"></i>
                                                <?php echo htmlspecialchars($student['mob'] ?? ''); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                </div>
                            <?php else: ?>
                                <!-- Show search field if no student_id in URL -->
                                <div class="form-group">
                                    <label for="student_id">Student <span class="text-danger">*</span></label>
                                    <div class="student-search-wrapper">
                                        <input type="text" id="student_search" class="form-control student-search-input"
                                            placeholder="Enter Student Name, ID or Mobile Number" autocomplete="off">
                                        <input type="hidden" name="student_id" id="student_id" required>
                                        <div id="student_search_results"></div>
                                    </div>
                                    <small class="form-text text-muted">Type at least 2 characters to search</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div id="student_details"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="session_date">Session Date <span
                                                class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="session_date" name="session_date"
                                            value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>"
                                            required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="session_duration">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="session_duration"
                                            name="session_duration" min="1" max="300" value="30" placeholder="30">
                                        <small class="form-text text-muted">Optional - Session duration in
                                            minutes</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="session_topic">Session Topic</label>
                                        <input type="text" class="form-control" id="session_topic" name="session_topic"
                                            placeholder="e.g., Career Guidance, Academic Support" value="General">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="session_notes">Session Notes <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="session_notes" name="session_notes" rows="5" required
                                    placeholder="Enter detailed notes about the counselling session, student concerns, advice given, etc."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="recommendations">Recommendations / Action Items</label>
                                <textarea class="form-control" id="recommendations" name="recommendations" rows="3"
                                    placeholder="Enter any suggestions, resources or next steps for the student..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">Session Status <span class="text-danger">*</span></label>
                                        <select name="status" id="status" class="form-control" required>
                                            <option value="completed">Completed</option>
                                            <option value="scheduled">Scheduled</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="follow_up_required"
                                        name="follow_up_required" onchange="toggleFollowUpDate()">
                                    <label class="custom-control-label" for="follow_up_required">
                                        Follow-up Required
                                    </label>
                                </div>
                            </div>

                            <div class="form-group css-create-session-224b51" id="follow_up_date_group">
                                <label for="follow_up_date">Follow-up Date</label>
                                <input type="date" class="form-control" id="follow_up_date" name="follow_up_date"
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                <small class="form-text text-muted">Schedule next session date</small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Session Record
                            </button>
                            <a href="sessions.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    function toggleFollowUpDate() {
        var checkbox = document.getElementById('follow_up_required');
        var dateGroup = document.getElementById('follow_up_date_group');
        var dateInput = document.getElementById('follow_up_date');

        if (checkbox.checked) {
            dateGroup.style.display = 'block';
            dateInput.required = true;
        } else {
            dateGroup.style.display = 'none';
            dateInput.required = false;
            dateInput.value = '';
        }
    }

    $(document).ready(function () {
        // Initialize Student Search Component only if search input exists
        const searchInputExists = document.getElementById('student_search');
        if (searchInputExists) {
            const studentSearch = new StudentSearchComponent({
                inputId: 'student_search',
                hiddenInputId: 'student_id',
                resultsContainerId: 'student_search_results',
                detailsContainerId: 'student_details',
                onSelect: function (student) {
                    $('#student_search').addClass('has-selection');
                    console.log('Student selected:', student);
                }
            });

            // Clear selection styling when input is cleared
            $('#student_search').on('input', function () {
                if ($(this).val().trim() === '') {
                    $(this).removeClass('has-selection');
                }
            });
        }
    });
</script>
