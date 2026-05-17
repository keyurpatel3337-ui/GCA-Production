<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

header('Content-Type: application/json');

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    sendErrorResponse('Unauthorized access', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Scholarship rule save - POST data: " . json_encode($_POST));

    $action = $_POST['action'] ?? '';
    error_log("Scholarship rule save - Action: " . $action);

    if ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            sendErrorResponse('Rule ID required', 400);
            exit;
        }
        try {
            $stmt = $conn->prepare("DELETE FROM tbl_scholarship_rules WHERE id = ?");
            if ($stmt->execute([$id])) {
                sendSuccessResponse(['id' => $id], 'Rule deleted successfully.');
                exit;
            } else {
                sendErrorResponse('Failed to delete rule.', 500);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Delete scholarship rule error: " . $e->getMessage());
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
            exit;
        }
    }

    // Add or Edit
    $id = $_POST['id'] ?? null;
    $scholarship_type_id = $_POST['scholarship_type_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;
    $group_id = $_POST['group_id'] ?? null;
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $amount = $_POST['scholarship_discount_amount'] ?? 0;
    $min_range = $_POST['min_range'] ?? 0;
    $max_range = $_POST['max_range'] ?? 0;
    $is_active = $_POST['is_active'] ?? 1;

    error_log("Scholarship rule save - Parsed data: type_id=$scholarship_type_id, course=$course_id, group=$group_id, amount=$amount, min=$min_range, max=$max_range");

    // Validation - check for empty or null values
    if (empty($scholarship_type_id) || empty($course_id) || empty($group_id)) {
        error_log("Scholarship rule save - Validation failed: type_id=" . var_export($scholarship_type_id, true) . ", course=" . var_export($course_id, true) . ", group=" . var_export($group_id, true));
        sendErrorResponse('All mandatory fields (Scholarship Type, Course, Group) must be filled.', 400);
        exit;
    }

    // Get Type Code
    $stmt = $conn->prepare("SELECT type_code FROM tbl_scholarship_types WHERE id = ?");
    $stmt->execute([$scholarship_type_id]);
    $type = $stmt->fetch(PDO::FETCH_ASSOC);
    $type_code = $type['type_code'] ?? '';

    $cols = [
        'scholarship_type_id' => $scholarship_type_id,
        'course_id' => $course_id,
        'group_id' => $group_id,
        'discount_type' => $discount_type,
        'scholarship_discount_amount' => $amount,
        'is_active' => $is_active,
        'gmsat_minimum_mark' => null,
        'gmsat_maximum_mark' => null,
        'board_pr_minimum' => null,
        'board_pr_maximum' => null
    ];

    if ($type_code === 'GMSAT') {
        $cols['gmsat_minimum_mark'] = $min_range;
        $cols['gmsat_maximum_mark'] = $max_range;
    } elseif ($type_code === 'BOARD') {
        $cols['board_pr_minimum'] = $min_range;
        $cols['board_pr_maximum'] = $max_range;
    } elseif ($type_code === 'ICC') {
        // ICC scholarship doesn't require marks range
        // Leave all mark ranges as null
    } else {
        // For any other future types, leave ranges as null
        error_log("Unknown scholarship type code: " . $type_code);
    }

    if ($action === 'add') {
        try {
            $sql = "INSERT INTO tbl_scholarship_rules 
                    (scholarship_type_id, course_id, group_id, discount_type, scholarship_discount_amount, is_active, 
                     gmsat_minimum_mark, gmsat_maximum_mark, board_pr_minimum, board_pr_maximum)
                    VALUES 
                    (:scholarship_type_id, :course_id, :group_id, :discount_type, :scholarship_discount_amount, :is_active,
                     :gmsat_minimum_mark, :gmsat_maximum_mark, :board_pr_minimum, :board_pr_maximum)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute($cols)) {
                $new_id = $conn->lastInsertId();
                sendSuccessResponse(['id' => $new_id], 'Rule added successfully.');
                exit;
            } else {
                error_log("Failed to add scholarship rule - PDO errorInfo: " . json_encode($stmt->errorInfo()));
                sendErrorResponse('Failed to add rule.', 500);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Add scholarship rule error: " . $e->getMessage());
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
            exit;
        }
    } elseif ($action === 'edit' && $id) {
        try {
            $cols['id'] = $id;
            $sql = "UPDATE tbl_scholarship_rules SET
                    scholarship_type_id = :scholarship_type_id,
                    course_id = :course_id,
                    group_id = :group_id,
                    discount_type = :discount_type,
                    scholarship_discount_amount = :scholarship_discount_amount,
                    is_active = :is_active,
                    gmsat_minimum_mark = :gmsat_minimum_mark,
                    gmsat_maximum_mark = :gmsat_maximum_mark,
                    board_pr_minimum = :board_pr_minimum,
                    board_pr_maximum = :board_pr_maximum
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute($cols)) {
                sendSuccessResponse(['id' => $id], 'Rule updated successfully.');
                exit;
            } else {
                error_log("Failed to update scholarship rule - PDO errorInfo: " . json_encode($stmt->errorInfo()));
                sendErrorResponse('Failed to update rule.', 500);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Update scholarship rule error: " . $e->getMessage());
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
            exit;
        }
    } else {
        sendErrorResponse('Invalid action or missing ID for edit', 400);
        exit;
    }
}
