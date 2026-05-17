<?php
// c:\xampp\htdocs\GCA-Production\portal\modules\online-exam\chemistry-exam\save_questions.php

require_once 'DocxParser.php';
// require_once '../../../../common/constants.php';
// require_once DB_CONNECT_FILE;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['word_file'])) {
    $file = $_FILES['word_file']['tmp_name'];

    try {
        $parser = new DocxParser($file);
        $html = $parser->parse();

        // In a real scenario, you'd split the HTML into questions by looking for "Q1", "Q2" patterns,
        // or by parsing table rows, similar to your existing table-based import.
        
        // Example mock insertion:
        /*
        $stmt = $conn->prepare("INSERT INTO tbl_oes_questions (standard, subject, question_text) VALUES (?, ?, ?)");
        $stmt->execute(['12th', 'CHEMISTRY', $html]);
        */

        echo json_encode([
            'success' => true,
            'message' => 'File parsed successfully!',
            'parsed_html' => $html // Send back to frontend for preview
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
}
