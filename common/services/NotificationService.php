<?php

/**
 * NotificationService - Core notification service class
 * 
 * Handles creation, retrieval, and management of in-app notifications
 * Integrates with existing email and WhatsApp notification functions
 * 
 * @author Antigravity AI
 * @version 1.0.0
 * @created 2025-12-27
 */

require_once __DIR__ . '/../db_connect.php';
require_once OPERATION_FILE;
require_once __DIR__ . '/../helpers/notification_functions.php';
require_once __DIR__ . '/../helpers/whatsapp_functions.php';

class NotificationService
{
    private PDO $conn;

    /**
     * Constructor
     * @param PDO|null $conn Database connection (uses global if not provided)
     */
    public function __construct(?PDO $conn = null)
    {
        if ($conn === null) {
            global $conn;
        }
        if ($conn === null) {
            throw new Exception('Database connection is required for NotificationService. Please ensure db_connect.php is properly loaded and database is accessible.');
        }
        $this->conn = $conn;
    }


    /**
     * Create a new notification
     * 
     * @param string $userType User type (student, counsellor, admin, accountant, principal)
     * @param int $userId User ID
     * @param string $type Notification type code
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $options Additional options (link, icon, icon_color, priority, reference_type, reference_id)
     * @return int|false Notification ID on success, false on failure
     */
    public function create(
        string $userType,
        int $userId,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): int|false {
        try {
            // Get notification type defaults
            $defaults = $this->getTypeDefaults($type);

            $sql = "INSERT INTO tbl_notifications 
                    (user_type, user_id, type, title, message, link, icon, icon_color, priority, reference_type, reference_id, created_by)
                    VALUES 
                    (:user_type, :user_id, :type, :title, :message, :link, :icon, :icon_color, :priority, :ref_type, :ref_id, :created_by)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':user_type' => $userType,
                ':user_id' => $userId,
                ':type' => $type,
                ':title' => $title,
                ':message' => $message,
                ':link' => $options['link'] ?? null,
                ':icon' => $options['icon'] ?? $defaults['icon'] ?? 'fa-bell',
                ':icon_color' => $options['icon_color'] ?? $defaults['color'] ?? 'primary',
                ':priority' => $options['priority'] ?? $defaults['priority'] ?? 'normal',
                ':ref_type' => $options['reference_type'] ?? null,
                ':ref_id' => $options['reference_id'] ?? null,
                ':created_by' => $options['created_by'] ?? $_SESSION['user_id'] ?? 1
            ]);

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("NotificationService::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notification type defaults from tbl_notification_types
     * 
     * @param string $type Type code
     * @return array Defaults array with icon, color, priority
     */
    private function getTypeDefaults(string $type): array
    {
        try {
            $sql = "SELECT default_icon as icon, default_color as color, default_priority as priority 
                    FROM tbl_notification_types WHERE type_code = :type AND is_active = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':type' => $type]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get unread notification count for a user
     * 
     * @param string $userType User type
     * @param int $userId User ID
     * @return int Unread count
     */
    public function getUnreadCount(string $userType, int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM tbl_notifications 
                    WHERE user_type = :user_type AND user_id = :user_id AND is_read = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':user_type' => $userType, ':user_id' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("NotificationService::getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get notifications for a user with pagination
     * 
     * @param string $userType User type
     * @param int $userId User ID
     * @param int $limit Number of notifications to fetch
     * @param int $offset Offset for pagination
     * @param bool|null $unreadOnly Only fetch unread notifications
     * @return array List of notifications
     */
    public function getNotifications(
        string $userType,
        int $userId,
        int $limit = 20,
        int $offset = 0,
        ?bool $unreadOnly = null
    ): array {
        try {
            $sql = "SELECT id, type, title, message, link, icon, icon_color, priority, 
                           is_read, read_at, reference_type, reference_id, created_at
                    FROM tbl_notifications 
                    WHERE user_type = :user_type AND user_id = :user_id";

            if ($unreadOnly === true) {
                $sql .= " AND is_read = 0";
            }

            $sql .= " ORDER BY created_at ASC LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':user_type', $userType, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format timestamps
            foreach ($notifications as &$notif) {
                $notif['time_ago'] = $this->timeAgo($notif['created_at']);
            }

            return $notifications;
        } catch (PDOException $e) {
            error_log("NotificationService::getNotifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a notification as read
     * 
     * @param int $notificationId Notification ID
     * @param string $userType User type (for security)
     * @param int $userId User ID (for security)
     * @return bool Success status
     */
    public function markAsRead(int $notificationId, string $userType, int $userId): bool
    {
        try {
            $sql = "UPDATE tbl_notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE id = :id AND user_type = :user_type AND user_id = :user_id AND is_read = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => $notificationId,
                ':user_type' => $userType,
                ':user_id' => $userId
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("NotificationService::markAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user
     * 
     * @param string $userType User type
     * @param int $userId User ID
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(string $userType, int $userId): int
    {
        try {
            $sql = "UPDATE tbl_notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE user_type = :user_type AND user_id = :user_id AND is_read = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':user_type' => $userType, ':user_id' => $userId]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("NotificationService::markAllAsRead error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete a notification
     * 
     * @param int $notificationId Notification ID
     * @param string $userType User type (for security)
     * @param int $userId User ID (for security)
     * @return bool Success status
     */
    public function delete(int $notificationId, string $userType, int $userId): bool
    {
        try {
            $sql = "DELETE FROM tbl_notifications 
                    WHERE id = :id AND user_type = :user_type AND user_id = :user_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => $notificationId,
                ':user_type' => $userType,
                ':user_id' => $userId
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("NotificationService::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger notification for an event - sends to all appropriate channels
     * 
     * @param string $eventType Event/notification type
     * @param array $data Event data (must include recipient info)
     * @return array Results for each channel
     */
    public function trigger(string $eventType, array $data): array
    {
        $results = ['in_app' => false, 'email' => false, 'whatsapp' => false];

        // Required fields
        if (empty($data['user_type']) || empty($data['user_id']) || empty($data['title']) || empty($data['message'])) {
            error_log("NotificationService::trigger - Missing required fields");
            return $results;
        }

        // Check user preferences
        $prefs = $this->getPreferences($data['user_type'], $data['user_id'], $eventType);

        // In-app notification
        if ($prefs['in_app']) {
            $notifId = $this->create(
                $data['user_type'],
                $data['user_id'],
                $eventType,
                $data['title'],
                $data['message'],
                $data['options'] ?? []
            );
            $results['in_app'] = $notifId !== false;
        }

        // Email notification (if recipient email provided)
        if ($prefs['email'] && !empty($data['email'])) {
            try {
                $emailResult = sendEmailTemplate(
                    $this->conn,
                    $eventType,
                    $data['email'],
                    $data['name'] ?? 'User',
                    $data['variables'] ?? []
                );
                $results['email'] = $emailResult['success'] ?? false;
            } catch (Exception $e) {
                error_log("NotificationService::trigger email error: " . $e->getMessage());
            }
        }

        // WhatsApp notification (if recipient mobile provided)
        if ($prefs['whatsapp'] && !empty($data['mobile'])) {
            try {
                $waResult = sendWhatsAppTemplate(
                    $this->conn,
                    $data['mobile'],
                    $eventType,
                    $data['variables'] ?? []
                );
                $results['whatsapp'] = $waResult['success'] ?? false;

                // REDIRECT TO EMAIL IF WHATSAPP DISABLED/FAILED
                if (!$results['whatsapp'] && !empty($data['email']) && !$results['email']) {
                    $emailResult = sendEmailTemplate(
                        $this->conn,
                        $eventType, // Use the same event type (assuming matching email template exists)
                        $data['email'],
                        $data['name'] ?? 'User',
                        $data['variables'] ?? []
                    );
                    $results['email'] = $emailResult['success'] ?? false;
                }
            } catch (Exception $e) {
                error_log("NotificationService::trigger whatsapp error: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Get user notification preferences
     * 
     * @param string $userType User type
     * @param int $userId User ID
     * @param string $notificationType Notification type (or 'all')
     * @return array Preferences
     */
    public function getPreferences(string $userType, int $userId, string $notificationType = 'all'): array
    {
        $defaults = ['in_app' => true, 'email' => true, 'whatsapp' => true, 'sound' => false];

        try {
            // Check specific type first, then 'all'
            $sql = "SELECT in_app, email, whatsapp, sound 
                    FROM tbl_notification_preferences 
                    WHERE user_type = :user_type AND user_id = :user_id 
                    AND notification_type IN (:type, 'all')
                    ORDER BY CASE WHEN notification_type = :type2 THEN 0 ELSE 1 END
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':user_type' => $userType,
                ':user_id' => $userId,
                ':type' => $notificationType,
                ':type2' => $notificationType
            ]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

            return $prefs ? array_merge($defaults, $prefs) : $defaults;
        } catch (PDOException $e) {
            return $defaults;
        }
    }

    /**
     * Save user notification preferences
     * 
     * @param string $userType User type
     * @param int $userId User ID
     * @param string $notificationType Notification type (or 'all')
     * @param array $prefs Preferences array
     * @return bool Success status
     */
    public function savePreferences(string $userType, int $userId, string $notificationType, array $prefs): bool
    {
        try {
            $sql = "INSERT INTO tbl_notification_preferences 
                    (user_type, user_id, notification_type, in_app, email, whatsapp, sound)
                    VALUES (:user_type, :user_id, :type, :in_app, :email, :whatsapp, :sound)
                    ON DUPLICATE KEY UPDATE 
                    in_app = VALUES(in_app), email = VALUES(email), 
                    whatsapp = VALUES(whatsapp), sound = VALUES(sound)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':user_type' => $userType,
                ':user_id' => $userId,
                ':type' => $notificationType,
                ':in_app' => $prefs['in_app'] ?? 1,
                ':email' => $prefs['email'] ?? 1,
                ':whatsapp' => $prefs['whatsapp'] ?? 1,
                ':sound' => $prefs['sound'] ?? 0
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("NotificationService::savePreferences error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users (bulk)
     * 
     * @param string $userType User type
     * @param array $userIds Array of user IDs
     * @param string $type Notification type
     * @param string $title Title
     * @param string $message Message
     * @param array $options Additional options
     * @return int Number of notifications created
     */
    public function sendBulk(
        string $userType,
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): int {
        $count = 0;
        foreach ($userIds as $userId) {
            if ($this->create($userType, (int) $userId, $type, $title, $message, $options)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Delete old notifications (cleanup)
     * 
     * @param int $daysOld Delete notifications older than this many days
     * @return int Number of notifications deleted
     */
    public function cleanup(int $daysOld = 30): int
    {
        try {
            $sql = "DELETE FROM tbl_notifications 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY) AND is_read = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':days' => $daysOld]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("NotificationService::cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Convert timestamp to human-readable "time ago" format
     * 
     * @param string $datetime Datetime string
     * @return string Time ago string
     */
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}

/**
 * Helper function to get NotificationService instance
 * @return NotificationService
 */
function getNotificationService(): NotificationService
{
    static $instance = null;
    if ($instance === null) {
        global $conn;
        $instance = new NotificationService($conn);
    }
    return $instance;
}

/**
 * Quick helper to create in-app notification
 * 
 * @param string $userType User type
 * @param int $userId User ID
 * @param string $type Notification type
 * @param string $title Title
 * @param string $message Message
 * @param array $options Options
 * @return int|false Notification ID or false
 */
function createInAppNotification(
    string $userType,
    int $userId,
    string $type,
    string $title,
    string $message,
    array $options = []
): int|false {
    return getNotificationService()->create($userType, $userId, $type, $title, $message, $options);
}

/**
 * Quick helper to notify admins
 * 
 * @param string $type Notification type
 * @param string $title Title
 * @param string $message Message
 * @param array $options Options
 * @return int Count of notifications sent
 */
function notifyAdmins(string $type, string $title, string $message, array $options = []): int
{
    global $conn;

    // Get all active admin users
    $sql = "SELECT id FROM tbl_users WHERE role = 'admin' AND status = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return getNotificationService()->sendBulk('admin', $adminIds, $type, $title, $message, $options);
}

/**
 * Quick helper to notify counsellors
 * 
 * @param string $type Notification type
 * @param string $title Title
 * @param string $message Message
 * @param array $options Options
 * @param int|null $counsellorId Specific counsellor ID (null for all)
 * @return int Count of notifications sent
 */
function notifyCounsellors(string $type, string $title, string $message, array $options = [], ?int $counsellorId = null): int
{
    global $conn;

    if ($counsellorId) {
        $result = getNotificationService()->create('counsellor', $counsellorId, $type, $title, $message, $options);
        return $result ? 1 : 0;
    }

    // Get all active counsellor users
    $sql = "SELECT id FROM tbl_users WHERE role = 'counsellor' AND status = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $counsellorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return getNotificationService()->sendBulk('counsellor', $counsellorIds, $type, $title, $message, $options);
}


