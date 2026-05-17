<?php

/**
 * Email Controller
 * Path: controllers/settings/email_controller.php
 */

require_once __DIR__ . '/../../../common/bootstrap.php';
require_once OPERATION_FILE;
require_once HELPER_EMAIL_FUNCTIONS;

$dbOps = new DatabaseOperations();

if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    sendErrorResponse('Unauthorized access', 403);
}

$action = $_GET['action'] ?? '';

if (empty($action)) {
    $route = $_GET['route'] ?? '';
    switch ($route) {
        case 'settings/email-templates':
            $action = 'get-templates';
            break;
        case 'settings/email-template-get':
            $action = 'get-template';
            break;
        case 'settings/email-template-save':
            $action = 'save-template';
            break;
        case 'settings/email-template-toggle':
            $action = 'toggle-status';
            break;
        case 'settings/email-template-delete':
            $action = 'delete-template';
            break;
        case 'settings/email-template-test':
            $action = 'test-template';
            break;
        case 'settings/email-template-download-csv':
            $action = 'download-csv-template';
            break;
        case 'settings/email-template-bulk-upload':
            $action = 'bulk-upload';
            break;
        case 'settings/email-logs':
            $action = 'get-logs';
            break;
        case 'settings/email-log-details':
            $action = 'get-log-details';
            break;
    }
}

// Don't set JSON header for download/upload actions that need their own headers
if (!in_array($action, ['download-csv-template'])) {
    header('Content-Type: application/json');
}

switch ($action) {
    case 'get-templates':
        try {
            $recipient_type = $_GET['recipient_type'] ?? null;
            $category = $_GET['category'] ?? null;
            $status = $_GET['status'] ?? null;

            $where = ["1=1"];
            $params = [];

            if ($recipient_type) {
                $where[] = "recipient_type = ?";
                $params[] = $recipient_type;
            }
            if ($category) {
                $where[] = "template_category = ?";
                $params[] = $category;
            }
            if ($status !== null && $status !== '') {
                $where[] = "is_active = ?";
                $params[] = $status;
            }

            $where_sql = implode(' AND ', $where);
            $sql = "SELECT et.*, u.name as created_by_name 
                    FROM tbl_email_templates et
                    LEFT JOIN tbl_users u ON et.created_by = u.id
                    WHERE $where_sql
                    ORDER BY et.id ASC";

            $templates = $dbOps->customSelect($sql, $params);

            // Fetch Stats
            $stats = $dbOps->customSelectOne(
                "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
                FROM tbl_email_templates"
            );

            sendSuccessResponse(['templates' => $templates, 'stats' => $stats], 'Templates fetched successfully');
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage());
        }
        break;

    case 'get-template':
        $id = $_GET['id'] ?? null;
        if (!$id)
            sendErrorResponse('Template ID is required');

        try {
            $template = $dbOps->selectOne('tbl_email_templates', ['*'], ['id' => $id]);
            if ($template) {
                sendSuccessResponse($template, 'Template details fetched');
            } else {
                sendErrorResponse('Template not found');
            }
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage());
        }
        break;

    case 'save-template':
        try {
            $id = $_POST['id'] ?? null;
            $variables = $_POST['variables'] ?? '[]';
            
            // Server-side JSON validation
            if (!empty($variables) && $variables !== '[]') {
                $decoded = json_decode($variables);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    // Log the invalid value for debugging
                    error_log("Invalid JSON received for email template variables: " . print_r($variables, true));
                    $variables = '[]'; // Fallback to empty array to avoid DB error
                }
            }

            $data = [
                'template_name' => trim($_POST['template_name']),
                'template_code' => trim($_POST['template_code']),
                'recipient_type' => $_POST['recipient_type'],
                'template_category' => $_POST['template_category'],
                'subject' => $_POST['subject'],
                'html_content' => $_POST['html_content'],
                'variables' => $variables,
                'priority' => $_POST['priority'] ?? 'normal',
                'cc_emails' => $_POST['cc_emails'] ?? null,
                'bcc_emails' => $_POST['bcc_emails'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            if (empty($data['template_name']) || empty($data['template_code'])) {
                sendErrorResponse('Template Name and Code are required');
            }

            if ($id) {
                // Update
                $sql = "UPDATE tbl_email_templates 
                        SET template_name = ?, template_code = ?, recipient_type = ?, 
                            template_category = ?, subject = ?, html_content = ?, 
                            variables = ?, priority = ?, cc_emails = ?, bcc_emails = ?, 
                            is_active = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['template_name'],
                    $data['template_code'],
                    $data['recipient_type'],
                    $data['template_category'],
                    $data['subject'],
                    $data['html_content'],
                    $data['variables'],
                    $data['priority'],
                    $data['cc_emails'],
                    $data['bcc_emails'],
                    $data['is_active'],
                    $id
                ]);
                sendSuccessResponse(null, 'Template updated successfully');
            } else {
                // Insert
                $sql = "INSERT INTO tbl_email_templates 
                        (template_name, template_code, recipient_type, template_category, 
                         subject, html_content, variables, priority, cc_emails, bcc_emails, 
                         is_active, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['template_name'],
                    $data['template_code'],
                    $data['recipient_type'],
                    $data['template_category'],
                    $data['subject'],
                    $data['html_content'],
                    $data['variables'],
                    $data['priority'],
                    $data['cc_emails'],
                    $data['bcc_emails'],
                    $data['is_active'],
                    $_SESSION['user_id']
                ]);
                sendSuccessResponse(null, 'Template saved successfully');
            }
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage());
        }
        break;

    case 'toggle-status':
        $id = $_POST['id'] ?? null;
        $status = $_POST['is_active'] ?? 0;
        if (!$id)
            sendErrorResponse('ID is required');

        try {
            $stmt = $conn->prepare("UPDATE tbl_email_templates SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            sendSuccessResponse(null, 'Status updated');
        } catch (PDOException $e) {
            sendErrorResponse('Database error');
        }
        break;

    case 'delete-template':
        $ids = $_POST['ids'] ?? [];
        if (empty($ids))
            sendErrorResponse('No templates selected');
        if (!is_array($ids))
            $ids = [$ids];

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM tbl_email_templates WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            sendSuccessResponse(null, count($ids) . ' template(s) deleted');
        } catch (PDOException $e) {
            sendErrorResponse('Database error or template in use');
        }
        break;

    case 'download-csv-template':
        require_once __DIR__ . '/download-email-template-csv.php';
        exit;

    case 'bulk-upload':
        require_once __DIR__ . '/bulk-upload-email-templates.php';
        exit;

    case 'test-template':
        $template_id = $_POST['template_id'] ?? null;
        $recipient_email = $_POST['test_email'] ?? null;
        $recipient_name = $_POST['test_name'] ?? 'Test User';

        if (!$template_id || !$recipient_email)
            sendErrorResponse('Template and email are required');

        // Extract variables from POST (prefixed with var_)
        $variables = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'var_') === 0) {
                $vName = substr($key, 4);
                $variables[$vName] = $value;
            }
        }

        try {
            // Fetch template code for sendTemplateEmail
            $stmt = $conn->prepare("SELECT template_code FROM tbl_email_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $templateCode = $stmt->fetchColumn();

            if (!$templateCode)
                sendErrorResponse('Template not found');

            $result = sendTemplateEmail($conn, $recipient_email, $recipient_name, $templateCode, $variables);

            if ($result['success']) {
                sendSuccessResponse(null, 'Test email sent successfully');
            } else {
                sendErrorResponse($result['error'] ?? 'Failed to send test email');
            }
        } catch (Exception $e) {
            sendErrorResponse('Error: ' . $e->getMessage());
        }
        break;

    case 'get-logs':
        try {
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            $status = $_GET['status'] ?? '';
            $recipient_type = $_GET['recipient_type'] ?? '';
            $search = $_GET['search'] ?? '';

            $where = ["DATE(l.created_at) BETWEEN ? AND ?"];
            $params = [$date_from, $date_to];

            if ($status) {
                $where[] = "l.status = ?";
                $params[] = $status;
            }
            if ($recipient_type) {
                $where[] = "l.recipient_type = ?";
                $params[] = $recipient_type;
            }
            if ($search) {
                $where[] = "(l.recipient_email LIKE ? OR l.recipient_name LIKE ? OR l.subject LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $where_sql = implode(' AND ', $where);

            // Logs
            $stmt = $conn->prepare("SELECT l.*, t.template_name, t.template_code
                                   FROM tbl_email_logs l
                                   LEFT JOIN tbl_email_templates t ON l.template_id = t.id
                                   WHERE $where_sql
                                   ORDER BY l.id ASC
                                   LIMIT 1000");
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats
            $stats_stmt = $conn->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued
                FROM tbl_email_logs l
                WHERE $where_sql");
            $stats_stmt->execute($params);
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

            sendSuccessResponse(['logs' => $logs, 'stats' => $stats], 'Logs fetched');
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage());
        }
        break;

    case 'get-log-details':
        $id = $_GET['id'] ?? null;
        if (!$id)
            sendErrorResponse('Log ID required');

        try {
            $stmt = $conn->prepare("SELECT l.*, t.template_name, t.template_code
                                   FROM tbl_email_logs l
                                   LEFT JOIN tbl_email_templates t ON l.template_id = t.id
                                   WHERE l.id = ?");
            $stmt->execute([$id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($log) {
                sendSuccessResponse($log, 'Log details fetched');
            } else {
                sendErrorResponse('Log not found');
            }
        } catch (PDOException $e) {
            sendErrorResponse('Database error');
        }
        break;

    default:
        sendErrorResponse('Invalid action');
        break;
}


