<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get notification ID and action
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$notification_id) {
    $_SESSION['alert'] = "Invalid notification ID.";
    $_SESSION['alert_type'] = "danger";
    header('Location: notifications.php');
    exit;
}

// Get notification details
$stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
$stmt->execute([$notification_id, $_SESSION['user_id']]);
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    $_SESSION['alert'] = "Notification not found.";
    $_SESSION['alert_type'] = "danger";
    header('Location: notifications.php');
    exit;
}

// Mark notification as read
if (!$notification['is_read']) {
    $update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $update_stmt->execute([$notification_id]);
}

// Redirect based on notification type and related content
$redirect_url = '../index.php'; // Default fallback

if ($notification['related_to'] == 'leave_application' && $notification['related_id']) {
    $redirect_url = "view_application.php?id=" . $notification['related_id'];
} elseif ($notification['related_to'] == 'user' && $notification['related_id']) {
    $redirect_url = "profile.php?id=" . $notification['related_id'];
} elseif ($notification['related_to'] == 'department' && $notification['related_id']) {
    $redirect_url = "departments.php?id=" . $notification['related_id'];
}

// Handle specific actions
if ($action == 'approve' && $notification['related_to'] == 'leave_application') {
    $redirect_url = "process_approval.php?action=approve&id=" . $notification['related_id'];
} elseif ($action == 'reject' && $notification['related_to'] == 'leave_application') {
    $redirect_url = "process_approval.php?action=reject&id=" . $notification['related_id'];
}

header('Location: ' . $redirect_url);
exit;
?>