<?php
require_once 'C:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';
use PhpOffice\PhpWord\IOFactory;

$file = 'C:/xampp/htdocs/GCA-Production/sample word file/12th JEE Maths [Paper-1] 06-05-26.docx';

try {
    $phpWord = IOFactory::load($file);
    $text = '';
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if (method_exists($element, 'getText')) {
                $text .= $element->getText() . "\n";
            } elseif (method_exists($element, 'getElements')) {
                foreach ($element->getElements() as $child) {
                    if (method_exists($child, 'getText')) {
                        $text .= $child->getText();
                    }
                }
                $text .= "\n";
            }
        }
    }
    echo "--- START CONTENT ---\n";
    echo $text;
    echo "\n--- END CONTENT ---\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
