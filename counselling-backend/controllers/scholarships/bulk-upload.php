<?php

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
$base_path = dirname(dirname(__DIR__));
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

header('Content-Type: application/json');

// Check if user has permission
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    sendErrorResponse('Unauthorized access', 403);
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['bulk_file']) || $_FILES['bulk_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['bulk_file'];
    $filePath = $file['tmp_name'];

    // Validate file type
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'csv') {
        throw new Exception('Only CSV files are allowed');
    }

    // Open and read CSV file
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Failed to open CSV file');
    }

    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $rowNumber = 0;

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception('Invalid CSV file - no header row found');
    }

    // Clean and normalize headers (remove BOM, trim, lowercase)
    $headers = array_map(function ($h) {
        // Remove UTF-8 BOM if present
        $h = str_replace("\xEF\xBB\xBF", '', $h);
        return strtolower(trim($h));
    }, $headers);

    // Validate headers
    $requiredHeaders = ['scholarship_type_code', 'course_name', 'group_name', 'min_range', 'max_range', 'discount_type', 'discount_value', 'is_active'];
    $headerMap = array_flip($headers);

    foreach ($requiredHeaders as $required) {
        if (!isset($headerMap[$required])) {
            // Log actual headers for debugging
            error_log("CSV Headers found: " . implode(', ', $headers));
            throw new Exception("Missing required column: $required. Found columns: " . implode(', ', $headers));
        }
    }

    // Begin transaction
    $conn->beginTransaction();

    // Process each row
    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;

        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        try {
            // Map row data to columns
            $data = [];
            foreach ($requiredHeaders as $header) {
                $index = $headerMap[$header];
                $data[$header] = isset($row[$index]) ? trim($row[$index]) : '';
            }

            // Validate required fields
            if (
                empty($data['scholarship_type_code']) || empty($data['course_name']) ||
                empty($data['min_range']) || empty($data['max_range']) ||
                empty($data['discount_type']) || empty($data['discount_value'])
            ) {
                throw new Exception('Missing required fields');
            }

            // Get scholarship type ID
            $stmt = $conn->prepare("SELECT id FROM tbl_scholarship_types WHERE type_code = ? AND is_active = 1");
            $stmt->execute([$data['scholarship_type_code']]);
            $scholarshipType = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$scholarshipType) {
                throw new Exception("Invalid scholarship type code: {$data['scholarship_type_code']}");
            }
            $scholarshipTypeId = $scholarshipType['id'];

            // Get course ID - try exact match first, then partial match
            $stmt = $conn->prepare("SELECT id, course_name FROM tbl_courses WHERE course_name = ? AND is_active = 1");
            $stmt->execute([$data['course_name']]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                // Try partial match
                $stmt = $conn->prepare("SELECT id, course_name FROM tbl_courses WHERE course_name LIKE ? AND is_active = 1 LIMIT 1");
                $stmt->execute(['%' . $data['course_name'] . '%']);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$course) {
                    // Get available courses for better error message
                    $stmt = $conn->prepare("SELECT course_name FROM tbl_courses WHERE is_active = 1 LIMIT 5");
                    $stmt->execute();
                    $availableCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    throw new Exception("Invalid course name: {$data['course_name']}. Available courses: " . implode(', ', $availableCourses));
                }
            }
            $courseId = $course['id'];

            // Get group ID (optional but if provided must be valid)
            $groupId = null;
            if (!empty($data['group_name']) && trim($data['group_name']) !== '') {
                $stmt = $conn->prepare("SELECT id FROM tbl_group WHERE group_name = ? AND is_active = 1");
                $stmt->execute([trim($data['group_name'])]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$group) {
                    // Get available groups for better error message
                    $stmt = $conn->prepare("SELECT group_name FROM tbl_group WHERE is_active = 1 LIMIT 5");
                    $stmt->execute();
                    $availableGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    throw new Exception("Invalid group name: {$data['group_name']}. Available groups: " . implode(', ', $availableGroups) . " (or leave empty)");
                }
                $groupId = $group['id'];
            }

            // Validate discount type
            if (!in_array(strtolower($data['discount_type']), ['percentage', 'amount'])) {
                throw new Exception("Invalid discount type: {$data['discount_type']}. Must be 'percentage' or 'amount'");
            }

            // Validate numeric values
            $minRange = floatval($data['min_range']);
            $maxRange = floatval($data['max_range']);
            $discountValue = floatval($data['discount_value']);

            // Allow min_range and max_range to be equal (for single value scholarships)
            // if ($minRange >= $maxRange) {
            //     throw new Exception("Min range must be less than max range");
            // }

            if ($discountValue <= 0) {
                throw new Exception("Discount value must be greater than 0");
            }

            // Validate percentage discount
            if (strtolower($data['discount_type']) === 'percentage' && $discountValue > 100) {
                throw new Exception("Percentage discount cannot exceed 100%");
            }

            // Determine which fields to use based on scholarship type
            $gmsatMinMark = null;
            $gmsatMaxMark = null;
            $boardPrMin = null;
            $boardPrMax = null;

            if ($data['scholarship_type_code'] === 'GMSAT') {
                $gmsatMinMark = $minRange;
                $gmsatMaxMark = $maxRange;
            } elseif ($data['scholarship_type_code'] === 'BOARD') {
                $boardPrMin = $minRange;
                $boardPrMax = $maxRange;
            }

            // Check for duplicate rule
            $checkSql = "SELECT id FROM tbl_scholarship_rules 
                        WHERE scholarship_type_id = ? 
                        AND course_id = ? 
                        AND (group_id = ? OR (group_id IS NULL AND ? IS NULL))
                        AND (
                            (gmsat_minimum_mark = ? AND gmsat_maximum_mark = ?) 
                            OR (board_pr_minimum = ? AND board_pr_maximum = ?)
                        )";
            $stmt = $conn->prepare($checkSql);
            $stmt->execute([
                $scholarshipTypeId,
                $courseId,
                $groupId,
                $groupId,
                $gmsatMinMark,
                $gmsatMaxMark,
                $boardPrMin,
                $boardPrMax
            ]);

            if ($stmt->fetch()) {
                throw new Exception("Duplicate rule exists for this combination");
            }

            // Insert scholarship rule
            $insertSql = "INSERT INTO tbl_scholarship_rules 
                (scholarship_type_id, course_id, group_id, 
                 gmsat_minimum_mark, gmsat_maximum_mark, 
                 board_pr_minimum, board_pr_maximum,
                 discount_type, scholarship_discount_amount, 
                 is_active, created_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

            $stmt = $conn->prepare($insertSql);
            $stmt->execute([
                $scholarshipTypeId,
                $courseId,
                $groupId,
                $gmsatMinMark,
                $gmsatMaxMark,
                $boardPrMin,
                $boardPrMax,
                strtolower($data['discount_type']),
                $discountValue,
                !empty($data['is_active']) && $data['is_active'] != '0' ? 1 : 0,
                $_SESSION['user_id'] ?? 1
            ]);

            $successCount++;

        } catch (Exception $e) {
            $errorCount++;
            $errors[] = [
                'row' => $rowNumber + 1, // +1 for header row
                'message' => $e->getMessage()
            ];

            // Log error but continue processing
            error_log("Bulk upload error at row $rowNumber: " . $e->getMessage());
        }
    }

    fclose($handle);

    // Commit transaction if at least one row was successful
    if ($successCount > 0) {
        $conn->commit();
    } else {
        $conn->rollBack();
        throw new Exception('No valid rows found to import');
    }

    // Return results
    sendSuccessResponse([
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'errors' => array_slice($errors, 0, 10), // Limit to first 10 errors for display
        'total_errors' => count($errors)
    ], "Bulk upload completed. $successCount successful, $errorCount failed.");

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Bulk upload fatal error: " . $e->getMessage());
    sendErrorResponse($e->getMessage(), 400);
}
