<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method.');
    header('Location: transport-fee-settings.php');
    exit;
}

// Check permissions (Counsellor, Principal, Super Admin, Accountant) - adjust as needed
// Assuming basic logged in check is done in session_config, but specific role check is good practice
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$academic_year_id = $_POST['academic_year_id'] ?? null;
$transport_fee = $_POST['transport_fee'] ?? 0;
$gst_rate = $_POST['gst_rate'] ?? 0;
$description = $_POST['description'] ?? '';
$is_active = $_POST['is_active'] ?? 0;
$created_by = $_SESSION['user_id'];

if (!$academic_year_id) {
    set_flash_message('error', 'Academic Year ID is missing.');
    header('Location: transport-fee-settings.php');
    exit;
}

try {
    // Check if record exists for this year
    $stmt = $conn->prepare('SELECT id FROM tbl_transport_fee_settings WHERE academic_year_id = ?');
    $stmt->execute([$academic_year_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update
        $sql = 'UPDATE tbl_transport_fee_settings 
                SET transport_fee = ?, gst_rate = ?, description = ?, is_active = ?, updated_at = NOW() 
                WHERE academic_year_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$transport_fee, $gst_rate, $description, $is_active, $academic_year_id]);
        set_flash_message('success', 'Transport fee settings updated successfully.');
    } else {
        // Insert
        $sql = 'INSERT INTO tbl_transport_fee_settings 
                (academic_year_id, transport_fee, gst_rate, description, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$academic_year_id, $transport_fee, $gst_rate, $description, $is_active, $created_by]);
        set_flash_message('success', 'Transport fee settings created successfully.');
    }
} catch (PDOException $e) {
    set_flash_message('error', 'Database Error: ' . $e->getMessage());
}

header('Location: transport-fee-settings.php');
exit;
?>
