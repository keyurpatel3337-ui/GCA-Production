<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$page_title = "Import Questions | OES";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $count = 0;
    
    // Skip header
    fgetcsv($handle);
    
    // Pre-fetch mappings
    $standards_map = $conn->query("SELECT stdid, stdtext FROM standard")->fetchAll(PDO::FETCH_KEY_PAIR);
    $standards_map = array_change_key_case(array_flip($standards_map), CASE_LOWER);
    
    $subjects_map = [];
    $sub_res = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0");
    while($s = $sub_res->fetch()) { $subjects_map[$s['standard_id'].'_'.strtolower(trim($s['subject_name']))] = $s['id']; }
    
    $chapters_map = [];
    $ch_res = $conn->query("SELECT chpid, subid, chapter FROM chapters WHERE activated = 1 AND is_deleted = 0");
    while($c = $ch_res->fetch()) { $chapters_map[$c['subid'].'_'.strtolower(trim($c['chapter']))] = $c['chpid']; }
    
    $topics_map = [];
    $tp_res = $conn->query("SELECT id, subject_id, chapter_id, topic_name_english FROM tbl_topics WHERE activated = 1 AND is_deleted = 0");
    while($t = $tp_res->fetch()) { $topics_map[$t['chapter_id'].'_'.strtolower(trim($t['topic_name_english']))] = $t['id']; }

    $q_types_map = $conn->query("SELECT id, type_name FROM tbl_oes_question_types")->fetchAll(PDO::FETCH_KEY_PAIR);
    $q_types_map = array_change_key_case(array_flip($q_types_map), CASE_LOWER);

    $lvl_map = ['level a' => 'Level A', 'level b' => 'Level B', 'level c' => 'Level C', 'level d' => 'Level D', 'level e' => 'Level E'];
    $created_by = (int)$_SESSION['user_id'] ?? 1;

    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
        if (count($data) < 13) continue;
        
        $std_text = strtolower(trim($data[0]));
        $sub_text = strtolower(trim($data[1]));
        $ch_text = strtolower(trim($data[2]));
        $tp_text = strtolower(trim($data[3]));
        $type_text = strtolower(trim($data[4]));
        $difficulty_text = strtolower(trim($data[5]));
        $q_text = trim($data[6]);
        $opt_a = trim($data[7]); $opt_b = trim($data[8]); $opt_c = trim($data[9]); $opt_d = trim($data[10]);
        $correct = strtoupper(trim($data[11]));
        $marks = (int)$data[12];

        $std_id = $standards_map[$std_text] ?? 0;
        $sub_id = $subjects_map[$std_id.'_'.$sub_text] ?? 0;
        $ch_id = $chapters_map[$sub_id.'_'.$ch_text] ?? 0;
        $tp_id = $topics_map[$ch_id.'_'.$tp_text] ?? 0;
        $type_id = $q_types_map[$type_text] ?? 1;
        $difficulty = $lvl_map[$difficulty_text] ?? 'Level A';

        if ($sub_id > 0 && !empty($q_text)) {
            try {
                $stmt_q = $conn->prepare("INSERT INTO tbl_oes_questions (standard_id, subject_id, chapter_id, topic_id, question_type_id, difficulty, marks, question_text, option_a, option_b, option_c, option_d, correct_option, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt_q->execute([$std_id, $sub_id, $ch_id, $tp_id, $type_id, $difficulty, $marks, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $created_by]);
                $count++;
            } catch (Exception $e) {
                // Skip on error
            }
        }
    }
    fclose($handle);
    header("Location: question-bank.php?msg=imported_$count");
    exit();
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px; max-width: 800px; margin: 0 auto;">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-csv mr-2 text-primary"></i> Import Questions</h5>
                        <a href="question-bank.php" class="btn btn-sm btn-light shadow-sm" style="border-radius: 10px;"><i class="fas fa-arrow-left mr-1"></i> Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert border-0 mb-4 d-flex align-items-start" style="border-radius: 12px; background: #e7f3ff; color: #004085; padding: 1.25rem;">
                        <i class="fas fa-info-circle fa-2x mr-3 mt-1 text-primary"></i>
                        <div>
                            <h6 class="font-weight-bold mb-1">CSV Format Requirements (13 Columns)</h6>
                            <p class="small mb-2">Your CSV file must contain exactly 13 columns in the following order:</p>
                            <div class="row small text-muted">
                                <div class="col-md-6">
                                    <ul class="pl-3 mb-0">
                                        <li><strong>Standard:</strong> e.g., '11th Science'</li>
                                        <li><strong>Subject:</strong> e.g., 'Physics'</li>
                                        <li><strong>Chapter:</strong> e.g., 'Kinematics'</li>
                                        <li><strong>Topic:</strong> e.g., 'Motion in 1D'</li>
                                        <li><strong>Type:</strong> e.g., 'MCQ'</li>
                                        <li><strong>Level:</strong> e.g., 'Level A'</li>
                                        <li><strong>Question:</strong> The question text</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="pl-3 mb-0">
                                        <li><strong>Option A:</strong> Text for option A</li>
                                        <li><strong>Option B:</strong> Text for option B</li>
                                        <li><strong>Option C:</strong> Text for option C</li>
                                        <li><strong>Option D:</strong> Text for option D</li>
                                        <li><strong>Correct:</strong> Letter A, B, C, or D</li>
                                        <li><strong>Marks:</strong> Numeric value e.g. 1</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-4">
                        <a href="download-sample.php?type=question" class="btn btn-outline-primary shadow-sm" style="border-radius: 10px; font-weight: 600;">
                            <i class="fas fa-download mr-2"></i> Download Sample CSV
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group mb-5">
                            <label class="small font-weight-bold text-muted mb-2">Select Filled CSV File <span class="text-danger">*</span></label>
                            <div class="custom-file" style="height: 50px;">
                                <input type="file" name="csv_file" class="form-control border-0 shadow-sm" style="background: #f8f9fa; border-radius: 10px; height: 50px; padding: 12px;" accept=".csv" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="question-bank.php" class="btn btn-light shadow-sm px-4" style="border-radius: 12px; height: 45px; line-height: 33px; font-weight: 600;">Cancel</a>
                            <button type="submit" class="btn btn-primary shadow-sm px-4" style="border-radius: 12px; height: 45px; font-weight: 600;">
                                <i class="fas fa-cloud-upload-alt mr-2"></i> Upload & Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include PORTAL_INCLUDE_PATH . 'footer.php'; ?>
