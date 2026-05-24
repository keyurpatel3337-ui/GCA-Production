<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Include PHPWord via composer autoload
require_once PORTAL_PATH . 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Table as TableStyle;

// Access Control
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    die("Unauthorized Access");
}

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if (!$exam_id) die("Invalid Exam ID");

try {
    $stmt = $conn->prepare("SELECT e.*, s.stdtext FROM tbl_oes_exams e LEFT JOIN standard s ON e.standard_id = s.stdid WHERE e.id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam) die("Exam not found.");

    $stmt_q = $conn->prepare("SELECT eq.order_no, q.*, sub.subject_name FROM tbl_oes_exam_questions eq JOIN tbl_oes_questions q ON eq.question_id = q.id LEFT JOIN tbl_subjects sub ON q.subject_id = sub.id WHERE eq.exam_id = ? ORDER BY sub.subject_name, eq.order_no ASC");
    $stmt_q->execute([$exam_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// --- Parameters ---
$paper_size = isset($_GET['size']) ? strtoupper($_GET['size']) : 'A4'; // A4, LEGAL, LETTER
$orientation = isset($_GET['orientation']) && $_GET['orientation'] === 'L' ? 'landscape' : 'portrait';
$num_cols = isset($_GET['cols']) ? (int)$_GET['cols'] : 1;
$margin_type = isset($_GET['margins']) ? $_GET['margins'] : 'normal';
$font_fam = isset($_GET['font_family']) ? ($_GET['font_family'] === 'sans' ? 'Arial' : 'Times New Roman') : 'Times New Roman';
$font_size = isset($_GET['font_size']) ? (int)str_replace('px', '', $_GET['font_size']) : 12;
$math_scale = isset($_GET['math_scale']) ? (int)$_GET['math_scale'] : 32;
$img_scale = isset($_GET['img_scale']) ? (int)$_GET['img_scale'] : 150;
$img_pos = isset($_GET['img_pos']) ? $_GET['img_pos'] : 'below';
$opt_style = isset($_GET['opt_style']) ? $_GET['opt_style'] : 'grid';
$show_marks = isset($_GET['marks']) && $_GET['marks'] === 'hide' ? false : true;

// --- Initialize PHPWord ---
$phpWord = new PhpWord();

// Set Document Settings
$sectionSettings = [
    'paperSize' => $paper_size,
    'orientation' => $orientation,
    'marginLeft' => Converter::cmToTwip($margin_type === 'narrow' ? 1.0 : ($margin_type === 'wide' ? 2.5 : 1.5)),
    'marginRight' => Converter::cmToTwip($margin_type === 'narrow' ? 1.0 : ($margin_type === 'wide' ? 2.5 : 1.5)),
    'marginTop' => Converter::cmToTwip($margin_type === 'narrow' ? 1.0 : ($margin_type === 'wide' ? 2.5 : 1.5)),
    'marginBottom' => Converter::cmToTwip($margin_type === 'narrow' ? 1.0 : ($margin_type === 'wide' ? 2.5 : 1.5)),
];
$section = $phpWord->addSection($sectionSettings);

// --- Styles ---
$phpWord->addTitleStyle(1, ['name' => $font_fam, 'size' => 18, 'bold' => true, 'color' => '000000'], ['alignment' => 'center', 'spaceAfter' => 120]);
$phpWord->addFontStyle('BaseFont', ['name' => $font_fam, 'size' => $font_size]);
$phpWord->addFontStyle('BoldFont', ['name' => $font_fam, 'size' => $font_size, 'bold' => true]);
$phpWord->addFontStyle('MarksFont', ['name' => $font_fam, 'size' => $font_size - 2, 'italic' => true]);
$phpWord->addParagraphStyle('P_Centered', ['alignment' => 'center', 'spaceAfter' => 60]);
$phpWord->addParagraphStyle('P_Right', ['alignment' => 'right']);
$phpWord->addParagraphStyle('Q_Block', ['spaceBefore' => 120, 'spaceAfter' => 60, 'keepNext' => true]);

// --- Header ---
$section->addText(strtoupper($exam['title']), ['name' => $font_fam, 'size' => 20, 'bold' => true], 'P_Centered');
$section->addText($exam['stdtext'] . ' - Exam Paper', ['name' => $font_fam, 'size' => 14, 'bold' => true], 'P_Centered');
$section->addLine(['weight' => 1, 'width' => 450, 'height' => 0, 'color' => '000000']);

// Meta Info Table
$table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
$table->addRow();
$e_date = isset($exam['exam_date']) ? date('d-m-Y', strtotime($exam['exam_date'])) : (isset($exam['start_time']) ? date('d-m-Y', strtotime($exam['start_time'])) : (isset($exam['created_at']) ? date('d-m-Y', strtotime($exam['created_at'])) : 'N/A'));
$table->addCell()->addText("Date: " . $e_date, 'BaseFont');
$e_duration = isset($exam['duration_mins']) ? $exam['duration_mins'] : (isset($exam['exam_duration']) ? $exam['exam_duration'] : (isset($exam['duration']) ? $exam['duration'] : 'N/A'));
$table->addCell()->addText("Time: " . $e_duration . " Mins", 'BaseFont', 'P_Right');
$table->addRow();
$table->addCell()->addText("Total Marks: " . $exam['total_marks'], 'BaseFont');
$table->addCell()->addText("Roll No: __________", 'BaseFont', 'P_Right');
$section->addLine(['weight' => 0.5, 'width' => 450, 'height' => 0, 'color' => '000000']);

/**
 * LaTeX Renderer for Word (Converts to Image and returns path)
 */
function latexToImage($latex, $img_h = 24) {
    $latex = trim(strip_tags(html_entity_decode($latex, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (empty($latex)) return null;

    $final_h = $img_h * 0.6; // Proportional scaling for Word
    if ($final_h < 14) $final_h = 14; 

    $cache_key = md5($latex);
    $cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'latex_word_' . $cache_key . '.png';

    if (!file_exists($cache_file)) {
        // Use cURL as file_get_contents might be disabled (allow_url_fopen=0)
        $url = "https://latex.codecogs.com/png.latex?\\dpi{300}\\bg_white " . rawurlencode($latex);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $img_data = curl_exec($ch);
        curl_close($ch);
        if ($img_data) file_put_contents($cache_file, $img_data);
    }
    
    if (file_exists($cache_file)) {
        list($width, $height) = getimagesize($cache_file);
        $w_px = $final_h;
        if ($height > 0) $w_px = $final_h * ($width / $height);
        return ['path' => $cache_file, 'height' => $final_h, 'width' => $w_px];
    }
    return null;
}

/**
 * Custom HTML Parser to add content to Section
 */
function addHtmlContent($container, $html, $fontStyle, $mathScale) {
    // Basic parser for mixed text and LaTeX/Images
    $html = preg_replace('/(\.\.\/)+uploads\//', BASE_URL . '/uploads/', $html);
    $pattern = '/(?:\$|&#36;){1,2}(.*?)(?:\$|&#36;){1,2}|(\\\\begin\{[a-z\*]+\}.*?\\\\end\{[a-z\*]+\})/s';
    
    $parts = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $className = is_object($container) ? get_class($container) : '';
    if ($container instanceof \PhpOffice\PhpWord\Element\TextRun || strpos($className, 'TextRun') !== false) {
        $textRun = $container;
    } else {
        $textRun = $container->addTextRun();
    }
    
    foreach ($parts as $index => $part) {
        if (empty($part)) continue;
        
        // If it was captured by regex (every 2nd or 3rd part depending on split)
        // But since we have multiple capture groups, let's just check if it looks like LaTeX
        if (strpos($part, '\\') !== false || (isset($parts[$index-1]) && strpos($html, $parts[$index-1].$part) === false)) {
            $img = latexToImage($part, $mathScale);
            if ($img) {
                $textRun->addImage($img['path'], ['height' => $img['height'], 'width' => $img['width'], 'wrappingStyle' => 'inline']);
                continue;
            }
        }
        
        // Handle normal text and <img> tags inside part
        if (preg_match_all('/<img[^>]+src="([^">]+)"/i', $part, $imgs)) {
            $textParts = preg_split('/<img[^>]+>/i', $part);
            foreach ($textParts as $ti => $tp) {
                $textRun->addText(strip_tags($tp), $fontStyle);
                if (isset($imgs[1][$ti])) {
                    $src = $imgs[1][$ti];
                    // Convert URL back to local path if it belongs to our domain
                    $localPath = str_replace(BASE_URL . '/uploads/', UPLOADS_PATH . '/', $src);
                    if (file_exists($localPath)) {
                        $textRun->addImage($localPath, ['height' => 50, 'wrappingStyle' => 'inline']);
                    }
                }
            }
        } else {
            $textRun->addText(strip_tags($part), $fontStyle);
        }
    }
}

// --- Questions ---
$q_sectionSettings = $sectionSettings;
if ($num_cols > 1) {
    $q_sectionSettings['colsNum'] = $num_cols;
    $q_sectionSettings['colsSpace'] = Converter::cmToTwip(0.8);
    $q_sectionSettings['breakType'] = 'continuous';
    $q_section = $phpWord->addSection($q_sectionSettings);
} else {
    $q_section = $section;
}

$current_subject = '';
foreach ($questions as $q) {
    if ($q['subject_name'] !== $current_subject) {
        $q_section->addText(strtoupper($q['subject_name']), ['name' => $font_fam, 'size' => 14, 'bold' => true, 'underline' => 'single'], 'P_Centered');
        $current_subject = $q['subject_name'];
    }

    // Use Table for Right positioning or standard Flow for Below positioning
    if (!empty($q['solution_image']) && $img_pos === 'right') {
        $img_path = UPLOADS_PATH . '/oes/' . $q['solution_image'];
        if (file_exists($img_path)) {
            $table = $q_section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
            $table->addRow();
            $q_cell = $table->addCell(Converter::cmToTwip(14.0)); // Approx 80% width
            $q_run = $q_cell->addTextRun();
            $q_run->addText($q['order_no'] . ". ", 'BoldFont');
            addHtmlContent($q_cell, $q['question_text'], 'BaseFont', $math_scale);
            if ($show_marks) {
                $q_cell->addText(" (" . number_format((float)$q['marks'], 2) . " Marks)", 'MarksFont');
            }
            
            $img_cell = $table->addCell();
            $img_cell->addImage($img_path, ['height' => $img_scale, 'alignment' => 'right']);
        } else {
            // No image found
            $q_block = $q_section->addTextRun('Q_Block');
            $q_block->addText($q['order_no'] . ". ", 'BoldFont');
            addHtmlContent($q_section, $q['question_text'], 'BaseFont', $math_scale);
            if ($show_marks) { $q_section->addText(" (" . number_format((float)$q['marks'], 2) . " Marks)", 'MarksFont'); }
        }
    } else {
        // Standard Below or No Image logic
        $q_block = $q_section->addTextRun('Q_Block');
        $q_block->addText($q['order_no'] . ". ", 'BoldFont');
        addHtmlContent($q_section, $q['question_text'], 'BaseFont', $math_scale);
        if ($show_marks) {
            $q_section->addText(" (" . number_format((float)$q['marks'], 2) . " Marks)", 'MarksFont');
        }

        if (!empty($q['solution_image']) && $img_pos === 'below') {
            $img_path = UPLOADS_PATH . '/oes/' . $q['solution_image'];
            if (file_exists($img_path)) {
                $q_section->addImage($img_path, ['height' => $img_scale, 'alignment' => 'center']);
            }
        }
    }

    // Options
    $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
    
    if ($opt_style === 'list') {
        foreach ($opts as $lbl => $val) {
            $optRun = $q_section->addTextRun(['marginLeft' => 400]);
            $optRun->addText("($lbl) ", 'BoldFont');
            addHtmlContent($optRun, $val, 'BaseFont', $math_scale * 0.8);
        }
    } else {
        $table = $q_section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        if ($opt_style === 'inline') {
            $table->addRow();
            foreach ($opts as $lbl => $val) {
                $cell = $table->addCell();
                $optRun = $cell->addTextRun();
                $optRun->addText("($lbl) ", 'BoldFont');
                addHtmlContent($optRun, $val, 'BaseFont', $math_scale * 0.8);
            }
        } else { // grid
            $table->addRow();
            $cells = ['A', 'B'];
            foreach ($cells as $lbl) {
                $cell = $table->addCell();
                $optRun = $cell->addTextRun();
                $optRun->addText("($lbl) ", 'BoldFont');
                addHtmlContent($optRun, $opts[$lbl], 'BaseFont', $math_scale * 0.8);
            }
            $table->addRow();
            $cells = ['C', 'D'];
            foreach ($cells as $lbl) {
                $cell = $table->addCell();
                $optRun = $cell->addTextRun();
                $optRun->addText("($lbl) ", 'BoldFont');
                addHtmlContent($optRun, $opts[$lbl], 'BaseFont', $math_scale * 0.8);
            }
        }
    }
}

$q_section->addTextBreak(2);
$q_section->addText("*** END OF PAPER ***", 'BoldFont', 'P_Centered');

// --- Output ---
$filename = str_replace(' ', '_', $exam['title']) . "_Paper.docx";

header("Content-Description: File Transfer");
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit();