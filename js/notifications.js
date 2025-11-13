// Notification handling JavaScript
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});

function loadNotifications() {
    // Determine the correct path based on current location
    const currentPath = window.location.pathname;
    const pathSegments = currentPath.split('/').filter(segment => segment !== '');
    
    let basePath = '';
    if (pathSegments.includes('modules') || pathSegments.includes('admin') || pathSegments.includes('dashboards')) {
        basePath = '../';
    }
    
    fetch(basePath + 'api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.unread_count);
            updateNotificationDropdown(data.notifications);
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-count');
    if (badge) {
        badge.textContent = count;
        if (count > 0) {
            badge.style.display = 'inline-block';
            badge.classList.add('notification-pulse'); // Add pulse animation for new notifications
        } else {
            badge.style.display = 'none';
            badge.classList.remove('notification-pulse');
        }
    }
}

// This function is no longer needed since we're using direct links
// Keeping it as a stub in case it's called elsewhere
function updateNotificationDropdown(notifications) {
    // No dropdown to update - notifications go directly to notifications page
    return;
}

function getBasePath() {
    const currentPath = window.location.pathname;
    const pathSegments = currentPath.split('/').filter(segment => segment !== '');
    
    if (pathSegments.includes('modules') || pathSegments.includes('admin') || pathSegments.includes('dashboards')) {
        return '../';
    }
    return './';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatNotificationTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));
    
    if (diffInMinutes < 1) {
        return 'Just now';
    } else if (diffInMinutes < 60) {
        return diffInMinutes + ' min ago';
    } else if (diffInMinutes < 1440) {
        const hours = Math.floor(diffInMinutes / 60);
        return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    } else {
        return date.toLocaleDateString();
    }
}