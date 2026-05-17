<?php
$out = shell_exec('php debug_options.php');
$data = json_decode($out, true);
if ($data && isset($data['questions'])) {
    foreach ($data['questions'] as $i => $q) {
        echo "Q" . ($i+1) . " Option A: " . $q['option_a'] . "\n";
        echo "Q" . ($i+1) . " Option B: " . $q['option_b'] . "\n";
        if ($i > 3) break;
    }
} else {
    echo "FAILED TO PARSE: \n" . substr($out, 0, 1000);
}
