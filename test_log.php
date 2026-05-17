<?php
require_once __DIR__ . '/common/helpers/report_functions.php';

$mobile = '7990965567';
$result = sendDailyCollectionSummary($conn, $mobile);

echo "Result: " . json_encode($result);
