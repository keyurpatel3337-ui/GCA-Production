<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    header("Location: " . PORTAL_URL . "/login.php");
    exit();
}

$page_title = "Import Topics | OES";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $count = 0;
    
    // Skip header
    fgetcsv($handle);
    
    // Pre-fetch all levels for mapping
    $standards_map = [];
    $std_res = $conn->query("SELECT stdid, stdtext FROM standard");
    while($std = $std_res->fetch()) {
        $standards_map[strtolower(trim($std['stdtext']))] = $std['stdid'];
    }
    
    $subjects_map = []; // key: stdid_subjectname
    $sub_res = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects");
    while($sub = $sub_res->fetch()) {
        $key = $sub['standard_id'] . '_' . strtolower(trim($sub['subject_name']));
        $subjects_map[$key] = $sub['id'];
    }

    $chapters_map = []; // key: subid_chaptername
    $ch_res = $conn->query("SELECT chpid, subid, chapter FROM tbl_chapters");
    while($ch = $ch_res->fetch()) {
        $key = $ch['subid'] . '_' . strtolower(trim($ch['chapter']));
        $chapters_map[$key] = $ch['chpid'];
    }

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) < 4) continue;
        
        $std_text = strtolower(trim($data[0]));
        $subject_text = strtolower(trim($data[1]));
        $chapter_text = strtolower(trim($data[2]));
        $topic_name = trim($data[3]);
        
        $std_id = isset($standards_map[$std_text]) ? $standards_map[$std_text] : 0;
        $sub_key = $std_id . '_' . $subject_text;
        $sub_id = isset($subjects_map[$sub_key]) ? $subjects_map[$sub_key] : 0;
        $ch_key = $sub_id . '_' . $chapter_text;
        $ch_id = isset($chapters_map[$ch_key]) ? $chapters_map[$ch_key] : 0;
        
        if ($ch_id > 0 && !empty($topic_name)) {
            $stmt = $conn->prepare("INSERT INTO tbl_topics (subject_id, chapter_id, topic_name_english, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$sub_id, $ch_id, $topic_name]);
            $count++;
        }
    }
    fclose($handle);
    header("Location: manage-topics.php?msg=imported_$count");
    exit();
}

include PORTAL_INCLUDE_PATH . 'header.php';
include PORTAL_INCLUDE_PATH . 'navbar.php';
include PORTAL_INCLUDE_PATH . 'sidebar.php';
?>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">
            <div class="card shadow-sm mb-4 border-0 css-import-topics-d2718c">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-csv mr-2 text-primary"></i> Import Topics</h5>
                        <a href="manage-topics.php" class="btn btn-sm btn-light shadow-sm css-import-topics-1033dd"><i class="fas fa-arrow-left mr-1"></i> Back</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert border-0 mb-4 d-flex align-items-start css-import-topics-4ad229">
                        <i class="fas fa-info-circle fa-2x mr-3 mt-1 text-primary"></i>
                        <div>
                            <h6 class="font-weight-bold mb-1">CSV Format Requirements</h6>
                            <p class="small mb-2">Your CSV file must contain exactly 4 columns in the following order:</p>
                            <ol class="small mb-0 pl-3 text-muted">
                                <li><strong>Standard:</strong> The name of the standard</li>
                                <li><strong>Subject:</strong> The name of the subject</li>
                                <li><strong>Chapter:</strong> The name of the chapter</li>
                                <li><strong>Topic Name:</strong> The name of the topic</li>
                            </ol>
                        </div>
                    </div>

                    <div class="text-center mb-4">
                        <a href="download-sample.php?type=topic" class="btn btn-outline-primary shadow-sm css-import-topics-186171">
                            <i class="fas fa-download mr-2"></i> Download Sample CSV
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group mb-5">
                            <label class="small font-weight-bold text-muted mb-2">Select Filled CSV File <span class="text-danger">*</span></label>
                            <div class="custom-file css-import-topics-e09a76">
                                <input type="file" name="csv_file" class="form-control border-0 shadow-sm css-import-topics-e8db52" accept=".csv" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="manage-topics.php" class="btn btn-light shadow-sm px-4 css-import-topics-8925b1">Cancel</a>
                            <button type="submit" class="btn btn-primary shadow-sm px-4 css-import-topics-756f01">
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
