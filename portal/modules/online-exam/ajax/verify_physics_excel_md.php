<?php

class PhysicsExcelVerifierMDMultiSheet {
    private $sharedStrings = [];
    
    public function loadSharedStrings($zip) {
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($zip->getFromIndex($index));
            foreach ($xml->si as $si) {
                $this->sharedStrings[] = (string)($si->t ?: $si->r->t);
            }
        }
    }

    public function parseSheet($zip, $sheetName, $stdLabel) {
        $rows = [];
        if (($index = $zip->locateName($sheetName)) !== false) {
            $xml = simplexml_load_string($zip->getFromIndex($index));
            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                $cellIdx = 0;
                foreach ($row->c as $cell) {
                    $r = (string)$cell['r'];
                    $colLetter = preg_replace('/[0-9]/', '', $r);
                    $targetIdx = ord($colLetter) - ord('A');
                    while($cellIdx < $targetIdx) { $rowData[$cellIdx++] = ''; }
                    $val = (string)$cell->v;
                    if (isset($cell['t']) && $cell['t'] == 's') {
                        $val = $this->sharedStrings[(int)$val] ?? '';
                    }
                    $rowData[$cellIdx++] = $val;
                }
                $rows[] = $rowData;
            }
        }
        
        $md = "\n---\n## STANDARD - $stdLabel\n\n";
        foreach ($rows as $row) {
            $col0 = trim($row[0] ?? '');
            $col2 = trim($row[2] ?? '');

            if (preg_match('/(?:Ch|Chapter)\s*[-:]?\s*(\d+)\s*(.*)/i', $col0, $m)) {
                $md .= "### CHAPTER " . $m[1] . ": " . $m[2] . "\n";
                continue;
            }

            if (!empty($col2) && stripos($col2, 'Topic Name') === false && stripos($col2, 'SUBJECT - PHYSICS') === false) {
                $md .= "- " . $col2 . "\n";
            }
        }
        return $md;
    }

    public function generateMD($filePath) {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== TRUE) return false;
        
        $this->loadSharedStrings($zip);
        
        $md = "# Physics Curriculum Extraction Verification\n\n";
        $md .= "**Source:** Physics STD-11 & 12 topics & Chapters.xlsx\n\n";
        
        // Process Sheet 1 (Std 11)
        $md .= $this->parseSheet($zip, 'xl/worksheets/sheet1.xml', '11');
        
        // Process Sheet 2 (Std 12)
        $md .= $this->parseSheet($zip, 'xl/worksheets/sheet2.xml', '12');
        
        $zip->close();
        
        $filename = 'Physics_Extraction_Verification.md';
        file_put_contents($filename, $md);
        return $filename;
    }
}

$verifier = new PhysicsExcelVerifierMDMultiSheet();
if ($verifier->generateMD('c:/xampp/htdocs/GCA-Production/Physics STD-11 & 12 topics & Chapters.xlsx')) {
    echo "Verification MD (Multi-Sheet) created.\n";
} else {
    echo "Failed to load Excel.\n";
}
