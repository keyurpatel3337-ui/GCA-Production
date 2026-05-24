<?php

/**
 * Notification API Endpoint
 * 
 * Handles all notification-related API requests
 * 
 * Endpoints:
 *   GET  ?action=list          - List notifications
 *   GET  ?action=count         - Get unread count
 *   POST ?action=read&id=X     - Mark notification as read
 *   POST ?action=read_all      - Mark all as read
 *   POST ?action=delete&id=X   - Delete notification
 *   GET  ?action=preferences   - Get notification preferences
 *   POST ?action=preferences   - Save notification preferences
 * 
 * @author Antigravity AI
 * @version 1.0.0
 */

// Enable error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Include session configuration to ensure proper session handling
    require_once __DIR__ . '/../session_config.php';

    require_once dirname(dirname(__DIR__)) . '/common/constants.php';
    require_once DB_CONNECT_FILE;
    require_once dirname(dirname(__DIR__)) . '/common/services/NotificationService.php';

    // Check database connection
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Notification API initialization error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => 'Server initialization error: ' . $e->getMessage(),
        'timestamp' => date('c')
    ]);
    exit;
}

// Response helper
function jsonResponse(bool $success, $data = null, string $message = '', int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('c')
    ]);
    exit;
}

// Get current user info from session
function getCurrentUser(): array
{
    // Check for student session first (student portal login)
    if (isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true && !empty($_SESSION['student_id'])) {
        return [
            'type' => 'student',
            'id' => (int) $_SESSION['student_id']
        ];
    }

    // Check for admin/staff session
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
        return [
            'type' => $_SESSION['user_role'], // super-admin, principle, counsellor, accountant
            'id' => (int) $_SESSION['user_id']
        ];
    }

    return [];
}

// Validate user is logged in
$user = getCurrentUser();
if (empty($user)) {
    jsonResponse(false, null, 'Authentication required', 401);
}

// Get action parameter from both GET and POST
$action = preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '');

try {
    // Initialize service
    $notificationService = new NotificationService($conn);

    // Route to appropriate handler
    switch ($action) {

        case 'list':
            // GET /api/notifications.php?action=list&limit=20&offset=0&unread_only=0
            $limit = min(50, max(1, (int) ($_POST['limit'] ?? 20)));
            $offset = max(0, (int) ($_POST['offset'] ?? 0));
            $unreadOnly = isset($_POST['unread_only']) ? (bool) $_POST['unread_only'] : null;

            $notifications = $notificationService->getNotifications(
                $user['type'],
                $user['id'],
                $limit,
                $offset,
                $unreadOnly
            );

            $unreadCount = $notificationService->getUnreadCount($user['type'], $user['id']);

            jsonResponse(true, [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'has_more' => count($notifications) === $limit
            ]);
            break;

        case 'count':
            // GET /api/notifications.php?action=count
            $count = $notificationService->getUnreadCount($user['type'], $user['id']);
            jsonResponse(true, ['unread_count' => $count]);
            break;

        case 'read':
            // POST /api/notifications.php?action=read&id=123
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'POST method required', 405);
            }

            $notificationId = (int) ($_POST['id'] ?? 0);
            if ($notificationId <= 0) {
                jsonResponse(false, null, 'Invalid notification ID', 400);
            }

            $success = $notificationService->markAsRead($notificationId, $user['type'], $user['id']);
            jsonResponse($success, null, $success ? 'Notification marked as read' : 'Notification not found');
            break;

        case 'read_all':
            // POST /api/notifications.php?action=read_all
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'POST method required', 405);
            }

            $count = $notificationService->markAllAsRead($user['type'], $user['id']);
            jsonResponse(true, ['marked_count' => $count], "$count notification(s) marked as read");
            break;

        case 'delete':
            // POST /api/notifications.php?action=delete&id=123
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'POST method required', 405);
            }

            $notificationId = (int) ($_POST['id'] ?? 0);
            if ($notificationId <= 0) {
                jsonResponse(false, null, 'Invalid notification ID', 400);
            }

            $success = $notificationService->delete($notificationId, $user['type'], $user['id']);
            jsonResponse($success, null, $success ? 'Notification deleted' : 'Notification not found or access denied');
            break;

        case 'preferences':
            // GET or POST /api/notifications.php?action=preferences
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Get preferences
                $notificationType = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['type'] ?? 'all');
                $prefs = $notificationService->getPreferences($user['type'], $user['id'], $notificationType);
                jsonResponse(true, $prefs);
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Save preferences
                $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                $notificationType = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['notification_type'] ?? 'all');

                $prefs = [
                    'in_app' => isset($input['in_app']) ? (int) $input['in_app'] : 1,
                    'email' => isset($input['email']) ? (int) $input['email'] : 1,
                    'whatsapp' => isset($input['whatsapp']) ? (int) $input['whatsapp'] : 1,
                    'sound' => isset($input['sound']) ? (int) $input['sound'] : 0
                ];

                $success = $notificationService->savePreferences($user['type'], $user['id'], $notificationType, $prefs);
                jsonResponse($success, null, $success ? 'Preferences saved' : 'Failed to save preferences');
            } else {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            break;

        default:
            jsonResponse(false, null, 'Invalid action. Valid actions: list, count, read, read_all, delete, preferences', 400);
    }
} catch (Exception $e) {
    error_log("Notification API error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonResponse(false, null, 'An error occurred while processing your request: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 500);
}
