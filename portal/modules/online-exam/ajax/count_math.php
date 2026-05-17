<?php
$c = file_get_contents('doc_xml.txt');
// Try both encodings
$c8 = mb_convert_encoding($c, 'UTF-8', 'UTF-16LE');
echo "UTF-8 Count (m:): " . substr_count($c8, 'm:') . "\n";
echo "Raw Count (m:): " . substr_count($c, 'm:') . "\n";
echo "UTF-8 Count (<m:r): " . substr_count($c8, '<m:r') . "\n";
