<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Include mPDF via composer autoload
require_once PORTAL_PATH . 'vendor/autoload.php';

// Access Control
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    http_response_code(403);
    exit("403 Forbidden");
}

// Temporary cache clear for LaTeX images (Run once, then can be removed)
// array_map('unlink', glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'latex_*.svg'));

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if (!$exam_id)
    die("Invalid Exam ID");

try {
    $stmt = $conn->prepare("SELECT e.*, s.stdtext FROM tbl_oes_exams e LEFT JOIN standard s ON e.standard_id = s.stdid WHERE e.id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam)
        die("Exam not found.");

    // Fetch Questions ordered by subject and order_no
    $stmt_q = $conn->prepare("SELECT eq.order_no, q.*, sub.subject_name FROM tbl_oes_exam_questions eq JOIN tbl_oes_questions q ON eq.question_id = q.id LEFT JOIN tbl_subjects sub ON q.subject_id = sub.id WHERE eq.exam_id = ? ORDER BY sub.subject_name, eq.order_no ASC");
    $stmt_q->execute([$exam_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// --- Professional Standard Parameters (Optimized by AI) ---
$paper_size = 'A4';
$orientation = 'P';
$num_cols = isset($_GET['cols']) ? (int) $_GET['cols'] : 1; // Default 1 column
$margin_type = 'normal';   // 15mm margins
$font_fam = 'serif';    // Academic serif font
$font_size = '12pt';     // Standard exam font size
$math_scale = 32;         // Base scale for smart math rendering
$img_scale = 120;        // Balanced image scale
$img_pos = 'below';    // Safe image positioning
$opt_style = 'grid';     // Space-efficient ABCD grid
$show_marks = true;       // Always show marks

// Margin logic (mm)
$m_val = 15;
if ($margin_type === 'narrow')
    $m_val = 10;
elseif ($margin_type === 'wide')
    $m_val = 25;

/**
 * LaTeX Renderer using CodeCogs (SVG)
 */
function processLatex($text, $img_h = 32)
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Twin-layer degree normalization: Clean ring operator U+2218 to standard degree symbol U+00B0 in plain text
    $text = str_replace(
        ["\xe2\x88\x98", "\xc2\xba", '∘', 'º'],
        '°',
        $text
    );

    $pattern = '/\${2}(.*?)\${2}|\$([^$]+?)\$|(\\\\begin\{[a-zA-Z\*]+\}.*?\\\\end\{[a-zA-Z\*]+\})/s';

    return preg_replace_callback($pattern, function ($matches) use ($img_h) {
        $latex = !empty($matches[1]) ? $matches[1]
            : (!empty($matches[2]) ? $matches[2]
                : (!empty($matches[3]) ? $matches[3] : ''));
        $latex = trim(strip_tags($latex));
        
        // LaTeX layer normalization: Convert degree circle characters inside math mode to standard LaTeX \circ command
        $latex = str_replace(
            ['°', '∘', 'º', '\\xe2\\x88\\x98', '\\xc2\\xba'],
            '\\circ',
            $latex
        );

        if (empty($latex))
            return '';

        // Smart height based on content type
        if (strpos($latex, '\\begin') !== false) {
            // Matrix — tallest
            $final_h = $img_h * 0.9;
            $prefix = "";
        } elseif (strpos($latex, '\\frac') !== false) {
            // Fraction — medium
            $final_h = $img_h * 0.75;
            $prefix = "\\textstyle ";
        } else {
            // Simple inline (angles, numbers) — small
            $final_h = $img_h * 0.5;
            if ($final_h < 10)
                $final_h = 10;
            $prefix = "\\textstyle ";
        }

        $cache_key = md5($latex);
        $cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'latex_' . $cache_key . '.svg';

        if (!file_exists($cache_file)) {
            $url = "https://latex.codecogs.com/svg.image?" . rawurlencode($prefix . $latex);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $svg_content = curl_exec($ch);
            curl_close($ch);
            if (!empty($svg_content) && strpos($svg_content, '<svg') !== false) {
                file_put_contents($cache_file, $svg_content);
            } else {
                return '<span class="css-generate-pdf-paper-b64e6d">$' . $latex . '$</span>';
            }
        }

        return '<img src="' . $cache_file . '" class="css-generate-pdf-paper-7eaa82">';
    }, $text);
}

/**
 * Handle relative paths for images
 */
function fixImagePaths($html)
{
    $uploadsPath = rtrim(UPLOADS_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return preg_replace('/(\.\.\/)+uploads\//', $uploadsPath, $html);
}

// Build HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    
</head>
<body>
    <div class="header">
        <h1>' . strtoupper($exam['title']) . '</h1>
        <h2>' . $exam['stdtext'] . ' - Exam Paper</h2>
    </div>
    
    <table class="meta-info">
        <tr>
            <td><strong>Date:</strong> ' . (isset($exam['exam_date']) ? date('d-m-Y', strtotime($exam['exam_date'])) : (isset($exam['start_time']) ? date('d-m-Y', strtotime($exam['start_time'])) : (isset($exam['created_at']) ? date('d-m-Y', strtotime($exam['created_at'])) : 'N/A'))) . '</td>
            <td align="right"><strong>Time:</strong> ' . (isset($exam['duration_mins']) ? $exam['duration_mins'] : (isset($exam['exam_duration']) ? $exam['exam_duration'] : (isset($exam['duration']) ? $exam['duration'] : 'N/A'))) . ' Mins</td>
        </tr>
        <tr>
            <td><strong>Total Marks:</strong> ' . $exam['total_marks'] . '</td>
            <td align="right"><strong>Roll No:</strong> __________</td>
        </tr>
    </table>

    ' . ($num_cols > 1 ? '<columns column-count="' . $num_cols . '" column-gap="10" />' : '') . '
';

$current_subject = '';
foreach ($questions as $q) {
    if ($q['subject_name'] !== $current_subject) {
        if ($current_subject !== '') {
            $html .= ($num_cols > 1 ? '</columns>' : '');
        }
        $html .= '<div class="subject-title">' . strtoupper($q['subject_name']) . '</div>';
        if ($num_cols > 1) {
            $html .= '<columns column-count="' . $num_cols . '" column-gap="10" />';
        }
        $current_subject = $q['subject_name'];
    }

    $q_text = processLatex($q['question_text'], $math_scale);
    $q_text = fixImagePaths($q_text);
    $marks = $show_marks ? '<span class="q-marks">(' . number_format((float) $q['marks'], 2) . ' Marks)</span>' : '';

    $html .= '<div class="question-block">';

    if (!empty($q['solution_image'])) {
        $img_path = UPLOADS_PATH . '/oes/' . $q['solution_image'];
        if (file_exists($img_path)) {
            if ($img_pos === 'right') {
                // Use a table to ensure side-by-side layout for float-right effect
                $html .= '<table class="q-row-table">
                            <tr>
                                <td class="q-num-cell">' . $q['order_no'] . '.</td>
                                <td class="q-text-cell">' . $q_text . ' ' . $marks . '</td>
                                <td class="q-img-cell">
                                    <img src="' . $img_path . '" class="css-generate-pdf-paper-359463">
                                </td>
                            </tr>
                          </table>';
            } else {
                // Standard block layout
                $html .= '<table class="q-row-table">
                            <tr>
                                <td class="q-num-cell">' . $q['order_no'] . '.</td>
                                <td class="q-text-cell">' . $q_text . ' ' . $marks . '</td>
                            </tr>
                          </table>
                          <div class="css-generate-pdf-paper-af3b25">
                            <img src="' . $img_path . '" class="css-generate-pdf-paper-1ec888">
                          </div>';
            }
        } else {
            // No image exists
            $html .= '<table class="q-row-table">
                        <tr>
                            <td class="q-num-cell">' . $q['order_no'] . '.</td>
                            <td class="q-text-cell">' . $q_text . ' ' . $marks . '</td>
                        </tr>
                      </table>';
        }
    } else {
        // No image column value
        $html .= '<table class="q-row-table">
                    <tr>
                        <td class="q-num-cell">' . $q['order_no'] . '.</td>
                        <td class="q-text-cell">' . $q_text . ' ' . $marks . '</td>
                    </tr>
                  </table>';
    }

    // Options
    // Detect content type across all options
    $all_opts_text = $q['option_a'] . $q['option_b'] . $q['option_c'] . $q['option_d'];

    if (strpos($all_opts_text, '\\begin') !== false) {
        $opt_scale = $math_scale * 1.0;  // matrix
    } elseif (strpos($all_opts_text, '\\frac') !== false) {
        $opt_scale = $math_scale * 0.85; // fractions — readable size
    } else {
        $opt_scale = $math_scale * 0.5;  // simple angles/numbers
    }

    $opts = [
        'A' => fixImagePaths(processLatex($q['option_a'], $opt_scale)),
        'B' => fixImagePaths(processLatex($q['option_b'], $opt_scale)),
        'C' => fixImagePaths(processLatex($q['option_c'], $opt_scale)),
        'D' => fixImagePaths(processLatex($q['option_d'], $opt_scale))
    ];

    if ($opt_style === 'list') {
        $html .= '<div class="options-container">';
        foreach ($opts as $lbl => $val) {
            $html .= '<div class="option-item css-generate-pdf-paper-6d61bb">
                        <table class="css-generate-pdf-paper-250fc7">
                            <tr>
                                <td class="css-generate-pdf-paper-f58c7d">(' . $lbl . ')</td>
                                <td class="css-generate-pdf-paper-392387">' . $val . '</td>
                            </tr>
                        </table>
                      </div>';
        }
        $html .= '</div>';
    } elseif ($opt_style === 'inline') {
        $html .= '<table class="inline-options"><tr>';
        foreach ($opts as $lbl => $val) {
            $html .= '<td class="css-generate-pdf-paper-19f684">
                        <table class="css-generate-pdf-paper-250fc7">
                            <tr>
                                <td class="css-generate-pdf-paper-f58c7d">(' . $lbl . ')</td>
                                <td class="css-generate-pdf-paper-392387">' . $val . '</td>
                            </tr>
                        </table>
                      </td>';
        }
        $html .= '</tr></table>';
    } else { // grid
        $html .= '<table class="grid-options">
                    <tr>';
        foreach (['A', 'B'] as $lbl) {
            $val = $opts[$lbl];
            $html .= '<td class="css-generate-pdf-paper-2e4ad7">
                        <table class="css-generate-pdf-paper-250fc7">
                            <tr>
                                <td class="css-generate-pdf-paper-f58c7d">(' . $lbl . ')</td>
                                <td class="css-generate-pdf-paper-392387">' . $val . '</td>
                            </tr>
                        </table>
                      </td>';
        }
        $html .= '</tr>
                    <tr>';
        foreach (['C', 'D'] as $lbl) {
            $val = $opts[$lbl];
            $html .= '<td class="css-generate-pdf-paper-2e4ad7">
                        <table class="css-generate-pdf-paper-250fc7">
                            <tr>
                                <td class="css-generate-pdf-paper-f58c7d">(' . $lbl . ')</td>
                                <td class="css-generate-pdf-paper-392387">' . $val . '</td>
                            </tr>
                        </table>
                      </td>';
        }
        $html .= '</tr>
                  </table>';
    }

    $html .= '</div>';
}

$html .= ($num_cols > 1 ? '</columns>' : '') . '
    <div class="css-generate-pdf-paper-afd679">
        *** END OF PAPER ***
    </div>
</body>
</html>';

// --- Generate mPDF ---
try {
    // Retrieve default configurations
    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    // Support both standard vendor directories and local assets directory
    $customFontDir = PORTAL_PATH . 'assets/fonts';

    // Map freeserif and freesans to use Shruti
    $fontData['freeserif'] = [
        'R' => 'shruti.ttf',
        'useOTL' => 0xFF, // Full OTL (GPOS & GSUB) for correct glyph reordering
        'useKashida' => 0xFF,
    ];
    $fontData['freesans'] = [
        'R' => 'shruti.ttf',
        'useOTL' => 0xFF,
        'useKashida' => 0xFF,
    ];
    // Support shruti directly
    $fontData['shruti'] = [
        'R' => 'shruti.ttf',
        'useOTL' => 0xFF,
        'useKashida' => 0xFF,
    ];
    // Also support notoserifgujarati and notosansgujarati keys mapping to Shruti
    $fontData['notoserifgujarati'] = [
        'R' => 'shruti.ttf',
        'useOTL' => 0xFF,
        'useKashida' => 0xFF,
    ];
    $fontData['notosansgujarati'] = [
        'R' => 'shruti.ttf',
        'useOTL' => 0xFF,
        'useKashida' => 0xFF,
    ];

    // Define Times New Roman mapping using local project files
    $fontData['timesnewroman'] = [
        'R' => 'times.ttf',
        'B' => 'timesbd.ttf',
        'I' => 'timesi.ttf',
        'BI' => 'timesbi.ttf',
    ];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => $paper_size . ($orientation === 'L' ? '-L' : ''),
        'margin_left' => $m_val,
        'margin_right' => $m_val,
        'margin_top' => $m_val,
        'margin_bottom' => $m_val,
        'default_font_size' => (int) $font_size,
        'fontDir' => array_merge($fontDirs, [
            $customFontDir,
            PORTAL_PATH . 'vendor/mpdf/mpdf/ttfonts'
        ]),
        'fontdata' => $fontData,
        'default_font' => 'timesnewroman',
        'autoScriptToLang' => true,
        'autoLangToFont' => true,
    ]);

    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    $mpdf->Output($exam['title'] . '_Paper.pdf', 'D');
} catch (Exception $e) {
    die("mPDF Error: " . $e->getMessage());
}
