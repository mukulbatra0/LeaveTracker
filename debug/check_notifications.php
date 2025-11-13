<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in first.";
    exit;
}

// Include database connection
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

echo "<h1>Debug: Notifications</h1>";
echo "<p>Current user ID: {$user_id}</p>";

// Check notifications for current user
$notifications_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$notifications_stmt = $conn->prepare($notifications_sql);
$notifications_stmt->execute([$user_id]);
$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Recent Notifications</h2>";
if (count($notifications) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Related To</th><th>Related ID</th><th>Link</th><th>Is Read</th><th>Created</th></tr>";
    
    foreach ($notifications as $notification) {
        echo "<tr>";
        echo "<td>" . $notification['id'] . "</td>";
        echo "<td>" . htmlspecialchars($notification['title']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($notification['message'], 0, 50)) . "...</td>";
        echo "<td>" . ($notification['related_to'] ?? 'NULL') . "</td>";
        echo "<td>" . ($notification['related_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($notification['link'] ?? 'NULL') . "</td>";
        echo "<td>" . ($notification['is_read'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . $notification['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Test Notification Links</h3>";
    foreach ($notifications as $notification) {
        $handler_url = "../modules/notification_handler.php?id=" . $notification['id'];
        echo "<p>Notification ID {$notification['id']}: <a href='{$handler_url}' target='_blank'>Test Link</a></p>";
    }
    
} else {
    echo "<p>No notifications found for current user.</p>";
}

// Test the API endpoint
echo "<h2>Test API Endpoint</h2>";
echo "<p>API URL: <a href='../api/get_notifications.php' target='_blank'>../api/get_notifications.php</a></p>";

// Show JavaScript debug info
echo "<h2>JavaScript Debug</h2>";
echo "<script>";
echo "console.log('Current path:', window.location.pathname);";
echo "console.log('Path segments:', window.location.pathname.split('/').filter(segment => segment !== ''));";

echo "function getBasePath() {";
echo "    const currentPath = window.location.pathname;";
echo "    const pathSegments = currentPath.split('/').filter(segment => segment !== '');";
echo "    if (pathSegments.includes('modules') || pathSegments.includes('admin') || pathSegments.includes('dashboards')) {";
echo "        return '../';";
echo "    }";
echo "    return './';";
echo "}";

echo "console.log('Base path:', getBasePath());";
echo "console.log('Notification handler URL would be:', getBasePath() + 'modules/notification_handler.php?id=123');";
echo "</script>";

echo "<p>Check the browser console (F12) to see the JavaScript debug output.</p>";

echo "<h2>Manual Test</h2>";
echo "<p>Try clicking this direct link to notification handler:</p>";
echo "<a href='../modules/notification_handler.php?id=1'>Test Notification Handler (ID: 1)</a>";

echo "<hr>";
echo "<p><a href='../modules/notifications.php'>← Back to Notifications</a></p>";
?>