<?php
require_once 'C:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';
$file = 'C:/xampp/htdocs/GCA-Production/sample word file/12th JEE Maths [Paper-1] 06-05-26.docx';
$phpWord = \PhpOffice\PhpWord\IOFactory::load($file);
foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $element) {
        echo get_class($element) . "\n";
    }
}
