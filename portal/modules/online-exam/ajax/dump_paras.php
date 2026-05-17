<?php
require_once 'C:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';
require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;

function getElementContent($element) {
    $content = '';
    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
        $content .= $element->getText();
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $child) {
            $content .= getElementContent($child);
        }
    }
    return $content;
}

$file = 'C:/xampp/htdocs/GCA-Production/sample word file/12th JEE Maths [Paper-1] 06-05-26.docx';

try {
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($file);
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            $txt = getElementContent($element);
            if (trim($txt)) {
                echo "LINE: " . $txt . "\n";
            }
        }
    }
} catch (Exception $e) { echo $e->getMessage(); }
