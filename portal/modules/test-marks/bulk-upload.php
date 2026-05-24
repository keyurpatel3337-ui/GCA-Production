<?php

/**
 * Test Marks Module - Bulk Upload
 * Upload multiple test marks via CSV file
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle template download
if (isset($_POST['action']) && $_POST['action'] === 'download_template') {
    $test_type = $_POST['type'] ?? 'omr_mcq';

    // Get students list - EXCLUDE students who already have marks for this test type
    $students = $conn->prepare("
        SELECT r.id as student_id, 
               CONCAT(r.surname, ' ', r.student_name) as student_name, 
               r.mob as mobile
        FROM tbl_gm_std_registration r
        WHERE r.status = 1
        AND r.id NOT IN (
            SELECT DISTINCT student_id 
            FROM tbl_test_marks 
            WHERE test_type = ?
        )
        ORDER BY r.surname, r.student_name
    ");
    $students->execute([$test_type]);
    $students = $students->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="test_marks_template_' . $test_type . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers based on test type
    if ($test_type === 'omr_mcq') {
        // OMR MCQ Template
        fputcsv($output, [
            'student_id',
            'student_name',
            'mobile',
            'test_name',
            'test_date',
            'total_questions',
            'total_marks',
            'correct_answers',
            'wrong_answers',
            'low_level_correct',
            'low_level_wrong',
            'medium_level_correct',
            'medium_level_wrong',
            'high_level_correct',
            'high_level_wrong',
            'remarks'
        ]);

        // Add sample rows with student data
        foreach ($students as $student) {
            fputcsv($output, [
                $student['student_id'],
                $student['student_name'],
                $student['mobile'],
                'Sample Test Name',     // test_name
                date('Y-m-d'),          // test_date
                100,                    // total_questions
                100,                    // total_marks
                0,                      // correct_answers
                0,                      // wrong_answers
                0,                      // low_level_correct
                0,                      // low_level_wrong
                0,                      // medium_level_correct
                0,                      // medium_level_wrong
                0,                      // high_level_correct
                0,                      // high_level_wrong
                ''                      // remarks
            ]);
        }
    } else {
        // Descriptive Template
        fputcsv($output, [
            'student_id',
            'student_name',
            'mobile',
            'test_name',
            'test_date',
            'total_marks',
            'obtained_marks',
            'subject1_name',
            'subject1_total',
            'subject1_obtained',
            'subject2_name',
            'subject2_total',
            'subject2_obtained',
            'subject3_name',
            'subject3_total',
            'subject3_obtained',
            'subject4_name',
            'subject4_total',
            'subject4_obtained',
            'remarks'
        ]);

        // Add sample rows with student data
        foreach ($students as $student) {
            fputcsv($output, [
                $student['student_id'],
                $student['student_name'],
                $student['mobile'],
                'Sample Test Name',     // test_name
                date('Y-m-d'),          // test_date
                100,                    // total_marks
                0,                      // obtained_marks
                'Physics',              // subject1_name
                25,                     // subject1_total
                0,                      // subject1_obtained
                'Chemistry',            // subject2_name
                25,                     // subject2_total
                0,                      // subject2_obtained
                'Biology',              // subject3_name
                25,                     // subject3_total
                0,                      // subject3_obtained
                'Maths',                // subject4_name
                25,                     // subject4_total
                0,                      // subject4_obtained
                ''                      // remarks
            ]);
        }
    }

    fclose($output);
    exit;
}

// Handle CSV upload
$upload_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $test_type = $_POST['test_type'] ?? 'omr_mcq';
    $file = $_FILES['csv_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        // MIME type validation for CSV
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mime_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

        if (!in_array($mimeType, $allowed_mime_types)) {
            set_flash_message('error', 'Invalid file type. Please upload a valid CSV file.');
            header('Location: bulk-upload.php');
            exit();
        }

        $handle = fopen($file['tmp_name'], 'r');

        // Skip header row
        $header = fgetcsv($handle);

        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors = [];
        $row_number = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;

            try {
                $student_id = intval($data[0]);
                $test_name = trim($data[3] ?? '');
                $test_date = $data[4] ?? date('Y-m-d');

                // Verify student exists
                $check = $conn->prepare("SELECT id FROM tbl_gm_std_registration WHERE id = ?");
                $check->execute([$student_id]);
                if (!$check->fetch()) {
                    $errors[] = "Row $row_number: Student ID $student_id not found";
                    $error_count++;
                    continue;
                }

                // Check for duplicate - skip if student already has marks for same test_name and test_date
                $dupCheck = $conn->prepare("
                    SELECT id FROM tbl_test_marks 
                    WHERE student_id = ? AND test_type = ? AND test_name = ? AND test_date = ?
                ");
                $dupCheck->execute([$student_id, $test_type, $test_name, $test_date]);
                if ($dupCheck->fetch()) {
                    $skipped_count++;
                    continue; // Skip duplicate record silently
                }

                if ($test_type === 'omr_mcq') {
                    // OMR MCQ data
                    $test_name = $data[3] ?? '';
                    $test_date = $data[4] ?? date('Y-m-d');
                    $total_questions = intval($data[5] ?? 100);
                    $total_marks = floatval($data[6] ?? 100);
                    $correct_answers = intval($data[7] ?? 0);
                    $wrong_answers = intval($data[8] ?? 0);
                    $unanswered = $total_questions - $correct_answers - $wrong_answers;

                    // Calculate obtained marks (simple: 1 mark per correct answer)
                    $marks_per_question = $total_marks / $total_questions;
                    $obtained_marks = $correct_answers * $marks_per_question;

                    $stmt = $conn->prepare("
                        INSERT INTO tbl_test_marks (
                            student_id, test_type, test_name, test_date, 
                            total_questions, total_marks, obtained_marks,
                            correct_answers, wrong_answers, unanswered,
                            low_level_correct, low_level_wrong,
                            medium_level_correct, medium_level_wrong,
                            high_level_correct, high_level_wrong,
                            remarks, status, created_by
                        ) VALUES (?, 'omr_mcq', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'evaluated', ?)
                    ");
                    $stmt->execute([
                        $student_id,
                        $test_name,
                        $test_date,
                        $total_questions,
                        $total_marks,
                        $obtained_marks,
                        $correct_answers,
                        $wrong_answers,
                        $unanswered,
                        intval($data[9] ?? 0),   // low_level_correct
                        intval($data[10] ?? 0),  // low_level_wrong
                        intval($data[11] ?? 0),  // medium_level_correct
                        intval($data[12] ?? 0),  // medium_level_wrong
                        intval($data[13] ?? 0),  // high_level_correct
                        intval($data[14] ?? 0),  // high_level_wrong
                        $data[15] ?? '',         // remarks
                        $_SESSION['user_id']
                    ]);
                } else {
                    // Descriptive data
                    $test_name = $data[3] ?? '';
                    $test_date = $data[4] ?? date('Y-m-d');
                    $total_marks = floatval($data[5] ?? 100);
                    $obtained_marks = floatval($data[6] ?? 0);

                    // Build subject marks JSON
                    $subjects = [];
                    for ($i = 0; $i < 4; $i++) {
                        $base = 7 + ($i * 3);
                        $name = $data[$base] ?? '';
                        if (!empty($name)) {
                            $subjects[] = [
                                'subject' => $name,
                                'total' => floatval($data[$base + 1] ?? 0),
                                'obtained' => floatval($data[$base + 2] ?? 0)
                            ];
                        }
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO tbl_test_marks (
                            student_id, test_type, test_name, test_date, 
                            total_marks, obtained_marks, subject_marks_json,
                            remarks, status, created_by
                        ) VALUES (?, 'descriptive', ?, ?, ?, ?, ?, ?, 'evaluated', ?)
                    ");
                    $stmt->execute([
                        $student_id,
                        $test_name,
                        $test_date,
                        $total_marks,
                        $obtained_marks,
                        json_encode($subjects),
                        $data[19] ?? '',  // remarks
                        $_SESSION['user_id']
                    ]);
                }

                $success_count++;
            } catch (Exception $e) {
                $errors[] = "Row $row_number: " . $e->getMessage();
                $error_count++;
            }
        }

        fclose($handle);
        $upload_result = [
            'success' => $success_count,
            'errors' => $error_count,
            'skipped' => $skipped_count,
            'error_details' => $errors
        ];
    } else {
        $upload_result = ['error' => 'File upload failed'];
    }
}

$page_title = "Bulk Upload Test Marks" ;
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



    <div class="container-fluid">
        <?php if ($upload_result): ?>
            <?php if (isset($upload_result['error'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <i class="fas fa-exclamation-circle"></i> <?php echo $upload_result['error']; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-<?php echo $upload_result['errors'] > 0 ? 'warning' : 'success'; ?> alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <h5><i class="fas fa-check-circle"></i> Upload Complete</h5>
                    <p>Successfully imported: <strong><?php echo $upload_result['success']; ?></strong> records</p>
                    <?php if (isset($upload_result['skipped']) && $upload_result['skipped'] > 0): ?>
                        <p><i class="fas fa-forward"></i> Skipped (duplicates):
                            <strong><?php echo $upload_result['skipped']; ?></strong></p>
                    <?php endif; ?>
                    <?php if ($upload_result['errors'] > 0): ?>
                        <p>Errors: <strong><?php echo $upload_result['errors']; ?></strong></p>
                        <details>
                            <summary>View error details</summary>
                            <ul class="mb-0">
                                <?php foreach ($upload_result['error_details'] as $err): ?>
                                    <li><?php echo htmlspecialchars($err ?? ''); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row">
            <!-- Download Template -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="card-title"><i class="fas fa-download"></i> Step 1: Download Template</h3>
                    </div>
                    <div class="card-body">
                        <p>Download a CSV template with all registered students. Fill in the test marks data and upload.
                        </p>

                        <div class="d-grid gap-2">
                            <form method="POST" action="bulk-upload.php" class="css-bulk-upload-46dcee">
                                <input type="hidden" name="action" value="download_template">
                                <input type="hidden" name="type" value="omr_mcq">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-qrcode"></i> Download OMR MCQ Template
                                </button>
                            </form>
                            <form method="POST" action="bulk-upload.php" class="css-bulk-upload-46dcee">
                                <input type="hidden" name="action" value="download_template">
                                <input type="hidden" name="type" value="descriptive">
                                <button type="submit" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-pencil-alt"></i> Download Descriptive Template
                                </button>
                            </form>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle"></i>
                            <strong>Template includes:</strong>
                            <ul class="mb-0">
                                <li>Student ID, Name, Mobile (pre-filled)</li>
                                <li>Required fields for marks entry</li>
                                <li>Sample data for reference</li>
                                <li><strong>Note:</strong> Students with existing marks for this test type are excluded
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload CSV -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title"><i class="fas fa-upload"></i> Step 2: Upload Filled CSV</h3>
                    </div>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><strong>Test Type</strong></label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="test_type" id="typeOMR" value="omr_mcq"
                                        checked>
                                    <label class="btn btn-outline-primary" for="typeOMR">
                                        <i class="fas fa-qrcode"></i> OMR MCQ
                                    </label>
                                    <input type="radio" class="btn-check" name="test_type" id="typeDescriptive"
                                        value="descriptive">
                                    <label class="btn btn-outline-success" for="typeDescriptive">
                                        <i class="fas fa-pencil-alt"></i> Descriptive
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><strong>Select CSV File</strong></label>
                                <input type="file" name="csv_file" class="form-control form-control-lg" accept=".csv"
                                    required>
                                <small class="text-muted">Only CSV files are accepted</small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-cloud-upload-alt"></i> Upload and Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                <h3 class="card-title"><i class="fas fa-info-circle"></i> Instructions</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-qrcode text-primary"></i> OMR MCQ Template Fields</h5>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th>Field</th>
                                <th>Description</th>
                            </tr>
                            <tr>
                                <td>student_id</td>
                                <td>Student ID (do not modify)</td>
                            </tr>
                            <tr>
                                <td>student_name</td>
                                <td>For reference only</td>
                            </tr>
                            <tr>
                                <td>mobile</td>
                                <td>For reference only</td>
                            </tr>
                            <tr>
                                <td>test_name</td>
                                <td>Name of the test</td>
                            </tr>
                            <tr>
                                <td>test_date</td>
                                <td>Date (YYYY-MM-DD)</td>
                            </tr>
                            <tr>
                                <td>total_questions</td>
                                <td>Total number of questions</td>
                            </tr>
                            <tr>
                                <td>total_marks</td>
                                <td>Maximum marks</td>
                            </tr>
                            <tr>
                                <td>correct_answers</td>
                                <td>Number of correct answers</td>
                            </tr>
                            <tr>
                                <td>wrong_answers</td>
                                <td>Number of wrong answers</td>
                            </tr>
                            <tr>
                                <td>low/medium/high_level_*</td>
                                <td>Difficulty breakdown</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-pencil-alt text-success"></i> Descriptive Template Fields</h5>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th>Field</th>
                                <th>Description</th>
                            </tr>
                            <tr>
                                <td>student_id</td>
                                <td>Student ID (do not modify)</td>
                            </tr>
                            <tr>
                                <td>student_name</td>
                                <td>For reference only</td>
                            </tr>
                            <tr>
                                <td>mobile</td>
                                <td>For reference only</td>
                            </tr>
                            <tr>
                                <td>test_name</td>
                                <td>Name of the test</td>
                            </tr>
                            <tr>
                                <td>test_date</td>
                                <td>Date (YYYY-MM-DD)</td>
                            </tr>
                            <tr>
                                <td>total_marks</td>
                                <td>Maximum marks</td>
                            </tr>
                            <tr>
                                <td>obtained_marks</td>
                                <td>Total obtained marks</td>
                            </tr>
                            <tr>
                                <td>subject*_name</td>
                                <td>Subject name (up to 4)</td>
                            </tr>
                            <tr>
                                <td>subject*_total</td>
                                <td>Subject total marks</td>
                            </tr>
                            <tr>
                                <td>subject*_obtained</td>
                                <td>Subject obtained marks</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Test Marks
            </a>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Single Entry
            </a>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>
