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
if (($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && isset($_GET['id'])) || 
    ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['application_id']))) {
    
    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        $action = $_GET['action'];
        $application_id = $_GET['id'];
        $reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';
    } else {
        $action = $_POST['action'];
        $application_id = $_POST['application_id'];
        $reason = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    }
    
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
        
        // Double-check that the application is still pending
        if ($application['status'] !== 'pending') {
            $_SESSION['alert'] = "This application has already been " . $application['status'] . " and cannot be modified.";
            $_SESSION['alert_type'] = "warning";
            header('Location: ../index.php');
            exit;
        }
        
        // Check if user has permission to approve this application
        $can_approve = false;
        $permission_error = "";
        
        if ($role == 'head_of_department') {
            // Head of department can approve applications from their department staff
            
            // Check if department_id is set in session
            if (!isset($_SESSION['department_id'])) {
                $permission_error = "Department information not found in session. Please log out and log in again.";
            } elseif ($application['department_id'] != $_SESSION['department_id']) {
                $permission_error = "You can only approve applications from your department.";
            } else {
                // Check if head of department has already approved this application
                // (Rejections can be overridden, but approvals cannot)
                $hod_approved_sql = "SELECT COUNT(*) as count FROM leave_approvals 
                                   WHERE leave_application_id = :app_id 
                                   AND approver_level = 'head_of_department' 
                                   AND status = 'approved'";
                $hod_approved_stmt = $conn->prepare($hod_approved_sql);
                $hod_approved_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                $hod_approved_stmt->execute();
                $hod_approved_count = $hod_approved_stmt->fetch()['count'];
                
                if ($hod_approved_count == 0) {
                    $can_approve = true;
                } else {
                    $permission_error = "This application has already been approved by a Head of Department and is awaiting Director approval.";
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
            
            // Check if director has already processed this application
            $director_processed_sql = "SELECT COUNT(*) as count FROM leave_approvals 
                                     WHERE leave_application_id = :app_id 
                                     AND approver_level = 'director'";
            $director_processed_stmt = $conn->prepare($director_processed_sql);
            $director_processed_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
            $director_processed_stmt->execute();
            $director_processed_count = $director_processed_stmt->fetch()['count'];
            
            if ($hod_count > 0 && $director_processed_count == 0) {
                $can_approve = true;
            } elseif ($hod_count == 0) {
                $permission_error = "This application must be approved by Head of Department first.";
            } elseif ($director_processed_count > 0) {
                $permission_error = "This application has already been processed by a Director.";
            }
        } elseif ($role == 'admin' || $role == 'hr_admin') {
            // Admin/HR Admin can approve any application, including director applications
            // Check if this is a director application that needs admin approval
            $admin_approval_sql = "SELECT COUNT(*) as count FROM leave_approvals 
                                 WHERE leave_application_id = :app_id 
                                 AND approver_level = 'admin'
                                 AND status = 'pending'";
            $admin_approval_stmt = $conn->prepare($admin_approval_sql);
            $admin_approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
            $admin_approval_stmt->execute();
            $admin_pending_count = $admin_approval_stmt->fetch()['count'];
            
            if ($admin_pending_count > 0) {
                $can_approve = true;
            } else {
                // Check if this is an emergency override situation or regular admin approval
                $permission_error = "This application does not require admin approval or has already been processed.";
            }
        }
        
        if ($can_approve) {
            // Validate required data before proceeding
            if (empty($user_id) || $user_id === null) {
                $_SESSION['alert'] = "Error: User session invalid. Please log out and log in again.";
                $_SESSION['alert_type'] = "danger";
                header('Location: ../index.php');
                exit;
            }
            
            if (empty($application['user_id']) || $application['user_id'] === null) {
                $_SESSION['alert'] = "Error: Application user data is missing.";
                $_SESSION['alert_type'] = "danger";
                header('Location: ../index.php');
                exit;
            }
            
            // Ensure we have valid integer values
            $user_id = (int)$user_id;
            $application_id = (int)$application_id;
            $app_user_id = (int)$application['user_id'];
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                if ($action == 'approve') {
                    // Determine the correct approver level
                    $approver_level = $role;
                    if ($role == 'hr_admin') {
                        $approver_level = 'admin'; // HR Admin acts as admin for approvals
                    }
                    
                    // Check if there's an existing pending approval record to update
                    $existing_approval_sql = "SELECT id FROM leave_approvals 
                                            WHERE leave_application_id = :app_id 
                                            AND approver_level = :approver_level 
                                            AND status = 'pending'";
                    $existing_approval_stmt = $conn->prepare($existing_approval_sql);
                    $existing_approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $existing_approval_stmt->bindParam(':approver_level', $approver_level, PDO::PARAM_STR);
                    $existing_approval_stmt->execute();
                    $existing_approval = $existing_approval_stmt->fetch();
                    
                    if ($existing_approval) {
                        // Update existing approval record
                        $approval_sql = "UPDATE leave_approvals 
                                       SET approver_id = :approver_id, status = 'approved', comments = :comments, updated_at = NOW()
                                       WHERE id = :approval_id";
                        $approval_stmt = $conn->prepare($approval_sql);
                        $approval_stmt->bindParam(':approver_id', $user_id, PDO::PARAM_INT);
                        $approval_stmt->bindParam(':comments', $reason, PDO::PARAM_STR);
                        $approval_stmt->bindParam(':approval_id', $existing_approval['id'], PDO::PARAM_INT);
                        $approval_stmt->execute();
                    } else {
                        // Create new approval record
                        $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, status, comments) 
                                       VALUES (:app_id, :approver_id, :approver_level, 'approved', :comments)";
                        $approval_stmt = $conn->prepare($approval_sql);
                        $approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                        $approval_stmt->bindParam(':approver_id', $user_id, PDO::PARAM_INT);
                        $approval_stmt->bindParam(':approver_level', $approver_level, PDO::PARAM_STR);
                        $approval_stmt->bindParam(':comments', $reason, PDO::PARAM_STR);
                        $approval_stmt->execute();
                    }
                    
                    // Check if this is the final approval (director approval, admin approval, or hr_admin approval)
                    if ($role == 'director' || $role == 'admin' || $role == 'hr_admin') {
                        // Final approval - update application status
                        $update_app_sql = "UPDATE leave_applications SET status = 'approved' WHERE id = :app_id";
                        $update_app_stmt = $conn->prepare($update_app_sql);
                        $update_app_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                        $update_app_stmt->execute();
                        
                        // Update leave balance
                        $current_year = date('Y');
                        $days = $application['days'];
                        $app_leave_type_id = $application['leave_type_id'];
                        $update_balance_sql = "UPDATE leave_balances 
                                             SET total_days = total_days - :days_subtract, used_days = used_days + :days_add 
                                             WHERE user_id = :user_id AND leave_type_id = :leave_type_id AND year = :year";
                        $update_balance_stmt = $conn->prepare($update_balance_sql);
                        $update_balance_stmt->bindParam(':days_subtract', $days, PDO::PARAM_STR);
                        $update_balance_stmt->bindParam(':days_add', $days, PDO::PARAM_STR);
                        $update_balance_stmt->bindParam(':user_id', $app_user_id, PDO::PARAM_INT);
                        $update_balance_stmt->bindParam(':leave_type_id', $app_leave_type_id, PDO::PARAM_INT);
                        $update_balance_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
                        $update_balance_stmt->execute();
                        
                        // Send final approval notification
                        if ($app_user_id > 0 && $application_id > 0) {
                            try {
                                $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                                   VALUES (:user_id, 'Leave Application Approved', 'Your leave application has been fully approved and is now active.', 'leave_application', :related_id)";
                                $notification_stmt = $conn->prepare($notification_sql);
                                $notification_stmt->bindParam(':user_id', $app_user_id, PDO::PARAM_INT);
                                $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                                $notification_stmt->execute();
                            } catch (PDOException $e) {
                                error_log("Error inserting final approval notification: " . $e->getMessage());
                                // Continue without notification rather than failing the whole process
                            }
                        }
                        
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
                            $director_id = $director['id'];
                            
                            // Validate director ID before inserting notification
                            if (!empty($director_id) && $director_id > 0 && $application_id > 0) {
                                try {
                                    $director_id = (int)$director_id;
                                    // Send notification to director
                                    $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                                       VALUES (:user_id, 'Leave Approval Required', 'A leave application requires your final approval.', 'leave_application', :related_id)";
                                    $notification_stmt = $conn->prepare($notification_sql);
                                    $notification_stmt->bindParam(':user_id', $director_id, PDO::PARAM_INT);
                                    $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                                    $notification_stmt->execute();
                                } catch (PDOException $e) {
                                    error_log("Error inserting director notification: " . $e->getMessage());
                                }
                            }
                        }
                        
                        // Notify applicant about intermediate approval
                        if ($app_user_id > 0 && $application_id > 0) {
                            try {
                                $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                                   VALUES (:user_id, 'Leave Application Update', 'Your leave application has been approved by your Head of Department and is now awaiting Director approval.', 'leave_application', :related_id)";
                                $notification_stmt = $conn->prepare($notification_sql);
                                $notification_stmt->bindParam(':user_id', $app_user_id, PDO::PARAM_INT);
                                $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                                $notification_stmt->execute();
                            } catch (PDOException $e) {
                                error_log("Error inserting intermediate approval notification: " . $e->getMessage());
                            }
                        }
                        
                        $message = "Leave application approved and forwarded to Director for final approval.";
                    }
                    
                } elseif ($action == 'reject') {
                    // Determine the correct approver level for rejection
                    $approver_level = $role;
                    if ($role == 'hr_admin') {
                        $approver_level = 'admin'; // HR Admin acts as admin for approvals
                    }
                    
                    // Check if there's an existing pending approval record to update
                    $existing_approval_sql = "SELECT id FROM leave_approvals 
                                            WHERE leave_application_id = :app_id 
                                            AND approver_level = :approver_level 
                                            AND status = 'pending'";
                    $existing_approval_stmt = $conn->prepare($existing_approval_sql);
                    $existing_approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $existing_approval_stmt->bindParam(':approver_level', $approver_level, PDO::PARAM_STR);
                    $existing_approval_stmt->execute();
                    $existing_approval = $existing_approval_stmt->fetch();
                    
                    if ($existing_approval) {
                        // Update existing approval record
                        $approval_sql = "UPDATE leave_approvals 
                                       SET approver_id = :approver_id, status = 'rejected', comments = :comments, updated_at = NOW()
                                       WHERE id = :approval_id";
                        $approval_stmt = $conn->prepare($approval_sql);
                        $approval_stmt->bindParam(':approver_id', $user_id, PDO::PARAM_INT);
                        $approval_stmt->bindParam(':comments', $reason, PDO::PARAM_STR);
                        $approval_stmt->bindParam(':approval_id', $existing_approval['id'], PDO::PARAM_INT);
                        $approval_stmt->execute();
                    } else {
                        // Create new rejection record
                        $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, status, comments) 
                                       VALUES (:app_id, :approver_id, :approver_level, 'rejected', :comments)";
                        $approval_stmt = $conn->prepare($approval_sql);
                        $approval_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                        $approval_stmt->bindParam(':approver_id', $user_id, PDO::PARAM_INT);
                        $approval_stmt->bindParam(':approver_level', $approver_level, PDO::PARAM_STR);
                        $approval_stmt->bindParam(':comments', $reason, PDO::PARAM_STR);
                        $approval_stmt->execute();
                    }
                    
                    // Update application status to rejected
                    $update_app_sql = "UPDATE leave_applications SET status = 'rejected' WHERE id = :app_id";
                    $update_app_stmt = $conn->prepare($update_app_sql);
                    $update_app_stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                    $update_app_stmt->execute();
                    
                    // Send rejection notification (app_user_id already validated above)
                    if ($app_user_id > 0 && $application_id > 0) {
                        try {
                            $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                               VALUES (:user_id, 'Leave Application Rejected', 'Your leave application has been rejected.', 'leave_application', :related_id)";
                            $notification_stmt = $conn->prepare($notification_sql);
                            $notification_stmt->bindParam(':user_id', $app_user_id, PDO::PARAM_INT);
                            $notification_stmt->bindParam(':related_id', $application_id, PDO::PARAM_INT);
                            $notification_stmt->execute();
                        } catch (PDOException $e) {
                            error_log("Error inserting rejection notification: " . $e->getMessage());
                        }
                    }
                    
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
                if ($user_id > 0 && $application_id > 0) {
                    try {
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        $action_type = $action . '_leave';
                        $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                                  VALUES (:user_id, :action, 'leave_applications', :entity_id, :details, :ip_address, :user_agent)";
                        $log_stmt = $conn->prepare($log_sql);
                        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $log_stmt->bindParam(':action', $action_type, PDO::PARAM_STR);
                        $log_stmt->bindParam(':entity_id', $application_id, PDO::PARAM_INT);
                        $log_stmt->bindParam(':details', $reason, PDO::PARAM_STR);
                        $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
                        $log_stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
                        $log_stmt->execute();
                    } catch (PDOException $e) {
                        error_log("Error inserting audit log: " . $e->getMessage());
                    }
                }
                
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
            $_SESSION['alert'] = !empty($permission_error) ? $permission_error : "You don't have permission to approve this application.";
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