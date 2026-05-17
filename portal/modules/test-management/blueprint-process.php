<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['blueprint_file'])) {
    set_flash_message('error', 'No file uploaded!');
    header('Location: blueprint-upload.php');
    exit;
}

$paper_set_id = $_POST['paper_set_id'] ?? 0;

// Validate paper set
try {
    $op = new Operation();

    $paper_set = $op->selectOne('tbl_paper_sets', ['*'], ['id' => $paper_set_id]);

    if (!$paper_set) {
        set_flash_message('error', 'Invalid paper set!');
        header('Location: blueprint-upload.php');
        exit;
    }
} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: blueprint-upload.php');
    exit;
}

// Handle file upload
$file = $_FILES['blueprint_file'];
$allowed_extensions = ['csv'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    set_flash_message('error', 'Invalid file format. Only CSV files (.csv) are allowed!');
    header('Location: blueprint-upload.php');
    exit;
}

if ($file['size'] > MAX_FILE_SIZE) {
    set_flash_message('error', 'File size exceeds maximum limit of 5MB!');
    header('Location: blueprint-upload.php');
    exit;
}

// Move uploaded file to temp location
$upload_dir = '../uploads/temp/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$temp_file = $upload_dir . uniqid('blueprint_') . '.' . $file_extension;

if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
    set_flash_message('error', 'Failed to upload file!');
    header('Location: blueprint-upload.php');
    exit;
}

// Parse CSV file
try {
    $rows = [];
    if (($handle = fopen($temp_file, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    } else {
        set_flash_message('error', 'Failed to open CSV file!');
        unlink($temp_file);
        header('Location: blueprint-upload.php');
        exit;
    }

    // Parse blueprint data
    $blueprint_data = [];
    $question_mapping = [];
    $current_subject = '';

    foreach ($rows as $row_index => $row) {
        // Skip empty rows
        if (empty(array_filter($row)))
            continue;

        // Row 1: Headers (subject categories)
        if ($row_index == 0) {
            // Store headers for subject mapping
            continue;
        }

        // Row 2: Difficulty levels
        if ($row_index == 1) {
            // Store difficulty level positions
            continue;
        }

        // Data rows (starting from row 3, index 2)
        if ($row_index >= 2) {
            $sr_no = trim($row[0] ?? '');
            $topic_name = trim($row[1] ?? '');

            if (empty($sr_no) || empty($topic_name))
                continue;

            // Detect subject category from row position
            if (strpos($topic_name, 'Maths') !== false || $sr_no <= 6) {
                $current_subject = 'Maths';
            } elseif (strpos($topic_name, 'Science') !== false || strpos($topic_name, 'Matter') !== false) {
                $current_subject = 'Science';
            } elseif (strpos($topic_name, 'Physics') !== false || strpos($topic_name, 'Motion') !== false) {
                $current_subject = 'Physics';
            } elseif (strpos($topic_name, 'Biology') !== false || strpos($topic_name, 'Life') !== false) {
                $current_subject = 'Biology';
            }

            // Extract question numbers
            $low_questions = [];
            $medium_questions = [];
            $high_questions = [];

            // Parse question numbers from columns (adjust based on your Excel structure)
            for ($i = 2; $i < count($row); $i++) {
                $value = trim($row[$i] ?? '');
                if (!empty($value) && $value !== '-' && is_numeric($value)) {
                    // Determine difficulty level based on column position
                    // Columns 2-5: Low, 6-8: Medium, 9-11: High (adjust as needed)
                    if ($i >= 2 && $i <= 5) {
                        $low_questions[] = (int) $value;
                    } elseif ($i >= 6 && $i <= 8) {
                        $medium_questions[] = (int) $value;
                    } elseif ($i >= 9 && $i <= 12) {
                        $high_questions[] = (int) $value;
                    }
                }
            }

            $all_questions = array_merge($low_questions, $medium_questions, $high_questions);

            if (!empty($all_questions)) {
                $blueprint_data[] = [
                    'sr_no' => $sr_no,
                    'subject_category' => $current_subject,
                    'topic_name_english' => $topic_name,
                    'total_questions' => count($all_questions),
                    'low_questions' => $low_questions,
                    'medium_questions' => $medium_questions,
                    'high_questions' => $high_questions
                ];
            }
        }
    }

    // Store in session for preview
    $_SESSION['blueprint_preview'] = [
        'paper_set_id' => $paper_set_id,
        'paper_set_name' => $paper_set['paper_set_name'],
        'data' => $blueprint_data,
        'file_path' => $temp_file
    ];

    // Redirect to preview page
    header('Location: blueprint-preview.php');
    exit;
} catch (Exception $e) {
    set_flash_message('error', 'Error processing file: ' . $e->getMessage());
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    header('Location: blueprint-upload.php');
    exit;
}
