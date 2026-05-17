<?php
require_once 'c:/xampp/htdocs/GCA-Production/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;

class PhysicsExcelImporter {
    private $conn;
    private $sharedStrings = [];
    private $rows = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

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
                // Handle sparse cells by using the 'r' attribute if necessary, but simple loop usually works
                $cellIdx = 0;
                foreach ($row->c as $cell) {
                    $r = (string)$cell['r']; // e.g. "A1"
                    $colLetter = preg_replace('/[0-9]/', '', $r);
                    $targetIdx = ord($colLetter) - ord('A');
                    while($cellIdx < $targetIdx) {
                        $rowData[$cellIdx++] = '';
                    }
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

    public function import() {
        // Truncate previous Physics data for these standards to avoid duplicates
        // $this->conn->exec("DELETE FROM chapters WHERE subid IN (1, 17)");
        // $this->conn->exec("DELETE FROM tbl_topics WHERE subject_id IN (1, 17)");

        $std_map = [
            'STD-11' => ['std_id' => 2, 'sub_id' => 1],
            'STD-12' => ['std_id' => 5, 'sub_id' => 17]
        ];
        
        $current_std_id = 2;
        $current_sub_id = 1;
        $current_chp_id = 0;
        
        $count_chp = 0;
        $count_top = 0;

        foreach ($this->rows as $row) {
            $col0 = trim($row[0] ?? '');
            $col1 = trim($row[1] ?? '');
            $col2 = trim($row[2] ?? '');

            if (stripos($col0, 'STD-11') !== false) {
                $current_std_id = 2; $current_sub_id = 1;
                echo "Processing Standard 11...\n";
                continue;
            }
            if (stripos($col0, 'STD-12') !== false) {
                $current_std_id = 5; $current_sub_id = 17;
                echo "Processing Standard 12...\n";
                continue;
            }

            // Detect Chapter in Col 0
            if (preg_match('/(?:Ch|Chapter)\s*[-:]?\s*(\d+)\s*(.*)/i', $col0, $m)) {
                $chp_num = $m[1];
                $chp_name = trim($m[2]);
                
                $stmt = $this->conn->prepare("INSERT INTO chapters (chapter, standard_id, subid, chapter_number, language_id, created_by, updated_by, updated_on) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$chp_name, $current_std_id, $current_sub_id, $chp_num, 2, 1, 1]);
                $current_chp_id = $this->conn->lastInsertId();
                $count_chp++;
                continue;
            }

            // Detect Topic in Col 2
            if (!empty($col2) && $current_chp_id > 0 && stripos($col2, 'Topic Name') === false) {
                $stmt = $this->conn->prepare("INSERT INTO tbl_topics (topic_name_english, chapter_id, subject_id, standard_id, created_by, updated_by, updated_on) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$col2, $current_chp_id, $current_sub_id, $current_std_id, 1, 1]);
                $count_top++;
            }
        }
        
        echo "\nImport Finished!\nChapters: $count_chp\nTopics: $count_top\n";
    }
}

$importer = new PhysicsExcelImporter($conn);
if ($importer->load('c:/xampp/htdocs/GCA-Production/Physics STD-11 & 12 topics & Chapters.xlsx')) {
    $importer->import();
}
