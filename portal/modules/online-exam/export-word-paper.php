<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    die("Unauthorized Access");
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

    $stmt_q = $conn->prepare("SELECT eq.order_no, q.*, sub.subject_name FROM tbl_oes_exam_questions eq JOIN tbl_oes_questions q ON eq.question_id = q.id LEFT JOIN tbl_subjects sub ON q.subject_id = sub.id WHERE eq.exam_id = ? ORDER BY eq.order_no ASC");
    $stmt_q->execute([$exam_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Get settings from URL
$show_header = isset($_GET['header']) && $_GET['header'] === 'hide' ? false : true;
$font_size = isset($_GET['font_size']) ? $_GET['font_size'] : '14pt';
if ($font_size == '10.5pt' || $font_size == '10.5px') $font_size = '14pt';
$opt_style = isset($_GET['opt_style']) ? $_GET['opt_style'] : 'grid';
$spacing_type = isset($_GET['spacing']) ? $_GET['spacing'] : 'normal';
$show_footer = isset($_GET['footer']) && $_GET['footer'] === 'show' ? true : false;
$show_marks = isset($_GET['marks']) && $_GET['marks'] === 'hide' ? false : true;

$q_padding = '10pt';
$rough_space = '0pt';
if ($spacing_type === 'compact') {
    $q_padding = '4pt';
} elseif ($spacing_type === 'wide') {
    $q_padding = '15pt';
    $rough_space = '40pt'; 
}

$filename = str_replace(' ', '_', $exam['title']) . "_Paper.doc";

header("Content-type: application/vnd.ms-word");
header("Content-Disposition: attachment;Filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word'
    xmlns='http://www.w3.org/TR/REC-html40'>

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0.8in;
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: <?= $font_size ?>;
            line-height: 1.4;
        }
        .header { text-align: center; margin-bottom: 15pt; border-bottom: 1pt solid #000; padding-bottom: 10pt; }
        .header h1 { font-size: 18pt; margin: 0; }
        .header h2 { font-size: 14pt; margin: 5pt 0; }
        .meta-info { width: 100%; margin-bottom: 15pt; border-bottom: 1pt solid #000; padding-bottom: 5pt; border-collapse: collapse; }
        .meta-info td { padding: 4pt 0; }
        .instructions { margin-bottom: 15pt; border: 1pt solid #ccc; padding: 10pt; }
        .question-block { padding-bottom: <?= $q_padding ?>; margin-bottom: 5pt; }
        .q-num { font-weight: bold; }
        .q-text-body { font-weight: normal; }
        .q-marks { font-weight: normal; font-size: 0.9em; color: #444; font-style: italic; }
        .options-table { width: 100%; margin-left: 10pt; border-collapse: collapse; }
        .option-cell { padding: 5pt 0; vertical-align: middle; }
        .opt-label { font-weight: bold; padding-right: 8pt; vertical-align: middle; }
        .opt-text { vertical-align: middle; display: inline-block; }
        
        .nested-table { border-collapse: collapse; width: 100%; margin: 10pt 0; }
        .nested-table td, .nested-table th { border: 1pt solid #ccc; padding: 5pt; }

        .signature-table { width: 100%; margin-top: 50pt; border-top: 1pt solid #eee; padding-top: 20pt; }
        .sig-box { text-align: center; width: 33%; }
        .sig-line { border-top: 1pt solid #000; margin: 0 20pt 5pt 20pt; }
        
        .rough-area { height: <?= $rough_space ?>; width: 100%; }
        img.latex-img { vertical-align: middle; margin: 2px 0; }
    </style>
</head>

<body>
    <?php if ($show_header): ?>
    <div class="header">
        <h1>GYANMANJARI CAREER ACADEMY</h1>
        <h2><?= htmlspecialchars((string)$exam['title']) ?></h2>
    </div>
    <?php endif; ?>

    <table class="meta-info">
        <tr>
            <td style="text-align:left; width: 33%;"><b>Standard:</b> <?= htmlspecialchars((string)$exam['stdtext'] ?: 'N/A') ?></td>
            <td style="text-align:center; width: 33%;"><b>Date:</b> <?= date('d-m-Y', strtotime($exam['start_time'])) ?></td>
            <td style="text-align:right; width: 33%;"><b>Max Marks:</b> <?= (float)$exam['total_marks'] ?></td>
        </tr>
        <tr>
            <td style="text-align:left;"><b>Time:</b> <?= date('h:i A', strtotime($exam['start_time'])) ?> - <?= date('h:i A', strtotime($exam['end_time'])) ?></td>
            <td style="text-align:center;"><b>Duration:</b> <?= (int)$exam['duration_mins'] ?> Mins</td>
            <td style="text-align:right;">&nbsp;</td>
        </tr>
    </table>

    <div class="questions">
        <?php
        function renderLatexToImg($text, $img_h = 24)
        {
            if (empty($text)) return '';
            
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $pattern = '/(?:\$|&#36;){1,2}(.*?)(?:\$|&#36;){1,2}/s';
            
            return preg_replace_callback($pattern, function ($matches) use ($img_h) {
                $latex = trim(strip_tags($matches[1]));
                if (empty($latex)) return '';
                
                $cache_key = md5($latex);
                $cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'latex_word_' . $cache_key . '.svg';

                if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
                    $svgData = file_get_contents($cache_file);
                } else {
                    $url = "https://latex.codecogs.com/svg.image?" . rawurlencode("\\textstyle " . $latex);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    $svgData = curl_exec($ch);
                    curl_close($ch);

                    if (!empty($svgData) && strpos($svgData, '<svg') !== false) {
                        file_put_contents($cache_file, $svgData);
                    } else {
                        return '$' . $latex . '$';
                    }
                }

                // Calculate width to maintain aspect ratio
                $w_pt = $img_h; 
                if (preg_match('/width=[\'"]([0-9.]+)pt[\'"]/', $svgData, $m_w) && 
                    preg_match('/height=[\'"]([0-9.]+)pt[\'"]/', $svgData, $m_h)) {
                    $orig_w = (float)$m_w[1];
                    $orig_h = (float)$m_h[1];
                    if ($orig_h > 0) {
                        $w_pt = $img_h * ($orig_w / $orig_h);
                    }
                }

                // For Word HTML export, we can use a high-DPI PNG or SVG. 
                // SVG is supported in Word 2016+ via HTML.
                $base64 = base64_encode($svgData);
                return '<img src="data:image/svg+xml;base64,' . $base64 . '" style="height:' . $img_h . 'pt; width:' . $w_pt . 'pt; vertical-align:middle; margin:2pt;" />';
            }, $text);
        }

        function renderQuestionList($list, $show_marks, $opt_style, $rough_space) {
            $current_subject = '';
            foreach ($list as $q) {
                if ($q['subject_name'] !== $current_subject) {
                    echo "<h3 style='text-align:center; text-decoration:underline; margin-top:25pt; margin-bottom:12pt;'>" . strtoupper(htmlspecialchars((string)$q['subject_name'])) . "</h3>";
                    $current_subject = $q['subject_name'];
                }

                $q_text = renderLatexToImg($q['question_text']);
                $opts = [
                    'A' => renderLatexToImg($q['option_a']),
                    'B' => renderLatexToImg($q['option_b']),
                    'C' => renderLatexToImg($q['option_c']),
                    'D' => renderLatexToImg($q['option_d'])
                ];
                ?>
                <div class="question-block">
                    <div class="q-text">
                        <span class="q-num">Q.<?= $q['order_no'] ?>:</span> 
                        <span class="q-text-body"><?= str_replace('<table', '<table class="nested-table"', $q_text) ?></span>
                        <?php if ($show_marks): ?>
                            <span class="q-marks">(<?= $q['marks'] ?> Marks)</span>
                        <?php endif; ?>
                    </div>
                    <table class="options-table">
                        <?php if ($opt_style === 'list'): ?>
                            <?php foreach ($opts as $l => $t): ?>
                                <tr><td class="option-cell"><span class="opt-label">(<?= $l ?>)</span><span class="opt-text"><?= $t ?></span></td></tr>
                            <?php endforeach; ?>
                        <?php elseif ($opt_style === 'inline'): ?>
                            <tr>
                                <?php foreach ($opts as $l => $t): ?>
                                    <td class="option-cell" style="width:25%"><span class="opt-label">(<?= $l ?>)</span><span class="opt-text"><?= $t ?></span></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php else: // grid (2x2) ?>
                            <tr>
                                <td class="option-cell" style="width:50%"><span class="opt-label">(A)</span><span class="opt-text"><?= $opts['A'] ?></span></td>
                                <td class="option-cell" style="width:50%"><span class="opt-label">(B)</span><span class="opt-text"><?= $opts['B'] ?></span></td>
                            </tr>
                            <tr>
                                <td class="option-cell"><span class="opt-label">(C)</span><span class="opt-text"><?= $opts['C'] ?></span></td>
                                <td class="option-cell"><span class="opt-label">(D)</span><span class="opt-text"><?= $opts['D'] ?></span></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                    <?php if ((float)$rough_space > 0): ?>
                        <div class="rough-area"></div>
                    <?php endif; ?>
                </div>
                <?php
            }
        }

        renderQuestionList($questions, $show_marks, $opt_style, $rough_space);
        ?>
    </div>

    <?php if ($show_footer): ?>
    <table class="signature-table">
        <tr>
            <td class="sig-box"><div class="sig-line"></div>Student Signature</td>
            <td class="sig-box"><div class="sig-line"></div>Supervisor Signature</td>
            <td class="sig-box"><div class="sig-line"></div>Principal Signature</td>
        </tr>
    </table>
    <?php endif; ?>

    <div style="text-align:center; margin-top:40px; font-weight:bold; border-top:1pt solid #000; padding-top:10pt;">
        *** END OF PAPER ***
    </div>
</body>
</html>