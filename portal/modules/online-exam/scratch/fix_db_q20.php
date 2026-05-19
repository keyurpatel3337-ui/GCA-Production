<?php
require_once 'C:/xampp/htdocs/GCA-Production/common/constants.php';
require_once DB_CONNECT_FILE;

try {
    $id = 20;
    $option_a = '$$\begin{bmatrix} 1 & 0 \\ 25 & 1 \end{bmatrix}$$';
    $option_b = '$$\begin{bmatrix} 1 & 5 \\ 0 & 1 \end{bmatrix}$$';
    $option_c = '$$\begin{bmatrix} 1 & 25 \\ 0 & 1 \end{bmatrix}$$';
    $option_d = '$$\begin{bmatrix} 1 & 0 \\ 5 & 1 \end{bmatrix}$$';

    $stmt = $conn->prepare("UPDATE tbl_oes_questions SET option_a = ?, option_b = ?, option_c = ?, option_d = ? WHERE id = ?");
    $stmt->execute([$option_a, $option_b, $option_c, $option_d, $id]);
    
    echo "Question ID $id updated successfully with Matrix LaTeX.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
