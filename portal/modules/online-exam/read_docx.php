<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = 'C:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank.docx';

require_once 'chemistry-exam/DocxParser.php';

try {
    $parser = new DocxParser($file);
    $html = $parser->parse();
    
    // Check if it has a table
    if (strpos($html, '<table') !== false) {
        echo "Format: TABLE LAYOUT\n";
    } else {
        echo "Format: LINEAR PARAGRAPH LAYOUT\n";
    }
    
    echo "Total Length of Extracted HTML: " . strlen($html) . " bytes\n\n";
    echo "First 1500 chars of HTML:\n";
    echo substr(strip_tags($html, '<table><tr><td><img><math>'), 0, 1500);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
