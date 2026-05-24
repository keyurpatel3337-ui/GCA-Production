<?php
/**
 * save_bulk_questions.php
 * Handles bulk question import via JSON payload (from CSV or Word import)
 * Target table: tbl_oes_questions
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

// Access control
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload || empty($payload['questions'])) {
    echo json_encode(['success' => false, 'message' => 'No questions data received']);
    exit();
}

$questions = $payload['questions'];
$global_meta = $payload['global_metadata'] ?? [];
$created_by = (int) ($_SESSION['user_id'] ?? 1);

// Global overrides from dropdowns
$global_std_id = !empty($global_meta['standard_id']) ? (int) $global_meta['standard_id'] : null;
$global_grp_id = !empty($global_meta['group_id']) ? (int) $global_meta['group_id'] : null;
$global_sub_id = !empty($global_meta['subject_id']) ? (int) $global_meta['subject_id'] : null;
$global_ch_id = !empty($global_meta['chapter_id']) ? (int) $global_meta['chapter_id'] : null;
$global_tp_id = !empty($global_meta['topic_id']) ? (int) $global_meta['topic_id'] : null;

// --- Pre-fetch Mapping Tables ---
$standards_map = $conn->query("SELECT stdid, stdtext FROM standard")->fetchAll(PDO::FETCH_KEY_PAIR);
$standards_map = array_change_key_case(array_flip($standards_map), CASE_LOWER);

$subjects_map = [];
$sub_res = $conn->query("SELECT id, standard_id, subject_name FROM tbl_subjects WHERE activated = 1 AND is_deleted = 0");
while ($s = $sub_res->fetch()) {
    $subjects_map[$s['standard_id'] . '_' . strtolower(trim($s['subject_name']))] = $s['id'];
}

$chapters_map = [];
$ch_res = $conn->query("SELECT chpid, subid, chapter FROM tbl_chapters WHERE activated = 1 AND is_deleted = 0");
while ($c = $ch_res->fetch()) {
    $chapters_map[$c['subid'] . '_' . strtolower(trim($c['chapter']))] = $c['chpid'];
}

$topics_map = [];
$tp_res = $conn->query("SELECT id, chapter_id, topic_name_english FROM tbl_topics WHERE activated = 1 AND is_deleted = 0");
while ($t = $tp_res->fetch()) {
    $topics_map[$t['chapter_id'] . '_' . strtolower(trim($t['topic_name_english']))] = $t['id'];
}

$q_types_map = $conn->query("SELECT id, type_name FROM tbl_oes_question_types")->fetchAll(PDO::FETCH_KEY_PAIR);
$q_types_map = array_change_key_case(array_flip($q_types_map), CASE_LOWER);

$groups_map = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
$groups_map = array_change_key_case(array_flip($groups_map), CASE_LOWER);

$valid_difficulties = ['Level A', 'Level B', 'Level C', 'Level D', 'Level E'];
$difficulty_aliases = [
    'a' => 'Level A',
    'b' => 'Level B',
    'c' => 'Level C',
    'd' => 'Level D',
    'e' => 'Level E',
    'level a' => 'Level A',
    'level b' => 'Level B',
    'level c' => 'Level C',
    'level d' => 'Level D',
    'level e' => 'Level E',
    'easy' => 'Level A',
    'medium' => 'Level C',
    'hard' => 'Level E',
];

// --- Sanitize HTML (preserve tags, strip dangerous attributes) ---
function sanitize_html(string $html): string
{
    if (empty(trim($html)))
        return '';
    // Strip event attributes (onclick etc) and javascript: hrefs
    $html = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $html);
    $html = preg_replace('/href\s*=\s*(["\'])javascript:.*?\1/i', 'href="#"', $html);
    return $html;
}

// --- Image Processing (Base64 to File) ---
function process_inline_images(string $html): string
{
    if (empty($html))
        return $html;

    // 1. Normalize any absolute paths back to relative paths for portability
    $html = str_replace(UPLOADS_URL . '/', '../../../uploads/', $html);
    $html = str_replace(['../../uploads/', '../../../../uploads/'], '../../../uploads/', $html);

    // 2. Final cleanup of raw or encoded Object strings
    $html = preg_replace('/(?:<|&lt;)\s*Object\s*:[^>;&]+(?:>|&gt;)/i', '', $html);

    // 3. Process Base64 images if present
    if (strpos($html, 'data:image/') !== false) {
        $targetDir = UPLOADS_PATH . 'oes' . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir))
            mkdir($targetDir, 0777, true);

        $html = preg_replace_callback('/<img[^>]+src=["\'](data:image\/[a-zA-Z0-9\+\-\.]+;base64,([a-zA-Z0-9\+\/=\s]+))["\'][^>]*>/i', function ($matches) use ($targetDir) {
            $fullMatch = $matches[0];
            $src = trim($matches[1]);
            $base64 = trim($matches[2]);

            preg_match('/data:image\/([a-zA-Z0-9\+\-\.]+);base64/', $src, $typeMatch);
            $type = $typeMatch[1] ?? 'png';
            if ($type === 'jpeg')
                $type = 'jpg';

            $data = base64_decode($base64);
            if (!$data)
                return $fullMatch;

            $filename = 'qimg_' . uniqid() . '_' . time() . '.' . $type;
            $filepath = $targetDir . $filename;

            if (file_put_contents($filepath, $data)) {
                return str_replace($src, '../../../uploads/oes/' . $filename, $fullMatch);
            }
            return $fullMatch;
        }, $html);
    }

    return $html;
}
// --- Process Questions ---
$stmt = $conn->prepare("
    INSERT INTO tbl_oes_questions 
    (standard_id, subject_id, chapter_id, topic_id, group_id, question_type_id, 
     difficulty, marks, negative_marks, question_text, option_a, option_b, 
     option_c, option_d, correct_option, explanation, video_solution_url, 
     question_text_guj, option_a_guj, option_b_guj, option_c_guj, option_d_guj, explanation_guj,
     solution_image, created_by, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
");

$success_count = 0;
$fail_count = 0;
$fail_reasons = [];

$conn->beginTransaction();

try {
    foreach ($questions as $idx => $q) {
        $row_num = $idx + 1;

        // --- Lookup Standard ---
        $std_text = strtolower(trim($q['standard'] ?? ''));
        $std_id = $standards_map[$std_text] ?? $global_std_id;

        // --- Lookup Subject ---
        $sub_id = 0;
        $sub_text = strtolower(trim($q['subject'] ?? ''));
        if (!empty($sub_text)) {
            $sub_key = ($std_id ? $std_id : '') . '_' . $sub_text;
            $sub_id = $subjects_map[$sub_key] ?? 0;

            if (!$sub_id) {
                // Try without standard constraint
                foreach ($subjects_map as $key => $sid) {
                    if (substr($key, strpos($key, '_') + 1) === $sub_text) {
                        $sub_id = $sid;
                        break;
                    }
                }
            }
        }

        // Fallback to global subject
        if (!$sub_id)
            $sub_id = $global_sub_id;

        if (!$sub_id) {
            $fail_count++;
            $fail_reasons[] = "Row $row_num: Subject " . (!empty($q['subject']) ? "'{$q['subject']}'" : "(Empty)") . " not found.";
            continue;
        }

        // --- Lookup/Create Chapter ---
        $ch_id = $global_ch_id;
        $ch_text = strtolower(trim($q['chapter'] ?? ''));
        if (!empty($ch_text)) {
            $ch_id = $chapters_map[$sub_id . '_' . $ch_text] ?? null;
            if (!$ch_id && $sub_id && $std_id) {
                // Auto-create missing chapter
                $ch_insert = $conn->prepare("
                    INSERT INTO tbl_chapters (subid, standard_id, chapter, chapter_number, activated, created_by, updated_by, updated_on) 
                    VALUES (?, ?, ?, ?, 1, ?, ?, NOW())
                ");
                $ch_name = trim($q['chapter']);
                // Get next chapter number for this subject
                $num_stmt = $conn->prepare("SELECT COALESCE(MAX(chapter_number), 0) + 1 FROM tbl_chapters WHERE subid = ?");
                $num_stmt->execute([$sub_id]);
                $ch_num = $num_stmt->fetchColumn();

                $ch_insert->execute([$sub_id, $std_id, $ch_name, $ch_num, $created_by, $created_by]);
                $ch_id = $conn->lastInsertId();
                $chapters_map[$sub_id . '_' . $ch_text] = $ch_id;
            }
        }

        // --- Lookup/Create Topic ---
        $tp_id = $global_tp_id;
        $tp_text = strtolower(trim($q['topic'] ?? ''));
        if (!empty($tp_text) && $ch_id) {
            $tp_id = $topics_map[$ch_id . '_' . $tp_text] ?? null;
            if (!$tp_id) {
                // Auto-create missing topic
                $tp_insert = $conn->prepare("
                    INSERT INTO tbl_topics (chapter_id, subject_id, standard_id, topic_name_english, activated, created_by, updated_by, updated_on) 
                    VALUES (?, ?, ?, ?, 1, ?, ?, NOW())
                ");
                $tp_name = trim($q['topic']);
                $tp_insert->execute([$ch_id, $sub_id, $std_id, $tp_name, $created_by, $created_by]);
                $tp_id = $conn->lastInsertId();
                $topics_map[$ch_id . '_' . $tp_text] = $tp_id;
            }
        }

        // --- Lookup Group ---
        $group_text = strtolower(trim($q['group_name'] ?? ''));
        $group_id = $groups_map[$group_text] ?? $global_grp_id;

        // --- Question Type (Normalize underscores to hyphens for things like 1_mark -> 1-mark) ---
        $type_text = strtolower(trim($q['question_type'] ?? 'mcq'));
        $type_text = str_replace('_', '-', $type_text);
        $type_id = $q_types_map[$type_text] ?? 1;

        // --- Difficulty ---
        $diff_raw = strtolower(trim($q['difficulty'] ?? 'level a'));
        $difficulty = $difficulty_aliases[$diff_raw] ?? 'Level A';

        // --- Marks ---
        $marks = max(0, (float) ($q['marks'] ?? 1));
        $neg_marks = max(0, (float) ($q['negative_marks'] ?? 0));

        // --- Question Body (Process Images) ---
        $question_text = process_inline_images(sanitize_html(trim($q['question_text'] ?? '')));
        if (empty(strip_tags($question_text)) && strpos($question_text, '<img') === false) {
            $fail_count++;
            $fail_reasons[] = "Row $row_num: Question text is empty.";
            continue;
        }

        // --- Options (Process Images) ---
        $is_mcq = ($type_id == 1);
        $option_a = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_a'] ?? ''))) : '';
        $option_b = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_b'] ?? ''))) : '';
        $option_c = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_c'] ?? ''))) : '';
        $option_d = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_d'] ?? ''))) : '';

        // --- Gujarati Inputs (Process Images) ---
        $question_text_guj = process_inline_images(sanitize_html(trim($q['question_text_guj'] ?? '')));
        $option_a_guj = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_a_guj'] ?? ''))) : '';
        $option_b_guj = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_b_guj'] ?? ''))) : '';
        $option_c_guj = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_c_guj'] ?? ''))) : '';
        $option_d_guj = $is_mcq ? process_inline_images(sanitize_html(trim($q['option_d_guj'] ?? ''))) : '';
        $explanation_guj = process_inline_images(sanitize_html(trim($q['explanation_guj'] ?? '')));

        // --- Correct Option ---
        $correct_option = $is_mcq ? strtoupper(trim($q['correct_option'] ?? '')) : null;
        if (empty($correct_option))
            $correct_option = null; // Allow empty

        if ($is_mcq && $correct_option && !in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            $fail_count++;
            $fail_reasons[] = "Row $row_num: Correct option '{$correct_option}' is invalid (must be A/B/C/D).";
            continue;
        }

        // --- Explanation & Video ---
        $explanation = process_inline_images(sanitize_html(trim($q['explanation'] ?? '')));
        $video_url = filter_var(trim($q['video_solution_url'] ?? ''), FILTER_SANITIZE_URL) ?: null;
        $solution_image = trim($q['solution_image'] ?? '') ?: null;

        // --- Insert ---
        $stmt->execute([
            $std_id,
            $sub_id,
            $ch_id,
            $tp_id,
            $group_id,
            $type_id,
            $difficulty,
            $marks,
            $neg_marks,
            $question_text,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $correct_option,
            $explanation,
            $video_url,
            $question_text_guj,
            $option_a_guj,
            $option_b_guj,
            $option_c_guj,
            $option_d_guj,
            $explanation_guj,
            $solution_image,
            $created_by
        ]);
        $success_count++;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'imported' => $success_count,
        'failed' => $fail_count,
        'fail_reasons' => array_slice($fail_reasons, 0, 20), // return first 20 errors
        'total' => count($questions),
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("[OES Bulk Import] " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error during import. All changes rolled back.',
        'detail' => $e->getMessage(),
    ]);
}

$conn = null;
?>