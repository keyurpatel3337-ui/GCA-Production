<?php
require_once 'C:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';
require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;

function getCellText($cell, $plainText = false) {
    $text = '';
    if (method_exists($cell, 'getElements')) {
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
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $child) {
            $content .= getElementContent($child, $plainText);
        }
    }
    return $content;
}

function ommlToLatex(DOMNode $node) {
    if ($node->nodeType === XML_TEXT_NODE) return htmlspecialchars($node->textContent);
    $latex = '';
    foreach ($node->childNodes as $child) {
        $name = str_replace('m:', '', $child->nodeName);
        switch ($name) {
            case 'r':
                foreach ($child->childNodes as $rChild) {
                    if ($rChild->nodeName == 'm:t') $latex .= $rChild->textContent;
                }
                break;
            default:
                if ($child->hasChildNodes()) $latex .= ommlToLatex($child);
                break;
        }
    }
    return trim($latex);
}

$file = 'C:/xampp/htdocs/GCA-Production/sample word file/12th JEE Maths [Paper-1] 06-05-26.docx';
$tempDocx = 'C:/xampp/htdocs/GCA-Production/uploads/oes/trace_temp.docx';

try {
    copy($file, $tempDocx);
    $zip = new ZipArchive;
    if ($zip->open($tempDocx) === TRUE) {
        $docXml = $zip->getFromName('word/document.xml');
        $dom = new DOMDocument();
        @$dom->loadXML($docXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');

        $mathNodes = $xpath->query('//m:oMathPara | //m:oMath[not(ancestor::m:oMathPara)]');
        echo "Found Math Nodes: " . $mathNodes->length . "\n";
        
        for ($i = $mathNodes->length - 1; $i >= 0; $i--) {
            $node = $mathNodes->item($i);
            $latex = ommlToLatex($node);
            $wNS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $rNode = $dom->createElementNS($wNS, 'w:r');
            $tNode = $dom->createElementNS($wNS, 'w:t');
            $tNode->setAttribute('xml:space', 'preserve');
            $latexText = !empty(trim($latex)) ? '$$' . $latex . '$$' : ' ';
            $tNode->appendChild($dom->createTextNode($latexText));
            $rNode->appendChild($tNode);
            $node->parentNode->replaceChild($rNode, $node);
        }
        
        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();
    }

    echo "Attempting to load PRE-PROCESSED PHPWord...\n";
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($tempDocx);
    echo "PHPWord loaded successfully.\n";

    $allParagraphs = [];
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $txt = getCellText($cell);
                        if (trim($txt)) $allParagraphs[] = $txt;
                    }
                }
            } else {
                $txt = getCellText($element);
                if (trim($txt)) {
                    $allParagraphs[] = $txt;
                }
            }
        }
    }

    echo "Total Paras/Cells: " . count($allParagraphs) . "\n";
    $fullText = implode("\n", $allParagraphs);
    
    // Check for "1. ", "2. " pattern
    $qBlocks = preg_split('/(?:\n|^)\s*(\d+)[\.\)]\s+/', $fullText, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    echo "Question Blocks Found: " . (count($qBlocks) / 2) . "\n";
    
    if (count($qBlocks) > 1) {
        for ($i = 1; $i < min(count($qBlocks), 6); $i += 2) {
            echo "Q Header: " . $qBlocks[$i] . "\n";
            echo "Q Body: " . substr($qBlocks[$i+1], 0, 150) . "...\n";
            echo "-------------------\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
