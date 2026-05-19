<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Counsellor
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// DEBUG: Log POST data
error_log("SESSION-ADD.PHP DEBUG - POST Data: " . print_r($_POST, true));
error_log("SESSION-ADD.PHP DEBUG - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

$page_title = "Add Counselling Session";
$page_breadcrumb = "Session -";
$counsellor_id = $_SESSION['user_id'];

// Get student_id from POST (from list page button)
$preselected_student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
error_log("SESSION-ADD.PHP DEBUG - Preselected Student ID: " . $preselected_student_id);
$preselected_student = null;

// Get my assigned students
try {
    $my_students = $dbOps->customSelect("SELECT id, CONCAT(surname, ' ', student_name) as name, mob FROM tbl_gm_std_registration WHERE counsellor_id = ? ORDER BY surname, student_name", [$counsellor_id]);

    // If student_id provided, get student details
    if ($preselected_student_id > 0) {
        $preselected_student = $dbOps->selectOne(
            'tbl_gm_std_registration',
            ['id', 'surname', 'student_name', 'fathers_name', 'mob'],
            ['id' => $preselected_student_id, 'counsellor_id' => $counsellor_id]
        );
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Students for Sessions");
    $my_students = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">

        <!-- DEBUG ALERT -->
        <?php if ($preselected_student_id > 0): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-info-circle"></i> <strong>DEBUG:</strong> Student ID received from list page:
                <?php echo $preselected_student_id; ?>
                <?php if ($preselected_student): ?>
                    | Student found:
                    <?php echo htmlspecialchars($preselected_student['surname'] . ' ' . $preselected_student['student_name'] ?? ''); ?>
                <?php else: ?>
                    | <span class="text-danger">Student NOT found in database or not assigned to you!</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle"></i> <strong>DEBUG:</strong> No student_id received from POST. POST
                data: <?php echo htmlspecialchars(json_encode($_POST) ?? ''); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i>


                
                    <div class="container-fluid">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle"></i> <?php echo gca_safe_html($_SESSION['error_msg']);
                                // Check if user is Counsellor
                                if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_SUPER_ADMIN)) {
                                    header('Location: ' . BASE_URL . '/index.php');
                                    exit;
                                }

                                $page_title = "Add Counselling Session";
                                $page_breadcrumb = "Session -";
                                $counsellor_id = $_SESSION['user_id'];

                                // Get my assigned students
                                try {
                                    $my_students = $dbOps->customSelect("SELECT id, CONCAT(surname, ' ', student_name) as name, mob FROM tbl_gm_std_registration WHERE counsellor_id = ? ORDER BY surname, student_name", [$counsellor_id]);
                                } catch (PDOException $e) {
                                    logDatabaseError($e, "Fetch Students for Sessions");
                                    $my_students = [];
                                }
                                ?>
                                <?php include '../../include/header.php'; ?>
                                <?php include '../../include/navbar.php'; ?>
                                <?php include '../../include/sidebar.php'; ?>




                                
                                    <div class="container-fluid">
                                        <?php if (isset($_SESSION['error'])): ?>
                                            <div class="alert alert-danger alert-dismissible fade show">
                                                <i class="fas fa-exclamation-circle"></i>


                                                
                                                    <div class="container-fluid">
                                                        <?php if (isset($_SESSION['error'])): ?>
                                                            <div class="alert alert-danger alert-dismissible fade show">
                                                                <i class="fas fa-exclamation-circle"></i>
                                                                <?php echo $_SESSION['error'];
                                                                ?>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="alert">&times;</button>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="card">
                                                                    <div class="card-header">
                                                                        <h3 class="card-title">
                                                                            <i class="fas fa-comments"></i> Session Information
                                                                        </h3>
                                                                    </div>
                                                                    <form id="sessionForm" method="POST">
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label>Select Student <span
                                                                                                class="text-danger">*</span></label>
                                                                                        <div class="student-search-wrapper">
                                                                                            <input type="text" id="student_search"
                                                                                                class="form-control student-search-input"
                                                                                                placeholder="Enter Student Name, ID or Mobile Number"
                                                                                                autocomplete="off">
                                                                                            <input type="hidden" name="student_id"
                                                                                                id="student_id" required>
                                                                                            <div id="student_search_results"></div>
                                                                                        </div>
                                                                                        <small class="form-text text-muted">Type at
                                                                                            least 2 characters to search</small>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label>Session Date <span
                                                                                                class="text-danger">*</span></label>
                                                                                        <input type="date" name="session_date"
                                                                                            class="form-control"
                                                                                            value="<?php echo date('Y-m-d'); ?>"
                                                                                            required>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label>Duration (minutes)</label>
                                                                                        <input type="number" name="duration"
                                                                                            class="form-control" min="15" max="180"
                                                                                            value="30" placeholder="e.g., 30">
                                                                                        <small class="form-text text-muted">Between
                                                                                            15 to 180 minutes</small>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label>Session Topic</label>
                                                                                        <input type="text" name="session_topic"
                                                                                            class="form-control"
                                                                                            placeholder="e.g., Career Guidance, Stress Management">
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="row">
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label>Session Notes <span
                                                                                                class="text-danger">*</span></label>
                                                                                        <textarea name="session_notes"
                                                                                            class="form-control" rows="5"
                                                                                            placeholder="Enter detailed notes about the session..."
                                                                                            required></textarea>
                                                                                        <small class="form-text text-muted">Include
                                                                                            key discussion points, student concerns,
                                                                                            and observations</small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="row">
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label>Recommendations/Action Items</label>
                                                                                        <textarea name="recommendations"
                                                                                            class="form-control" rows="4"
                                                                                            placeholder="Enter recommendations or follow-up actions..."></textarea>
                                                                                        <small
                                                                                            class="form-text text-muted">Suggested
                                                                                            next steps, resources, or follow-up
                                                                                            activities</small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label>Status <span
                                                                                                class="text-danger">*</span></label>
                                                                                        <select name="status" class="form-control"
                                                                                            required>
                                                                                            <option value="completed">Completed
                                                                                            </option>
                                                                                            <option value="scheduled">Scheduled
                                                                                            </option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="card-footer">
                                                                            <button type="submit" class="btn btn-success">
                                                                                <i class="fas fa-save"></i> Save Session
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
                                                    $(document).ready(function () {
                                                        // Initialize Student Search Component
                                                        const studentSearch = new StudentSearchComponent({
                                                            inputId: 'student_search',
                                                            hiddenInputId: 'student_id',
                                                            resultsContainerId: 'student_search_results',
                                                            onSelect: function (student) {
                                                                $('#student_search').addClass('has-selection');
                                                                console.log('Student selected:', student);
                                                            }
                                                        });

                                                        // Auto-select student if passed via POST from list page
                                                        <?php if ($preselected_student): ?>
                                                            setTimeout(function () {
                                                                const student = <?php echo json_encode($preselected_student); ?>;
                                                                console.log('Auto-selecting preselected student:', student);

                                                                const fullName = [student.surname, student.student_name, student.fathers_name]
                                                                    .filter(Boolean)
                                                                    .join(' ');

                                                                // Set the search input and hidden field
                                                                $('#student_search').val(fullName + ' (' + student.mob + ')').addClass('has-selection');
                                                                $('#student_id').val(student.id);

                                                                console.log('Student auto-selected: ID=' + student.id + ', Name=' + fullName);
                                                            }, 100);
                                                        <?php endif; ?>

                                                        // Clear selection styling when input is cleared
                                                        $('#student_search').on('input', function () {
                                                            if ($(this).val().trim() === '') {
                                                                $(this).removeClass('has-selection');
                                                            }
                                                        });

                                                        // Form submission via API
                                                        $('#sessionForm').on('submit', function (e) {
                                                            e.preventDefault();

                                                            const studentId = $('#student_id').val();
                                                            if (!studentId) {
                                                                showToast('error', 'Error', 'Please select a student');
                                                                $('#student_search').focus();
                                                                return false;
                                                            }

                                                            // Collect form data
                                                            const formData = {};
                                                            $(this).serializeArray().forEach(item => {
                                                                formData[item.name] = item.value;
                                                            });

                                                            // Submit via API
                                                            $.api.post('students/session-save', formData).then(response => {
                                                                if (response.success) {
                                                                    if (typeof showToast === "function") { showToast("success", "Success", response.message || "Session saved successfully."); } setTimeout(() => { window.location.href = "sessions.php"; }, 2000);
                                                                } else {
                                                                    showToast('error', 'Error', response.error || response.message || 'Failed to save session.');
                                                                }
                                                            }).catch(error => {
                                                                console.error('API Error:', error);
                                                                showToast('error', 'Error', error.message || 'An unexpected error occurred.');
                                                            });
                                                        });
                                                    });
                                                </script>SESSION['error'], ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                                            </div>
                                        <?php endif; ?>

                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h3 class="card-title">
                                                            <i class="fas fa-comments"></i> Session Information
                                                        </h3>
                                                    </div>
                                                    <form id="sessionForm" method="POST">
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Select Student <span
                                                                                class="text-danger">*</span></label>
                                                                        <div class="student-search-wrapper">
                                                                            <input type="text" id="student_search"
                                                                                class="form-control student-search-input"
                                                                                placeholder="Enter Student Name, ID or Mobile Number"
                                                                                autocomplete="off">
                                                                            <input type="hidden" name="student_id"
                                                                                id="student_id" required>
                                                                            <div id="student_search_results"></div>
                                                                        </div>
                                                                        <small class="form-text text-muted">Type at least 2
                                                                            characters to search</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Session Date <span
                                                                                class="text-danger">*</span></label>
                                                                        <input type="date" name="session_date"
                                                                            class="form-control"
                                                                            value="<?php echo date('Y-m-d'); ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Duration (minutes)</label>
                                                                        <input type="number" name="duration"
                                                                            class="form-control" min="15" max="180" value="30"
                                                                            placeholder="e.g., 30">
                                                                        <small class="form-text text-muted">Between 15 to 180
                                                                            minutes</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Session Topic</label>
                                                                        <input type="text" name="session_topic"
                                                                            class="form-control"
                                                                            placeholder="e.g., Career Guidance, Stress Management">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label>Session Notes <span
                                                                                class="text-danger">*</span></label>
                                                                        <textarea name="session_notes" class="form-control"
                                                                            rows="5"
                                                                            placeholder="Enter detailed notes about the session..."
                                                                            required></textarea>
                                                                        <small class="form-text text-muted">Include key
                                                                            discussion points, student concerns, and
                                                                            observations</small>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label>Recommendations/Action Items</label>
                                                                        <textarea name="recommendations" class="form-control"
                                                                            rows="4"
                                                                            placeholder="Enter recommendations or follow-up actions..."></textarea>
                                                                        <small class="form-text text-muted">Suggested next
                                                                            steps, resources, or follow-up activities</small>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Status <span class="text-danger">*</span></label>
                                                                        <select name="status" class="form-control" required>
                                                                            <option value="completed">Completed</option>
                                                                            <option value="scheduled">Scheduled</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer">
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="fas fa-save"></i> Save Session
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
                                    $(document).ready(function () {
                                        // Initialize Student Search Component
                                        const studentSearch = new StudentSearchComponent({
                                            inputId: 'student_search',
                                            hiddenInputId: 'student_id',
                                            resultsContainerId: 'student_search_results',
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

                                        // Form submission via API
                                        $('#sessionForm').on('submit', function (e) {
                                            e.preventDefault();

                                            const studentId = $('#student_id').val();
                                            if (!studentId) {
                                                showToast('error', 'Error', 'Please select a student');
                                                $('#student_search').focus();
                                                return false;
                                            }

                                            // Collect form data
                                            const formData = {};
                                            $(this).serializeArray().forEach(item => {
                                                formData[item.name] = item.value;
                                            });

                                            // Submit via API
                                            $.api.post('students/session-save', formData).then(response => {
                                                if (response.success) {
                                                    if (typeof showToast === "function") { showToast("success", "Success", response.message || "Session saved successfully."); } setTimeout(() => { window.location.href = "sessions.php"; }, 2000);
                                                } else {
                                                    showToast('error', 'Error', response.error || response.message || 'Failed to save session.');
                                                }
                                            }).catch(error => {
                                                console.error('API Error:', error);
                                                showToast('error', 'Error', error.message || 'An unexpected error occurred.');
                                            });
                                        });
                                    });
                                </script>SESSION['error']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-comments"></i> Session Information
                                        </h3>
                                    </div>
                                    <form id="sessionForm" method="POST">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Select Student <span class="text-danger">*</span></label>
                                                        <div class="student-search-wrapper">
                                                            <input type="text" id="student_search"
                                                                class="form-control student-search-input"
                                                                placeholder="Enter Student Name, ID or Mobile Number"
                                                                autocomplete="off">
                                                            <input type="hidden" name="student_id" id="student_id" required>
                                                            <div id="student_search_results"></div>
                                                        </div>
                                                        <small class="form-text text-muted">Type at least 2 characters to
                                                            search</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Session Date <span class="text-danger">*</span></label>
                                                        <input type="date" name="session_date" class="form-control"
                                                            value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Duration (minutes)</label>
                                                        <input type="number" name="duration" class="form-control" min="15"
                                                            max="180" value="30" placeholder="e.g., 30">
                                                        <small class="form-text text-muted">Between 15 to 180
                                                            minutes</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Session Topic</label>
                                                        <input type="text" name="session_topic" class="form-control"
                                                            placeholder="e.g., Career Guidance, Stress Management">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label>Session Notes <span class="text-danger">*</span></label>
                                                        <textarea name="session_notes" class="form-control" rows="5"
                                                            placeholder="Enter detailed notes about the session..."
                                                            required></textarea>
                                                        <small class="form-text text-muted">Include key discussion points,
                                                            student concerns, and observations</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label>Recommendations/Action Items</label>
                                                        <textarea name="recommendations" class="form-control" rows="4"
                                                            placeholder="Enter recommendations or follow-up actions..."></textarea>
                                                        <small class="form-text text-muted">Suggested next steps, resources,
                                                            or follow-up activities</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Status <span class="text-danger">*</span></label>
                                                        <select name="status" class="form-control" required>
                                                            <option value="completed">Completed</option>
                                                            <option value="scheduled">Scheduled</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save"></i> Save Session
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
                    $(document).ready(function () {
                        // Initialize Student Search Component
                        const studentSearch = new StudentSearchComponent({
                            inputId: 'student_search',
                            hiddenInputId: 'student_id',
                            resultsContainerId: 'student_search_results',
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

                        // Form submission via API
                        $('#sessionForm').on('submit', function (e) {
                            e.preventDefault();

                            const studentId = $('#student_id').val();
                            if (!studentId) {
                                showToast('error', 'Error', 'Please select a student');
                                $('#student_search').focus();
                                return false;
                            }

                            // Collect form data
                            const formData = {};
                            $(this).serializeArray().forEach(item => {
                                formData[item.name] = item.value;
                            });

                            // Submit via API
                            $.api.post('students/session-save', formData).then(response => {
                                if (response.success) {
                                    if (typeof showToast === "function") { showToast("success", "Success", response.message || "Session saved successfully."); } setTimeout(() => { window.location.href = "sessions.php"; }, 2000);
                                } else {
                                    showToast('error', 'Error', response.error || response.message || 'Failed to save session.');
                                }
                            }).catch(error => {
                                console.error('API Error:', error);
                                showToast('error', 'Error', error.message || 'An unexpected error occurred.');
                            });
                        });
                    });
                </script>SESSION['error'], ENT_QUOTES, 'UTF-8');
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-comments"></i> Session Information
                        </h3>
                    </div>
                    <form id="sessionForm" method="POST">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Select Student <span class="text-danger">*</span></label>
                                        <div class="student-search-wrapper">
                                            <input type="text" id="student_search"
                                                class="form-control student-search-input"
                                                placeholder="Enter Student Name, ID or Mobile Number"
                                                autocomplete="off">
                                            <input type="hidden" name="student_id" id="student_id" required>
                                            <div id="student_search_results"></div>
                                        </div>
                                        <small class="form-text text-muted">Type at least 2 characters to search</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Session Date <span class="text-danger">*</span></label>
                                        <input type="date" name="session_date" class="form-control"
                                            value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Duration (minutes)</label>
                                        <input type="number" name="duration" class="form-control" min="15" max="180"
                                            value="30" placeholder="e.g., 30">
                                        <small class="form-text text-muted">Between 15 to 180 minutes</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Session Topic</label>
                                        <input type="text" name="session_topic" class="form-control"
                                            placeholder="e.g., Career Guidance, Stress Management">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Session Notes <span class="text-danger">*</span></label>
                                        <textarea name="session_notes" class="form-control" rows="5"
                                            placeholder="Enter detailed notes about the session..." required></textarea>
                                        <small class="form-text text-muted">Include key discussion points, student
                                            concerns, and observations</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Recommendations/Action Items</label>
                                        <textarea name="recommendations" class="form-control" rows="4"
                                            placeholder="Enter recommendations or follow-up actions..."></textarea>
                                        <small class="form-text text-muted">Suggested next steps, resources, or
                                            follow-up activities</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status <span class="text-danger">*</span></label>
                                        <select name="status" class="form-control" required>
                                            <option value="completed">Completed</option>
                                            <option value="scheduled">Scheduled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Session
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
    $(document).ready(function () {
        // Initialize Student Search Component
        const studentSearch = new StudentSearchComponent({
            inputId: 'student_search',
            hiddenInputId: 'student_id',
            resultsContainerId: 'student_search_results',
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

        // Form submission via API
        $('#sessionForm').on('submit', function (e) {
            e.preventDefault();

            const studentId = $('#student_id').val();
            if (!studentId) {
                showToast('error', 'Error', 'Please select a student');
                $('#student_search').focus();
                return false;
            }

            // Collect form data
            const formData = {};
            $(this).serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });

            // Submit via API
            $.api.post('students/session-save', formData).then(response => {
                if (response.success) {
                    if (typeof showToast === 'function') {
                        showToast('success', 'Success', response.message || 'Session saved successfully.');
                    }
                    setTimeout(() => {
                        window.location.href = 'sessions.php';
                    }, 2000);
                } else {
                    showToast('error', 'Error', response.error || response.message || 'Failed to save session.');
                }
            }).catch(error => {
                console.error('API Error:', error);
                showToast('error', 'Error', error.message || 'An unexpected error occurred.');
            });
        });
    });
</script>
