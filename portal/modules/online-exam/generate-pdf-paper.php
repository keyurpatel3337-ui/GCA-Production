<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Include TCPDF
require_once PORTAL_PATH . 'vendor/autoload.php';

// Proper HTTP 403 response for unauthorized access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    http_response_code(403);
    exit("403 Forbidden");
}

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if (!$exam_id)
    die("Invalid Exam ID");

try {
    $stmt = $conn->prepare("SELECT e.*, s.stdtext FROM tbl_oes_exams e LEFT JOIN standard s ON e.standard_id = s.stdid WHERE e.id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam)
        die("Exam not found.");

    // FIXED: Order by subject and order_no to ensure continuity
    $stmt_q = $conn->prepare("SELECT eq.order_no, q.*, sub.subject_name FROM tbl_oes_exam_questions eq JOIN tbl_oes_questions q ON eq.question_id = q.id LEFT JOIN tbl_subjects sub ON q.subject_id = sub.id WHERE eq.exam_id = ? ORDER BY sub.subject_name, eq.order_no ASC");
    $stmt_q->execute([$exam_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Get Settings
$show_header = isset($_GET['header']) && $_GET['header'] === 'hide' ? false : true;
$font_size_val = isset($_GET['font_size']) ? $_GET['font_size'] : '12px';
$opt_style = isset($_GET['opt_style']) ? $_GET['opt_style'] : 'grid';
$spacing_type = isset($_GET['spacing']) ? $_GET['spacing'] : 'normal';
$show_footer = isset($_GET['footer']) && $_GET['footer'] === 'show' ? true : false;
$show_marks = isset($_GET['marks']) && $_GET['marks'] === 'hide' ? false : true;

$base_font = (int) $font_size_val;

// Define a custom class to handle margin resets on each page
class ExamPDF extends TCPDF
{
    public function Header()
    {
        // Reset margins at the start of each new page as requested
        $this->SetMargins(15, 15, 15);
        $this->SetX(15);
    }
}

// Create PDF with UTF-8 support using our custom class
$pdf = new ExamPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('GCA OES');
$pdf->SetTitle($exam['title']);
$pdf->setPrintHeader(true); // Enable header callback for margin reset
$pdf->setHeaderMargin(0);   // No actual header content space
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15); // Standard 15mm margins
$pdf->SetAutoPageBreak(TRUE, 15);
// Bug 7: Tagged PDF support is version-dependent. Removed call to undefined method.
if (method_exists($pdf, 'setTagged')) {
    $pdf->setTagged(true);
}
$pdf->AddFont('freeserif', '', 'freeserif.php', true); // Bug 6: Ensure font is embedded
$pdf->SetFont('freeserif', '', $base_font);
$pdf->AddPage();

/**
 * LaTeX Processor
 */
function processLatex($text, $img_h = 24)
{
    if (empty($text))
        return '';

    // Bug 1: Use SVG for real vector rendering
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $pattern = '/(?:\$|&#36;){1,2}(.*?)(?:\$|&#36;){1,2}/s';

    return preg_replace_callback($pattern, function ($matches) use ($img_h) {
        $latex = trim(strip_tags($matches[1])); // Strip tags to avoid breaking LaTeX
        if (empty($latex))
            return '';

        $cache_key = md5($latex);
        $cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'latex_' . $cache_key . '.svg';

        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            $svgData = file_get_contents($cache_file);
        } else {
            $url = "https://latex.codecogs.com/svg.image?" . rawurlencode("\\textstyle " . $latex);

            // Fetch SVG via cURL for better reliability
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $svgData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 && !empty($svgData) && strpos($svgData, '<svg') !== false) {
                file_put_contents($cache_file, $svgData);
            } else {
                return '$' . $latex . '$'; // Fallback to raw LaTeX text if fetch fails
            }
        }

        // Use the physical file path. TCPDF handles .svg files better than data URIs.
        // Use forward slashes for better cross-platform/HTML compatibility
        $normalized_path = str_replace('\\', '/', $cache_file);

        // Manual Aspect Ratio Correction:
        // TCPDF sometimes fails to detect SVG dimensions correctly, causing squashing.
        // We parse the intrinsic width/height from the SVG file to calculate the correct width.
        $w_pt = $img_h; // Default fallback
        if (file_exists($cache_file)) {
            $svg_content = file_get_contents($cache_file);
            if (
                preg_match('/width=[\'"]([0-9.]+)pt[\'"]/', $svg_content, $m_w) &&
                preg_match('/height=[\'"]([0-9.]+)pt[\'"]/', $svg_content, $m_h)
            ) {
                $orig_w = (float) $m_w[1];
                $orig_h = (float) $m_h[1];
                if ($orig_h > 0) {
                    $w_pt = $img_h * ($orig_w / $orig_h);
                }
            }
        }
        $w_pt = round($w_pt, 2);
        $img_h = round($img_h, 2);

        return '<img src="' . $normalized_path . '" width="' . $w_pt . 'pt" height="' . $img_h . 'pt" align="absmiddle" />';
    }, $text);
}

/**
 * Helper to convert BASE_URL paths to local filesystem paths for TCPDF
 */
function urlToPath($html) {
    if (defined('BASE_URL') && defined('UPLOADS_PATH')) {
        $baseUrl = rtrim(BASE_URL, '/');
        $uploadsPath = rtrim(UPLOADS_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        // Convert URL-based uploads to local paths
        $html = str_replace($baseUrl . '/uploads/', $uploadsPath, $html);
    }
    return $html;
}

/**
 * Question Renderer
 */
function getQuestionsHtml($list, $show_marks, $opt_style, $spacing_type, $base_font)
{
    $q_html = '';
    $lh_q = ($spacing_type === 'compact') ? 1.2 : 1.4;
    $lh_o = ($spacing_type === 'compact') ? 1.1 : 1.3;
    $q_margin = ($spacing_type === 'compact') ? '4pt' : (($spacing_type === 'wide') ? '25pt' : '10pt');

    foreach ($list as $q) {
        $q_raw = (string) $q['question_text'];
        $q_raw = preg_replace('/\(Copy\s*\d+\)/i', '', $q_raw);
        // Process LaTeX first
        $text = processLatex($q_raw, 32);
        // Convert URLs to paths for images
        $text = urlToPath($text);
        $marks_text = $show_marks ? '<span style="font-weight:normal; font-size:0.85em; font-style:italic;"> (' . number_format((float) $q['marks'], 2) . ' Marks)</span>' : '';

        // Question Stem
        $q_html .= '<table width="100%" cellpadding="0" style="margin-bottom:2pt; border-collapse:collapse;">
            <tr>
                <td width="30pt" style="font-weight:bold; vertical-align:top; line-height:' . $lh_q . ';">Q.' . (int) $q['order_no'] . '</td>
                <td width="510pt" style="vertical-align:top; line-height:' . $lh_q . ';">' . $text . ' ' . $marks_text . '</td>
            </tr>
        </table>';

        // Bug 2: Fetch and render all options
        $opts = [
            'A' => urlToPath(processLatex($q['option_a'] ?? '', 24)),
            'B' => urlToPath(processLatex($q['option_b'] ?? '', 24)),
            'C' => urlToPath(processLatex($q['option_c'] ?? '', 24)),
            'D' => urlToPath(processLatex($q['option_d'] ?? '', 24))
        ];

        // Options Layout - Use an empty spacer column for left indentation (30pt)
        $q_html .= '<table width="100%" cellpadding="2">
            <tr>
                <td width="30pt">&nbsp;</td>
                <td width="510pt">
                    <table width="100%" cellpadding="1">';

        if ($opt_style === 'list') {
            foreach ($opts as $l => $t) {
                $q_html .= '<tr><td width="25pt" style="font-weight:bold;">(' . $l . ')</td><td width="485pt" style="line-height:' . $lh_o . ';">' . $t . '</td></tr>';
            }
        } elseif ($opt_style === 'inline') {
            $q_html .= '<tr>
                <td width="25%" style="line-height:' . $lh_o . ';"><b>(A)</b> ' . $opts['A'] . '</td>
                <td width="25%" style="line-height:' . $lh_o . ';"><b>(B)</b> ' . $opts['B'] . '</td>
                <td width="25%" style="line-height:' . $lh_o . ';"><b>(C)</b> ' . $opts['C'] . '</td>
                <td width="25%" style="line-height:' . $lh_o . ';"><b>(D)</b> ' . $opts['D'] . '</td>
            </tr>';
        } else { // Grid (2x2)
            $q_html .= '<tr>
                <td width="50%" style="line-height:' . $lh_o . ';"><b>(A)</b> ' . $opts['A'] . '</td>
                <td width="50%" style="line-height:' . $lh_o . ';"><b>(B)</b> ' . $opts['B'] . '</td>
            </tr>
            <tr>
                <td width="50%" style="line-height:' . $lh_o . ';"><b>(C)</b> ' . $opts['C'] . '</td>
                <td width="50%" style="line-height:' . $lh_o . ';"><b>(D)</b> ' . $opts['D'] . '</td>
            </tr>';
        }
        $q_html .= '</table>
                </td>
            </tr>
        </table>';

        // Spacer between questions
        $q_html .= '<div style="line-height:' . $q_margin . ';">&nbsp;</div>';
    }
    return $q_html;
}

$html = '';

// Header
if ($show_header) {
    $html .= '<div style="text-align:center;">
        <h1 style="font-size:20pt; margin:0;">GYANMANJARI CAREER ACADEMY</h1>
        <div style="font-size:14pt; font-weight:bold;">' . htmlspecialchars($exam['title']) . '</div>
    </div><br>';
}

// FIXED: Centered Subject heading
$subject_name = !empty($questions) ? $questions[0]['subject_name'] : 'N/A';
$html .= '<div style="text-align:center; font-weight:bold; font-size:1.2em; margin-bottom:10pt;">Subject: ' . htmlspecialchars((string) $subject_name) . '</div>';

// Meta
// Meta Table
$html .= '<table width="100%" style="border-bottom:1pt solid #000; padding-bottom:5pt;" cellpadding="2">
    <tr>
        <td width="30%"><b>Standard:</b> ' . htmlspecialchars((string) $exam['stdtext'] ?: 'N/A') . '</td>
        <td width="40%" align="center">&nbsp;</td>
        <td width="30%" align="right"><b>Max Marks:</b> ' . number_format((float) $exam['total_marks'], 2) . '</td> 
    </tr>
    <tr>
        <td width="30%"><b>Date:</b> ' . htmlspecialchars(date('d-m-Y', strtotime($exam['start_time']))) . '</td>
        <td width="40%" align="center"><b>Time:</b> ' . htmlspecialchars(date('h:i', strtotime($exam['start_time']))) . ' – ' . htmlspecialchars(date('h:i A', strtotime($exam['end_time']))) . '</td>
        <td width="30%" align="right"><b>Duration:</b> ' . (int) $exam['duration_mins'] . ' Mins</td>
    </tr>
</table><br>';

// Bug 8: Validation for placeholder instructions
$instructions = trim((string) $exam['description']);
if (empty($instructions)) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red;'>
        <b>Error:</b> General Instructions are missing. 
        Please provide valid instructions before generating the PDF.
    </div>");
}

$html .= '<div style="border:0.5pt solid #ccc; padding:10pt; background-color:#f9f9f9;">
    <b>General Instructions:</b><br>' . nl2br(htmlspecialchars($instructions)) . '
</div><br>';

$html .= getQuestionsHtml($questions, $show_marks, $opt_style, $spacing_type, $base_font);

if ($show_footer) {
    $html .= '<br><br><table width="100%" cellpadding="5" style="border-top:1pt solid #eee;">
        <tr>
            <td align="center">____________________<br>Student Signature</td>
            <td align="center">____________________<br>Supervisor Signature</td>
            <td align="center">____________________<br>Principal Signature</td>
        </tr>
    </table>';
}

$html .= '<br><div align="center" style="font-size:10pt;">*** END OF PAPER ***</div>';

$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
$pdf->Output(str_replace(' ', '_', $exam['title']) . '_Paper.pdf', 'I');
