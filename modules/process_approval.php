<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Include email notification class
require_once '../classes/EmailNotification.php';
$emailNotification = new EmailNotification($conn);

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user has approval permissions
$allowed_roles = ['head_of_department', 'director', 'admin'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Process approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $application_id = $_GET['id'];
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';
    
    // Verify the application exists and user has permission to approve it
    $check_sql = "SELECT la.*, u.first_name, u.last_name, u.email, u.department_id, lt.name as leave_type_name,
                  d.name as department_name
                  FROM leave_applications la 
                  JOIN users u ON la.user_id = u.id 
                  JOIN leave_types lt ON la.leave_type_id = lt.id
                  JOIN departments d ON u.department_id = d.id
                  WHERE la.id = :application_id AND la.status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $application = $check_stmt->fetch();
        
        // Check if user has permission to approve this application
        $can_approve = false;
        
        if ($role == 'head_of_department') {
            // Head of department can approve applications from their department staff
            if ($application['department_id'] == $_SESSION['department_id']) {
                // Check if this is the first level approval (no previous approvals)
                $prev_approval_sql = "SELECT COUNT(*) as count FROM leave_approvals 
                                     WHERE leave_application_id = :app_id";
                $prev_approval_stmt = $conn->prepare($prev_approval_sql);
                $prev_approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                $prev_approval_stmt->execute();
                $prev_count = $prev_approval_stmt->fetch()['count'];
                
                if ($prev_count == 0) {
                    $can_approve = true;
                }
            }
        } elseif ($role == 'director') {
            // Director can approve applications that have been approved by head of department
            $hod_approval_sql = "SELECT COUNT(*) as count FROM leave_approvals 
                               WHERE leave_application_id = :app_id 
                               AND approver_level = 'head_of_department' 
                               AND status = 'approved'";
            $hod_approval_stmt = $conn->prepare($hod_approval_sql);
            $hod_approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
            $hod_approval_stmt->execute();
            $hod_count = $hod_approval_stmt->fetch()['count'];
            
            if ($hod_count > 0) {
                $can_approve = true;
            }
        } elseif ($role == 'admin') {
            // Admin can approve any application (for emergency cases)
            $can_approve = true;
        }
        
        if ($can_approve) {
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                if ($action == 'approve') {
                    // Create approval record
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, status, comments) 
                                   VALUES (:app_id, :approver_id, :approver_level, 'approved', :comments)";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $user_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_level', $role, PDO::PARAM_STR);
                    $approval_stmt->bindParam(':comments', $reason, PDO::PARAM_STR);
                    $approval_stmt->execute();
                    
                    // Check if this is the final approval (director approval or admin override)
                    if ($role == 'director' || $role == 'admin') {
                        // Final approval - update application status
                        $update_app_sql = "UPDATE leave_applications SET status = 'approved' WHERE id = :app_id";
                        $update_app_stmt = $conn->prepare($update_app_sql);
                        $update_app_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                        $update_app_stmt->execute();
                        
                        // Update leave balance
                        $current_year = date('Y');
                        $update_balance_sql = "UPDATE leave_balances 
                                             SET balance = balance - :days, used = used + :days 
                                             WHERE user_id = :user_id AND leave_type_id = :leave_type_id AND year = :year";
                        $update_balance_stmt = $conn->prepare($update_balance_sql);
                        $update_balance_stmt->bindParam(':days', $application['days'], PDO::PARAM_STR);
                        $update_balance_stmt->bindParam(':user_id', $application['user_id'], PDO::PARAM_INT);
                        $update_balance_stmt->bindParam(':leave_type_id', $application['leave_type_id'], PDO::PARAM_INT);
                        $update_balance_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
                        $update_balance_stmt->execute();
                        
                        // Send final approval notification
                        $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                           VALUES (:user_id, 'Leave Application Approved', 'Your leave application has been fully approved and is now active.', 'leave_application', :related_id)";
                        $notification_stmt = $conn->prepare($notification_sql);
                        $notification_stmt->bindParam(':user_id', $application['user_id'], PDO::PARAM_INT);
                        $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                        $notification_stmt->execute();
                        
                        // Send email notification
                        $emailNotification->sendLeaveStatusNotification(
                            $application['email'],
                            $application['first_name'] . ' ' . $application['last_name'],
                            'approved',
                            $application['leave_type_name'],
                            $application['start_date'],
                            $application['end_date'],
                            'Your leave application has been fully approved.'
                        );
                        
                        $message = "Leave application has been fully approved.";
                    } else {
                        // Intermediate approval - notify next approver (director)
                        $director_sql = "SELECT id, email, first_name, last_name FROM users WHERE role = 'director' AND status = 'active' LIMIT 1";
                        $director_stmt = $conn->prepare($director_sql);
                        $director_stmt->execute();
                        
                        if ($director_stmt->rowCount() > 0) {
                            $director = $director_stmt->fetch();
                            
                            // Send notification to director
                            $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                               VALUES (:user_id, 'Leave Approval Required', 'A leave application requires your final approval.', 'leave_application', :related_id)";
                            $notification_stmt = $conn->prepare($notification_sql);
                            $notification_stmt->bindParam(':user_id', $director['id'], PDO::PARAM_INT);
                            $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                            $notification_stmt->execute();
                        }
                        
                        // Notify applicant about intermediate approval
                        $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                           VALUES (:user_id, 'Leave Application Update', 'Your leave application has been approved by your Head of Department and is now awaiting Director approval.', 'leave_application', :related_id)";
                        $notification_stmt = $conn->prepare($notification_sql);
                        $notification_stmt->bindParam(':user_id', $application['user_id'], PDO::PARAM_INT);
                        $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                        $notification_stmt->execute();
                        
                        $message = "Leave application approved and forwarded to Director for final approval.";
                    }
                    
                } elseif ($action == 'reject') {
                    // Create rejection record
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, status, comments) 
                                   VALUES (:app_id, :approver_id, :approver_level, 'rejected', :comments)";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $user_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_level', $role, PDO::PARAM_STR);
                    $approval_stmt->bindParam(':comments', $reason, PDO::PARAM_STR);
                    $approval_stmt->execute();
                    
                    // Update application status to rejected
                    $update_app_sql = "UPDATE leave_applications SET status = 'rejected' WHERE id = :app_id";
                    $update_app_stmt = $conn->prepare($update_app_sql);
                    $update_app_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $update_app_stmt->execute();
                    
                    // Send rejection notification
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                       VALUES (:user_id, 'Leave Application Rejected', 'Your leave application has been rejected.', 'leave_application', :related_id)";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $application['user_id'], PDO::PARAM_INT);
                    $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                    $notification_stmt->execute();
                    
                    // Send email notification
                    $emailNotification->sendLeaveStatusNotification(
                        $application['email'],
                        $application['first_name'] . ' ' . $application['last_name'],
                        'rejected',
                        $application['leave_type_name'],
                        $application['start_date'],
                        $application['end_date'],
                        $reason
                    );
                    
                    $message = "Leave application has been rejected.";
                }
                
                // Log the action
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                          VALUES (:user_id, :action, 'leave_applications', :entity_id, :details, :ip_address, :user_agent)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':action', $action . '_leave', PDO::PARAM_STR);
                $log_stmt->bindParam(':entity_id', $application_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':details', $reason, PDO::PARAM_STR);
                $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
                $log_stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = $message;
                $_SESSION['alert_type'] = "success";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                
                $_SESSION['alert'] = "Error: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "You don't have permission to approve this application.";
            $_SESSION['alert_type'] = "danger";
        }
    } else {
        $_SESSION['alert'] = "Invalid application or application already processed.";
        $_SESSION['alert_type'] = "danger";
    }
} else {
    $_SESSION['alert'] = "Invalid request.";
    $_SESSION['alert_type'] = "danger";
}

// Redirect back to dashboard
header('Location: ../index.php');
exit;
?>