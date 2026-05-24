<?php
/**
 * Flash Message Helper
 * Displays session messages only once and automatically clears them
 * 
 * Usage:
 * - Set message: set_flash_message('success', 'Operation completed successfully');
 * - Display message: Call display_flash_messages() in your page or let footer handle it
 * - Messages are automatically cleared after being fetched once
 */

/**
 * Set a flash message in session
 * 
 * @param string $type Message type: 'success', 'error', 'warning', 'info'
 * @param string $message The message text
 * @param bool $preserve If true, message will persist until explicitly cleared
 */
function set_flash_message($type, $message, $preserve = false)
{
    if (!isset($_SESSION)) {
        session_start();
    }

    // Validate type
    $validTypes = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $validTypes)) {
        $type = 'info';
    }

    // Store message with unique ID to prevent duplicates
    $messageId = md5($type . $message . microtime());

    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][$messageId] = [
        'type' => $type,
        'message' => $message,
        'preserve' => $preserve,
        'created_at' => time()
    ];
}

/**
 * Get all flash messages and clear them (unless preserved)
 * 
 * @return array Array of messages with 'type' and 'message' keys
 */
function get_flash_messages($clear = true)
{
    if (!isset($_SESSION)) {
        session_start();
    }

    if (!isset($_SESSION['flash_messages']) || empty($_SESSION['flash_messages'])) {
        return [];
    }

    $messages = $_SESSION['flash_messages'];
    $toReturn = [];
    $currentTime = time();

    foreach ($messages as $id => $msg) {
        // Skip and remove expired messages (older than 5 minutes)
        if (($currentTime - $msg['created_at']) > 300) {
            unset($_SESSION['flash_messages'][$id]);
            continue;
        }

        $toReturn[] = $msg;

        // Clear if not preserved and $clear is true
        if ($clear && !$msg['preserve']) {
            unset($_SESSION['flash_messages'][$id]);
        }
    }

    // If all messages cleared, remove the array
    if (empty($_SESSION['flash_messages'])) {
        unset($_SESSION['flash_messages']);
    }

    return $toReturn;
}

/**
 * Check if there are any flash messages
 * 
 * @param string|null $type Optional: Check for specific type
 * @return bool
 */
function has_flash_messages($type = null)
{
    if (!isset($_SESSION)) {
        session_start();
    }

    if (!isset($_SESSION['flash_messages']) || empty($_SESSION['flash_messages'])) {
        return false;
    }

    if ($type === null) {
        return true;
    }

    foreach ($_SESSION['flash_messages'] as $msg) {
        if ($msg['type'] === $type) {
            return true;
        }
    }

    return false;
}

/**
 * Clear all flash messages
 * 
 * @param string|null $type Optional: Clear only specific type
 */
function clear_flash_messages($type = null)
{
    if (!isset($_SESSION)) {
        session_start();
    }

    if (!isset($_SESSION['flash_messages'])) {
        return;
    }

    if ($type === null) {
        unset($_SESSION['flash_messages']);
        return;
    }

    foreach ($_SESSION['flash_messages'] as $id => $msg) {
        if ($msg['type'] === $type) {
            unset($_SESSION['flash_messages'][$id]);
        }
    }

    if (empty($_SESSION['flash_messages'])) {
        unset($_SESSION['flash_messages']);
    }
}

/**
 * Display flash messages as HTML alerts
 * 
 * @return void
 */
function display_flash_messages()
{
    $messages = get_flash_messages(true);

    if (empty($messages)) {
        return;
    }

    foreach ($messages as $msg) {
        $alertClass = 'alert-info';
        $icon = 'fa-info-circle';

        switch ($msg['type']) {
            case 'success':
                $alertClass = 'alert-success';
                $icon = 'fa-check-circle';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                $icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                $icon = 'fa-exclamation-triangle';
                break;
        }

        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo '<i class="fas ' . $icon . '"></i> ';
        echo htmlspecialchars($msg['message'] ?? '');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Migrate old session messages to flash message system
 * Call this in session_config.php to automatically convert old-style messages
 */
function migrate_old_session_messages()
{
    if (!isset($_SESSION)) {
        session_start();
    }

    // Migrate old success messages
    if (isset($_SESSION['success_msg']) && !empty($_SESSION['success_msg'])) {
        set_flash_message('success', $_SESSION['success_msg']);
        unset($_SESSION['success_msg']);
    }

    if (isset($_SESSION['success']) && !empty($_SESSION['success'])) {
        set_flash_message('success', $_SESSION['success']);
        unset($_SESSION['success']);
    }

    // Migrate old error messages
    if (isset($_SESSION['error_msg']) && !empty($_SESSION['error_msg'])) {
        set_flash_message('error', $_SESSION['error_msg']);
        unset($_SESSION['error_msg']);
    }

    if (isset($_SESSION['error']) && !empty($_SESSION['error'])) {
        set_flash_message('error', $_SESSION['error']);
        unset($_SESSION['error']);
    }

    // Migrate warning messages
    if (isset($_SESSION['warning_msg']) && !empty($_SESSION['warning_msg'])) {
        set_flash_message('warning', $_SESSION['warning_msg']);
        unset($_SESSION['warning_msg']);
    }

    if (isset($_SESSION['warning']) && !empty($_SESSION['warning'])) {
        set_flash_message('warning', $_SESSION['warning']);
        unset($_SESSION['warning']);
    }
}
