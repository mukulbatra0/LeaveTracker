<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get leave applications for the user
$sql = "SELECT la.*, lt.name as leave_type_name 
        FROM leave_applications la 
        JOIN leave_types lt ON la.leave_type_id = lt.id 
        WHERE la.user_id = :user_id 
        ORDER BY la.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$leave_applications = $stmt->fetchAll();

// Function to get approval status
function getApprovalStatus($conn, $leave_application_id) {
    $sql = "SELECT la.status as application_status, 
                  COUNT(lap.id) as total_approvers,
                  SUM(CASE WHEN lap.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                  SUM(CASE WHEN lap.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
           FROM leave_applications la
           LEFT JOIN leave_approvals lap ON la.id = lap.leave_application_id
           WHERE la.id = :leave_application_id
           GROUP BY la.id, la.status";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get current approver
function getCurrentApprover($conn, $leave_application_id) {
    $sql = "SELECT lap.approver_level, u.first_name, u.last_name, u.email
           FROM leave_approvals lap
           JOIN users u ON lap.approver_id = u.id
           WHERE lap.leave_application_id = :leave_application_id
           AND lap.status = 'pending'
           ORDER BY lap.created_at ASC
           LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Process cancel leave application
if (isset($_POST['cancel_leave']) && isset($_POST['leave_id'])) {
    $leave_id = $_POST['leave_id'];
    
    // Check if the leave application belongs to the user
    $check_sql = "SELECT * FROM leave_applications WHERE id = :id AND user_id = :user_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $leave_data = $check_stmt->fetch();
        
        // Only allow cancellation if status is pending
        if ($leave_data['status'] == 'pending') {
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Update leave application status
                $update_sql = "UPDATE leave_applications SET status = 'cancelled', updated_at = NOW() WHERE id = :id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Delete pending approvals
                $delete_approvals_sql = "DELETE FROM leave_approvals WHERE leave_application_id = :leave_application_id AND status = 'pending'";
                $delete_approvals_stmt = $conn->prepare($delete_approvals_sql);
                $delete_approvals_stmt->bindParam(':leave_application_id', $leave_id, PDO::PARAM_INT);
                $delete_approvals_stmt->execute();
                
                // Log the action
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                          VALUES (:user_id, 'cancel', 'leave_applications', :entity_id, 'Leave application cancelled', :ip_address, :user_agent)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':entity_id', $leave_id, PDO::PARAM_INT);
                $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
                $log_stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['alert'] = "Leave application cancelled successfully.";
                $_SESSION['alert_type'] = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                
                $_SESSION['alert'] = "Error: " . $e->getMessage();
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['alert'] = "Only pending leave applications can be cancelled.";
            $_SESSION['alert_type'] = "warning";
        }
    } else {
        $_SESSION['alert'] = "Invalid leave application.";
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: ../modules/my_leaves.php");
    exit;
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-calendar-check me-2"></i>My Leave Applications</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="../modules/apply_leave.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Apply for Leave
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']); unset($_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <?php if (count($leave_applications) == 0): ?>
        <div class="card shadow-professional">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                <h4>No Leave Applications Found</h4>
                <p class="text-muted">You haven't applied for any leave yet.</p>
                <a href="../modules/apply_leave.php" class="btn btn-primary mt-2">
                    <i class="fas fa-plus me-1"></i> Apply for Leave
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-professional">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th>Days</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_applications as $leave): ?>
                                <?php 
                                    $approval_status = getApprovalStatus($conn, $leave['id']);
                                    $current_approver = getCurrentApprover($conn, $leave['id']);
                                    
                                    // Determine status badge class
                                    $status_class = '';
                                    switch ($leave['status']) {
                                        case 'pending':
                                            $status_class = 'bg-warning';
                                            break;
                                        case 'approved':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'rejected':
                                            $status_class = 'bg-danger';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'bg-secondary';
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $start_date = date('M d, Y', strtotime($leave['start_date']));
                                            $end_date = date('M d, Y', strtotime($leave['end_date']));
                                            echo $start_date;
                                            if ($start_date != $end_date) {
                                                echo ' to ' . $end_date;
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo $leave['days']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($leave['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#leaveDetailsModal<?php echo $leave['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($leave['status'] == 'pending'): ?>
                                                <form method="post" onsubmit="return confirm('Are you sure you want to cancel this leave application?');">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" name="cancel_leave" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Leave Details Modal -->
                                        <div class="modal fade" id="leaveDetailsModal<?php echo $leave['id']; ?>" tabindex="-1" aria-labelledby="leaveDetailsModalLabel<?php echo $leave['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="leaveDetailsModalLabel<?php echo $leave['id']; ?>">
                                                            Leave Application Details
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Leave Information</h6>
                                                                <table class="table table-sm">
                                                                    <tr>
                                                                        <th>Leave Type:</th>
                                                                        <td>
                                                                            <span class="badge bg-secondary">
                                                                                <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Start Date:</th>
                                                                        <td><?php echo date('F d, Y', strtotime($leave['start_date'])); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>End Date:</th>
                                                                        <td><?php echo date('F d, Y', strtotime($leave['end_date'])); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Days:</th>
                                                                        <td><?php echo $leave['days']; ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Status:</th>
                                                                        <td>
                                                                            <span class="badge <?php echo $status_class; ?>">
                                                                                <?php echo ucfirst($leave['status']); ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Applied On:</th>
                                                                        <td><?php echo date('F d, Y H:i', strtotime($leave['created_at'])); ?></td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Approval Information</h6>
                                                                <?php if ($leave['status'] == 'pending' && $current_approver): ?>
                                                                    <div class="alert alert-info">
                                                                        <small>
                                                                            <i class="fas fa-info-circle me-1"></i>
                                                                            Currently awaiting approval from: 
                                                                            <strong><?php echo htmlspecialchars($current_approver['first_name'] . ' ' . $current_approver['last_name']); ?></strong>
                                                                            (<?php echo ucwords(str_replace('_', ' ', $current_approver['approver_level'])); ?>)
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($leave['status'] == 'rejected'): ?>
                                                                    <div class="alert alert-danger">
                                                                        <small>
                                                                            <i class="fas fa-times-circle me-1"></i>
                                                                            Your leave application was rejected.
                                                                            <?php if (!empty($leave['rejection_reason'])): ?>
                                                                                <br>Reason: <?php echo htmlspecialchars($leave['rejection_reason']); ?>
                                                                            <?php endif; ?>
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($leave['status'] == 'approved'): ?>
                                                                    <div class="alert alert-success">
                                                                        <small>
                                                                            <i class="fas fa-check-circle me-1"></i>
                                                                            Your leave application has been fully approved.
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($leave['status'] == 'cancelled'): ?>
                                                                    <div class="alert alert-secondary">
                                                                        <small>
                                                                            <i class="fas fa-ban me-1"></i>
                                                                            This leave application was cancelled.
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <h6 class="mt-3">Reason for Leave</h6>
                                                                <p><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></p>
                                                                
                                                                <?php if ($leave['attachment']): ?>
                                                                    <h6 class="mt-3">Attachment</h6>
                                                                    <div class="document-preview">
                                                                        <?php 
                                                                            $file_ext = pathinfo($leave['attachment'], PATHINFO_EXTENSION);
                                                                            $file_path = '/ELMS/uploads/' . $leave['attachment'];
                                                                            
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
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>