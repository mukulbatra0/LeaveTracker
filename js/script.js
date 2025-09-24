// ELMS JavaScript Functions
$(document).ready(function() {
    // Load notifications
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});

function loadNotifications() {
    $.ajax({
        url: 'modules/get_notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            updateNotificationBadge(data.count);
            updateNotificationList(data.notifications);
        },
        error: function() {
            console.log('Failed to load notifications');
        }
    });
}

function updateNotificationBadge(count) {
    const badge = $('#notification-count');
    if (count > 0) {
        badge.text(count).show();
    } else {
        badge.hide();
    }
}

function updateNotificationList(notifications) {
    const list = $('#notification-list');
    list.empty();
    
    if (notifications.length === 0) {
        list.append('<li class="dropdown-item text-center">No new notifications</li>');
    } else {
        notifications.forEach(function(notification) {
            const listItem = $('<li class="dropdown-item"></li>').text(notification.title);
            list.append(listItem);
        });
    }
}