<?php

/**
 * WhatsApp Controller
 * Path: controllers/settings/whatsapp_controller.php
 */

require_once __DIR__ . '/../../../common/bootstrap.php';
require_once OPERATION_FILE;
require_once HELPER_WHATSAPP_FUNCTIONS;

$dbOps = new DatabaseOperations();

if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    sendErrorResponse('Unauthorized access', 403);
}

$action = $_GET['action'] ?? '';

if (empty($action)) {
    $route = $_GET['route'] ?? '';
    switch ($route) {
        case 'settings/whatsapp-templates':
            $action = 'get-templates';
            break;
        case 'settings/whatsapp-template-get':
            $action = 'get-template';
            break;
        case 'settings/whatsapp-template-save':
            $action = 'save-template';
            break;
        case 'settings/whatsapp-template-toggle':
            $action = 'toggle-status';
            break;
        case 'settings/whatsapp-template-delete':
            $action = 'delete-template';
            break;
        case 'settings/whatsapp-template-test':
            $action = 'test-template';
            break;
        case 'settings/whatsapp-template-download-csv':
            $action = 'download-csv-template';
            break;
        case 'settings/whatsapp-template-bulk-upload':
            $action = 'bulk-upload';
            break;
        case 'settings/whatsapp-logs':
            $action = 'get-logs';
            break;
        case 'settings/whatsapp-log-details':
            $action = 'get-log-details';
            break;
        case 'settings/whatsapp-logs-sync':
            $action = 'sync-status';
            break;
    }
}

// Don't set JSON header for download/upload actions that need their own headers
if (!in_array($action, ['download-csv-template'])) {
    header('Content-Type: application/json');
}

switch ($action) {
    case 'sync-status':
        $limit = $_GET['limit'] ?? 50;
        $stats = syncWhatsAppStatus($conn, $limit);
        sendSuccessResponse($stats, 'WhatsApp statuses synchronized');
        break;
    case 'get-templates':
        $filters = [
            'category' => $_GET['category'] ?? null,
            'status' => $_GET['status'] ?? null,
        ];
        $templates = getAllWhatsAppTemplates($conn, $filters);
        sendSuccessResponse(['templates' => $templates], 'Templates fetched successfully');
        break;

    case 'get-template':
        $id = $_GET['id'] ?? null;
        if (!$id)
            sendErrorResponse('Template ID is required');

        $template = getWhatsAppTemplate($conn, $id);
        if ($template) {
            sendSuccessResponse($template, 'Template details fetched');
        } else {
            sendErrorResponse('Template not found');
        }
        break;

    case 'save-template':
        try {
            $id = $_POST['id'] ?? null;

            // WhatsApp API configuration is statically set in code

            // Process variables
            $vars_input = $_POST['variable_names'] ?? '';
            if (is_string($vars_input)) {
                // Convert comma-separated string to array
                $vars_array = array_filter(array_map('trim', explode(',', $vars_input)));
                // Re-index array
                $vars_array = array_values($vars_array);
            } else {
                $vars_array = $vars_input;
            }
            $variables_json = json_encode($vars_array);

            $data = [
                'template_name' => trim($_POST['template_name']),
                'template_category' => $_POST['template_category'],
                'body_text' => $_POST['body_text'],
                'variables' => $variables_json,
                'approval_status' => $_POST['approval_status'] ?? 'draft',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'header_type' => $_POST['header_type'] ?? 'none'
            ];

            if (empty($data['template_name'])) {
                sendErrorResponse('Template Name is required');
            }

            if ($id) {
                // Update
                $sql = "UPDATE tbl_whatsapp_templates 
                        SET template_name = ?, template_category = ?, 
                            body_text = ?, variables = ?, approval_status = ?, 
                            is_active = ?, header_type = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['template_name'],
                    $data['template_category'],
                    $data['body_text'],
                    $data['variables'],
                    $data['approval_status'],
                    $data['is_active'],
                    $data['header_type'],
                    $id
                ]);
                sendSuccessResponse(null, 'Template updated successfully');
            } else {
                // Insert
                $sql = "INSERT INTO tbl_whatsapp_templates 
                        (template_name, template_category, body_text, 
                         variables, approval_status, is_active, header_type, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['template_name'],
                    $data['template_category'],
                    $data['body_text'],
                    $data['variables'],
                    $data['approval_status'],
                    $data['is_active'],
                    $data['header_type'],
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
            $stmt = $conn->prepare("UPDATE tbl_whatsapp_templates SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            sendSuccessResponse('Status updated');
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
            $stmt = $conn->prepare("DELETE FROM tbl_whatsapp_templates WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            sendSuccessResponse(count($ids) . ' template(s) deleted');
        } catch (PDOException $e) {
            sendErrorResponse('Database error or template in use');
        }
        break;

    case 'test-template':
        $template_id = $_POST['template_id'] ?? null;
        $number = $_POST['test_number'] ?? '';
        $vars = $_POST['test_variables'] ?? '';

        if (!$template_id || !$number)
            sendErrorResponse('Template and number are required');

        $variables = !empty($vars) ? array_map('trim', explode(',', $vars)) : [];
        $result = testWhatsAppTemplate($conn, $template_id, $number, $variables);

        if ($result['success']) {
            sendSuccessResponse('Test message sent', $result);
        } else {
            sendErrorResponse($result['error'] ?? 'Failed to send test message', 400, $result);
        }
        break;

    case 'download-csv-template':
        require_once __DIR__ . '/download-whatsapp-template-csv.php';
        exit;

    case 'bulk-upload':
        require_once __DIR__ . '/bulk-upload-whatsapp-templates.php';
        exit;

    case 'get-logs':
        try {
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            $status = $_GET['status'] ?? '';
            $template_id = $_GET['template_id'] ?? '';
            $search = $_GET['search'] ?? '';

            // Pagination parameters
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $where = ["DATE(l.created_at) BETWEEN ? AND ?"];
            $params = [$date_from, $date_to];

            if ($status) {
                $where[] = "l.status = ?";
                $params[] = $status;
            }
            if ($template_id) {
                $where[] = "l.template_id = ?";
                $params[] = $template_id;
            }
            if ($search) {
                $where[] = "(l.recipient_number LIKE ? OR l.message_id LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $where_sql = implode(' AND ', $where);

            // Get total count for pagination
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_whatsapp_logs l WHERE $where_sql");
            $count_stmt->execute($params);
            $total_records = (int)$count_stmt->fetchColumn();
            $total_pages = ceil($total_records / $limit);

            // Logs
            $stmt = $conn->prepare("SELECT l.*, t.template_name, t.template_category as category, 'BhashSMS' as provider_name
                                   FROM tbl_whatsapp_logs l
                                   LEFT JOIN tbl_whatsapp_templates t ON l.template_id = t.id
                                   WHERE $where_sql
                                   ORDER BY l.id ASC
                                   LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats
            $stats_stmt = $conn->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM tbl_whatsapp_logs l
                WHERE $where_sql");
            $stats_stmt->execute($params);
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

            sendSuccessResponse([
                'logs' => $logs, 
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_records' => $total_records,
                    'limit' => $limit
                ]
            ], 'Logs fetched');
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage());
        }
        break;

    case 'get-log-details':
        $id = $_GET['id'] ?? null;
        if (!$id)
            sendErrorResponse('Log ID required');

        try {
            $stmt = $conn->prepare("SELECT l.*, t.template_name, t.template_category as category, 'BhashSMS' as provider_name
                                   FROM tbl_whatsapp_logs l
                                   LEFT JOIN tbl_whatsapp_templates t ON l.template_id = t.id
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


