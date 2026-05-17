<?php
$conn = new PDO('mysql:host=localhost;dbname=counselling;charset=utf8', 'root', 'GCA_Secure_#2026_Portal');
$stmt = $conn->query('SHOW TABLES LIKE "%payment%"');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $conn->query('SHOW TABLES LIKE "%fee%"');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
