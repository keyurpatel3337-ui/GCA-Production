<?php
/**
 * Settings Helper Functions
 * 
 * Provides functions to get/set system settings from database
 */

/**
 * Get a single setting value
 * 
 * @param PDO $conn Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value
 */
function getSetting($conn, $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT setting_value, setting_type FROM tbl_system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return $default;
        }
        
        // Cast based on type
        switch ($row['setting_type']) {
            case 'number':
                return (int) $row['setting_value'];
            case 'boolean':
                return $row['setting_value'] === '1' || $row['setting_value'] === 'true';
            case 'json':
                return json_decode($row['setting_value'], true);
            default:
                return $row['setting_value'];
        }
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Set a setting value
 * 
 * @param PDO $conn Database connection
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success
 */
function setSetting($conn, $key, $value) {
    try {
        // Convert value to string for storage
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $value = json_encode($value);
        }
        
        $stmt = $conn->prepare("UPDATE tbl_system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get all settings by category
 * 
 * @param PDO $conn Database connection
 * @param string $category Category name
 * @return array Settings array
 */
function getSettingsByCategory($conn, $category) {
    try {
        $stmt = $conn->prepare("SELECT setting_key, setting_value, setting_type, description 
                                FROM tbl_system_settings 
                                WHERE category = ? 
                                ORDER BY id");
        $stmt->execute([$category]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            
            // Cast based on type
            switch ($row['setting_type']) {
                case 'number':
                    $value = (int) $value;
                    break;
                case 'boolean':
                    $value = $value === '1' || $value === 'true';
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settings[$row['setting_key']] = [
                'value' => $value,
                'type' => $row['setting_type'],
                'description' => $row['description']
            ];
        }
        
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all settings as flat array
 * 
 * @param PDO $conn Database connection
 * @return array Settings as key => value
 */
function getAllSettings($conn) {
    try {
        $sql = "SELECT setting_key, setting_value, setting_type FROM tbl_system_settings";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            
            switch ($row['setting_type']) {
                case 'number':
                    $value = (int) $value;
                    break;
                case 'boolean':
                    $value = $value === '1' || $value === 'true';
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settings[$row['setting_key']] = $value;
        }
        
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Log an audit action
 * 
 * @param PDO $conn Database connection
 * @param string $action_type Action performed
 * @param string $action_category Category name
 * @param string $description Additional details
 * @return bool Success
 */
function logAudit($conn, $action_type, $action_category = null, $description = null) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['user_name'] ?? 'System';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $conn->prepare("INSERT INTO tbl_audit_logs 
                                (performed_by, user_name, action_type, action_category, description, ip_address, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $userName, $action_type, $action_category, $description, $ipAddress]);
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get recent audit logs
 * 
 * @param PDO $conn Database connection
 * @param int $limit Number of records
 * @param string $category Filter by category
 * @return array Logs
 */
function getAuditLogs($conn, $limit = 100, $category = null) {
    try {
        $sql = "SELECT * FROM tbl_audit_logs";
        $params = [];
        
        if ($category) {
            $sql .= " WHERE action_category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}


