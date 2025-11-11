<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
$allowed_roles = ['department_head', 'dean', 'principal', 'hr_admin'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

// Get pending leave applications for approval
$sql = "SELECT la.*, 
               lt.name as leave_type_name,
               u.first_name, 
               u.last_name, 
               u.email,
               d.name as department_name,
               lap.id as approval_id,
               lap.approver_level
        FROM leave_applications la 
        JOIN leave_types lt ON la.leave_type_id = lt.id 
        JOIN users u ON la.user_id = u.id 
        JOIN departments d ON u.department_id = d.id
        JOIN leave_approvals lap ON la.id = lap.leave_application_id
        WHERE lap.approver_id = :user_id 
        AND lap.status = 'pending'
        AND la.status = 'pending'
        ORDER BY la.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$pending_approvals = $stmt->fetchAll();

// Get recently approved/rejected applications
$recent_sql = "SELECT la.*, 
                lt.name as leave_type_name,
                u.first_name, 
                u.last_name,
                d.name as department_name,
                lap.status as approval_status,
                lap.approver_level,
                lap.comments,
                lap.updated_at as approval_date
         FROM leave_approvals lap
         JOIN leave_applications la ON lap.leave_application_id = la.id
         JOIN leave_types lt ON la.leave_type_id = lt.id 
         JOIN users u ON la.user_id = u.id 
         JOIN departments d ON u.department_id = d.id
         WHERE lap.approver_id = :user_id 
         AND lap.status IN ('approved', 'rejected')
         ORDER BY lap.updated_at DESC
         LIMIT 10";
$recent_stmt = $conn->prepare($recent_sql);
$recent_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$recent_stmt->execute();
$recent_actions = $recent_stmt->fetchAll();

// Process approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && isset($_POST['approval_id'])) {
        $action = $_POST['action'];
        $approval_id = $_POST['approval_id'];
        $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
        
        // Verify the approval record belongs to this user
        $check_sql = "SELECT lap.*, la.user_id as applicant_id, la.leave_type_id, la.days, la.status as application_status
                     FROM leave_approvals lap 
                     JOIN leave_applications la ON lap.leave_application_id = la.id
                     WHERE lap.id = :approval_id AND lap.approver_id = :user_id AND lap.status = 'pending'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':approval_id', $approval_id, PDO::PARAM_INT);
        $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $approval_data = $check_stmt->fetch();
            $leave_application_id = $approval_data['leave_application_id'];
            $applicant_id = $approval_data['applicant_id'];
            $leave_type_id = $approval_data['leave_type_id'];
            $days = $approval_data['days'];
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Update approval status
                $update_approval_sql = "UPDATE leave_approvals 
                                      SET status = :status, comments = :comments, updated_at = NOW() 
                                      WHERE id = :approval_id";
                $update_approval_stmt = $conn->prepare($update_approval_sql);
                $update_approval_stmt->bindParam(':status', $action, PDO::PARAM_STR);
                $update_approval_stmt->bindParam(':comments', $comments, PDO::PARAM_STR);
                $update_approval_stmt->bindParam(':approval_id', $approval_id, PDO::PARAM_INT);
                $update_approval_stmt->execute();
                
                // If rejected, update leave application status
                if ($action == 'rejected') {
                    $update_application_sql = "UPDATE leave_applications 
                                            SET status = 'rejected', rejection_reason = :rejection_reason, updated_at = NOW() 
                                            WHERE id = :leave_application_id";
                    $update_application_stmt = $conn->prepare($update_application_sql);
                    $update_application_stmt->bindParam(':rejection_reason', $comments, PDO::PARAM_STR);
                    $update_application_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $update_application_stmt->execute();
                    
                    // Delete any remaining pending approvals
                    $delete_approvals_sql = "DELETE FROM leave_approvals 
                                           WHERE leave_application_id = :leave_application_id AND status = 'pending'";
                    $delete_approvals_stmt = $conn->prepare($delete_approvals_sql);
                    $delete_approvals_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $delete_approvals_stmt->execute();
                    
                    // Create notification for applicant
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                        VALUES (:user_id, 'Leave Application Rejected', 'Your leave application has been rejected.', 'leave_application', :related_id)";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $applicant_id, PDO::PARAM_INT);
                    $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                    $notification_stmt->execute();
                    
                    // Send email notification to applicant
                    $applicant_info_sql = "SELECT email, first_name, last_name FROM users WHERE id = :id";
                    $applicant_info_stmt = $conn->prepare($applicant_info_sql);
                    $applicant_info_stmt->bindParam(':id', $applicant_id, PDO::PARAM_INT);
                    $applicant_info_stmt->execute();
                    $applicant_info = $applicant_info_stmt->fetch();
                    
                    $leave_info_sql = "SELECT la.start_date, la.end_date, lt.name as leave_type_name 
                                     FROM leave_applications la 
                                     JOIN leave_types lt ON la.leave_type_id = lt.id 
                                     WHERE la.id = :id";
                    $leave_info_stmt = $conn->prepare($leave_info_sql);
                    $leave_info_stmt->bindParam(':id', $leave_application_id, PDO::PARAM_INT);
                    $leave_info_stmt->execute();
                    $leave_info = $leave_info_stmt->fetch();
                    
                    if ($applicant_info && $leave_info) {
                        $emailNotification->sendLeaveStatusNotification(
                            $applicant_info['email'],
                            $applicant_info['first_name'] . ' ' . $applicant_info['last_name'],
                            'rejected',
                            $leave_info['leave_type_name'],
                            $leave_info['start_date'],
                            $leave_info['end_date'],
                            $comments
                        );
                    }
                } 
                // If approved, check if this was the final approval
                else if ($action == 'approved') {
                    // Check if there are any more pending approvals
                    $pending_check_sql = "SELECT COUNT(*) as pending_count 
                                        FROM leave_approvals 
                                        WHERE leave_application_id = :leave_application_id AND status = 'pending'";
                    $pending_check_stmt = $conn->prepare($pending_check_sql);
                    $pending_check_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $pending_check_stmt->execute();
                    $pending_count = $pending_check_stmt->fetch()['pending_count'];
                    
                    // If no more pending approvals, update leave application status
                    if ($pending_count == 0) {
                        $update_application_sql = "UPDATE leave_applications 
                                                SET status = 'approved', updated_at = NOW() 
                                                WHERE id = :leave_application_id";
                        $update_application_stmt = $conn->prepare($update_application_sql);
                        $update_application_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                        $update_application_stmt->execute();
                        
                        // Update leave balance
                        $current_year = date('Y');
                        $update_balance_sql = "UPDATE leave_balances 
                                             SET balance = balance - :days, used = used + :days, updated_at = NOW() 
                                             WHERE user_id = :user_id AND leave_type_id = :leave_type_id AND year = :year";
                        $update_balance_stmt = $conn->prepare($update_balance_sql);
                        $update_balance_stmt->bindParam(':days', $days, PDO::PARAM_STR);
                        $update_balance_stmt->bindParam(':user_id', $applicant_id, PDO::PARAM_INT);
                        $update_balance_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
                        $update_balance_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
                        $update_balance_stmt->execute();
                        
                        // Create notification for applicant
                        $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                            VALUES (:user_id, 'Leave Application Approved', 'Your leave application has been approved.', 'leave_application', :related_id)";
                        $notification_stmt = $conn->prepare($notification_sql);
                        $notification_stmt->bindParam(':user_id', $applicant_id, PDO::PARAM_INT);
                        $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                        $notification_stmt->execute();
                        
                        // Send email notification to applicant
                        $applicant_info_sql = "SELECT email, first_name, last_name FROM users WHERE id = :id";
                        $applicant_info_stmt = $conn->prepare($applicant_info_sql);
                        $applicant_info_stmt->bindParam(':id', $applicant_id, PDO::PARAM_INT);
                        $applicant_info_stmt->execute();
                        $applicant_info = $applicant_info_stmt->fetch();
                        
                        $leave_info_sql = "SELECT la.start_date, la.end_date, lt.name as leave_type_name 
                                         FROM leave_applications la 
                                         JOIN leave_types lt ON la.leave_type_id = lt.id 
                                         WHERE la.id = :id";
                        $leave_info_stmt = $conn->prepare($leave_info_sql);
                        $leave_info_stmt->bindParam(':id', $leave_application_id, PDO::PARAM_INT);
                        $leave_info_stmt->execute();
                        $leave_info = $leave_info_stmt->fetch();
                        
                        if ($applicant_info && $leave_info) {
                            $emailNotification->sendLeaveStatusNotification(
                                $applicant_info['email'],
                                $applicant_info['first_name'] . ' ' . $applicant_info['last_name'],
                                'approved',
                                $leave_info['leave_type_name'],
                                $leave_info['start_date'],
                                $leave_info['end_date'],
                                $comments
                            );
                        }
                    } else {
                        // Get next approver
                        $next_approver_sql = "SELECT lap.approver_id, u.email, u.first_name, u.last_name 
                                            FROM leave_approvals lap 
                                            JOIN users u ON lap.approver_id = u.id 
                                            WHERE lap.leave_application_id = :leave_application_id AND lap.status = 'pending' 
                                            ORDER BY lap.created_at ASC 
                                            LIMIT 1";
                        $next_approver_stmt = $conn->prepare($next_approver_sql);
                        $next_approver_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                        $next_approver_stmt->execute();
                        
                        if ($next_approver_stmt->rowCount() > 0) {
                            $next_approver = $next_approver_stmt->fetch();
                            
                            // Create notification for next approver
                            $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                                VALUES (:user_id, 'Leave Approval Required', 'A leave application requires your approval.', 'leave_application', :related_id)";
                            $notification_stmt = $conn->prepare($notification_sql);
                            $notification_stmt->bindParam(':user_id', $next_approver['approver_id'], PDO::PARAM_INT);
                            $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                            $notification_stmt->execute();
                        }
                        
                        // Create notification for applicant about partial approval
                        $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                            VALUES (:user_id, 'Leave Application Update', 'Your leave application has been approved by one level and is awaiting further approval.', 'leave_application', :related_id)";
                        $notification_stmt = $conn->prepare($notification_sql);
                        $notification_stmt->bindParam(':user_id', $applicant_id, PDO::PARAM_INT);
                        $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                        $notification_stmt->execute();
                    }
                }
                
                // Log the action
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                          VALUES (:user_id, :action, 'leave_approvals', :entity_id, :details, :ip_address, :user_agent)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $log_stmt->bindParam(':entity_id', $approval_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':details', $comments, PDO::PARAM_STR);
                $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
                $log_stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Leave application " . ($action == 'approved' ? 'approved' : 'rejected') . " successfully.";
                $_SESSION['alert_type'] = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                
                $_SESSION['alert'] = "Error: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Invalid approval request.";
            $_SESSION['alert_type'] = "danger";
        }
        
        header('Location: ./modules/leave_approvals.php');
        exit;
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-check-circle me-2"></i>Leave Approvals</h2>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']); unset($_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (count($pending_approvals) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-double fa-3x text-muted mb-3"></i>
                            <h5>No Pending Approvals</h5>
                            <p class="text-muted">You don't have any leave applications waiting for your approval.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_approvals as $approval): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($approval['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($approval['leave_type_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $start_date = date('M d, Y', strtotime($approval['start_date']));
                                                    $end_date = date('M d, Y', strtotime($approval['end_date']));
                                                    echo $start_date;
                                                    if ($start_date != $end_date) {
                                                        echo ' to ' . $end_date;
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo $approval['days']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($approval['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $approval['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $approval['id']; ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $approval['id']; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- View Modal -->
                                                <div class="modal fade" id="viewModal<?php echo $approval['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $approval['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="viewModalLabel<?php echo $approval['id']; ?>">Leave Application Details</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <h6>Employee Information</h6>
                                                                        <table class="table table-sm">
                                                                            <tr>
                                                                                <th>Name:</th>
                                                                                <td><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Email:</th>
                                                                                <td><?php echo htmlspecialchars($approval['email']); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Department:</th>
                                                                                <td><?php echo htmlspecialchars($approval['department_name']); ?></td>
                                                                            </tr>
                                                                        </table>
                                                                        
                                                                        <h6 class="mt-3">Leave Information</h6>
                                                                        <table class="table table-sm">
                                                                            <tr>
                                                                                <th>Leave Type:</th>
                                                                                <td>
                                                                                    <span class="badge bg-primary">
                                                                                        <?php echo htmlspecialchars($approval['leave_type_name']); ?>
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Start Date:</th>
                                                                                <td><?php echo date('F d, Y', strtotime($approval['start_date'])); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>End Date:</th>
                                                                                <td><?php echo date('F d, Y', strtotime($approval['end_date'])); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Days:</th>
                                                                                <td><?php echo $approval['days']; ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Applied On:</th>
                                                                                <td><?php echo date('F d, Y H:i', strtotime($approval['created_at'])); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Your Role:</th>
                                                                                <td><?php echo ucwords(str_replace('_', ' ', $approval['approver_level'])); ?></td>
                                                                            </tr>
                                                                        </table>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <h6>Reason for Leave</h6>
                                                                        <p><?php echo nl2br(htmlspecialchars($approval['reason'])); ?></p>
                                                                        
                                                                        <?php if ($approval['attachment']): ?>
                                                                            <h6 class="mt-3">Attachment</h6>
                                                                            <div class="document-preview">
                                                                                <?php 
                                                                                    $file_ext = pathinfo($approval['attachment'], PATHINFO_EXTENSION);
                                                                                    $file_path = '/uploads/' . $approval['attachment'];
                                                                                    
                                                                                    if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])): 
                                                                                ?>
                                                                                    <img src="<?php echo $file_path; ?>" class="img-fluid img-thumbnail" alt="Attachment">
                                                                                <?php elseif (strtolower($file_ext) == 'pdf'): ?>
                                                                                    <div class="pdf-preview">
                                                                                        <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                                                                    </div>
                                                                                <?php else: ?>
                                                                                    <div class="doc-preview">
                                                                                        <i class="fas fa-file-alt fa-3x text-primary"></i>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <a href="<?php echo $file_path; ?>" class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                                                                                    <i class="fas fa-download me-1"></i> Download Attachment
                                                                                </a>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        
                                                                        <!-- Leave Balance -->
                                                                        <?php
                                                                            $balance_sql = "SELECT balance, used, max_days 
                                                                                         FROM leave_balances lb
                                                                                         JOIN leave_types lt ON lb.leave_type_id = lt.id
                                                                                         WHERE lb.user_id = :user_id 
                                                                                         AND lb.leave_type_id = :leave_type_id 
                                                                                         AND lb.year = :year";
                                                                            $balance_stmt = $conn->prepare($balance_sql);
                                                                            $balance_stmt->bindParam(':user_id', $approval['user_id'], PDO::PARAM_INT);
                                                                            $balance_stmt->bindParam(':leave_type_id', $approval['leave_type_id'], PDO::PARAM_INT);
                                                                            $balance_stmt->bindParam(':year', date('Y'), PDO::PARAM_STR);
                                                                            $balance_stmt->execute();
                                                                            
                                                                            if ($balance_stmt->rowCount() > 0) {
                                                                                $balance_data = $balance_stmt->fetch();
                                                                                $balance = $balance_data['balance'];
                                                                                $used = $balance_data['used'];
                                                                                $max_days = $balance_data['max_days'];
                                                                                $percentage = ($used / $max_days) * 100;
                                                                        ?>
                                                                            <h6 class="mt-3">Leave Balance</h6>
                                                                            <div class="card">
                                                                                <div class="card-body">
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>Available: <?php echo $balance; ?> days</span>
                                                                                        <span>Used: <?php echo $used; ?> days</span>
                                                                                    </div>
                                                                                    <div class="progress mt-2">
                                                                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                                                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                                            <?php echo $percentage; ?>%
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="text-muted mt-1 small">
                                                                                        <i class="fas fa-info-circle me-1"></i>
                                                                                        Total annual allowance: <?php echo $max_days; ?> days
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $approval['id']; ?>" data-bs-dismiss="modal">
                                                                    <i class="fas fa-check me-1"></i> Approve
                                                                </button>
                                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $approval['id']; ?>" data-bs-dismiss="modal">
                                                                    <i class="fas fa-times me-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Approve Modal -->
                                                <div class="modal fade" id="approveModal<?php echo $approval['id']; ?>" tabindex="-1" aria-labelledby="approveModalLabel<?php echo $approval['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="approveModalLabel<?php echo $approval['id']; ?>">Approve Leave Application</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to approve this leave application for <strong><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></strong>?</p>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="approve-comments<?php echo $approval['id']; ?>" class="form-label">Comments (Optional)</label>
                                                                        <textarea class="form-control" id="approve-comments<?php echo $approval['id']; ?>" name="comments" rows="3"></textarea>
                                                                    </div>
                                                                    
                                                                    <input type="hidden" name="approval_id" value="<?php echo $approval['approval_id']; ?>">
                                                                    <input type="hidden" name="action" value="approved">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-success">
                                                                        <i class="fas fa-check me-1"></i> Approve
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Reject Modal -->
                                                <div class="modal fade" id="rejectModal<?php echo $approval['id']; ?>" tabindex="-1" aria-labelledby="rejectModalLabel<?php echo $approval['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="rejectModalLabel<?php echo $approval['id']; ?>">Reject Leave Application</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to reject this leave application for <strong><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></strong>?</p>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="reject-comments<?php echo $approval['id']; ?>" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                                                                        <textarea class="form-control" id="reject-comments<?php echo $approval['id']; ?>" name="comments" rows="3" required></textarea>
                                                                    </div>
                                                                    
                                                                    <input type="hidden" name="approval_id" value="<?php echo $approval['approval_id']; ?>">
                                                                    <input type="hidden" name="action" value="rejected">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">
                                                                        <i class="fas fa-times me-1"></i> Reject
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Actions</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent_actions) == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5>No Recent Actions</h5>
                            <p class="text-muted">You haven't approved or rejected any leave applications recently.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Action</th>
                                        <th>Date</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_actions as $action): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($action['first_name'] . ' ' . $action['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($action['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($action['leave_type_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $start_date = date('M d, Y', strtotime($action['start_date']));
                                                    $end_date = date('M d, Y', strtotime($action['end_date']));
                                                    echo $start_date;
                                                    if ($start_date != $end_date) {
                                                        echo ' to ' . $end_date;
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($action['approval_status'] == 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($action['approval_date'])); ?></td>
                                            <td>
                                                <?php if (!empty($action['comments'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($action['comments']); ?>">
                                                        <i class="fas fa-comment-alt"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>