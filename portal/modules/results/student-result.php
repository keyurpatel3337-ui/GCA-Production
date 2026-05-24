<?php
// Redirect to correct location
$result_id = $_POST['result_id'] ?? $_POST['id'] ?? 0;
header('Location: ../students/result.php?id=' . $result_id);
exit;
