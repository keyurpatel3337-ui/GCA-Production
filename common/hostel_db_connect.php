<?php
/**
 * Hostel Database Connection
 * 
 * This file establishes a connection to the 'hostel' database
 * used for room allotments, complaints, and student services.
 */

// Use same credentials as main system if appropriate, 
// but pointing to the 'hostel' database specifically.
$hostel_host = "localhost";
$hostel_dbname = "hostel";
$hostel_username = "root";
$hostel_password = "GCA_Secure_#2026_Portal";

try {
    $hostel_conn = new PDO("mysql:host=$hostel_host;dbname=$hostel_dbname;charset=utf8mb4", $hostel_username, $hostel_password);
    $hostel_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $hostel_conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set time zone
    $hostel_conn->exec("SET time_zone = '+05:30'");
    
} catch (PDOException $e) {
    error_log("Hostel database connection failed: " . $e->getMessage());
    // Assign null so calling scripts can handle it gracefully if needed
    $hostel_conn = null;
    
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo "Hostel Database Error: " . $e->getMessage();
    }
}
