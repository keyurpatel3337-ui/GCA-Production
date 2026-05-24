<?php
/**
 * Parent Portal Helper Functions
 */

if (!function_exists('createParentAccount')) {
    /**
     * Creates a parent account if it doesn't already exist.
     * 
     * @param string $mobile Father's mobile number
     * @param PDO $conn Database connection
     * @return bool True if created or already exists, false on failure
     */
    function createParentAccount($mobile, $conn)
    {
        if (empty($mobile))
            return false;

        try {
            // Check if already exists
            $stmt = $conn->prepare("SELECT id FROM tbl_parent_login WHERE mobile_number = ?");
            $stmt->execute([$mobile]);
            if ($stmt->fetch()) {
                return true; // Already exists
            }

            // Create new account with mobile as default password
            $hashed_password = password_hash($mobile, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO tbl_parent_login (mobile_number, password, created_at) VALUES (?, ?, NOW())");
            return $stmt->execute([$mobile, $hashed_password]);
        } catch (PDOException $e) {
            error_log("Parent Account Creation Error: " . $e->getMessage());
            return false;
        }
    }
}
