<?php
/**
 * Full end-to-end test: OLE extraction + PHPWord parse + injection
 * Shows exactly what each question field contains after processing.
 */
require_once 'c:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;

$file    = 'c:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank_Che_G.docx';
$tempDir = UPLOADS_PATH . 'oes' . DIRECTORY_SEPARATOR;
if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);

$tempDocx = $tempDir . uniqid('ftest_') . '.docx';
copy($file, $tempDocx);

$WORD_COLS = [
    'standard','group_name','question_type','difficulty','subject',
    'chapter','topic','question_text','option_a','option_b','option_c',
    'option_d','correct_option','explanation','video_solution_url','solution_image'
];

// ── Step 1: Open ZIP, extract OLE map & pre-process math ──────────────────
$oleImageMap = [];
$zip = new ZipArchive;
if ($zip->open($tempDocx) === TRUE) {
    $docXml = $zip->getFromName('word/document.xml');
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadXML($docXml);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');

    // ── OLE extraction ──
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
                                if (strtolower($attr->localName) === 'id') { $rId = $attr->value; break; }
                            }
                            $mapKey = 't'.$tblIdx.'_r'.$rIdx.'_c'.$cIdx;
                            if ($rId && isset($relMap[$rId]) && !isset($oleImageMap[$mapKey])) {
                                $emfBytes = $zip->getFromName('word/' . $relMap[$rId]);
                                if ($emfBytes !== false && strlen($emfBytes) > 100) {
                                    $emfFile = $tempDir . 'ole_'.$mapKey.'_'.uniqid().'.emf';
                                    $pngFile = $tempDir . 'qimg_ole_'.uniqid().'_'.time().'.png';
                                    file_put_contents($emfFile, $emfBytes);
                                    $oleImageMap[$mapKey] = ['emf'=>$emfFile,'png'=>$pngFile,'url'=>BASE_URL.'/uploads/oes/'.basename($pngFile)];
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
    echo "OLE objects found: " . count($oleImageMap) . "\n";
    echo "Keys: " . implode(', ', array_keys($oleImageMap)) . "\n\n";

    // ── Math pre-processing (replaces w:object → [Equation/Object]) ──
    $mathNodes = $xpath->query("//*[local-name()='oMathPara'] | //*[local-name()='oMath'] | //*[local-name()='object']");
    if ($mathNodes->length > 0) {
        for ($mi = $mathNodes->length - 1; $mi >= 0; $mi--) {
            $node = $mathNodes->item($mi);
            $wNS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $rNode = $dom->createElementNS($wNS, 'w:r');
            $tNode = $dom->createElementNS($wNS, 'w:t');
            $tNode->setAttribute('xml:space', 'preserve');
            $tNode->appendChild($dom->createTextNode(' [Equation/Object] '));
            $rNode->appendChild($tNode);
            if ($node->parentNode) $node->parentNode->replaceChild($rNode, $node);
        }
        $newXml = $dom->saveXML();
        if ($newXml) $zip->addFromString('word/document.xml', $newXml);
    }
    $zip->close();
}

// ── Step 2: Convert EMFs → PNGs ──────────────────────────────────────────
if (!empty($oleImageMap)) {
    $pyLines = [
        'import os,ctypes,struct',
        'from PIL import Image',
        'class H(ctypes.Structure):',
        '    _fields_=[("a",ctypes.c_uint32),("b",ctypes.c_uint32),("l",ctypes.c_int32),("t",ctypes.c_int32),("r",ctypes.c_int32),("bt",ctypes.c_int32),("fl",ctypes.c_int32),("ft",ctypes.c_int32),("fr",ctypes.c_int32),("fb",ctypes.c_int32),("c",ctypes.c_uint32),("d",ctypes.c_uint32),("e",ctypes.c_uint32),("f",ctypes.c_uint32),("g",ctypes.c_uint16),("h",ctypes.c_uint16),("i",ctypes.c_uint32),("j",ctypes.c_uint32),("k",ctypes.c_uint32),("m",ctypes.c_uint32),("n",ctypes.c_uint32),("o",ctypes.c_uint32),("p",ctypes.c_uint32)]',
        'class B(ctypes.Structure):',
        '    _fields_=[("s",ctypes.c_uint32),("w",ctypes.c_int32),("h",ctypes.c_int32),("p",ctypes.c_uint16),("b",ctypes.c_uint16),("c",ctypes.c_uint32),("i",ctypes.c_uint32),("x",ctypes.c_int32),("y",ctypes.c_int32),("u",ctypes.c_uint32),("v",ctypes.c_uint32)]',
        'g=ctypes.windll.gdi32; u=ctypes.windll.user32',
        'def cvt(ep,pp):',
        '    he=g.GetEnhMetaFileA(ep.encode("ansi"))',
        '    if not he: print("FAIL:"+ep); return',
        '    hdr=H(); g.GetEnhMetaFileHeader(he,ctypes.sizeof(hdr),ctypes.byref(hdr))',
        '    bw=hdr.r-hdr.l; bh=hdr.bt-hdr.t',
        '    fw=int((hdr.fr-hdr.fl)/100.0*96/25.4); fh=int((hdr.fb-hdr.ft)/100.0*96/25.4)',
        '    W=max(bw,fw,80)*2; Hh=max(bh,fh,80)*2',
        '    hs=u.GetDC(0); hd=g.CreateCompatibleDC(hs)',
        '    hb=g.CreateCompatibleBitmap(hs,W,Hh); g.SelectObject(hd,hb)',
        '    rc=(ctypes.c_int32*4)(0,0,W,Hh); u.FillRect(hd,rc,g.GetStockObject(0))',
        '    g.PlayEnhMetaFile(hd,he,rc)',
        '    bi=B(); bi.s=ctypes.sizeof(B); bi.w=W; bi.h=-Hh; bi.p=1; bi.b=24',
        '    st=((W*3+3)&~3); buf=(ctypes.c_byte*(st*Hh))()',
        '    g.GetDIBits(hd,hb,0,Hh,buf,ctypes.byref(bi),0)',
        '    tmp=pp+".bmp"',
        '    with open(tmp,"wb") as f:',
        '        f.write(struct.pack("<2sIHHI",b"BM",54+st*Hh,0,0,54))',
        '        f.write(struct.pack("<IiiHHIIiiII",40,W,-Hh,1,24,0,st*Hh,0,0,0,0))',
        '        f.write(bytes(buf))',
        '    Image.open(tmp).save(pp); os.remove(tmp)',
        '    g.DeleteObject(hb); g.DeleteDC(hd); u.ReleaseDC(0,hs); g.DeleteEnhMetaFile(he)',
        '    print("OK:"+pp)',
    ];
    foreach ($oleImageMap as $job) {
        $e = str_replace('\\', '\\\\', $job['emf']);
        $p = str_replace('\\', '\\\\', $job['png']);
        $pyLines[] = "try: cvt('$e','$p')";
        $pyLines[] = "except Exception as _e: print('ERR:'+str(_e))";
    }
    $pyFile = $tempDir . 'ftest_cvt_' . uniqid() . '.py';
    file_put_contents($pyFile, implode("\n", $pyLines));
    $out = shell_exec('python ' . escapeshellarg($pyFile) . ' 2>&1');
    @unlink($pyFile);
    foreach ($oleImageMap as $job) { if (file_exists($job['emf'])) @unlink($job['emf']); }

    $ok = substr_count($out, 'OK:');
    $err = substr_count($out, 'ERR:');
    echo "Python conversion: {$ok} OK, {$err} ERR\n";
    if ($err) echo $out . "\n";

    foreach ($oleImageMap as $key => &$job) {
        $job['img_html'] = file_exists($job['png'])
            ? '<img src="'.$job['url'].'" style="max-width:300px;height:auto;" />'
            : '';
    }
    unset($job);
    echo "\n";
}

// ── Step 3: PHPWord parse + OLE injection ─────────────────────────────────
$phpWord = IOFactory::load($tempDocx);
$questions = [];
$oleRichFields = ['question_text','option_a','option_b','option_c','option_d','explanation'];

foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $element) {
        if (!($element instanceof Table)) continue;
        $rows = $element->getRows();
        $startIndex = 0;
        if (count($rows) > 0) {
            $firstCellText = getCellText($rows[0]->getCells()[0]);
            if (stripos($firstCellText, 'standard') !== false || stripos($firstCellText, 'subject') !== false)
                $startIndex = 1;
        }
        for ($i = $startIndex; $i < count($rows); $i++) {
            $cells = $rows[$i]->getCells();
            if (empty($cells)) continue;
            $q = [];
            foreach ($WORD_COLS as $index => $key) {
                if (!isset($cells[$index])) continue;
                $isPlain = !in_array($key, $oleRichFields);
                $cellContent = trim(getCellText($cells[$index], $isPlain));

                // OLE injection
                if (!empty($oleImageMap)) {
                    $mapKey = 't0_r' . $i . '_c' . $index;
                    if (isset($oleImageMap[$mapKey]['img_html']) && $oleImageMap[$mapKey]['img_html'] !== '') {
                        $imgHtml = $oleImageMap[$mapKey]['img_html'];
                        if (empty(trim(strip_tags($cellContent))) || trim($cellContent) === '[Equation/Object]') {
                            $cellContent = $imgHtml;
                        } elseif (strpos($cellContent, '[Equation/Object]') !== false) {
                            $cellContent = str_replace('[Equation/Object]', $imgHtml, $cellContent);
                        } else {
                            $cellContent = $cellContent . ' ' . $imgHtml;
                        }
                    }
                }
                $q[$key] = $cellContent;
            }
            if (!empty($q['question_text'])) $questions[] = $q;
        }
    }
}
@unlink($tempDocx);

// ── Output ────────────────────────────────────────────────────────────────
echo "=== PARSED " . count($questions) . " QUESTIONS ===\n\n";
foreach ($questions as $qi => $q) {
    echo "Q" . ($qi+1) . " [row t0_r" . ($qi+1) . "]:\n";
    foreach (['question_text','option_a','option_b','option_c','option_d'] as $f) {
        $v = $q[$f] ?? '';
        $hasImg = strpos($v, '<img') !== false;
        $textPart = mb_substr(trim(strip_tags($v)), 0, 60);
        $imgPart  = $hasImg ? ' + [IMAGE]' : '';
        $display  = ($textPart ?: '(no text)') . $imgPart;
        if (empty(trim($v))) $display = '(EMPTY)';
        echo "  " . strtoupper(str_replace('_',' ',$f)) . ": $display\n";
    }
    echo "\n";
}

// ── Helper functions ──────────────────────────────────────────────────────
function getCellText($cell, $plainText = false) {
    $text = '';
    if (method_exists($cell, 'getElements')) {
        foreach ($cell->getElements() as $el) {
            $text .= getElContent($el, $plainText);
        }
    }
    return $text;
}
function getElContent($el, $plainText) {
    if ($el instanceof \PhpOffice\PhpWord\Element\Text) return $el->getText();
    if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
        $t = '';
        foreach ($el->getElements() as $c) $t .= getElContent($c, $plainText);
        return $t;
    }
    if (method_exists($el, 'getElements')) {
        $t = '';
        foreach ($el->getElements() as $c) $t .= getElContent($c, $plainText);
        return $t;
    }
    return '';
}
