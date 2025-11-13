<?php
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

try {
    // Get unread count
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->execute([$user_id]);
    $unread_count = $count_stmt->fetchColumn();
    
    // Get recent notifications (last 10)
    $notifications_stmt = $conn->prepare("
        SELECT id, title, message, related_to, related_id, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notifications_stmt->execute([$user_id]);
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'unread_count' => (int)$unread_count,
        'notifications' => $notifications
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log("Notification API error: " . $e->getMessage());
}
?>