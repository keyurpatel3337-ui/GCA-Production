<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT)) {
    set_flash_message('danger', 'Unauthorized access.');
    header('Location: students.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['students'])) {
    $studentsData = $_POST['students'] ?? [];
    
    if (empty($studentsData)) {
        set_flash_message('warning', 'No students to update.');
        header('Location: students.php');
        exit;
    }

    $dbOps = new DatabaseOperations();
    $successCount = 0;
    $errorCount = 0;

    $regTableFields = ['gr_no', 'mob', 'amob', 'email', 'parent_mob'];
    $enrollTableFields = ['roll_no', 'division_id'];

    foreach ($studentsData as $id => $data) {
        $id = intval($id);
        
        // Prepare updates for registration table
        $regUpdates = [];
        foreach ($regTableFields as $field) {
            if (isset($data[$field])) {
                $regUpdates[$field] = trim($data[$field]);
            }
        }

        // Prepare updates for enrollment table
        $enrollUpdates = [];
        foreach ($enrollTableFields as $field) {
            if (isset($data[$field])) {
                $val = trim($data[$field]);
                $enrollUpdates[$field] = ($val === '' ? null : $val);
            }
        }

        if (empty($regUpdates) && empty($enrollUpdates)) continue;

        $conn->beginTransaction();
        try {
            $registrationUpdateSuccess = true;
            $enrollmentUpdateSuccess = true;

            // Update tbl_gm_std_registration
            if (!empty($regUpdates)) {
                $regSql = "UPDATE tbl_gm_std_registration SET ";
                $parts = [];
                $params = [];
                foreach ($regUpdates as $f => $v) {
                    $parts[] = "$f = ?";
                    $params[] = $v;
                }
                $regSql .= implode(', ', $parts) . " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $conn->prepare($regSql);
                $registrationUpdateSuccess = $stmt->execute($params);
            }

            // Update tbl_enrolled_students
            if (!empty($enrollUpdates)) {
                // Check if student is enrolled first
                $checkEnroll = $dbOps->customSelect("SELECT enrollment_id FROM tbl_enrolled_students WHERE registration_id = ?", [$id]);
                if (!empty($checkEnroll)) {
                    $enrollSql = "UPDATE tbl_enrolled_students SET ";
                    $parts = [];
                    $params = [];
                    foreach ($enrollUpdates as $f => $v) {
                        $parts[] = "$f = ?";
                        $params[] = $v;
                    }
                    $enrollSql .= implode(', ', $parts) . " WHERE registration_id = ?";
                    $params[] = $id;
                    
                    $stmt = $conn->prepare($enrollSql);
                    $enrollmentUpdateSuccess = $stmt->execute($params);
                }
            }

            if ($registrationUpdateSuccess && $enrollmentUpdateSuccess) {
                $conn->commit();
                $successCount++;
            } else {
                $conn->rollBack();
                $errorCount++;
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errorCount++;
        }
    }

    $redirectUrl = 'bulk-student-edit.php';
    if (isset($_POST['redirect_course'])) {
        $params = [
            'course' => $_POST['redirect_course'],
            'group' => $_POST['redirect_group'] ?? '',
            'medium' => $_POST['redirect_medium'] ?? '',
            'division' => $_POST['redirect_division'] ?? ''
        ];
        $redirectUrl .= '?' . http_build_query($params);
    }

    set_flash_message($successCount > 0 ? 'success' : 'danger', "Bulk update completed: $successCount successful, $errorCount failed.");
    header('Location: ' . $redirectUrl);
    exit;
} else {
    header('Location: students.php');
    exit;
}
