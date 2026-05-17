<?php
$url = 'http://localhost/GCA-Production/portal/modules/online-exam/ajax/import-word-server.php';
$file_path = 'C:/xampp/htdocs/GCA-Production/sample word file/OES Question Bank.docx';

$cfile = new CURLFile($file_path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'OES Question Bank.docx');
$data = ['word_file' => $cfile];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Bypass cookie/session since we mocked roles? Wait, if we use curl we don't have a session!
// We will get a 302 redirect to login if we don't have a session.
// We must modify test_import.php to require import-word-server.php, but bypass the `exit;` or just read the json before exit.
