<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get document ID
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    header('HTTP/1.0 404 Not Found');
    exit('Document not found');
}

try {
    // Get document information
    $stmt = $conn->prepare("SELECT d.*, la.user_id 
                           FROM documents d 
                           JOIN leave_applications la ON d.leave_application_id = la.id 
                           WHERE d.id = ?");
    $stmt->execute([$doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('HTTP/1.0 404 Not Found');
        exit('Document not found');
    }
    
    // Check if user has permission to download
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    $can_download = false;
    
    // Document owner can download
    if ($document['user_id'] == $user_id) {
        $can_download = true;
    }
    
    // HR admin can download any document
    if ($role == 'hr_admin') {
        $can_download = true;
    }
    
    // Department heads can download documents from their department
    if ($role == 'department_head') {
        $stmt = $conn->prepare("SELECT u.department_id 
                               FROM users u 
                               JOIN leave_applications la ON u.id = la.user_id 
                               WHERE la.id = ?");
        $stmt->execute([$document['leave_application_id']]);
        $app_dept = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_dept = $stmt->fetchColumn();
        
        if ($app_dept == $user_dept) {
            $can_download = true;
        }
    }
    
    if (!$can_download) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
    
    // Check if file exists
    $file_path = '../uploads/' . $document['file_path'];
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found on server');
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output file
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
}
?>