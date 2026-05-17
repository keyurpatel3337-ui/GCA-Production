<?php
require_once 'c:/xampp/htdocs/GCA-Production/portal/vendor/autoload.php';

class PhysicsExcelVerifier {
    private $sharedStrings = [];
    private $rows = [];
    
    public function load($filePath) {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== TRUE) return false;
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($zip->getFromIndex($index));
            foreach ($xml->si as $si) {
                $this->sharedStrings[] = (string)($si->t ?: $si->r->t);
            }
        }
        if (($index = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
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
                $this->rows[] = $rowData;
            }
        }
        $zip->close();
        return true;
    }

    public function generateVerificationDoc() {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        
        $currentStd = "STD-11";
        $section->addTitle("Physics Curriculum Extraction Verification", 1);
        $section->addText("Source: Physics STD-11 & 12 topics & Chapters.xlsx", ['bold' => true]);
        $section->addTextBreak(1);
        
        foreach ($this->rows as $row) {
            $col0 = trim($row[0] ?? '');
            $col1 = trim($row[1] ?? '');
            $col2 = trim($row[2] ?? '');

            if (stripos($col0, 'STD-11') !== false) {
                $section->addPageBreak();
                $section->addTitle("STANDARD - 11", 1);
                continue;
            }
            if (stripos($col0, 'STD-12') !== false) {
                $section->addPageBreak();
                $section->addTitle("STANDARD - 12", 1);
                continue;
            }

            if (preg_match('/(?:Ch|Chapter)\s*[-:]?\s*(\d+)\s*(.*)/i', $col0, $m)) {
                $section->addTextBreak(1);
                $section->addText("CHAPTER " . $m[1] . ": " . $m[2], ['bold' => true, 'size' => 12]);
                continue;
            }

            if (!empty($col2) && stripos($col2, 'Topic Name') === false) {
                $section->addText("- " . $col2, ['size' => 10]);
            }
        }

        $filename = 'Physics_Extraction_Verification.docx';
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filename);
        return $filename;
    }
}

$verifier = new PhysicsExcelVerifier();
if ($verifier->load('c:/xampp/htdocs/GCA-Production/Physics STD-11 & 12 topics & Chapters.xlsx')) {
    $file = $verifier->generateVerificationDoc();
    echo "Verification document created: " . $file . "\n";
    echo "URL: " . "http://localhost/GCA-Production/portal/modules/online-exam/ajax/" . $file . "\n";
} else {
    echo "Failed to load Excel.\n";
}
