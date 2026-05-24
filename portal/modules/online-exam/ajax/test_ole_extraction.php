<?php
/**
 * Standalone CLI test for ChemDraw OLE extraction
 * Bypasses auth/session — tests only the parser logic
 */

// Load autoloader and constants
require_once 'c:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;

$file     = dirname(dirname(dirname(dirname(__DIR__)))) . '/sample word file/OES Question Bank_Che_G.docx';
$fileName = basename($file);
$questions = [];

$tempDir = UPLOADS_PATH . 'oes' . DIRECTORY_SEPARATOR;
if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);

$tempDocx = $tempDir . uniqid('test_docx_') . '.docx';
copy($file, $tempDocx);

$zip = new ZipArchive;
if ($zip->open($tempDocx) !== TRUE) {
    echo "ERROR: Cannot open zip\n";
    exit(1);
}

$docXml = $zip->getFromName('word/document.xml');
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadXML($docXml);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');

// === CHEMDRAW OLE EXTRACTION ===
$oleImageMap = [];
$relsXml = $zip->getFromName('word/_rels/document.xml.rels');
if ($relsXml) {
    $domRels = new DOMDocument();
    @$domRels->loadXML($relsXml);
    $relMap = [];
    foreach ($domRels->getElementsByTagName('Relationship') as $rel) {
        $relMap[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
    }
    
    echo "=== SCANNING FOR OLE OBJECTS ===\n";
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
                        $target = isset($relMap[$rId]) ? $relMap[$rId] : 'NOT_FOUND';
                        echo "  Found OLE at t{$tblIdx}_r{$rIdx}_c{$cIdx} | rId=$rId | target=$target\n";
                        
                        if ($rId && isset($relMap[$rId])) {
                            $emfBytes = $zip->getFromName('word/' . $relMap[$rId]);
                            if ($emfBytes !== false && strlen($emfBytes) > 100) {
                                $emfFile = $tempDir . 'ole_t' . $tblIdx . '_r' . $rIdx . '_c' . $cIdx . '_' . uniqid() . '.emf';
                                $pngFile = $tempDir . 'qimg_ole_' . uniqid() . '_' . time() . '.png';
                                file_put_contents($emfFile, $emfBytes);
                                $oleImageMap['t' . $tblIdx . '_r' . $rIdx . '_c' . $cIdx] = [
                                    'emf' => $emfFile,
                                    'png' => $pngFile,
                                    'url' => BASE_URL . '/uploads/oes/' . basename($pngFile),
                                ];
                                echo "    -> EMF saved: " . basename($emfFile) . " (" . strlen($emfBytes) . " bytes)\n";
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
    echo "Total OLE images found: " . count($oleImageMap) . "\n\n";
}

$zip->close();

// EMF -> PNG conversion
if (!empty($oleImageMap)) {
    echo "=== CONVERTING EMF -> PNG ===\n";
    
    $pyLines = [
        'import sys,os,ctypes,struct',
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
        // Per-call try/except so one failure doesn't abort the whole script
        $pyLines[] = "try: cvt('$e','$p')";
        $pyLines[] = "except Exception as _e: print('ERR:'+str(_e))";
    }
    $pyFile = $tempDir . 'ole_cvt_' . uniqid() . '.py';
    file_put_contents($pyFile, implode("\n", $pyLines));
    $pyOutput = shell_exec('python ' . escapeshellarg($pyFile) . ' 2>&1');
    @unlink($pyFile);
    echo $pyOutput . "\n";
    
    foreach ($oleImageMap as $key => &$job) {
        $exists = file_exists($job['png']);
        $job['img_html'] = $exists
            ? '<img src="' . $job['url'] . '" class="css-test_ole_extraction-3cf331" />'
            : '';
        echo "  $key -> PNG: " . ($exists ? 'CREATED (' . filesize($job['png']) . ' bytes)' : 'MISSING') . "\n";
    }
    unset($job);
    echo "\n";
}

@unlink($tempDocx);

echo "=== DONE ===\n";
echo "OLE map keys: " . implode(', ', array_keys($oleImageMap)) . "\n";
