// notificationUtils.js - Centralized notification system for payment modules

class NotificationManager {
    constructor() {
        this.container = null;
        this.notifications = new Map();
        this.autoHideTimeout = 5000; // 5 seconds default
        this.maxNotifications = 3;
        this.notificationId = 0;
        
        this.init();
    }

    // Initialize notification system
    init() {
        // Create notification container if it doesn't exist
        this.createNotificationContainer();
        
        // Set up global notification function
        window.showNotification = (message, type = 'info', duration = this.autoHideTimeout) => {
            return this.show(message, type, duration);
        };
    }

    // Create notification container
    createNotificationContainer() {
        let container = document.getElementById('notification-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }
        
        this.container = container;
    }

    // Show notification
    show(message, type = 'info', duration = this.autoHideTimeout) {
        if (!message) return null;

        const id = ++this.notificationId;
        
        // Remove oldest notification if we have too many
        if (this.notifications.size >= this.maxNotifications) {
            const oldestId = Array.from(this.notifications.keys())[0];
            this.hide(oldestId);
        }

        // Create notification element
        const notification = this.createNotificationElement(id, message, type);
        
        // Add to container
        this.container.appendChild(notification);
        
        // Store reference
        this.notifications.set(id, {
            element: notification,
            timeout: null,
            type: type
        });

        // Trigger entrance animation
        requestAnimationFrame(() => {
            notification.classList.remove('translate-x-full', 'opacity-0');
        });

        // Set auto-hide timeout
        if (duration > 0) {
            const timeoutId = setTimeout(() => {
                this.hide(id);
            }, duration);
            
            this.notifications.get(id).timeout = timeoutId;
        }

        return id;
    }

    // Create notification element
    createNotificationElement(id, message, type) {
        const notification = document.createElement('div');
        notification.id = `notification-${id}`;
        notification.className = `
            transform translate-x-full opacity-0 transition-all duration-300 ease-out
            max-w-sm w-full bg-white rounded-lg shadow-lg border border-gray-200
            overflow-hidden cursor-pointer hover:shadow-xl
        `.trim();

        const colors = this.getNotificationColors(type);
        
        notification.innerHTML = `
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        ${this.getNotificationIcon(type, colors.iconClass)}
                    </div>
                    <div class="ml-3 w-0 flex-1">
                        <div class="text-sm font-medium ${colors.titleClass}">
                            ${this.getNotificationTitle(type)}
                        </div>
                        <div class="mt-1 text-sm ${colors.messageClass}">
                            ${message}
                        </div>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button class="inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition ease-in-out duration-150"
                                onclick="window.notificationManager.hide(${id})">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-2 text-xs text-gray-500 border-t">
                <div class="flex justify-between items-center">
                    <span>Click to dismiss</span>
                    <span id="notification-${id}-timer"></span>
                </div>
            </div>
        `;

        // Add click to dismiss
        notification.addEventListener('click', () => {
            this.hide(id);
        });

        return notification;
    }

    // Get notification colors based on type
    getNotificationColors(type) {
        const colorSchemes = {
            success: {
                iconClass: 'text-green-400',
                titleClass: 'text-green-800',
                messageClass: 'text-green-700'
            },
            error: {
                iconClass: 'text-red-400',
                titleClass: 'text-red-800',
                messageClass: 'text-red-700'
            },
            warning: {
                iconClass: 'text-yellow-400',
                titleClass: 'text-yellow-800',
                messageClass: 'text-yellow-700'
            },
            info: {
                iconClass: 'text-blue-400',
                titleClass: 'text-blue-800',
                messageClass: 'text-blue-700'
            }
        };

        return colorSchemes[type] || colorSchemes.info;
    }

    // Get notification icon based on type
    getNotificationIcon(type, colorClass) {
        const icons = {
            success: `
                <svg class="h-5 w-5 ${colorClass}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            `,
            error: `
                <svg class="h-5 w-5 ${colorClass}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            `,
            warning: `
                <svg class="h-5 w-5 ${colorClass}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            `,
            info: `
                <svg class="h-5 w-5 ${colorClass}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            `
        };

        return icons[type] || icons.info;
    }

    // Get notification title based on type
    getNotificationTitle(type) {
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };

        return titles[type] || 'Notification';
    }

    // Hide notification
    hide(id) {
        const notificationData = this.notifications.get(id);
        if (!notificationData) return;

        const { element, timeout } = notificationData;

        // Clear timeout if exists
        if (timeout) {
            clearTimeout(timeout);
        }

        // Trigger exit animation
        element.classList.add('translate-x-full', 'opacity-0');

        // Remove from DOM after animation
        setTimeout(() => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
            this.notifications.delete(id);
        }, 300);
    }

    // Hide all notifications
    hideAll() {
        Array.from(this.notifications.keys()).forEach(id => {
            this.hide(id);
        });
    }

    // Show success notification
    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    // Show error notification
    error(message, duration = 8000) { // Errors stay longer by default
        return this.show(message, 'error', duration);
    }

    // Show warning notification
    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    }

    // Show info notification
    info(message, duration) {
        return this.show(message, 'info', duration);
    }

    // Update notification content
    update(id, message, type) {
        const notificationData = this.notifications.get(id);
        if (!notificationData) return false;

        const colors = this.getNotificationColors(type || 'info');
        const titleElement = notificationData.element.querySelector('.font-medium');
        const messageElement = notificationData.element.querySelector('.mt-1');
        const iconContainer = notificationData.element.querySelector('.flex-shrink-0');

        if (titleElement) {
            titleElement.textContent = this.getNotificationTitle(type || 'info');
            titleElement.className = `text-sm font-medium ${colors.titleClass}`;
        }

        if (messageElement) {
            messageElement.textContent = message;
            messageElement.className = `mt-1 text-sm ${colors.messageClass}`;
        }

        if (iconContainer) {
            iconContainer.innerHTML = this.getNotificationIcon(type || 'info', colors.iconClass);
        }

        return true;
    }

    // Get notification count
    getCount() {
        return this.notifications.size;
    }

    // Check if notification exists
    exists(id) {
        return this.notifications.has(id);
    }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the global notification manager
    if (!window.notificationManager) {
        window.notificationManager = new NotificationManager();
    }
});

// Export for use in other modules (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}