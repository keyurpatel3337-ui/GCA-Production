<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

try {
    $id = intval($_POST['id'] ?? 0);
    $receipt_title = trim($_POST['receipt_title'] ?? '');
    $organization_name = trim($_POST['organization_name'] ?? '');
    $organization_type = trim($_POST['organization_type'] ?? 'other');
    $gst_number = trim($_POST['gst_number'] ?? '');
    $pan_number = trim($_POST['pan_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $authorized_signatory = trim($_POST['authorized_signatory'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $footer_text = trim($_POST['footer_text'] ?? '');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');

    // Validation
    if (empty($receipt_title) || empty($organization_name)) {
        sendErrorResponse('Receipt title and organization name are required', 400);
    }

    // Handle file uploads
    $upload_dir = __DIR__ . '/../../uploads/receipt_config/';
    $upload_url_base = 'uploads/receipt_config/'; // Relative to backend
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true); // Secure permissions (not world-writable)
    }

    $logo_path = $_POST['existing_logo'] ?? '';
    $signature_path = $_POST['existing_signature'] ?? '';

    // Allowed file types with MIME validation
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        
        // Validate extension
        if (in_array($logo_ext, $allowed_ext)) {
            // Validate MIME type (check actual file content, not just extension)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_mime = $finfo->file($_FILES['logo']['tmp_name']);
            
            if (in_array($file_mime, $allowed_mime)) {
                // Generate secure random filename to prevent path traversal
                $logo_filename = 'logo_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $logo_ext;
                $logo_target = $upload_dir . $logo_filename;

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_target)) {
                    // Set secure file permissions
                    chmod($logo_target, 0644);
                    
                    // Delete old logo if exists
                    if (!empty($logo_path)) {
                        $old_file = __DIR__ . '/../../' . $logo_path;
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    $logo_path = $upload_url_base . $logo_filename;
                }
            }
        }
    }

    // Handle signature upload
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $sig_ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));

        // Validate extension
        if (in_array($sig_ext, $allowed_ext)) {
            // Validate MIME type (check actual file content)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_mime = $finfo->file($_FILES['signature']['tmp_name']);
            
            if (in_array($file_mime, $allowed_mime)) {
                // Generate secure random filename
                $sig_filename = 'signature_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $sig_ext;
                $sig_target = $upload_dir . $sig_filename;

                if (move_uploaded_file($_FILES['signature']['tmp_name'], $sig_target)) {
                    // Set secure file permissions
                    chmod($sig_target, 0644);
                    
                    // Delete old signature if exists
                    if (!empty($signature_path)) {
                        $old_file = __DIR__ . '/../../' . $signature_path;
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    $signature_path = $upload_url_base . $sig_filename;
                }
            }
        }
    }

    if ($id > 0) {
        // Update existing configuration
        $stmt = $conn->prepare("UPDATE tbl_receipt_configuration SET 
            receipt_title = ?, organization_name = ?, organization_type = ?, gst_number = ?, pan_number = ?,
            address = ?, city = ?, state = ?, pincode = ?, phone = ?, email = ?, website = ?,
            logo_path = ?, signature_path = ?, authorized_signatory = ?, designation = ?,
            footer_text = ?, terms_conditions = ?, updated_at = NOW()
            WHERE id = ?");

        $stmt->execute([
            $receipt_title,
            $organization_name,
            $organization_type,
            $gst_number,
            $pan_number,
            $address,
            $city,
            $state,
            $pincode,
            $phone,
            $email,
            $website,
            $logo_path,
            $signature_path,
            $authorized_signatory,
            $designation,
            $footer_text,
            $terms_conditions,
            $id
        ]);

        sendSuccessResponse(['id' => $id], 'Receipt configuration updated successfully');
    } else {
        // Insert new configuration
        $created_by = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO tbl_receipt_configuration 
            (receipt_title, organization_name, organization_type, gst_number, pan_number, address, city, state, pincode,
             phone, email, website, logo_path, signature_path, authorized_signatory, designation,
             footer_text, terms_conditions, is_active, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())");

        $stmt->execute([
            $receipt_title,
            $organization_name,
            $organization_type,
            $gst_number,
            $pan_number,
            $address,
            $city,
            $state,
            $pincode,
            $phone,
            $email,
            $website,
            $logo_path,
            $signature_path,
            $authorized_signatory,
            $designation,
            $footer_text,
            $terms_conditions,
            $created_by
        ]);

        $new_id = $conn->lastInsertId();
        sendSuccessResponse(['id' => $new_id], 'Receipt configuration saved successfully');
    }
} catch (PDOException $e) {
    sendErrorResponse('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendErrorResponse('Error: ' . $e->getMessage(), 500);
}
