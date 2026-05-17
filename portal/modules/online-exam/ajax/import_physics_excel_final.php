<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

class PhysicsFinalImporter {
    private $conn;
    private $sharedStrings = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function loadSharedStrings($zip) {
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($zip->getFromIndex($index));
            foreach ($xml->si as $si) {
                $this->sharedStrings[] = (string)($si->t ?: $si->r->t);
            }
        }
    }

    private function getSheetRows($zip, $sheetName) {
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
        return $rows;
    }

    public function import($filePath) {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== TRUE) die("Failed to open XLSX");
        $this->loadSharedStrings($zip);

        // Define Variants to Import
        // Standard ID => Subject ID
        $variants_11 = [
            1 => 9, // 11th Gujarati -> Physics 9
            2 => 1  // 11th English -> Physics 1
        ];
        $variants_12 = [
            4 => 25, // 12th Gujarati -> Physics 25
            5 => 17  // 12th English -> Physics 17
        ];

        echo "Starting Final Import...\n";

        // 1. Process Standard 11 (Sheet 1)
        $rows11 = $this->getSheetRows($zip, 'xl/worksheets/sheet1.xml');
        $this->processVariants($rows11, $variants_11);

        // 2. Process Standard 12 (Sheet 2)
        $rows12 = $this->getSheetRows($zip, 'xl/worksheets/sheet2.xml');
        $this->processVariants($rows12, $variants_12);

        $zip->close();
        echo "\nFinal Import Complete!\n";
    }

    private function processVariants($rows, $variants) {
        foreach ($variants as $std_id => $sub_id) {
            echo "Importing for Standard ID: $std_id (Subject ID: $sub_id)...\n";
            $current_chp_id = 0;
            $count_chp = 0;
            $count_top = 0;

            foreach ($rows as $row) {
                $col0 = trim($row[0] ?? '');
                $col2 = trim($row[2] ?? '');

                // Detect Chapter
                if (preg_match('/(?:Ch|Chapter)\s*[-:]?\s*(\d+)\s*(.*)/i', $col0, $m)) {
                    $chp_num = (int)$m[1];
                    $chp_name = trim($m[2]);

                    $stmt = $this->conn->prepare("INSERT INTO chapters (chapter, standard_id, subid, chapter_number, language_id, created_by, updated_by, updated_on) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$chp_name, $std_id, $sub_id, $chp_num, ($std_id == 1 || $std_id == 4 ? 1 : 2), 1, 1]); // 1=Guj, 2=Eng
                    $current_chp_id = $this->conn->lastInsertId();
                    $count_chp++;
                    continue;
                }

                // Detect Topic
                if (!empty($col2) && $current_chp_id > 0 && stripos($col2, 'Topic Name') === false && stripos($col2, 'SUBJECT - PHYSICS') === false) {
                    $stmt = $this->conn->prepare("INSERT INTO tbl_topics (topic_name_english, chapter_id, subject_id, standard_id, created_by, updated_by, updated_on) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$col2, $current_chp_id, $sub_id, $std_id, 1, 1]);
                    $count_top++;
                }
            }
            echo "Finished Std $std_id: $count_chp Chapters, $count_top Topics.\n";
        }
    }
}

$importer = new PhysicsFinalImporter($conn);
$importer->import('c:/xampp/htdocs/GCA-Production/Physics STD-11 & 12 topics & Chapters.xlsx');
