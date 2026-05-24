<?php
require_once __DIR__ . '/../../common/constants.php';
require_once DB_CONNECT_FILE;

try {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_student_leaves` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `leave_type` varchar(50) NOT NULL,
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `reason` text NOT NULL,
        `doc_path` varchar(255) DEFAULT NULL,
        `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `reviewed_by` int(11) DEFAULT NULL,
        `reviewed_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `remarks` text,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `reviewed_by` (`reviewed_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);
    echo "Table 'tbl_student_leaves' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>