<?php
$file = 'c:/xampp/htdocs/GCA-Production/Physics STD-11 & 12 topics & Chapters.xlsx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    $strings = [];
    if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $xml = simplexml_load_string($zip->getFromIndex($index));
        foreach ($xml->si as $si) {
            $strings[] = (string)$si->t;
        }
    }
    
    $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $val = (string)$cell->v;
            if (isset($cell['t']) && $cell['t'] == 's') $val = $strings[(int)$val];
            $rowData[] = $val;
        }
        if (stripos(implode(' ', $rowData), 'STD-12') !== false) {
            echo "Found STD-12 at row!\n";
            print_r($rowData);
        }
    }
    
    $zip->close();
} else {
    echo "Failed to open XLSX\n";
}
