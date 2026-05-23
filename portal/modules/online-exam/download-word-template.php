<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
/**
 * download-word-template.php
 * Generates and downloads a Word (.docx) template for bulk question import.
 * No external library required — uses PHP's built-in ZipArchive + raw OOXML.
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    http_response_code(403);
    exit('Access denied.');
}

// Get template type
$template_type = isset($_GET['type']) ? $_GET['type'] : 'full';

// ---- Build table rows XML ----

// Header row style
$thStyle = 'w:fill="1F497D" w:color="auto"'; // dark blue header bg

if ($template_type === 'simple') {
    // Column headers (11 columns)
    $headers = [
        'Question Text',
        'Option A',
        'Option B',
        'Option C',
        'Option D',
        'Correct Answer',
        'Question Text (Gujarati)',
        'Option A (Gujarati)',
        'Option B (Gujarati)',
        'Option C (Gujarati)',
        'Option D (Gujarati)',
    ];
} else {
    // Column headers (22 columns)
    $headers = [
        'Standard',
        'Group',
        'Subject',
        'Chapter',
        'Topic',
        'Question Type',
        'Difficulty Level',
        'Question Text',
        'Option A',
        'Option B',
        'Option C',
        'Option D',
        'Correct Answer',
        'Solution Text',
        'Solution Video Link',
        'Solution Image',
        'Question Text (Gujarati)',
        'Option A (Gujarati)',
        'Option B (Gujarati)',
        'Option C (Gujarati)',
        'Option D (Gujarati)',
        'Solution Text (Gujarati)',
    ];
}

// ---- Fetch Actual Data for Samples ----
$sampleRows = [];
try {
    // Fetch some real subjects with their standards
    $stmt = $conn->query("
        SELECT s.stdnumber, s.stdtext, sub.subject_name 
        FROM tbl_subjects sub
        JOIN standard s ON sub.standard_id = s.stdid
        WHERE sub.activated = 1 AND sub.is_deleted = 0
        LIMIT 3
    ");
    $actual_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch a real group
    $grp_row = $conn->query("SELECT group_name FROM tbl_group WHERE is_active = 1 LIMIT 1")->fetch();
    $sample_grp = $grp_row ? $grp_row['group_name'] : 'General';

    // Fetch real question types
    $type_stmt = $conn->query("SELECT type_name FROM tbl_oes_question_types WHERE status = 1 LIMIT 5");
    $actual_types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($actual_types)) $actual_types = ['MCQ'];

    foreach ($actual_data as $idx => $data) {
        $q_type = $actual_types[$idx % count($actual_types)];
        
        // Map standard text to 11th, 12th, Re-neet
        $std_name = '11th';
        $std_num = (int)$data['stdnumber'];
        if ($std_num === 11) {
            $std_name = '11th';
        } elseif ($std_num === 12) {
            $std_name = '12th';
        } elseif ($std_num === 13) {
            $std_name = 'Re-neet';
        } else {
            $txt = strtolower($data['stdtext']);
            if (strpos($txt, '11') !== false) {
                $std_name = '11th';
            } elseif (strpos($txt, '12') !== false) {
                $std_name = '12th';
            } elseif (strpos($txt, 'reneet') !== false || strpos($txt, 're-neet') !== false) {
                $std_name = 'Re-neet';
            } else {
                $std_name = '11th';
            }
        }

        if ($template_type === 'simple') {
            $sampleRows[] = [
                'Example question for ' . $data['subject_name'] . ' (' . $q_type . ')...',
                $q_type === 'MCQ' ? 'Option A' : '',
                $q_type === 'MCQ' ? 'Option B' : '',
                $q_type === 'MCQ' ? 'Option C' : '',
                $q_type === 'MCQ' ? 'Option D' : '',
                $q_type === 'MCQ' ? 'A' : '',
                'દરેક પ્રશ્ન માટે અહીં ગુજરાતી લખાણ આવશે (' . $q_type . ')...',
                $q_type === 'MCQ' ? 'વિકલ્પ A' : '',
                $q_type === 'MCQ' ? 'વિકલ્પ B' : '',
                $q_type === 'MCQ' ? 'વિકલ્પ C' : '',
                $q_type === 'MCQ' ? 'વિકલ્પ D' : '',
            ];
        } else {
            $sampleRows[] = [
                $std_name,
                $sample_grp,
                $data['subject_name'],
                'Chapter 1',
                'General Topic',
                $q_type,
                'Level A',
                'Example question for ' . $data['subject_name'] . ' (' . $q_type . ')...',
                $q_type === 'MCQ' ? 'Option A' : '',
                $q_type === 'MCQ' ? 'Option B' : '',
                $q_type === 'MCQ' ? 'Option C' : '',
                $q_type === 'MCQ' ? 'Option D' : '',
                $q_type === 'MCQ' ? 'A' : '',
                'Explanation/Solution for this ' . $q_type . ' question.',
                '',
                '',
                'દરેક પ્રશ્ન માટે અહીં ગુજરાતી લખાણ આવશે (' . $q_type . ')...',
                $q_type === 'MCQ' ? 'વિકલ્પ A' : '',
                $q_type === 'MCQ' ? 'વિકલ્પ B' : '',
                $q_type === 'MCQ' ? 'વિકલ્પ C' : '',
                $q_type === 'MCQ' ? 'વિકલ્પ D' : '',
                'આ પ્રશ્નનો ઉકેલ / સમજૂતી અહીં આવશે.',
            ];
        }
    }
} catch (Exception $e) {
    // Fallback to static sample if database query fails
    if ($template_type === 'simple') {
        $sampleRows = [
            ['Sample Question?', 'A', 'B', 'C', 'D', 'A', 'નમૂનારૂપ પ્રશ્ન?', 'વિકલ્પ A', 'વિકલ્પ B', 'વિકલ્પ C', 'વિકલ્પ D']
        ];
    } else {
        $sampleRows = [
            ['12th', 'A Group', 'Physics', 'Ch-1', 'Motion', 'MCQ', 'Level A', 'Sample Question?', 'A', 'B', 'C', 'D', 'A', 'Exp', '', '', 'નમૂનારૂપ પ્રશ્ન?', 'વિકલ્પ A', 'વિકલ્પ B', 'વિકલ્પ C', 'વિકલ્પ D', 'સમજૂતી']
        ];
    }
}

// ---- XML Helpers ----
function escXml($text): string
{
    // Ensure it's a string and strip any invalid XML control characters
    $s = (string)$text;
    $s = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $s);
    return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function makeCell(string $text, bool $isHeader = false, int $width = 1400): string
{
    $shading = $isHeader
        ? '<w:shd w:val="clear" w:color="auto" w:fill="1F497D"/>'
        : '<w:shd w:val="clear" w:color="auto" w:fill="FFFFFF"/>';
    $rPr = $isHeader
        ? '<w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="28"/><w:szCs w:val="28"/></w:rPr>'
        : '<w:rPr><w:sz w:val="28"/><w:szCs w:val="28"/></w:rPr>';
    $paraShade = $isHeader ? '<w:shd w:val="clear" w:color="auto" w:fill="1F497D"/>' : '';

    return '<w:tc>
        <w:tcPr>
            <w:tcW w:w="' . $width . '" w:type="dxa"/>
            ' . $shading . '
            <w:tcBorders>
                <w:top w:val="single" w:sz="4" w:space="0" w:color="AAAAAA"/>
                <w:left w:val="single" w:sz="4" w:space="0" w:color="AAAAAA"/>
                <w:bottom w:val="single" w:sz="4" w:space="0" w:color="AAAAAA"/>
                <w:right w:val="single" w:sz="4" w:space="0" w:color="AAAAAA"/>
            </w:tcBorders>
        </w:tcPr>
        <w:p>
            <w:pPr>
                <w:spacing w:before="60" w:after="60"/>
                ' . $paraShade . '
            </w:pPr>
            <w:r>' . $rPr . '<w:t xml:space="preserve">' . escXml($text) . '</w:t></w:r>
        </w:p>
    </w:tc>';
}

// Column widths (narrow for short cols, wide for content cols)
if ($template_type === 'simple') {
    $colWidths = [3000, 1500, 1500, 1500, 1500, 1200, 3000, 1500, 1500, 1500, 1500];
} else {
    $colWidths = [1100, 1000, 900, 900, 900, 900, 800, 2200, 1300, 1300, 1300, 1300, 900, 1800, 1500, 900, 2200, 1300, 1300, 1300, 1300, 1800];
}

function makeRow(array $cells, bool $isHeader, array $widths): string
{
    $xml = '<w:tr>';
    if ($isHeader) {
        $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
    } else {
        $xml .= '<w:trPr><w:trHeight w:val="400"/></w:trPr>';
    }
    foreach ($cells as $i => $cell) {
        $xml .= makeCell($cell, $isHeader, $widths[$i] ?? 1200);
    }
    $xml .= '</w:tr>';
    return $xml;
}

// Build full table XML
$tableXml = '<w:tbl>
    <w:tblPr>
        <w:tblStyle w:val="TableGrid"/>
        <w:tblW w:w="0" w:type="auto"/>
        <w:tblLayout w:type="auto"/>
        <w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>
    </w:tblPr>
    <w:tblGrid>';

foreach ($colWidths as $w) {
    $tableXml .= '<w:gridCol w:w="' . $w . '"/>';
}
$tableXml .= '</w:tblGrid>';

// Header row
$tableXml .= makeRow($headers, true, $colWidths);

// Sample data rows
foreach ($sampleRows as $row) {
    $tableXml .= makeRow($row, false, $colWidths);
}

// Blank rows removed as per user request

$tableXml .= '</w:tbl>';

// ---- Instruction paragraph ----
function makePara(string $text, bool $bold = false, string $color = '000000', int $sz = 20): string
{
    $bTag = $bold ? '<w:b/>' : '';
    return '<w:p>
        <w:r>
            <w:rPr>' . $bTag . '<w:color w:val="' . $color . '"/><w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/></w:rPr>
            <w:t xml:space="preserve">' . escXml($text) . '</w:t>
        </w:r>
    </w:p>';
}

// ---- Build document.xml ----
$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"
    xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
    xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing"
    xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
    xmlns:w10="urn:schemas-microsoft-com:office:word"
    xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"
    xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"
    xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk"
    xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"
    xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
    mc:Ignorable="w14 wp14">
<w:body>

' . makePara('OES Question Bank — Bulk Import Template (Word)', true, '1F497D', 36) . '
' . makePara('') . '
' . makePara('INSTRUCTIONS:', true, '333333', 24) . '
' . makePara('1. Fill in the table below. The first row (blue header) will be skipped during import.', false, '444444', 22) . '
' . makePara('2. Column order is fixed — do not add, remove, or reorder columns.', false, '444444', 22) . '
' . makePara('3. Correct Answer must be exactly: A, B, C, or D.', false, '444444', 22) . '
' . makePara('4. Difficulty must be one of: A, B, C, D, or E  (maps to Level A–E).', false, '444444', 22) . '
' . makePara('5. For non-MCQ questions (e.g., 5_mark), leave Options A–D blank.', false, '444444', 22) . '
' . makePara('6. Nested tables inside cells are now supported (Recommended for complex data).', false, '228B22', 22) . '
' . makePara('7. Subject must match a name already configured in the system.', false, '444444', 22) . '
' . makePara('8. For Math/Chemistry formulas, use LaTeX format (e.g., $E=mc^2$) for best results.', false, '00008B', 22) . '
' . makePara('9. The last 6 columns support side-by-side Gujarati translations for bilingual questions.', false, 'B8860B', 22) . '
' . makePara('') . '
' . makePara('COLUMN REFERENCE:', true, '333333', 24) . '
' . makePara('Col 1: Std | Col 2: Group | Col 3: Sub | Col 4: Chp | Col 5: Topic | Col 6: Type | Col 7: Diff | Col 8: Body', false, '555555', 20) . '
' . makePara('Col 9-12: Opt A-D | Col 13: Correct Ans | Col 14: Solution | Col 15: Video | Col 16: Image', false, '555555', 20) . '
' . makePara('Col 17: Gujarati Body | Col 18-21: Gujarati Opt A-D | Col 22: Gujarati Solution', false, '555555', 20) . '
' . makePara('') . '

' . $tableXml . '

<w:sectPr>
    <w:pgSz w:w="20000" w:h="15840" w:orient="landscape"/>
    <w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="708" w:footer="708" w:gutter="0"/>
</w:sectPr>
</w:body>
</w:document>';

// ---- [Content_Types].xml ----
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>';

// ---- _rels/.rels ----
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';

// ---- word/_rels/document.xml.rels ----
$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

// ---- word/styles.xml ----
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    mc:Ignorable="w14">
    <w:docDefaults>
        <w:rPrDefault>
            <w:rPr>
                <w:rFonts w:ascii="Calibri" w:eastAsia="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
                <w:sz w:val="28"/><w:szCs w:val="28"/>
            </w:rPr>
        </w:rPrDefault>
    </w:docDefaults>
    <w:style w:type="table" w:default="1" w:styleId="TableNormal">
        <w:name w:val="Normal Table"/>
        <w:uiPriority w:val="99"/><w:semiHidden/><w:unhideWhenUsed/>
    </w:style>
    <w:style w:type="table" w:styleId="TableGrid">
        <w:name w:val="Table Grid"/>
        <w:basedOn w:val="TableNormal"/>
        <w:uiPriority w:val="59"/><w:rsid w:val="00A110F4"/>
        <w:tblPr>
            <w:tblBorders>
                <w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                <w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                <w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                <w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                <w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                <w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/>
            </w:tblBorders>
        </w:tblPr>
    </w:style>
</w:styles>';

// ---- Build .docx (ZIP) in memory ----
$tmpFile = tempnam(sys_get_temp_dir(), 'oes_template_') . '.docx';

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Failed to create Word template file.');
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('word/document.xml', $documentXml);
$zip->addFromString('word/styles.xml', $stylesXml);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->close();

// ---- Send download response ----
$filename = 'OES_Question_Import_Template.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

ob_end_clean();
readfile($tmpFile);
unlink($tmpFile);
exit();
?>