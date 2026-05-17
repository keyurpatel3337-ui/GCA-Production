<?php
$zip = new ZipArchive();
if ($zip->open('C:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank.docx')) {
    $xml = $zip->getFromName('word/document.xml');
    
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Q1 is row 2. Option A is column 9 (index 8).
    $rows = $xpath->query('//w:tbl/w:tr');
    $row = $rows->item(1); // Row 2 (Q1)
    
    $cells = $xpath->query('.//w:tc', $row);
    if ($cells->length > 8) {
        $cellA = $cells->item(8);
        echo $dom->saveXML($cellA);
    }
}
