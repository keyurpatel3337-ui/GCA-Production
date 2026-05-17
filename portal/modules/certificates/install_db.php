<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

try {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_issued_certificates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `certificate_type` varchar(100) NOT NULL,
        `serial_number` varchar(50) NOT NULL,
        `issued_date` date NOT NULL,
        `academic_year_id` int(11) DEFAULT NULL,
        `issued_by` int(11) NOT NULL,
        `remarks` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `certificate_type` (`certificate_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    echo "Table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
