<?php
session_start();

// Include database connection for logging
require_once 'config/db.php';

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    try {
        $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                  VALUES (:user_id, 'logout', 'users', :entity_id, 'User logged out', :ip_address, :user_agent)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(":entity_id", $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(":ip_address", $ip_address, PDO::PARAM_STR);
        $log_stmt->bindParam(":user_agent", $user_agent, PDO::PARAM_STR);
        $log_stmt->execute();
    } catch (Exception $e) {
        // Ignore logging errors during logout
    }
}

// Destroy all session data
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: login.php');
exit;
?>