<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_admin'])) {
    echo "Access denied. You must be logged in as admin.";
    exit;
}

// Include database connection
require_once '../config/db.php';

echo "<h1>Fix Missing Approval Records</h1>";

// Find director applications that don't have approval records
$missing_approvals_sql = "SELECT la.id, la.user_id, u.first_name, u.last_name, la.created_at
                         FROM leave_applications la
                         JOIN users u ON la.user_id = u.id
                         WHERE u.role = 'director' 
                         AND la.status = 'pending'
                         AND NOT EXISTS (
                             SELECT 1 FROM leave_approvals lap 
                             WHERE lap.leave_application_id = la.id
                         )";
$missing_approvals_stmt = $conn->prepare($missing_approvals_sql);
$missing_approvals_stmt->execute();
$missing_approvals = $missing_approvals_stmt->fetchAll();

if (count($missing_approvals) > 0) {
    echo "<p>Found " . count($missing_approvals) . " director application(s) without approval records:</p>";
    
    foreach ($missing_approvals as $app) {
        echo "<li>App ID: {$app['id']}, Director: {$app['first_name']} {$app['last_name']}, Created: {$app['created_at']}</li>";
    }
    
    echo "<h2>Creating Missing Approval Records...</h2>";
    
    // Get the first available admin
    $admin_sql = "SELECT id FROM users WHERE role IN ('admin', 'hr_admin') AND status = 'active' ORDER BY role = 'admin' DESC LIMIT 1";
    $admin_stmt = $conn->prepare($admin_sql);
    $admin_stmt->execute();
    $admin = $admin_stmt->fetch();
    
    if ($admin) {
        $admin_id = $admin['id'];
        echo "<p>Using Admin ID: {$admin_id} for approval records</p>";
        
        $success_count = 0;
        
        foreach ($missing_approvals as $app) {
            try {
                // Create approval record
                $create_approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, status, created_at) 
                                       VALUES (:app_id, :approver_id, 'admin', 'pending', NOW())";
                $create_approval_stmt = $conn->prepare($create_approval_sql);
                $create_approval_stmt->bindParam(':app_id', $app['id'], PDO::PARAM_INT);
                $create_approval_stmt->bindParam(':approver_id', $admin_id, PDO::PARAM_INT);
                $create_approval_stmt->execute();
                
                // Create notification
                $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id, created_at) 
                                    VALUES (:user_id, 'Director Leave Approval Required', 'A Director leave application requires your approval.', 'leave_application', :related_id, NOW())";
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bindParam(':user_id', $admin_id, PDO::PARAM_INT);
                $notification_stmt->bindParam(':related_id', $app['id'], PDO::PARAM_INT);
                $notification_stmt->execute();
                
                echo "<p>✅ Created approval record for App ID: {$app['id']}</p>";
                $success_count++;
                
            } catch (PDOException $e) {
                echo "<p>❌ Failed to create approval record for App ID: {$app['id']} - Error: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<h2>Summary</h2>";
        echo "<p>Successfully created {$success_count} approval records out of " . count($missing_approvals) . " missing records.</p>";
        
    } else {
        echo "<p>❌ No admin users found to assign approvals to!</p>";
    }
    
} else {
    echo "<p>✅ No missing approval records found. All director applications have proper approval records.</p>";
}

echo "<hr>";
echo "<p><a href='debug_director_approvals.php'>← Back to Debug</a> | <a href='director_leave_approvals.php'>Director Approvals Page</a></p>";
?>