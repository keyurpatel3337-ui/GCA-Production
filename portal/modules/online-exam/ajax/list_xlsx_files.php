<?php
$file = 'c:/xampp/htdocs/GCA-Production/Physics STD-11 & 12 topics & Chapters.xlsx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    for($i = 0; $i < $zip->numFiles; $i++) {
        echo "File: " . $zip->getNameIndex($i) . "\n";
    }
    $zip->close();
}
