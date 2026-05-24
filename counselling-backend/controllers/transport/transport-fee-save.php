<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
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
    $course_id = isset($_POST['course_id']) && !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $transport_fee = floatval($_POST['transport_fee']);
    $gst_rate = isset($_POST['gst_rate']) ? floatval($_POST['gst_rate']) : 0.0;
    $term1_months = isset($_POST['term1_months']) ? intval($_POST['term1_months']) : 7;
    $term2_months = isset($_POST['term2_months']) ? intval($_POST['term2_months']) : 6;
    $annual_months = isset($_POST['annual_months']) ? intval($_POST['annual_months']) : 12;
    $collection_timeline = $_POST['collection_timeline'] ?? 'Term-wise';
    $description = trim($_POST['remarks'] ?? $_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];

    try {
        if ($config_id > 0) {
            // Update existing configuration
            $stmt = $conn->prepare("UPDATE tbl_transport_fee_settings 
                                    SET academic_year_id = ?,
                                        course_id = ?,
                                        transport_fee = ?, 
                                        term1_months = ?, 
                                        term2_months = ?, 
                                        annual_months = ?, 
                                        collection_timeline = ?,
                                        gst_rate = ?, 
                                        description = ?, 
                                        is_active = ? 
                                    WHERE id = ?");
            $stmt->execute([
                $academic_year_id,
                $course_id,
                $transport_fee,
                $term1_months,
                $term2_months,
                $annual_months,
                $collection_timeline,
                $gst_rate,
                $description,
                $is_active,
                $config_id
            ]);
            sendSuccessResponse(['id' => $config_id], 'Transport fee configuration updated successfully!');
        } else {
            // Insert new configuration
            $stmt = $conn->prepare("INSERT INTO tbl_transport_fee_settings 
                                   (academic_year_id, course_id, transport_fee, term1_months, term2_months, annual_months, collection_timeline, gst_rate, 
                                    description, is_active, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $academic_year_id,
                $course_id,
                $transport_fee,
                $term1_months,
                $term2_months,
                $annual_months,
                $collection_timeline,
                $gst_rate,
                $description,
                $is_active,
                $user_id
            ]);
            $new_id = $conn->lastInsertId();
            sendSuccessResponse(['id' => $new_id], 'Transport fee configuration added successfully!');
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Save Transport Fee Configuration");
        if ($e->getCode() == 23000) {
            sendErrorResponse('Configuration for this academic year and course already exists!', 400);
        } else {
            sendErrorResponse('Error saving transport fee configuration. Please try again.', 500);
        }
    }
}

sendErrorResponse('Invalid request method', 405);
