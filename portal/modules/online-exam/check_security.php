<?php
header('Content-Type: text/plain');
echo "GCA Security & Connectivity Check\n";
echo "================================\n\n";

$targets = [
    "Google (Basic Internet)" => "https://www.google.com",
    "CodeCogs Domain" => "https://latex.codecogs.com",
    "CodeCogs IP (Direct)" => "http://45.76.230.217",
    "QuickLatex" => "https://query.quicklatex.com"
];

foreach ($targets as $name => $url) {
    echo "Testing $name...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 400) {
        echo "RESULT: SUCCESS (HTTP $httpCode)\n";
    } else {
        echo "RESULT: FAILED\n";
        echo "HTTP Code: $httpCode\n";
        echo "CURL Error: $error\n";
    }
    echo "--------------------------------\n";
}

echo "\nCheck Complete.";
