<?php
require_once __DIR__ . '/session_config.php';
require_once dirname(__DIR__) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($mobile) || empty($password)) {
        set_flash_message('error', 'Please fill in all fields.');
        header('Location: parent-login.php');
        exit;
    }

    try {
        // First check if parent account exists in tbl_parent_login
        $stmt = $conn->prepare("SELECT * FROM tbl_parent_login WHERE mobile_number = :mobile");
        $stmt->execute(['mobile' => $mobile]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        $authenticated = false;

        if ($parent) {
            if (password_verify($password, $parent['password'])) {
                $authenticated = true;
            }
        } else {
            // If no parent account exists, check if any student has this mobile number (as student mob or parent_mob)
            // and try using that mobile number as initial password (backward compatibility)
            $stmt = $conn->prepare("SELECT id, mob, parent_mob FROM tbl_gm_std_registration WHERE mob = :mob1 OR parent_mob = :mob2 LIMIT 1");
            $stmt->execute(['mob1' => $mobile, 'mob2' => $mobile]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student && ($password === ($student['parent_mob'] ?? $student['mob']))) {
                // Initial login - create parent account automatically
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $conn->prepare("INSERT INTO tbl_parent_login (mobile_number, password) VALUES (:mobile, :password)");
                $insertStmt->execute(['mobile' => $mobile, 'password' => $hashed_password]);
                $authenticated = true;
            }
        }

        if ($authenticated) {
            // Find all children associated with this mobile number using parent_mob as primary link
            $stmt = $conn->prepare("SELECT id, student_name, surname, fathers_name FROM tbl_gm_std_registration WHERE parent_mob = :mob1 OR mob = :mob2");
            $stmt->execute(['mob1' => $mobile, 'mob2' => $mobile]);
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($children)) {
                set_flash_message('error', 'No students found associated with this mobile number.');
                header('Location: parent-login.php');
                exit;
            }

            // Set parent session
            $_SESSION['is_parent_login'] = true;
            $_SESSION['parent_mobile'] = $mobile;
            $_SESSION['children'] = $children;
            $_SESSION['active_student_id'] = $children[0]['id']; // Default to first child
            $_SESSION['student_id'] = $children[0]['id']; // Also set student_id for backward compatibility
            $_SESSION['user_role'] = 'parent';
            $_SESSION['login_time'] = time();

            header('Location: modules/parent-portal/dashboard.php');
            exit;
        } else {
            set_flash_message('error', 'Invalid mobile number or password.');
            header('Location: parent-login.php');
            exit;
        }
    } catch (PDOException $e) {
        logError("Parent login error: " . $e->getMessage());
        set_flash_message('error', 'An error occurred. Please try again later.');
        header('Location: parent-login.php');
        exit;
    }
} else {
    header('Location: parent-login.php');
    exit;
}
