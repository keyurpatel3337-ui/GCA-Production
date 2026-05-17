<?php

/**
 * Receipt Mapping Helper Functions
 * Functions for managing receipt configuration mappings to fee types
 */

require_once __DIR__ . '/../db_connect.php';
require_once OPERATION_FILE;

/**
 * Get appropriate receipt configuration for a fee type
 * 
 * @param PDO $conn Database connection
 * @param string $fee_type Type of fee (school_fee, trust_facilities_fee, etc.)
 * @param int|null $school_id School ID (if applicable)
 * @return int|null Receipt configuration ID or null if not found
 */
function getReceiptConfigForFee($conn, $fee_type, $school_id = null)
{
    global $dbOps;
    if (!isset($dbOps)) {
        require_once OPERATION_FILE;
        $dbOps = new DatabaseOperations();
    }
    try {
        // Try to find specific mapping (prefer school-specific if provided) - SQL INJECTION SAFE
        $sql = "SELECT receipt_config_id 
                FROM tbl_fee_receipt_mapping
                WHERE fee_type = ?
                AND (school_id = ? OR school_id IS NULL)
                AND is_active = 1
                ORDER BY school_id DESC
                LIMIT 1";

        $results = $dbOps->customSelect($sql, [$fee_type, $school_id]);

        if (!empty($results)) {
            return $results[0]['receipt_config_id'];
        }

        // Fallback to default active receipt - SQL INJECTION SAFE
        $fallback = $dbOps->select('tbl_receipt_configuration', ['id'], ['is_active' => 1], 'id ASC', 1);

        return !empty($fallback) ? $fallback[0]['id'] : null;
    } catch (PDOException $e) {
        error_log("Error in getReceiptConfigForFee: " . $e->getMessage());
        return null;
    }
}

/**
 * Get receipt configuration details
 * 
 * @param PDO $conn Database connection
 * @param int $receipt_config_id Receipt configuration ID
 * @return array|null Receipt configuration details or null if not found
 */
function getReceiptConfigDetails($conn, $receipt_config_id)
{
    try {
        $stmt = $conn->prepare("
            SELECT * FROM tbl_receipt_configuration 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$receipt_config_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getReceiptConfigDetails: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all active receipt configurations
 * 
 * @param PDO $conn Database connection
 * @param string|null $organization_type Filter by organization type (optional)
 * @return array List of receipt configurations
 */
function getAllReceiptConfigs($conn, $organization_type = null)
{
    try {
        if ($organization_type) {
            $stmt = $conn->prepare("
                SELECT * FROM tbl_receipt_configuration 
                WHERE is_active = 1 AND organization_type = ?
                ORDER BY organization_name DESC
            ");
            $stmt->execute([$organization_type]);
        } else {
            global $dbOps;
            if (isset($dbOps)) {
                return $dbOps->customSelect("
                    SELECT * FROM tbl_receipt_configuration 
                    WHERE is_active = 1
                    ORDER BY organization_type, organization_name ASC
                ", []);
            } else {
                $stmt = $conn->query("
                    SELECT * FROM tbl_receipt_configuration 
                    WHERE is_active = 1
                    ORDER BY organization_type, organization_name DESC
                ");
            }
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getAllReceiptConfigs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all fee-to-receipt mappings
 * 
 * @param PDO $conn Database connection
 * @return array List of mappings with details
 */
function getAllFeeReceiptMappings($conn)
{
    try {
        global $dbOps;
        $query = "
            SELECT 
                frm.id,
                frm.fee_type,
                frm.school_id,
                COALESCE(s.school_name, '(All Schools)') as school_name,
                frm.receipt_config_id,
                rc.organization_name,
                rc.receipt_title,
                rc.organization_type,
                frm.is_active
            FROM tbl_fee_receipt_mapping frm
            LEFT JOIN tbl_schools s ON frm.school_id = s.id
            JOIN tbl_receipt_configuration rc ON frm.receipt_config_id = rc.id
            WHERE frm.is_active = 1
            ORDER BY frm.fee_type, frm.school_id
        ";

        if (isset($dbOps)) {
            return $dbOps->customSelect($query, []);
        } else {
            $stmt = $conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error in getAllFeeReceiptMappings: " . $e->getMessage());
        return [];
    }
}

/**
 * Create or update fee-to-receipt mapping
 * 
 * @param PDO $conn Database connection
 * @param string $fee_type Fee type
 * @param int $receipt_config_id Receipt configuration ID
 * @param int|null $school_id School ID (optional)
 * @return bool Success status
 */
function saveFeeReceiptMapping($conn, $fee_type, $receipt_config_id, $school_id = null)
{
    try {
        // Check if mapping exists
        $stmt = $conn->prepare("
            SELECT id FROM tbl_fee_receipt_mapping
            WHERE fee_type = ? AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$fee_type, $school_id, $school_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing mapping
            $stmt = $conn->prepare("
                UPDATE tbl_fee_receipt_mapping 
                SET receipt_config_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$receipt_config_id, $existing['id']]);
        } else {
            // Insert new mapping
            $stmt = $conn->prepare("
                INSERT INTO tbl_fee_receipt_mapping 
                (fee_type, school_id, receipt_config_id, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$fee_type, $school_id, $receipt_config_id]);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error in saveFeeReceiptMapping: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete fee-to-receipt mapping
 * 
 * @param PDO $conn Database connection
 * @param int $mapping_id Mapping ID
 * @return bool Success status
 */
function deleteFeeReceiptMapping($conn, $mapping_id)
{
    try {
        $stmt = $conn->prepare("
            UPDATE tbl_fee_receipt_mapping 
            SET is_active = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$mapping_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error in deleteFeeReceiptMapping: " . $e->getMessage());
        return false;
    }
}

/**
 * Get fee types (for dropdown/selection)
 * 
 * @return array List of fee types with display names
 */
function getFeeTypes()
{
    return [
        'school_fee' => 'Tuition Fee',
        'trust_facilities_fee' => 'Trust Facilities Fee',
        'hostel_fee' => 'Hostel Fee',
        'hostel_security' => 'Hostel Security Deposit',
        'tuition_fee' => 'Tuition Fee',
        'token_fee' => 'Token Fee',
        'other' => 'Other Fee'
    ];
}

/**
 * Format fee type for display
 * 
 * @param string $fee_type Fee type
 * @return string Formatted fee type name
 */
function formatFeeType($fee_type)
{
    $types = getFeeTypes();
    return $types[$fee_type] ?? ucwords(str_replace('_', ' ', $fee_type));
}

/**
 * Get organization types (for dropdown/selection)
 * 
 * @return array List of organization types
 */
function getOrganizationTypes()
{
    return [
        'school' => 'School',
        'trust' => 'Trust',
        'hostel' => 'Hostel',
        'other' => 'Other'
    ];
}


