/**
 * NotificationBell - Real-time notification component
 *
 * Features:
 * - Automatic polling for new notifications (configurable interval)
 * - Badge counter for unread notifications
 * - Dropdown panel with notification list
 * - Click-to-read functionality
 * - Toast notifications for high-priority items
 * - Sound notification option
 *
 * @author Antigravity AI
 * @version 1.0.0
 */

class NotificationBell {
  constructor(options = {}) {
    this.options = {
      apiUrl: './api/notifications.php', // Relative to current page
      pollInterval: 30000, // 30 seconds
      maxNotifications: 10,
      enableSound: false,
      bellSelector: '#notification-bell',
      badgeSelector: '#notification-badge',
      dropdownSelector: '#notification-dropdown',
      listSelector: '#notification-list',
      ...options,
    };

    this.unreadCount = 0;
    this.pollTimer = null;
    this.isOpen = false;

    this.init();
  }

  /**
   * Initialize the notification bell
   */
  init() {
    this.bellElement = document.querySelector(this.options.bellSelector);
    this.badgeElement = document.querySelector(this.options.badgeSelector);
    this.dropdownElement = document.querySelector(
      this.options.dropdownSelector
    );
    this.listElement = document.querySelector(this.options.listSelector);

    if (!this.bellElement) {
      console.warn('NotificationBell: Bell element not found');
      return;
    }

    // Setup event listeners
    this.setupEventListeners();

    // Initial fetch
    this.fetchNotifications();

    // Start polling
    this.startPolling();

    console.log('NotificationBell initialized');
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Toggle dropdown on bell click
    this.bellElement.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.toggleDropdown();
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
      if (this.isOpen && !this.dropdownElement?.contains(e.target)) {
        this.closeDropdown();
      }
    });

    // Handle notification item clicks
    if (this.listElement) {
      this.listElement.addEventListener('click', (e) => {
        const item = e.target.closest('.notification-item');
        if (item) {
          this.handleNotificationClick(item);
        }
      });
    }

    // Mark all as read button
    const markAllBtn = document.querySelector('#mark-all-read');
    if (markAllBtn) {
      markAllBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.markAllAsRead();
      });
    }
  }

  /**
   * Start polling for new notifications
   */
  startPolling() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
    }

    this.pollTimer = setInterval(() => {
      this.fetchUnreadCount();
    }, this.options.pollInterval);
  }

  /**
   * Stop polling
   */
  stopPolling() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  }

  /**
   * Fetch unread count only (lightweight poll)
   */
  async fetchUnreadCount() {
    try {
      const response = await fetch(`${this.options.apiUrl}?action=count`, {
        credentials: 'same-origin',
      });
      const data = await response.json();

      if (data.success) {
        const newCount = data.data.unread_count;

        // Check if we have new notifications
        if (newCount > this.unreadCount) {
          this.onNewNotification(newCount - this.unreadCount);
        }

        this.unreadCount = newCount;
        this.updateBadge();
      }
    } catch (error) {
      console.error('Failed to fetch unread count:', error);
    }
  }

  /**
   * Fetch full notification list
   */
  async fetchNotifications() {
    try {
      const response = await fetch(
        `${this.options.apiUrl}?action=list&limit=${this.options.maxNotifications}`,
        { credentials: 'same-origin' }
      );
      const data = await response.json();

      if (data.success) {
        this.unreadCount = data.data.unread_count;
        this.updateBadge();
        this.renderNotifications(data.data.notifications);
      }
    } catch (error) {
      console.error('Failed to fetch notifications:', error);
    }
  }

  /**
   * Update the badge counter
   */
  updateBadge() {
    if (!this.badgeElement) return;

    if (this.unreadCount > 0) {
      this.badgeElement.textContent =
        this.unreadCount > 99 ? '99+' : this.unreadCount;
      this.badgeElement.classList.remove('d-none');
    } else {
      this.badgeElement.classList.add('d-none');
    }
  }

  /**
   * Render notifications in the dropdown list
   */
  renderNotifications(notifications) {
    if (!this.listElement) return;

    if (notifications.length === 0) {
      this.listElement.innerHTML = `
                <div class="notification-empty text-center py-4">
                    <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">No notifications</p>
                </div>
            `;
      return;
    }

    this.listElement.innerHTML = notifications
      .map((notif) => this.renderNotificationItem(notif))
      .join('');
  }

  /**
   * Render a single notification item
   */
  renderNotificationItem(notif) {
    const unreadClass = notif.is_read ? '' : 'unread';
    const iconColorClass = `text-${notif.icon_color || 'primary'}`;
    const priorityClass =
      notif.priority === 'urgent' || notif.priority === 'high'
        ? 'priority-high'
        : '';

    return `
            <div class="notification-item ${unreadClass} ${priorityClass}" 
                 data-id="${notif.id}" 
                 data-link="${notif.link || ''}"
                 role="button">
                <div class="notification-icon ${iconColorClass}">
                    <i class="fas ${notif.icon || 'fa-bell'}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(
                      notif.title
                    )}</div>
                    <div class="notification-message">${this.escapeHtml(
                      notif.message
                    )}</div>
                    <div class="notification-time">
                        <i class="far fa-clock"></i> ${notif.time_ago}
                    </div>
                </div>
                ${!notif.is_read ? '<div class="notification-dot"></div>' : ''}
            </div>
        `;
  }

  /**
   * Handle notification item click
   */
  async handleNotificationClick(item) {
    const id = item.dataset.id;
    const link = item.dataset.link;
    const isUnread = item.classList.contains('unread');

    // Mark as read if unread
    if (isUnread) {
      await this.markAsRead(id);
      item.classList.remove('unread');
      item.querySelector('.notification-dot')?.remove();
    }

    // Navigate to link if provided
    if (link) {
      this.closeDropdown();
      window.location.href = link;
    }
  }

  /**
   * Mark a notification as read
   */
  async markAsRead(notificationId) {
    try {
      const response = await fetch(
        `${this.options.apiUrl}?action=read&id=${notificationId}`,
        {
          method: 'POST',
          credentials: 'same-origin',
        }
      );
      const data = await response.json();

      if (data.success) {
        this.unreadCount = Math.max(0, this.unreadCount - 1);
        this.updateBadge();
      }
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  }

  /**
   * Mark all notifications as read
   */
  async markAllAsRead() {
    try {
      const response = await fetch(`${this.options.apiUrl}?action=read_all`, {
        method: 'POST',
        credentials: 'same-origin',
      });
      const data = await response.json();

      if (data.success) {
        this.unreadCount = 0;
        this.updateBadge();

        // Update UI
        document
          .querySelectorAll('.notification-item.unread')
          .forEach((item) => {
            item.classList.remove('unread');
            item.querySelector('.notification-dot')?.remove();
          });

        this.showToast('All notifications marked as read', 'success');
      }
    } catch (error) {
      console.error('Failed to mark all as read:', error);
    }
  }

  /**
   * Toggle dropdown visibility
   */
  toggleDropdown() {
    if (this.isOpen) {
      this.closeDropdown();
    } else {
      this.openDropdown();
    }
  }

  /**
   * Open the dropdown
   */
  openDropdown() {
    if (!this.dropdownElement) return;

    this.dropdownElement.classList.add('show');
    this.isOpen = true;

    // Refresh notifications when opening
    this.fetchNotifications();
  }

  /**
   * Close the dropdown
   */
  closeDropdown() {
    if (!this.dropdownElement) return;

    this.dropdownElement.classList.remove('show');
    this.isOpen = false;
  }

  /**
   * Called when new notifications arrive
   */
  onNewNotification(count) {
    // Play sound if enabled
    if (this.options.enableSound) {
      this.playNotificationSound();
    }

    // Show toast notification
    this.showToast(
      `You have ${count} new notification${count > 1 ? 's' : ''}`,
      'info'
    );

    // Refresh the list if dropdown is open
    if (this.isOpen) {
      this.fetchNotifications();
    }
  }

  /**
   * Play notification sound
   */
  playNotificationSound() {
    try {
      const audio = new Audio(
        '../../assets/sounds/notification.mp3'
      );
      audio.volume = 0.5;
      audio.play().catch(() => {});
    } catch (e) {
      // Sound not supported
    }
  }

  /**
   * Show toast notification
   */
  showToast(message, type = 'info') {
    // Use global showToast if available
    if (typeof window.showToast === 'function') {
      window.showToast(type, '', message);
    } else {
      // Fallback to console
      console.log(`[${type.toUpperCase()}] ${message}`);
    }
  }

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Cleanup
   */
  destroy() {
    this.stopPolling();
  }
}

// Auto-initialize if DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize if the bell element exists
  const bellElement = document.querySelector('#notification-bell');
  if (bellElement) {
    // Get API URL from data attribute or use default
    const apiUrl =
      bellElement.getAttribute('data-api-url') || './api/notifications.php';
    window.notificationBell = new NotificationBell({ apiUrl });
  }
});
