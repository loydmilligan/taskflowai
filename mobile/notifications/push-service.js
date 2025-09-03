/**
 * Push Notification Service for TaskFlow AI
 * Handles push notification registration, management, and delivery
 */

class PushNotificationService {
  constructor() {
    this.isSupported = this.checkSupport();
    this.subscription = null;
    this.vapidPublicKey = null; // Will be set from server
    this.notificationTypes = {
      TASK_REMINDER: 'task_reminder',
      TASK_DUE: 'task_due',
      PROJECT_UPDATE: 'project_update',
      AI_SUGGESTION: 'ai_suggestion',
      SYNC_COMPLETE: 'sync_complete',
      COLLABORATION: 'collaboration'
    };
    this.defaultIcon = '/icons/icon-192x192.png';
    this.defaultBadge = '/icons/badge-72x72.png';
  }

  /**
   * Check if push notifications are supported
   */
  checkSupport() {
    return (
      'serviceWorker' in navigator &&
      'PushManager' in window &&
      'Notification' in window
    );
  }

  /**
   * Request notification permission
   */
  async requestPermission() {
    if (!this.isSupported) {
      throw new Error('Push notifications are not supported');
    }

    if (Notification.permission === 'granted') {
      return 'granted';
    }

    if (Notification.permission === 'denied') {
      throw new Error('Push notifications are blocked. Please enable them in browser settings.');
    }

    const permission = await Notification.requestPermission();
    
    if (permission !== 'granted') {
      throw new Error('Push notification permission denied');
    }

    return permission;
  }

  /**
   * Get or create push subscription
   */
  async subscribeToPush() {
    if (!this.isSupported) {
      throw new Error('Push notifications are not supported');
    }

    try {
      // Request permission first
      await this.requestPermission();

      // Get service worker registration
      const registration = await navigator.serviceWorker.ready;

      // Check if already subscribed
      let subscription = await registration.pushManager.getSubscription();

      if (!subscription) {
        // Get VAPID key from server
        if (!this.vapidPublicKey) {
          this.vapidPublicKey = await this.getVapidPublicKey();
        }

        // Create new subscription
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: this.urlB64ToUint8Array(this.vapidPublicKey)
        });
      }

      this.subscription = subscription;

      // Send subscription to server
      await this.sendSubscriptionToServer(subscription);

      return subscription;
    } catch (error) {
      console.error('Failed to subscribe to push notifications:', error);
      throw error;
    }
  }

  /**
   * Unsubscribe from push notifications
   */
  async unsubscribeFromPush() {
    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();

      if (subscription) {
        await subscription.unsubscribe();
        await this.removeSubscriptionFromServer(subscription);
        this.subscription = null;
      }

      return true;
    } catch (error) {
      console.error('Failed to unsubscribe from push notifications:', error);
      throw error;
    }
  }

  /**
   * Get subscription status
   */
  async getSubscriptionStatus() {
    if (!this.isSupported) {
      return {
        isSupported: false,
        permission: 'default',
        isSubscribed: false
      };
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();

      return {
        isSupported: true,
        permission: Notification.permission,
        isSubscribed: !!subscription,
        subscription: subscription
      };
    } catch (error) {
      console.error('Failed to get subscription status:', error);
      return {
        isSupported: true,
        permission: Notification.permission,
        isSubscribed: false,
        error: error.message
      };
    }
  }

  /**
   * Send local notification
   */
  async sendLocalNotification(options) {
    if (Notification.permission !== 'granted') {
      throw new Error('Notification permission not granted');
    }

    const defaultOptions = {
      icon: this.defaultIcon,
      badge: this.defaultBadge,
      vibrate: [200, 100, 200],
      requireInteraction: false,
      renotify: true
    };

    const notificationOptions = { ...defaultOptions, ...options };

    // Use service worker notification if available
    try {
      const registration = await navigator.serviceWorker.ready;
      await registration.showNotification(
        options.title || 'TaskFlow AI',
        notificationOptions
      );
    } catch (error) {
      // Fallback to regular notification
      new Notification(options.title || 'TaskFlow AI', notificationOptions);
    }
  }

  /**
   * Schedule local notification
   */
  async scheduleNotification(options, delay) {
    setTimeout(() => {
      this.sendLocalNotification(options);
    }, delay);
  }

  /**
   * Create task reminder notification
   */
  createTaskReminderNotification(task) {
    return {
      title: 'Task Reminder',
      body: `Don't forget: ${task.title}`,
      icon: this.defaultIcon,
      badge: this.defaultBadge,
      data: {
        type: this.notificationTypes.TASK_REMINDER,
        taskId: task.id,
        url: `/?task=${task.id}`
      },
      actions: [
        {
          action: 'complete',
          title: 'Mark Complete',
          icon: '/icons/action-complete.png'
        },
        {
          action: 'snooze',
          title: 'Snooze 1h',
          icon: '/icons/action-snooze.png'
        }
      ],
      requireInteraction: true,
      vibrate: [200, 100, 200, 100, 200]
    };
  }

  /**
   * Create task due notification
   */
  createTaskDueNotification(task) {
    const isOverdue = new Date(task.due_date) < new Date();
    
    return {
      title: isOverdue ? 'Task Overdue!' : 'Task Due Soon',
      body: `${task.title} ${isOverdue ? 'was due' : 'is due'} ${this.formatDueDate(task.due_date)}`,
      icon: this.defaultIcon,
      badge: this.defaultBadge,
      data: {
        type: this.notificationTypes.TASK_DUE,
        taskId: task.id,
        isOverdue: isOverdue,
        url: `/?task=${task.id}`
      },
      actions: [
        {
          action: 'view',
          title: 'View Task',
          icon: '/icons/action-view.png'
        },
        {
          action: 'extend',
          title: 'Extend Deadline',
          icon: '/icons/action-extend.png'
        }
      ],
      requireInteraction: true,
      vibrate: isOverdue ? [500, 200, 500, 200, 500] : [200, 100, 200]
    };
  }

  /**
   * Create AI suggestion notification
   */
  createAISuggestionNotification(suggestion) {
    return {
      title: 'AI Suggestion',
      body: suggestion.message,
      icon: this.defaultIcon,
      badge: this.defaultBadge,
      data: {
        type: this.notificationTypes.AI_SUGGESTION,
        suggestionId: suggestion.id,
        url: '/?view=chat'
      },
      actions: [
        {
          action: 'view',
          title: 'View',
          icon: '/icons/action-view.png'
        },
        {
          action: 'dismiss',
          title: 'Dismiss',
          icon: '/icons/action-dismiss.png'
        }
      ]
    };
  }

  /**
   * Update notification preferences
   */
  async updateNotificationPreferences(preferences) {
    try {
      const response = await fetch('/api/notifications/preferences', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(preferences)
      });

      if (!response.ok) {
        throw new Error('Failed to update notification preferences');
      }

      // Store preferences locally for offline access
      localStorage.setItem('taskflow_notification_preferences', JSON.stringify(preferences));

      return await response.json();
    } catch (error) {
      console.error('Error updating notification preferences:', error);
      throw error;
    }
  }

  /**
   * Get notification preferences
   */
  getNotificationPreferences() {
    try {
      const stored = localStorage.getItem('taskflow_notification_preferences');
      return stored ? JSON.parse(stored) : this.getDefaultPreferences();
    } catch (error) {
      console.error('Error getting notification preferences:', error);
      return this.getDefaultPreferences();
    }
  }

  /**
   * Get default notification preferences
   */
  getDefaultPreferences() {
    return {
      taskReminders: true,
      taskDue: true,
      projectUpdates: true,
      aiSuggestions: true,
      syncComplete: false,
      collaboration: true,
      quietHours: {
        enabled: true,
        start: '22:00',
        end: '08:00'
      },
      vibration: true,
      sound: true
    };
  }

  /**
   * Test notification
   */
  async testNotification() {
    try {
      await this.sendLocalNotification({
        title: 'TaskFlow AI Test',
        body: 'Push notifications are working correctly!',
        tag: 'test',
        data: {
          type: 'test',
          timestamp: Date.now()
        }
      });

      return { success: true, message: 'Test notification sent' };
    } catch (error) {
      throw new Error(`Test notification failed: ${error.message}`);
    }
  }

  // Private helper methods

  /**
   * Get VAPID public key from server
   */
  async getVapidPublicKey() {
    try {
      const response = await fetch('/api/notifications/vapid-key');
      const data = await response.json();
      return data.publicKey;
    } catch (error) {
      // Mock VAPID key for demo
      return 'BEl62iUYgUivxIkv69yViEuiBIa40HI80NqIk9jLFScXIWe5gNJQPaYg8wbwRdOFGJGkjJJHpnXgMkVi6BdBb0mQ';
    }
  }

  /**
   * Send subscription to server
   */
  async sendSubscriptionToServer(subscription) {
    try {
      const response = await fetch('/api/notifications/subscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          subscription: subscription,
          userAgent: navigator.userAgent,
          timestamp: Date.now()
        })
      });

      if (!response.ok) {
        throw new Error('Failed to send subscription to server');
      }

      return await response.json();
    } catch (error) {
      console.error('Error sending subscription to server:', error);
      // Don't throw error - offline functionality should still work
    }
  }

  /**
   * Remove subscription from server
   */
  async removeSubscriptionFromServer(subscription) {
    try {
      const response = await fetch('/api/notifications/unsubscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ subscription })
      });

      return response.ok;
    } catch (error) {
      console.error('Error removing subscription from server:', error);
      return false;
    }
  }

  /**
   * Convert VAPID key
   */
  urlB64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  /**
   * Format due date for notification
   */
  formatDueDate(dueDateString) {
    const dueDate = new Date(dueDateString);
    const now = new Date();
    const diff = dueDate - now;
    
    if (diff < 0) {
      // Overdue
      const daysPast = Math.floor(Math.abs(diff) / (1000 * 60 * 60 * 24));
      if (daysPast === 0) return 'today';
      if (daysPast === 1) return 'yesterday';
      return `${daysPast} days ago`;
    } else {
      // Due soon
      const daysLeft = Math.floor(diff / (1000 * 60 * 60 * 24));
      if (daysLeft === 0) return 'today';
      if (daysLeft === 1) return 'tomorrow';
      return `in ${daysLeft} days`;
    }
  }
}

// Notification UI Manager
class NotificationUIManager {
  constructor(pushService) {
    this.pushService = pushService;
  }

  /**
   * Show notification settings panel
   */
  async showNotificationSettings() {
    const status = await this.pushService.getSubscriptionStatus();
    const preferences = this.pushService.getNotificationPreferences();

    const modalHTML = `
      <div id="notification-settings-modal" class="notification-modal">
        <div class="notification-modal-content">
          <div class="notification-header">
            <h2>Notification Settings</h2>
            <button id="close-notification-settings" class="close-btn">&times;</button>
          </div>
          
          <div class="notification-section">
            <h3>Push Notifications</h3>
            <div class="notification-status">
              <span class="status-indicator ${status.permission === 'granted' ? 'granted' : 'denied'}">
                ${this.getPermissionStatusText(status)}
              </span>
            </div>
            
            ${status.permission !== 'granted' ? `
              <button id="enable-notifications" class="btn-primary">Enable Notifications</button>
            ` : `
              <button id="test-notification" class="btn-secondary">Test Notification</button>
              <button id="disable-notifications" class="btn-secondary">Disable Notifications</button>
            `}
          </div>
          
          ${status.permission === 'granted' ? this.createPreferencesSection(preferences) : ''}
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    this.setupEventListeners();
    this.addNotificationStyles();
  }

  /**
   * Create preferences section
   */
  createPreferencesSection(preferences) {
    return `
      <div class="notification-section">
        <h3>Notification Types</h3>
        <div class="notification-preferences">
          <label class="notification-toggle">
            <input type="checkbox" ${preferences.taskReminders ? 'checked' : ''} 
                   data-pref="taskReminders">
            <span>Task Reminders</span>
          </label>
          
          <label class="notification-toggle">
            <input type="checkbox" ${preferences.taskDue ? 'checked' : ''} 
                   data-pref="taskDue">
            <span>Due Date Alerts</span>
          </label>
          
          <label class="notification-toggle">
            <input type="checkbox" ${preferences.projectUpdates ? 'checked' : ''} 
                   data-pref="projectUpdates">
            <span>Project Updates</span>
          </label>
          
          <label class="notification-toggle">
            <input type="checkbox" ${preferences.aiSuggestions ? 'checked' : ''} 
                   data-pref="aiSuggestions">
            <span>AI Suggestions</span>
          </label>
          
          <label class="notification-toggle">
            <input type="checkbox" ${preferences.collaboration ? 'checked' : ''} 
                   data-pref="collaboration">
            <span>Collaboration</span>
          </label>
        </div>
      </div>
      
      <div class="notification-section">
        <h3>Quiet Hours</h3>
        <label class="notification-toggle">
          <input type="checkbox" ${preferences.quietHours.enabled ? 'checked' : ''} 
                 id="quiet-hours-enabled">
          <span>Enable Quiet Hours</span>
        </label>
        
        <div class="quiet-hours-config ${!preferences.quietHours.enabled ? 'disabled' : ''}">
          <div class="time-range">
            <label>From: <input type="time" id="quiet-start" value="${preferences.quietHours.start}"></label>
            <label>To: <input type="time" id="quiet-end" value="${preferences.quietHours.end}"></label>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Setup event listeners for settings modal
   */
  setupEventListeners() {
    // Close modal
    document.getElementById('close-notification-settings')?.addEventListener('click', 
      () => this.hideNotificationSettings()
    );

    // Enable notifications
    document.getElementById('enable-notifications')?.addEventListener('click', 
      () => this.handleEnableNotifications()
    );

    // Disable notifications
    document.getElementById('disable-notifications')?.addEventListener('click', 
      () => this.handleDisableNotifications()
    );

    // Test notification
    document.getElementById('test-notification')?.addEventListener('click', 
      () => this.handleTestNotification()
    );

    // Preference changes
    document.querySelectorAll('[data-pref]').forEach(input => {
      input.addEventListener('change', (e) => this.handlePreferenceChange(e.target));
    });

    // Quiet hours toggle
    document.getElementById('quiet-hours-enabled')?.addEventListener('change', 
      (e) => this.handleQuietHoursToggle(e.target.checked)
    );

    // Time inputs
    ['quiet-start', 'quiet-end'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.saveQuietHours());
    });
  }

  /**
   * Handle enable notifications
   */
  async handleEnableNotifications() {
    try {
      await this.pushService.subscribeToPush();
      this.hideNotificationSettings();
      this.showNotificationSettings(); // Refresh modal
    } catch (error) {
      alert(`Failed to enable notifications: ${error.message}`);
    }
  }

  /**
   * Handle disable notifications
   */
  async handleDisableNotifications() {
    if (confirm('Are you sure you want to disable push notifications?')) {
      try {
        await this.pushService.unsubscribeFromPush();
        this.hideNotificationSettings();
        this.showNotificationSettings(); // Refresh modal
      } catch (error) {
        alert(`Failed to disable notifications: ${error.message}`);
      }
    }
  }

  /**
   * Handle test notification
   */
  async handleTestNotification() {
    try {
      await this.pushService.testNotification();
    } catch (error) {
      alert(`Test notification failed: ${error.message}`);
    }
  }

  /**
   * Handle preference change
   */
  handlePreferenceChange(input) {
    const preferences = this.pushService.getNotificationPreferences();
    preferences[input.dataset.pref] = input.checked;
    this.pushService.updateNotificationPreferences(preferences);
  }

  /**
   * Handle quiet hours toggle
   */
  handleQuietHoursToggle(enabled) {
    const preferences = this.pushService.getNotificationPreferences();
    preferences.quietHours.enabled = enabled;
    this.pushService.updateNotificationPreferences(preferences);

    const configElement = document.querySelector('.quiet-hours-config');
    if (configElement) {
      configElement.classList.toggle('disabled', !enabled);
    }
  }

  /**
   * Save quiet hours settings
   */
  saveQuietHours() {
    const preferences = this.pushService.getNotificationPreferences();
    preferences.quietHours.start = document.getElementById('quiet-start').value;
    preferences.quietHours.end = document.getElementById('quiet-end').value;
    this.pushService.updateNotificationPreferences(preferences);
  }

  /**
   * Hide notification settings modal
   */
  hideNotificationSettings() {
    const modal = document.getElementById('notification-settings-modal');
    if (modal) {
      modal.remove();
    }
  }

  /**
   * Get permission status text
   */
  getPermissionStatusText(status) {
    if (!status.isSupported) return 'Not Supported';
    
    switch (status.permission) {
      case 'granted':
        return status.isSubscribed ? 'Enabled' : 'Permission Granted';
      case 'denied':
        return 'Blocked';
      default:
        return 'Not Set';
    }
  }

  /**
   * Add notification styles
   */
  addNotificationStyles() {
    if (document.getElementById('notification-styles')) return;

    const styles = `
      <style id="notification-styles">
        .notification-modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 10000;
        }
        
        .notification-modal-content {
          background: white;
          padding: 0;
          border-radius: 16px;
          max-width: 500px;
          width: 90%;
          max-height: 80vh;
          overflow-y: auto;
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .notification-header {
          padding: 1.5rem;
          border-bottom: 1px solid #e5e7eb;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        
        .notification-header h2 {
          margin: 0;
          color: #1a73e8;
        }
        
        .close-btn {
          background: none;
          border: none;
          font-size: 1.5rem;
          cursor: pointer;
          color: #666;
        }
        
        .notification-section {
          padding: 1.5rem;
          border-bottom: 1px solid #f3f4f6;
        }
        
        .notification-section:last-child {
          border-bottom: none;
        }
        
        .notification-section h3 {
          margin: 0 0 1rem 0;
          color: #374151;
        }
        
        .notification-status {
          margin-bottom: 1rem;
        }
        
        .status-indicator {
          padding: 0.5rem 1rem;
          border-radius: 20px;
          font-size: 0.875rem;
          font-weight: 500;
        }
        
        .status-indicator.granted {
          background: #d1fae5;
          color: #065f46;
        }
        
        .status-indicator.denied {
          background: #fee2e2;
          color: #991b1b;
        }
        
        .notification-preferences {
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }
        
        .notification-toggle {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          cursor: pointer;
        }
        
        .notification-toggle input[type="checkbox"] {
          width: 18px;
          height: 18px;
          cursor: pointer;
        }
        
        .quiet-hours-config {
          margin-top: 1rem;
        }
        
        .quiet-hours-config.disabled {
          opacity: 0.5;
          pointer-events: none;
        }
        
        .time-range {
          display: flex;
          gap: 1rem;
          align-items: center;
        }
        
        .time-range label {
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }
        
        .time-range input[type="time"] {
          padding: 0.5rem;
          border: 1px solid #d1d5db;
          border-radius: 6px;
        }
      </style>
    `;

    document.head.insertAdjacentHTML('beforeend', styles);
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { PushNotificationService, NotificationUIManager };
} else {
  window.PushNotificationService = PushNotificationService;
  window.NotificationUIManager = NotificationUIManager;
}