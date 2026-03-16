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

// Check if user role is allowed to apply for leave
if ($role == 'hr_admin') {
    $_SESSION['alert'] = "HR Admins cannot apply for leave through this system.";
    $_SESSION['alert_type'] = "warning";
    header('Location: index.php');
    exit;
}

// Get available leave types for the user's role
$leave_types_sql = "SELECT id, name, description, max_days, requires_attachment 
                   FROM leave_types 
                   WHERE FIND_IN_SET(:role, applicable_to) > 0 
                   ORDER BY name ASC";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->bindParam(':role', $role, PDO::PARAM_STR);
$leave_types_stmt->execute();
$leave_types = $leave_types_stmt->fetchAll();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $leave_type_id = trim($_POST['leave_type_id']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $days = trim($_POST['days']);
    $reason = trim($_POST['reason']);
    $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
    $half_day_period = $is_half_day ? trim($_POST['half_day_period']) : null;
    $mode_of_transport = !empty($_POST['mode_of_transport']) ? trim($_POST['mode_of_transport']) : null;
    $work_adjustment = !empty($_POST['work_adjustment']) ? trim($_POST['work_adjustment']) : null;
    
    // Validate leave type
    $valid_leave_type = false;
    $requires_attachment = false;
    foreach ($leave_types as $leave_type) {
        if ($leave_type['id'] == $leave_type_id) {
            $valid_leave_type = true;
            $requires_attachment = $leave_type['requires_attachment'];
            break;
        }
    }
    
    if (!$valid_leave_type) {
        $_SESSION['alert'] = "Invalid leave type selected.";
        $_SESSION['alert_type'] = "danger";
        header('Location: apply_leave.php');
        exit;
    }
    
    // Validate dates
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date);
    $current_timestamp = strtotime(date('Y-m-d'));
    
    if ($start_timestamp < $current_timestamp) {
        $_SESSION['alert'] = "Start date cannot be in the past.";
        $_SESSION['alert_type'] = "danger";
        header('Location: apply_leave.php');
        exit;
    }
    
    if ($end_timestamp < $start_timestamp) {
        $_SESSION['alert'] = "End date cannot be before start date.";
        $_SESSION['alert_type'] = "danger";
        header('Location: apply_leave.php');
        exit;
    }
    
    // Validate days
    if (!is_numeric($days) || $days <= 0) {
        $_SESSION['alert'] = "Invalid number of days.";
        $_SESSION['alert_type'] = "danger";
        header('Location: apply_leave.php');
        exit;
    }
    
    // Validate half day settings
    if ($is_half_day) {
        if ($days != 0.5) {
            $_SESSION['alert'] = "Half day leave must be exactly 0.5 days.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
        if (!in_array($half_day_period, ['first_half', 'second_half'])) {
            $_SESSION['alert'] = "Please select first half or second half for half day leave.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
        if ($start_date != $end_date) {
            $_SESSION['alert'] = "Half day leave must have same start and end date.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
    }
    
    // Check leave balance
    $current_year = date('Y');
    $balance_sql = "SELECT (total_days - used_days) as balance FROM leave_balances 
                   WHERE user_id = :user_id AND leave_type_id = :leave_type_id AND year = :year";
    $balance_stmt = $conn->prepare($balance_sql);
    $balance_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $balance_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
    $balance_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
    $balance_stmt->execute();
    
    if ($balance_stmt->rowCount() > 0) {
        $balance = $balance_stmt->fetch()['balance'];
        if ($days > $balance) {
            $_SESSION['alert'] = "Insufficient leave balance. You have {$balance} days available.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
    } else {
        // If no balance record exists, create one
        $leave_type_sql = "SELECT max_days FROM leave_types WHERE id = :id";
        $leave_type_stmt = $conn->prepare($leave_type_sql);
        $leave_type_stmt->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
        $leave_type_stmt->execute();
        $max_days = $leave_type_stmt->fetch()['max_days'];
        
        $insert_balance_sql = "INSERT INTO leave_balances (user_id, leave_type_id, total_days, used_days, year) 
                              VALUES (:user_id, :leave_type_id, :balance, 0, :year)";
        $insert_balance_stmt = $conn->prepare($insert_balance_sql);
        $insert_balance_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insert_balance_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $insert_balance_stmt->bindParam(':balance', $max_days, PDO::PARAM_STR);
        $insert_balance_stmt->bindParam(':year', $current_year, PDO::PARAM_STR);
        $insert_balance_stmt->execute();
        
        if ($days > $max_days) {
            $_SESSION['alert'] = "Insufficient leave balance. You have {$max_days} days available.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
    }
    
    // Handle file upload if required
    $attachment = null;
    if ($requires_attachment) {
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] == UPLOAD_ERR_NO_FILE) {
            $_SESSION['alert'] = "Attachment is required for this leave type.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
        
        // Get allowed attachment types from settings
        $settings_sql = "SELECT setting_value FROM settings WHERE setting_key = 'allowed_attachment_types'";
        $settings_stmt = $conn->prepare($settings_sql);
        $settings_stmt->execute();
        $allowed_types = explode(',', $settings_stmt->fetch()['setting_value']);
        
        // Get max attachment size from settings (in MB)
        $settings_sql = "SELECT setting_value FROM settings WHERE setting_key = 'max_attachment_size'";
        $settings_stmt = $conn->prepare($settings_sql);
        $settings_stmt->execute();
        $max_size = (int)$settings_stmt->fetch()['setting_value'] * 1024 * 1024; // Convert to bytes
        
        $file = $_FILES['attachment'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['alert'] = "Invalid file type. Allowed types: " . implode(', ', $allowed_types);
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
        
        // Validate file size
        if ($file_size > $max_size) {
            $_SESSION['alert'] = "File is too large. Maximum size is {$max_size} bytes.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
        
        // Generate unique filename
        $new_file_name = uniqid('leave_') . '.' . $file_ext;
        $upload_dir = '../uploads/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
            $attachment = $new_file_name;
        } else {
            $_SESSION['alert'] = "Failed to upload file.";
            $_SESSION['alert_type'] = "danger";
            header('Location: apply_leave.php');
            exit;
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert leave application
        $insert_sql = "INSERT INTO leave_applications (user_id, leave_type_id, start_date, end_date, days, reason, attachment, is_half_day, half_day_period, mode_of_transport, work_adjustment) 
                      VALUES (:user_id, :leave_type_id, :start_date, :end_date, :days, :reason, :attachment, :is_half_day, :half_day_period, :mode_of_transport, :work_adjustment)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $insert_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
        $insert_stmt->bindParam(':days', $days, PDO::PARAM_STR);
        $insert_stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $insert_stmt->bindParam(':attachment', $attachment, PDO::PARAM_STR);
        $insert_stmt->bindParam(':is_half_day', $is_half_day, PDO::PARAM_INT);
        $insert_stmt->bindParam(':half_day_period', $half_day_period, PDO::PARAM_STR);
        $insert_stmt->bindParam(':mode_of_transport', $mode_of_transport, PDO::PARAM_STR);
        $insert_stmt->bindParam(':work_adjustment', $work_adjustment, PDO::PARAM_STR);
        $insert_stmt->execute();
        
        $leave_application_id = $conn->lastInsertId();
        
        // Get approval chain setting from system configuration
        $approval_chain_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'default_approval_chain'";
        $approval_chain_stmt = $conn->prepare($approval_chain_sql);
        $approval_chain_stmt->execute();
        $approval_chain_result = $approval_chain_stmt->fetch();
        $approval_chain = $approval_chain_result ? $approval_chain_result['setting_value'] : 'hod,director';
        
        // Determine approval workflow based on applicant's role and approval chain setting
        $applicant_role = $_SESSION['role'];
        $applicant_user_id = $_SESSION['user_id'];
        
        if ($applicant_role == 'director') {
            // Director applications always go to admin for approval
            $admin_sql = "SELECT id, email, first_name, last_name FROM users WHERE role IN ('admin', 'hr_admin') AND status = 'active' ORDER BY role = 'admin' DESC LIMIT 1";
            $admin_stmt = $conn->prepare($admin_sql);
            $admin_stmt->execute();
            
            if ($admin_stmt->rowCount() > 0) {
                $admin = $admin_stmt->fetch();
                
                try {
                    // Create approval record for admin
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level) 
                                    VALUES (:leave_application_id, :approver_id, 'admin')";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $admin['id'], PDO::PARAM_INT);
                    $approval_stmt->execute();
                    
                    // Create notification for admin
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                        VALUES (:user_id, 'Director Leave Approval Required', 'A Director leave application requires your approval.', 'leave_application', :related_id)";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $admin['id'], PDO::PARAM_INT);
                    $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                    $notification_stmt->execute();
                    
                    $approver_info = $admin;
                    $approver_type = 'Admin';
                    
                } catch (PDOException $e) {
                    error_log("Error creating director approval record: " . $e->getMessage());
                    $_SESSION['alert'] = "Leave application created but approval workflow setup failed. Please contact administrator.";
                    $_SESSION['alert_type'] = "warning";
                }
            } else {
                error_log("No admin users found for director leave approval");
                $_SESSION['alert'] = "Leave application created but no admin users available for approval. Please contact administrator.";
                $_SESSION['alert_type'] = "warning";
            }
        } elseif ($applicant_role == 'head_of_department') {
            // HOD applications depend on approval chain setting
            if ($approval_chain == 'hod') {
                // If chain is only HOD, then HOD leave goes to Admin
                $admin_sql = "SELECT id, email, first_name, last_name FROM users WHERE role IN ('admin', 'hr_admin') AND status = 'active' ORDER BY role = 'admin' DESC LIMIT 1";
                $admin_stmt = $conn->prepare($admin_sql);
                $admin_stmt->execute();
                
                if ($admin_stmt->rowCount() > 0) {
                    $admin = $admin_stmt->fetch();
                    
                    // Create approval record for admin
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level) 
                                    VALUES (:leave_application_id, :approver_id, 'admin')";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $admin['id'], PDO::PARAM_INT);
                    $approval_stmt->execute();
                    
                    // Create notification for admin
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                        VALUES (:user_id, 'HOD Leave Approval Required', 'A Head of Department leave application requires your approval.', 'leave_application', :related_id)";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $admin['id'], PDO::PARAM_INT);
                    $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                    $notification_stmt->execute();
                    
                    $approver_info = $admin;
                    $approver_type = 'Admin';
                }
            } else {
                // If chain includes Director, HOD leave goes to Director
                $director_sql = "SELECT id, email, first_name, last_name FROM users WHERE role = 'director' AND status = 'active' LIMIT 1";
                $director_stmt = $conn->prepare($director_sql);
                $director_stmt->execute();
                
                if ($director_stmt->rowCount() > 0) {
                    $director = $director_stmt->fetch();
                    
                    // Create approval record for director
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level) 
                                    VALUES (:leave_application_id, :approver_id, 'director')";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $director['id'], PDO::PARAM_INT);
                    $approval_stmt->execute();
                    
                    // Create notification for director
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                        VALUES (:user_id, 'HOD Leave Approval Required', 'A Head of Department leave application requires your approval.', 'leave_application', :related_id)";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $director['id'], PDO::PARAM_INT);
                    $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
                    $notification_stmt->execute();
                    
                    $approver_info = $director;
                    $approver_type = 'Director';
                }
            }
        } else {
            // Regular staff - workflow depends on approval chain setting
            $dept_id = $_SESSION['department_id'];
            $dept_head_sql = "SELECT head_id FROM departments WHERE id = :dept_id";
            $dept_head_stmt = $conn->prepare($dept_head_sql);
            $dept_head_stmt->bindParam(':dept_id', $dept_id, PDO::PARAM_INT);
            $dept_head_stmt->execute();
            $dept_head = $dept_head_stmt->fetch();
            
            if ($dept_head && $dept_head['head_id'] && $dept_head['head_id'] != $applicant_user_id) {
                // Create approval record for department head
                $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level) 
                                VALUES (:leave_application_id, :approver_id, 'head_of_department')";
                $approval_stmt = $conn->prepare($approval_sql);
                $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                $approval_stmt->bindParam(':approver_id', $dept_head['head_id'], PDO::PARAM_INT);
                $approval_stmt->execute();
                
                // Get department head info for notification
                $dept_head_email_sql = "SELECT email, first_name, last_name FROM users WHERE id = :id";
                $dept_head_email_stmt = $conn->prepare($dept_head_email_sql);
                $dept_head_email_stmt->bindParam(':id', $dept_head['head_id'], PDO::PARAM_INT);
                $dept_head_email_stmt->execute();
                $approver_info = $dept_head_email_stmt->fetch();
                $approver_type = 'Head of Department';
            }
        }
        
        // Send notifications and emails if approver info is available
        if (isset($approver_info) && $approver_info) {
            // Send email notification to approver
            $applicant_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
            $leave_type_name_sql = "SELECT name FROM leave_types WHERE id = :id";
            $leave_type_name_stmt = $conn->prepare($leave_type_name_sql);
            $leave_type_name_stmt->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
            $leave_type_name_stmt->execute();
            $leave_type_name = $leave_type_name_stmt->fetchColumn();
            
            $emailNotification->sendLeaveApplicationNotification(
                $approver_info['email'],
                $applicant_name,
                $leave_type_name,
                $start_date,
                $end_date,
                $leave_application_id
            );
        }
        
        // Log the action
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                  VALUES (:user_id, 'create', 'leave_applications', :entity_id, 'Leave application submitted', :ip_address, :user_agent)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':entity_id', $leave_application_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $log_stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
        $log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['alert'] = "Leave application submitted successfully.";
        $_SESSION['alert_type'] = "success";
        header('Location: my_leaves.php');
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header('Location: apply_leave.php');
        exit;
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-professional">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>Apply for Leave</h4>
                </div>
                <div class="card-body">
                    <?php if(count($leave_types) == 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> No leave types available for your role.
                        </div>
                    <?php else: ?>
                        <?php
                        // Get user details
                        $user_details_sql = "SELECT u.first_name, u.last_name, u.email, u.role, d.name as department_name 
                                            FROM users u 
                                            LEFT JOIN departments d ON u.department_id = d.id 
                                            WHERE u.id = :user_id";
                        $user_details_stmt = $conn->prepare($user_details_sql);
                        $user_details_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $user_details_stmt->execute();
                        $user_details = $user_details_stmt->fetch();
                        
                        // Format role for display
                        $role_display = ucwords(str_replace('_', ' ', $user_details['role']));
                        ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                            <!-- Applicant Information Section -->
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2 mb-3">Applicant Information</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="applicant_name" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="applicant_name" 
                                               value="<?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?>" 
                                               readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="designation" class="form-label">Designation</label>
                                        <input type="text" class="form-control" id="designation" 
                                               value="<?php echo htmlspecialchars($role_display); ?>" 
                                               readonly>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" 
                                               value="<?php echo htmlspecialchars($user_details['department_name'] ?? 'N/A'); ?>" 
                                               readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="application_date" class="form-label">Application Date</label>
                                        <input type="text" class="form-control" id="application_date" 
                                               value="<?php echo date('d/m/Y'); ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Leave Details Section -->
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2 mb-3">Leave Details</h5>
                                
                                <div class="mb-3">
                                    <label for="leave_type_id" class="form-label">Type of Leave Required <span class="text-danger">*</span></label>
                                    <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                        <option value="">Select Leave Type</option>
                                        <?php foreach($leave_types as $leave_type): ?>
                                            <option value="<?php echo $leave_type['id']; ?>" data-requires-attachment="<?php echo $leave_type['requires_attachment']; ?>">
                                                <?php echo htmlspecialchars($leave_type['name']); ?>
                                                <?php if(!empty($leave_type['description'])): ?>
                                                    - <?php echo htmlspecialchars($leave_type['description']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="attachment-note" style="display: none;">
                                        <i class="fas fa-info-circle me-1"></i> This leave type requires supporting documentation.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_half_day" name="is_half_day" value="1">
                                        <label class="form-check-label" for="is_half_day">
                                            Apply for Half Day Leave
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="start_date" class="form-label">From Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="end_date" class="form-label">To Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="days" class="form-label">Number of Days <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="days" name="days" step="0.5" min="0.5" required readonly>
                                    </div>
                                </div>
                                
                                <div class="mb-3" id="half_day_period_field" style="display: none;">
                                    <label class="form-label">Select Half Day Period <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="half_day_period" id="first_half" value="first_half">
                                            <label class="form-check-label" for="first_half">
                                                First Half (Morning)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="half_day_period" id="second_half" value="second_half">
                                            <label class="form-check-label" for="second_half">
                                                Second Half (Afternoon)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-text mb-3">
                                    <i class="fas fa-info-circle me-1"></i> Number of days will be calculated automatically based on your date selection.
                                </div>
                                <div class="alert alert-warning mt-2" id="balance-warning" style="display: none;"></div>
                                <div class="alert alert-info mt-2" id="holiday-note" style="display: none;"></div>
                                <div class="alert alert-warning mt-2" id="academic-event-warning" style="display: none;"></div>
                                
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Reason for Leave <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="reason" name="reason" rows="4" 
                                              placeholder="Please provide detailed reason for your leave application..." required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mode_of_transport" class="form-label">Mode of Transport for Official Work (if any)</label>
                                    <input type="text" class="form-control" id="mode_of_transport" name="mode_of_transport" 
                                           placeholder="e.g., Personal vehicle, Public transport, Flight, etc.">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i> Specify if you'll be traveling for official work during leave period.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="work_adjustment" class="form-label">Work Adjustment During Leave Period (if any)</label>
                                    <textarea class="form-control" id="work_adjustment" name="work_adjustment" rows="3" 
                                              placeholder="Mention any work arrangements, handover details, or coverage plans..."></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i> Describe how your work will be managed during your absence.
                                    </div>
                                </div>
                                
                                <div class="mb-3" id="attachment-field" style="display: none;">
                                    <label for="attachment" class="form-label">Supporting Document <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="attachment" name="attachment" onchange="previewDocument(this)">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i> Allowed file types: pdf, doc, docx, jpg, jpeg, png. Maximum size: 5MB.
                                    </div>
                                    <div id="document-preview" class="mt-3"></div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" id="submit-btn">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Application
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show/hide attachment field based on leave type selection
        document.getElementById('leave_type_id').addEventListener('change', function() {
            var requiresAttachment = this.options[this.selectedIndex].getAttribute('data-requires-attachment');
            var attachmentField = document.getElementById('attachment-field');
            var attachmentNote = document.getElementById('attachment-note');
            var attachmentInput = document.getElementById('attachment');
            
            if (requiresAttachment == 1) {
                attachmentField.style.display = 'block';
                attachmentNote.style.display = 'block';
                attachmentInput.required = true;
            } else {
                attachmentField.style.display = 'none';
                attachmentNote.style.display = 'none';
                attachmentInput.required = false;
            }
        });
        
        // Handle half day checkbox
        var isHalfDayCheckbox = document.getElementById('is_half_day');
        var halfDayPeriodField = document.getElementById('half_day_period_field');
        var startDateInput = document.getElementById('start_date');
        var endDateInput = document.getElementById('end_date');
        var daysInput = document.getElementById('days');
        var firstHalfRadio = document.getElementById('first_half');
        var secondHalfRadio = document.getElementById('second_half');
        
        isHalfDayCheckbox.addEventListener('change', function() {
            if (this.checked) {
                halfDayPeriodField.style.display = 'block';
                firstHalfRadio.required = true;
                secondHalfRadio.required = true;
                
                // Set days to 0.5 and make end date same as start date
                daysInput.value = '0.5';
                if (startDateInput.value) {
                    endDateInput.value = startDateInput.value;
                    endDateInput.disabled = true;
                }
            } else {
                halfDayPeriodField.style.display = 'none';
                firstHalfRadio.required = false;
                secondHalfRadio.required = false;
                firstHalfRadio.checked = false;
                secondHalfRadio.checked = false;
                endDateInput.disabled = false;
                
                // Recalculate days
                calculateDays();
            }
        });
        
        // Calculate days automatically
        function calculateDays() {
            var isHalfDay = isHalfDayCheckbox.checked;
            
            if (isHalfDay) {
                daysInput.value = '0.5';
                return;
            }
            
            var startDate = startDateInput.value;
            var endDate = endDateInput.value;
            
            if (startDate && endDate) {
                var start = new Date(startDate);
                var end = new Date(endDate);
                
                if (end >= start) {
                    var timeDiff = end.getTime() - start.getTime();
                    var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                    daysInput.value = daysDiff;
                } else {
                    daysInput.value = '';
                }
            } else {
                daysInput.value = '';
            }
        }
        
        // Bind date change events
        startDateInput.addEventListener('change', function() {
            if (isHalfDayCheckbox.checked) {
                endDateInput.value = this.value;
                daysInput.value = '0.5';
            } else {
                calculateDays();
                endDateInput.setAttribute('min', this.value);
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (!isHalfDayCheckbox.checked) {
                calculateDays();
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>