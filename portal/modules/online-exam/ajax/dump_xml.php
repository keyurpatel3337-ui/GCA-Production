<?php
$file = 'C:/xampp/htdocs/GCA-Production/sample word file/12th JEE Maths [Paper-1] 06-05-26.docx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    echo $zip->getFromName('word/document.xml');
    $zip->close();
} else {
    echo "Failed to open zip";
}
