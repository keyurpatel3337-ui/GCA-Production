<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $config_id = isset($_POST['config_id']) ? intval($_POST['config_id']) : 0;
    $academic_year_id = intval($_POST['academic_year_id']);
    $boys_hostel_fee = floatval($_POST['boys_hostel_fee']);
    $girls_hostel_fee = floatval($_POST['girls_hostel_fee']);
    $gst_applicable = isset($_POST['gst_applicable']) ? intval($_POST['gst_applicable']) : 0;
    $gst_rate = isset($_POST['gst_rate']) ? floatval($_POST['gst_rate']) : 0.0;
    $security_deposit = isset($_POST['security_deposit']) ? floatval($_POST['security_deposit']) : 0.0;
    $mess_charges_included = isset($_POST['mess_charges_included']) ? 1 : 0;
    $ac_room_extra_charge = isset($_POST['ac_room_extra_charge']) ? floatval($_POST['ac_room_extra_charge']) : 0.0;
    $split_threshold = isset($_POST['split_threshold']) ? floatval($_POST['split_threshold']) : 0.0;
    $remarks = trim($_POST['remarks'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];

    try {
        if ($config_id > 0) {
            // Update existing configuration
            $stmt = $conn->prepare("UPDATE tbl_hostel_fee_settings 
                                   SET academic_year_id = ?,
                                       boys_hostel_fee = ?, 
                                       girls_hostel_fee = ?, 
                                       gst_applicable = ?, 
                                       gst_rate = ?, 
                                       security_deposit = ?, 
                                       split_threshold = ?,
                                       mess_charges_included = ?, 
                                       ac_room_extra_charge = ?, 
                                       remarks = ?, 
                                       is_active = ? 
                                    WHERE id = ?");
            $stmt->execute([
                $academic_year_id,
                $boys_hostel_fee,
                $girls_hostel_fee,
                $gst_applicable,
                $gst_rate,
                $security_deposit,
                $split_threshold,
                $mess_charges_included,
                $ac_room_extra_charge,
                $remarks,
                $is_active,
                $config_id
            ]);
            sendSuccessResponse(['id' => $config_id], 'Hostel fee configuration updated successfully!');
        } else {
            // Insert new configuration
            $stmt = $conn->prepare("INSERT INTO tbl_hostel_fee_settings 
                                   (academic_year_id, boys_hostel_fee, girls_hostel_fee, 
                                    gst_applicable, gst_rate, security_deposit, split_threshold,
                                    mess_charges_included, ac_room_extra_charge, 
                                    remarks, is_active, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $academic_year_id,
                $boys_hostel_fee,
                $girls_hostel_fee,
                $gst_applicable,
                $gst_rate,
                $security_deposit,
                $split_threshold,
                $mess_charges_included,
                $ac_room_extra_charge,
                $remarks,
                $is_active,
                $user_id
            ]);
            $new_id = $conn->lastInsertId();
            sendSuccessResponse(['id' => $new_id], 'Hostel fee configuration added successfully!');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Hostel Fee Configuration");
        if ($e->getCode() == 23000) {
            sendErrorResponse('Configuration for this academic year already exists!', 400);
        } else {
            sendErrorResponse('Error saving hostel fee configuration. Please try again.', 500);
        }
    }
}

sendErrorResponse('Invalid request method', 405);
