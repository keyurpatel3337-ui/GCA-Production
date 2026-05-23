<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/portal/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Image;

header('Content-Type: application/json');

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER, ROLE_TEACHER, ROLE_COMPUTER_OPERATOR, ROLE_OES_DATA_ENTRY_OPERATOR])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['word_file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['word_file']['tmp_name'];
$fileName = $_FILES['word_file']['name'];

    $phpWordFailed = false;
    $parseError = '';
    $questions = [];

    try {
        // --- Pre-process the DOCX to handle MathML & prevent PHPWord crash ---
        $tempDir = UPLOADS_PATH . 'oes' . DIRECTORY_SEPARATOR;
        if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
        
        $tempDocx = $tempDir . uniqid('docx_') . '.docx';
        copy($file, $tempDocx);

        $zip = new ZipArchive;
        if ($zip->open($tempDocx) === TRUE) {
            $docXml = $zip->getFromName('word/document.xml');
            if ($docXml !== false) {
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadXML($docXml);
                libxml_clear_errors();
                
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');

                // === CHEMDRAW / OLE IMAGE EXTRACTION ===
                // Must run BEFORE math processing which removes w:object nodes
                $oleImageMap = [];
                $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
                if ($relsXml) {
                    $domRels = new DOMDocument();
                    @$domRels->loadXML($relsXml);
                    $relMap = [];
                    foreach ($domRels->getElementsByTagName('Relationship') as $rel) {
                        $relMap[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
                    }
                    $tblNodes = $xpath->query('//w:tbl');
                    $tblIdx = 0;
                    foreach ($tblNodes as $tblNode) {
                        $trNodes = $xpath->query('w:tr', $tblNode);
                        $rIdx = 0;
                        foreach ($trNodes as $trNode) {
                            $tcNodes = $xpath->query('w:tc', $trNode);
                            $cIdx = 0;
                            foreach ($tcNodes as $tcNode) {
                                $objFound = $xpath->query('.//*[local-name()="object"]', $tcNode);
                                if ($objFound->length > 0) {
                                    $imgNodes = $xpath->query('.//*[local-name()="imagedata"]', $tcNode);
                                    foreach ($imgNodes as $imgNode) {
                                        $rId = '';
                                        foreach ($imgNode->attributes as $attr) {
                                            if (strtolower($attr->localName) === 'id') {
                                                $rId = $attr->value;
                                                break;
                                            }
                                        }
                                        if ($rId && isset($relMap[$rId])) {
                                            $target = $relMap[$rId];
                                            $mapKey = 't' . $tblIdx . '_r' . $rIdx . '_c' . $cIdx;
                                            // Skip if we already captured an image for this cell
                                            if (isset($oleImageMap[$mapKey])) {
                                                break;
                                            }
                                            $emfBytes = $zip->getFromName('word/' . $target);
                                            if ($emfBytes !== false && strlen($emfBytes) > 100) {
                                                $emfFile = $tempDir . 'ole_t' . $tblIdx . '_r' . $rIdx . '_c' . $cIdx . '_' . uniqid() . '.emf';
                                                $pngFile = $tempDir . 'qimg_ole_' . uniqid() . '_' . time() . '.png';
                                                file_put_contents($emfFile, $emfBytes);
                                                $oleImageMap[$mapKey] = [
                                                    'emf' => $emfFile,
                                                    'png' => $pngFile,
                                                    'url' => UPLOADS_URL . '/oes/' . basename($pngFile),
                                                ];
                                            }
                                        }
                                    }
                                }
                                $cIdx++;
                            }
                            $rIdx++;
                        }
                        $tblIdx++;
                    }
                }

                // Using local-name() to be namespace-agnostic and more aggressive
                $mathNodes = $xpath->query("//*[local-name()='oMathPara'] | //*[local-name()='oMath'] | //*[local-name()='object']");
                if ($mathNodes->length > 0) {
                    for ($i = $mathNodes->length - 1; $i >= 0; $i--) {
                        $node = $mathNodes->item($i);
                        
                        // Avoid double-processing if we already handled the parent oMathPara or object
                        $parentMath = $xpath->query("ancestor::*[local-name()='oMathPara' or local-name()='object']", $node);
                        if (($node->localName === 'oMath') && $parentMath->length > 0) {
                            continue;
                        }

                        $latex = '';
                        if ($node->localName !== 'object') {
                            $latex = ommlToLatex($node);
                        }
                        
                        $wNS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
                        $rNode = $dom->createElementNS($wNS, 'w:r');
                        $tNode = $dom->createElementNS($wNS, 'w:t');
                        $tNode->setAttribute('xml:space', 'preserve');
                        
                        // Use a placeholder if LaTeX is empty or if it's an OLE object
                        $latexText = !empty(trim($latex)) ? ' $$' . $latex . '$$ ' : ' [Equation/Object] ';
                        $tNode->appendChild($dom->createTextNode($latexText));
                        $rNode->appendChild($tNode);
                        
                        // Replace the node
                        if ($node->parentNode) {
                            $node->parentNode->replaceChild($rNode, $node);
                        }
                    }
                    $newXml = $dom->saveXML();
                    if ($newXml) {
                        $zip->addFromString('word/document.xml', $newXml);
                        // DEBUG: Save pre-processed XML for inspection
                        file_put_contents($tempDir . 'debug_preprocessed.xml', $newXml);
                    }
                }
            }
            
            if ($zip->close()) {
                $fileToLoad = $tempDocx;
            } else {
                $fileToLoad = $file;
            }
        } else {
            $fileToLoad = $file;
        }

        // === EMF → PNG CONVERSION via PowerShell + .NET System.Drawing ===
        // Uses System.Drawing.Imaging.Metafile — reliable from Apache service context,
        // no desktop/screen session required, built into all Windows Server versions.
        if (!empty($oleImageMap)) {
            $psJobs = [];
            foreach ($oleImageMap as $job) {
                // PowerShell-safe single-quote escaped paths
                $e = str_replace("'", "''", $job['emf']);
                $p = str_replace("'", "''", $job['png']);
                $psJobs[] = "Convert-Emf '" . $e . "' '" . $p . "'";
            }

            $psScript = implode("\r\n", array_merge([
                'Add-Type -AssemblyName System.Drawing',
                'function Convert-Emf($emfPath, $pngPath) {',
                '    try {',
                '        $mf = New-Object System.Drawing.Imaging.Metafile($emfPath)',
                '        $w  = [Math]::Max($mf.Width,  80) * 2',
                '        $h  = [Math]::Max($mf.Height, 80) * 2',
                '        $bm = New-Object System.Drawing.Bitmap($w, $h)',
                '        $gr = [System.Drawing.Graphics]::FromImage($bm)',
                '        $gr.SmoothingMode   = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality',
                '        $gr.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic',
                '        $gr.Clear([System.Drawing.Color]::White)',
                '        $gr.DrawImage($mf, 0, 0, $w, $h)',
                '        $gr.Flush()',
                '        $bm.Save($pngPath, [System.Drawing.Imaging.ImageFormat]::Png)',
                '        $gr.Dispose(); $bm.Dispose(); $mf.Dispose()',
                '        Write-Host "OK:$pngPath"',
                '    } catch {',
                '        Write-Host "ERR:$($_.Exception.Message) | $emfPath"',
                '    }',
                '}',
            ], $psJobs));

            $psFile = $tempDir . 'ole_cvt_' . uniqid() . '.ps1';
            file_put_contents($psFile, $psScript);
            // -NonInteractive -NoProfile: faster startup, no profile dependencies
            // -ExecutionPolicy Bypass: run unsigned scripts from Apache
            shell_exec('powershell.exe -NonInteractive -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($psFile) . ' 2>&1');
            @unlink($psFile);

            // Cleanup EMF temp files
            foreach ($oleImageMap as $job) {
                if (file_exists($job['emf'])) @unlink($job['emf']);
            }
            // Build img_html for each successfully converted image
            foreach ($oleImageMap as $key => &$job) {
                if (file_exists($job['png'])) {
                    $w = null;
                    $h = null;
                    $size = @getimagesize($job['png']);
                    if ($size) {
                        $w = round($size[0] / 2);
                        $h = round($size[1] / 2);
                    }
                    
                    $style_str = 'max-width:100%; margin:5px 0;';
                    if ($w && $h) {
                        $style_str .= " width:{$w}px; height:{$h}px;";
                    } else {
                        $style_str .= ' max-width:300px; height:auto;';
                    }
                    
                    $job['img_html'] = '<img src="' . htmlspecialchars($job['url']) . '" style="' . $style_str . '" />';
                } else {
                    $job['img_html'] = '';
                }
            }
            unset($job);
        }

        $phpWord = IOFactory::load($fileToLoad);

        // Map of template columns (Strict Positional based on download-word-template.php)
        $WORD_COLS = [
            'standard', 'group_name', 'subject', 'chapter', 'topic', 'question_type', 'difficulty',
            'question_text', 'option_a', 'option_b', 'option_c',
            'option_d', 'correct_option', 'explanation', 'video_solution_url', 'solution_image',
            'question_text_guj', 'option_a_guj', 'option_b_guj', 'option_c_guj', 'option_d_guj', 'explanation_guj'
        ];

        $WORD_COLS_SIMPLE = [
            'question_text', 'option_a', 'option_b', 'option_c',
            'option_d', 'correct_option', 'question_text_guj',
            'option_a_guj', 'option_b_guj', 'option_c_guj', 'option_d_guj'
        ];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    $rows = $element->getRows();
                    
                    $startIndex = 0;
                    if (count($rows) > 0) {
                        $firstCellText = getCellText($rows[0]->getCells()[0]);
                        if (stripos($firstCellText, 'standard') !== false || stripos($firstCellText, 'subject') !== false || stripos($firstCellText, 'question') !== false) {
                            $startIndex = 1;
                        }
                    }

                    $oleRichFields = [
                        'question_text','option_a','option_b','option_c','option_d','explanation',
                        'question_text_guj','option_a_guj','option_b_guj','option_c_guj','option_d_guj','explanation_guj'
                    ];
                    for ($i = $startIndex; $i < count($rows); $i++) {
                        $cells = $rows[$i]->getCells();
                        if (empty($cells)) continue;
                        $q = [];
                        
                        // Dynamically choose column mapping based on uploaded table size
                        $active_cols = (count($cells) < 15) ? $WORD_COLS_SIMPLE : $WORD_COLS;
                        
                        foreach ($active_cols as $index => $key) {
                            if (isset($cells[$index])) {
                                $isPlain = !in_array($key, $oleRichFields);
                                $cellContent = trim(getCellText($cells[$index], $isPlain));
                                // === OLE/ChemDraw Image Injection ===
                                if (!empty($oleImageMap)) {
                                    $mapKey = 't0_r' . $i . '_c' . $index;
                                    if (isset($oleImageMap[$mapKey]['img_html']) && $oleImageMap[$mapKey]['img_html'] !== '') {
                                        $imgHtml = $oleImageMap[$mapKey]['img_html'];
                                        if (empty(trim(strip_tags($cellContent))) || trim($cellContent) === '[Equation/Object]') {
                                            // Case 1: Cell is purely an image (empty text or exact placeholder)
                                            $cellContent = $imgHtml;
                                        } elseif (strpos($cellContent, '[Equation/Object]') !== false) {
                                            // Case 2: Cell has text + image - replace placeholder inline
                                            $cellContent = str_replace('[Equation/Object]', $imgHtml, $cellContent);
                                        } else {
                                            // Case 3: OLE was detected in XML but text exists - append image
                                            $cellContent = $cellContent . ' ' . $imgHtml;
                                        }
                                    }
                                }
                                $q[$key] = $cellContent;
                            }
                        }
                        if (!empty($q['question_text'])) {
                            if (empty($q['marks'])) $q['marks'] = 1;
                            if (empty($q['question_type'])) $q['question_type'] = 'MCQ';
                            $questions[] = $q;
                        }
                    }
                }
            }
        }

    } catch (Exception $e) {
        $phpWordFailed = true;
        $parseError = $e->getMessage();
        if (strpos($parseError, 'Invalid or uninitialized Zip object') !== false) {
            $parseError = "The file is not a valid modern Word document (.docx). Please ensure it is saved as a true .docx file, not an older .doc file.";
        }
    }

    if ($phpWordFailed || empty($questions)) {
        echo json_encode(['success' => false, 'message' => "Could not find any questions in the document. Please ensure you are using the correct table template.\nPHP Error: " . $parseError]);
        exit;
    }

    // --- Final Cleanup of Object Strings (Fail-safe) ---
    foreach ($questions as &$q) {
        foreach ($q as $key => &$val) {
            if (is_string($val)) {
                $val = preg_replace('/(?:<|&lt;)\s*Object\s*:[^>;&]+(?:>|&gt;)/i', '', $val);
                if (trim($val) === '[Equation/Object]') $val = ''; 
                
                // Clean degree characters inside LaTeX formulas ($$...$$) for KaTeX compatibility
                $val = preg_replace_callback('/\$\$(.*?)\$\$/s', function($matches) {
                    $latex = $matches[1];
                    $latex = preg_replace('/\^\{\s*[°º˚◦]\s*\}/u', '^{\\circ}', $latex);
                    $latex = preg_replace('/\^[°º˚◦]/u', '^\\circ', $latex);
                    $latex = preg_replace('/(?<!\^)(?<!\^\{)[°º˚◦]/u', '^\\circ', $latex);
                    
                    // Collapse any identical adjacent duplicated mathematical formulas in the same cell
                    $latex = preg_replace('/^(.+?)\s*\1$/u', '$1', trim($latex));
                    
                    return '$$' . $latex . '$$';
                }, $val);

                $val = trim($val);
            }
        }
    }

    // --- Process Batch Image Conversions (EMF/WMF to PNG) ---
    global $emfToConvertQueue;
    if (!empty($emfToConvertQueue)) {
        $psScript = "Add-Type -AssemblyName System.Drawing;\n";
        foreach ($emfToConvertQueue as $job) {
            $src = $job['src'];
            $dst = $job['dst'];
            $psScript .= "try { \$img = [System.Drawing.Image]::FromFile('$src'); \$img.Save('$dst', [System.Drawing.Imaging.ImageFormat]::Png); \$img.Dispose(); Remove-Item -Path '$src' -ErrorAction SilentlyContinue; } catch { }\n";
        }
        
        $tempPsFile = UPLOADS_PATH . 'oes' . DIRECTORY_SEPARATOR . 'convert_' . uniqid() . '.ps1';
        file_put_contents($tempPsFile, $psScript);
        shell_exec("powershell -ExecutionPolicy Bypass -File " . escapeshellarg($tempPsFile));
        @unlink($tempPsFile);
    }

    // --- Final Cleanup of Temp File ---
    if (isset($fileToLoad) && isset($file) && $fileToLoad !== $file && file_exists($fileToLoad)) {
        @unlink($fileToLoad);
    }

    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'source' => $fileName,
        'mode' => 'table',
        'count' => count($questions)
    ]);

/**
 * Helper to convert Office MathML DOM nodes into LaTeX strings for MathJax.
 */
function ommlToLatex($node) {
    if (!$node) return '';
    if ($node->nodeType === XML_TEXT_NODE) {
        return htmlspecialchars($node->textContent);
    }
    
    $latex = '';
    $name = localName($node);
    
    switch ($name) {
        case 'oMathPara':
        case 'oMath':
        case 'e': 
            foreach ($node->childNodes as $child) $latex .= ommlToLatex($child);
            break;
        case 'r': 
            foreach ($node->childNodes as $child) {
                if (localName($child) === 't') {
                    $t = $child->textContent;
                    $t = str_replace(['×', '÷', '−'], ['\times', '\div', '-'], $t);
                    $latex .= $t;
                } else {
                    $latex .= ommlToLatex($child);
                }
            }
            break;
        case 'f': // Fraction
            $num = ''; $den = '';
            foreach ($node->childNodes as $child) {
                $n = localName($child);
                if ($n === 'num') $num = ommlToLatex($child);
                if ($n === 'den') $den = ommlToLatex($child);
            }
            $latex .= '\frac{' . $num . '}{' . $den . '}';
            break;
        case 'rad': // Radical / Root
            $deg = ''; $e = '';
            foreach ($node->childNodes as $child) {
                $n = localName($child);
                if ($n === 'deg') $deg = ommlToLatex($child);
                if ($n === 'e') $e = ommlToLatex($child);
            }
            $latex .= $deg ? "\\sqrt[{$deg}]{{{$e}}}" : "\\sqrt{{$e}}";
            break;
        case 'sSup': // Superscript
            $e = ''; $sup = '';
            foreach ($node->childNodes as $child) {
                $n = localName($child);
                if ($n === 'e') $e = ommlToLatex($child);
                if ($n === 'sup') $sup = ommlToLatex($child);
            }
            $latex .= "{$e}^{{{$sup}}}";
            break;
        case 'sSub': // Subscript
            $e = ''; $sub = '';
            foreach ($node->childNodes as $child) {
                $n = localName($child);
                if ($n === 'e') $e = ommlToLatex($child);
                if ($n === 'sub') $sub = ommlToLatex($child);
            }
            $latex .= "{$e}_{{{$sub}}}";
            break;
        case 'sSubSup': // Sub-Sup
            $e = ''; $sub = ''; $sup = '';
            foreach ($node->childNodes as $child) {
                $n = localName($child);
                if ($n === 'e') $e = ommlToLatex($child);
                if ($n === 'sub') $sub = ommlToLatex($child);
                if ($n === 'sup') $sup = ommlToLatex($child);
            }
            $latex .= "{$e}_{{{$sub}}}^{{{$sup}}}";
            break;
        case 'd': // Delimiter
            $beg = '(';
            $end = ')';
            foreach ($node->childNodes as $child) {
                if (localName($child) === 'dPr') {
                    foreach ($child->childNodes as $prop) {
                        $pName = localName($prop);
                        if ($pName === 'beg') {
                            $val = $prop->getAttribute('m:val') ?: $prop->getAttribute('val');
                            if ($val !== null && $val !== '') $beg = $val;
                        }
                        if ($pName === 'end') {
                            $val = $prop->getAttribute('m:val') ?: $prop->getAttribute('val');
                            if ($val !== null && $val !== '') $end = $val;
                        }
                    }
                }
            }
            
            $e = '';
            foreach ($node->childNodes as $child) {
                if (localName($child) === 'e') {
                    $e = ommlToLatex($child);
                }
            }
            
            $leftCmd = '\\left' . ($beg === '{' ? '\\{' : ($beg === '' ? '.' : $beg));
            $rightCmd = '\\right' . ($end === '}' ? '\\}' : ($end === '' ? '.' : $end));
            
            $latex .= "{$leftCmd} {$e} {$rightCmd}";
            break;
            
        case 'm': // Matrix
            $rows = [];
            foreach ($node->childNodes as $child) {
                if (localName($child) === 'mr') {
                    $rows[] = ommlToLatex($child);
                }
            }
            $latex .= '\\begin{matrix} ' . implode(' \\\\ ', $rows) . ' \\end{matrix}';
            break;
            
        case 'mr': // Matrix Row
            $elements = [];
            foreach ($node->childNodes as $child) {
                if (localName($child) === 'e') {
                    $elements[] = ommlToLatex($child);
                }
            }
            $latex .= implode(' & ', $elements);
            break;
        default:
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    if (localName($child) !== 'ctrlPr') {
                        $latex .= ommlToLatex($child);
                    }
                }
            }
            break;
    }
    
    return $latex;
}

function localName($node) {
    if (!$node) return '';
    $name = $node->nodeName;
    if (strpos($name, ':') !== false) {
        return explode(':', $name)[1];
    }
    return $name;
}

function getCellText($cell, $plainText = false) {
    $text = '';
    if ($cell instanceof \PhpOffice\PhpWord\Element\Table) {
        if (!$plainText) $text .= '<table class="table table-bordered table-sm" style="width:auto; margin:10px 0;">';
        foreach ($cell->getRows() as $row) {
            if (!$plainText) $text .= '<tr>';
            foreach ($row->getCells() as $cellObj) {
                if (!$plainText) $text .= '<td style="padding:5px; border:1px solid #ddd;">';
                $text .= getCellText($cellObj, $plainText);
                if (!$plainText) $text .= '</td>';
            }
            if (!$plainText) $text .= '</tr>';
        }
        if (!$plainText) $text .= '</table>';
    } elseif (method_exists($cell, 'getElements')) {
        foreach ($cell->getElements() as $element) {
            $text .= getElementContent($element, $plainText);
        }
    }
    return $text;
}

function getElementContent($element, $plainText) {
    $content = '';
    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
        $content .= $element->getText();
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        foreach ($element->getElements() as $child) {
            $content .= getElementContent($child, $plainText);
        }
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Image || method_exists($element, 'getImageStringData')) {
        if ($plainText) {
            $content .= ' [Image] ';
        } else {
            try {
                $imageData = '';
                if (method_exists($element, 'getImageStringData')) {
                    $imageData = $element->getImageStringData();
                    if (!empty($imageData) && !preg_match('~^[a-f0-9]+$~i', $imageData)) {
                        $imageData = base64_encode($imageData);
                    } elseif (!empty($imageData)) {
                        $imageData = base64_encode(hex2bin($imageData));
                    }
                }
                
                $img_style_str = 'max-width:100%; margin:5px 0;';
                if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
                    $style = $element->getStyle();
                    if ($style) {
                        $w = method_exists($style, 'getWidth') ? $style->getWidth() : null;
                        $h = method_exists($style, 'getHeight') ? $style->getHeight() : null;
                        if ($w !== null) {
                            $w_str = is_numeric($w) ? $w . 'px' : $w;
                            $img_style_str .= ' width:' . $w_str . ';';
                        }
                        if ($h !== null) {
                            $h_str = is_numeric($h) ? $h . 'px' : $h;
                            $img_style_str .= ' height:' . $h_str . ';';
                        }
                    }
                }
                if (strpos($img_style_str, 'width:') === false) {
                    $img_style_str .= ' max-width:300px; height:auto;';
                }

                if ($imageData) {
                    $binaryData = base64_decode($imageData);
                    $filename = 'qimg_' . uniqid() . '_' . time() . '.png';
                    $uploadDir = UPLOADS_PATH . 'oes' . DIRECTORY_SEPARATOR;
                    
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $filePath = $uploadDir . $filename;
                    if (file_put_contents($filePath, $binaryData)) {
                        $imgUrl = BASE_URL . '/uploads/oes/' . $filename;
                        $content .= '<img src="' . $imgUrl . '" style="' . $img_style_str . '" />';
                    } else {
                        // Fallback to base64 if saving fails
                        $content .= '<img src="data:image/png;base64,' . $imageData . '" style="' . $img_style_str . '" />';
                    }
                } else {
                    $content .= ' [Image] ';
                }
            } catch (Exception $e) {
                $content .= ' [Image Error] ';
            }
        }
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        $content .= getCellText($element, $plainText);
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $child) {
            $content .= getElementContent($child, $plainText);
        }
    }
    return $content;
}
