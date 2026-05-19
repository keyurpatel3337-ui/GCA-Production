<?php
define('APP_INIT', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct DB connection for debugging
$host = "localhost";
$dbname = "counselling";
$username = "root";
$password = "GCA_Secure_#2026_Portal";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$stmt = $conn->query("SELECT id, question_text FROM tbl_oes_questions WHERE question_text LIKE '%<img%' LIMIT 5");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . "\n";
    if (preg_match_all('/<img[^>]+src="([^">]+)"/', $row['question_text'], $matches)) {
        echo "Images found: " . implode(", ", $matches[1]) . "\n";
    } else {
        echo "No images in text.\n";
    }
    echo "-------------------\n";
}
?>
