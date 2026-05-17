<?php
$zip = new ZipArchive();
if ($zip->open('C:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank.docx')) {
    $xml = $zip->getFromName('word/document.xml');
    
    // Find m:oMathPara or m:oMath inside table cells
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
    
    $nodes = $xpath->query('//w:tc//m:oMathPara | //w:tc//m:oMath[not(ancestor::m:oMathPara)]');
    if ($nodes->length > 0) {
        $node = $nodes->item(0);
        echo "Found: " . $node->nodeName . "\n";
        echo "Parent: " . $node->parentNode->nodeName . "\n";
        echo "Grandparent: " . $node->parentNode->parentNode->nodeName . "\n";
        echo "Great-Grandparent: " . $node->parentNode->parentNode->parentNode->nodeName . "\n";
    }
}
