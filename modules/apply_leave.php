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

// Check if user role is allowed to apply for leave
if ($role == 'hr_admin') {
    $_SESSION['alert'] = "HR Admins cannot apply for leave through this system.";
    $_SESSION['alert_type'] = "warning";
    header('Location: ../index.php');
    exit;
}

// Get user details for policy-based filtering and designation
$user_details_sql = "SELECT staff_type, gender, employment_type, designation FROM users WHERE id = :user_id";
$user_details_stmt = $conn->prepare($user_details_sql);
$user_details_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$user_details_stmt->execute();
$user_data = $user_details_stmt->fetch();

$user_staff_type = $user_data['staff_type'] ?? 'teaching';
$user_gender = $user_data['gender'] ?? 'male';
$user_employment_type = $user_data['employment_type'] ?? 'full_time';
$user_designation = $user_data['designation'] ?? '';

// Get available leave types based on policy rules that match user's attributes
$leave_types_sql = "SELECT DISTINCT lt.id, lt.name, lt.description, lt.default_days as max_days, lt.requires_attachment 
                   FROM leave_types lt
                   LEFT JOIN leave_policy_rules lpr ON lt.id = lpr.leave_type_id 
                   WHERE lt.is_active = 1
                   AND (
                       lpr.id IS NULL 
                       OR (
                           lpr.is_active = 1
                           AND (lpr.staff_type = :staff_type OR lpr.staff_type = 'all')
                           AND (lpr.gender = :gender OR lpr.gender = 'all')
                           AND (lpr.employment_type = :employment_type OR lpr.employment_type = 'all')
                       )
                   )
                   ORDER BY lt.name ASC";
$leave_types_stmt = $conn->prepare($leave_types_sql);
$leave_types_stmt->bindParam(':staff_type', $user_staff_type, PDO::PARAM_STR);
$leave_types_stmt->bindParam(':gender', $user_gender, PDO::PARAM_STR);
$leave_types_stmt->bindParam(':employment_type', $user_employment_type, PDO::PARAM_STR);
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
    $designation = !empty($_POST['designation']) ? trim($_POST['designation']) : null;
    $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
    $half_day_period = $is_half_day ? trim($_POST['half_day_period']) : null;
    $mode_of_transport = !empty($_POST['mode_of_transport']) ? trim($_POST['mode_of_transport']) : null;
    $work_adjustment = !empty($_POST['work_adjustment']) ? trim($_POST['work_adjustment']) : null;
    $visit_address = !empty($_POST['visit_address']) ? trim($_POST['visit_address']) : null;
    $contact_number = !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null;
    
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
    
    // Check for overlapping leave applications
    $overlap_sql = "SELECT la.id, la.start_date, la.end_date, la.status, la.is_half_day, la.half_day_period, lt.name as leave_type_name
                    FROM leave_applications la
                    JOIN leave_types lt ON la.leave_type_id = lt.id
                    WHERE la.user_id = :user_id 
                    AND la.status NOT IN ('rejected', 'cancelled')
                    AND (
                        (la.start_date <= :end_date AND la.end_date >= :start_date)
                    )";
    $overlap_stmt = $conn->prepare($overlap_sql);
    $overlap_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $overlap_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $overlap_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $overlap_stmt->execute();
    
    if ($overlap_stmt->rowCount() > 0) {
        $has_real_overlap = false;
        $overlapping_leave = null;
        
        while ($existing_leave = $overlap_stmt->fetch()) {
            // Special case: Same day with different half-day periods is allowed
            if ($is_half_day && $existing_leave['is_half_day'] && 
                $start_date == $end_date && 
                $existing_leave['start_date'] == $existing_leave['end_date'] &&
                $start_date == $existing_leave['start_date'] &&
                $half_day_period != $existing_leave['half_day_period']) {
                // Different half-day periods on same day - this is allowed
                continue;
            }
            
            // Real overlap found
            $has_real_overlap = true;
            $overlapping_leave = $existing_leave;
            break;
        }
        
        if ($has_real_overlap && $overlapping_leave) {
            $overlap_start = date('d/m/Y', strtotime($overlapping_leave['start_date']));
            $overlap_end = date('d/m/Y', strtotime($overlapping_leave['end_date']));
            $overlap_status = ucfirst($overlapping_leave['status']);
            $overlap_type = $overlapping_leave['leave_type_name'];
            
            $overlap_detail = "";
            if ($overlapping_leave['is_half_day']) {
                $period = $overlapping_leave['half_day_period'] == 'first_half' ? 'First Half' : 'Second Half';
                $overlap_detail = " ({$period})";
            }
            
            $_SESSION['alert'] = "You already have a {$overlap_status} leave application ({$overlap_type}{$overlap_detail}) from {$overlap_start} to {$overlap_end} that overlaps with the selected dates. Please choose different dates or cancel the existing application first.";
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
        // If no balance record exists, create one based on policy rules
        // Get user details first
        $user_sql = "SELECT staff_type, gender, employment_type FROM users WHERE id = :uid";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->execute([':uid' => $user_id]);
        $user_info = $user_stmt->fetch();
        
        $u_staff = $user_info['staff_type'] ?? 'teaching';
        $u_gender = $user_info['gender'] ?? 'male';
        $u_emp = $user_info['employment_type'] ?? 'full_time';
        
        // Find matching policy
        $policy_sql = "SELECT allocated_days FROM leave_policy_rules 
                      WHERE leave_type_id = :ltid AND is_active = 1
                      AND (staff_type = :staff_type OR staff_type = 'all')
                      AND (gender = :gender OR gender = 'all')
                      AND (employment_type = :employment_type OR employment_type = 'all')
                      ORDER BY 
                        CASE WHEN staff_type != 'all' THEN 0 ELSE 1 END,
                        CASE WHEN gender != 'all' THEN 0 ELSE 1 END,
                        CASE WHEN employment_type != 'all' THEN 0 ELSE 1 END
                      LIMIT 1";
        $policy_stmt = $conn->prepare($policy_sql);
        $policy_stmt->execute([
            ':ltid' => $leave_type_id,
            ':staff_type' => $u_staff,
            ':gender' => $u_gender,
            ':employment_type' => $u_emp
        ]);
        
        $policy = $policy_stmt->fetch();
        
        if ($policy) {
            $max_days = $policy['allocated_days'];
        } else {
            // Fallback to default_days if no policy found
            $leave_type_sql = "SELECT default_days as max_days FROM leave_types WHERE id = :id";
            $leave_type_stmt = $conn->prepare($leave_type_sql);
            $leave_type_stmt->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
            $leave_type_stmt->execute();
            $max_days = $leave_type_stmt->fetch()['max_days'] ?? 0;
        }
        
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
        $settings_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'allowed_file_types'";
        $settings_stmt = $conn->prepare($settings_sql);
        $settings_stmt->execute();
        $allowed_types = explode(',', $settings_stmt->fetch()['setting_value']);
        
        // Get max attachment size from settings (in MB)
        $settings_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'max_file_size'";
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
        $insert_sql = "INSERT INTO leave_applications (user_id, leave_type_id, start_date, end_date, days, reason, designation, attachment, is_half_day, half_day_period, mode_of_transport, work_adjustment, visit_address, contact_number) 
                      VALUES (:user_id, :leave_type_id, :start_date, :end_date, :days, :reason, :designation, :attachment, :is_half_day, :half_day_period, :mode_of_transport, :work_adjustment, :visit_address, :contact_number)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $insert_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
        $insert_stmt->bindParam(':days', $days, PDO::PARAM_STR);
        $insert_stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $insert_stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $insert_stmt->bindParam(':attachment', $attachment, PDO::PARAM_STR);
        $insert_stmt->bindParam(':is_half_day', $is_half_day, PDO::PARAM_INT);
        $insert_stmt->bindParam(':half_day_period', $half_day_period, PDO::PARAM_STR);
        $insert_stmt->bindParam(':mode_of_transport', $mode_of_transport, PDO::PARAM_STR);
        $insert_stmt->bindParam(':work_adjustment', $work_adjustment, PDO::PARAM_STR);
        $insert_stmt->bindParam(':visit_address', $visit_address, PDO::PARAM_STR);
        $insert_stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
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
                    // Generate email token for one-click action
                    $email_token = bin2hex(random_bytes(32));
                    $token_expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
                    
                    // Create approval record for admin
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, email_token, token_expires_at) 
                                    VALUES (:leave_application_id, :approver_id, 'admin', :token, :expires)";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $admin['id'], PDO::PARAM_INT);
                    $approval_stmt->bindParam(':token', $email_token, PDO::PARAM_STR);
                    $approval_stmt->bindParam(':expires', $token_expires, PDO::PARAM_STR);
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
                    
                    // Generate email token for one-click action
                    $email_token = bin2hex(random_bytes(32));
                    $token_expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
                    
                    // Create approval record for admin
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, email_token, token_expires_at) 
                                    VALUES (:leave_application_id, :approver_id, 'admin', :token, :expires)";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $admin['id'], PDO::PARAM_INT);
                    $approval_stmt->bindParam(':token', $email_token, PDO::PARAM_STR);
                    $approval_stmt->bindParam(':expires', $token_expires, PDO::PARAM_STR);
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
                    
                    // Generate email token for one-click action
                    $email_token = bin2hex(random_bytes(32));
                    $token_expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
                    
                    // Create approval record for director
                    $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, email_token, token_expires_at) 
                                    VALUES (:leave_application_id, :approver_id, 'director', :token, :expires)";
                    $approval_stmt = $conn->prepare($approval_sql);
                    $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                    $approval_stmt->bindParam(':approver_id', $director['id'], PDO::PARAM_INT);
                    $approval_stmt->bindParam(':token', $email_token, PDO::PARAM_STR);
                    $approval_stmt->bindParam(':expires', $token_expires, PDO::PARAM_STR);
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
                // Generate email token for one-click action
                $email_token = bin2hex(random_bytes(32));
                $token_expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                // Create approval record for department head
                $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level, email_token, token_expires_at) 
                                VALUES (:leave_application_id, :approver_id, 'head_of_department', :token, :expires)";
                $approval_stmt = $conn->prepare($approval_sql);
                $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
                $approval_stmt->bindParam(':approver_id', $dept_head['head_id'], PDO::PARAM_INT);
                $approval_stmt->bindParam(':token', $email_token, PDO::PARAM_STR);
                $approval_stmt->bindParam(':expires', $token_expires, PDO::PARAM_STR);
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
                $leave_application_id,
                isset($email_token) ? $email_token : ''
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


<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <!-- Header Section -->
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-file-signature me-2 text-primary"></i>Leave Application</h2>
                        <p class="text-muted mb-0">Complete the form below to submit your leave request</p>
                    </div>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if(count($leave_types) == 0): ?>
                <div class="alert alert-warning shadow-sm">
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
                
                <!-- Progress Indicator -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <div class="progress-container">
                            <div class="progress-step active" data-step="1">
                                <div class="progress-step-icon">
                                    <i class="fas fa-user"></i>
                                    <div class="progress-step-check"><i class="fas fa-check"></i></div>
                                </div>
                                <div class="progress-step-label">Personal Info</div>
                            </div>
                            <div class="progress-line"></div>
                            <div class="progress-step" data-step="2">
                                <div class="progress-step-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div class="progress-step-check"><i class="fas fa-check"></i></div>
                                </div>
                                <div class="progress-step-label">Leave Details</div>
                            </div>
                            <div class="progress-line"></div>
                            <div class="progress-step" data-step="3">
                                <div class="progress-step-icon">
                                    <i class="fas fa-clipboard-list"></i>
                                    <div class="progress-step-check"><i class="fas fa-check"></i></div>
                                </div>
                                <div class="progress-step-label">Additional Info</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-lg-5">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="leaveApplicationForm">
                            
                            <!-- Form Steps -->
                            <div class="form-step active" id="step1">
                                <div class="step-header mb-4">
                                    <h4 class="step-title"><i class="fas fa-user-circle me-2 text-primary"></i>Applicant Information</h4>
                                    <p class="text-muted mb-0">Your personal details are pre-filled from your profile</p>
                                </div>
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="applicant_name" 
                                                   value="<?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?>" 
                                                   readonly>
                                            <label for="applicant_name"><i class="fas fa-user me-2"></i>Full Name</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="designation" name="designation" 
                                                   value="<?php echo htmlspecialchars($user_designation); ?>" 
                                                   readonly required>
                                            <label for="designation"><i class="fas fa-id-badge me-2"></i>Designation <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="department" 
                                                   value="<?php echo htmlspecialchars($user_details['department_name'] ?? 'N/A'); ?>" 
                                                   readonly>
                                            <label for="department"><i class="fas fa-building me-2"></i>Department</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="application_date" 
                                                   value="<?php echo date('d/m/Y'); ?>" 
                                                   readonly>
                                            <label for="application_date"><i class="fas fa-calendar me-2"></i>Application Date</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="step-actions">
                                    <button type="button" class="btn btn-lg btn-primary px-5" onclick="nextStep(2)">
                                        Continue <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                                
                                <!-- Step 2: Leave Details -->
                                <div class="form-step" id="step2">
                                    <div class="step-header mb-4">
                                        <h4 class="step-title"><i class="fas fa-calendar-alt me-2 text-primary"></i>Leave Details</h4>
                                        <p class="text-muted mb-0">Specify your leave type, dates, and reason</p>
                                    </div>
                                    
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <label for="leave_type_id" class="form-label fw-semibold">
                                                <i class="fas fa-list-alt me-2 text-primary"></i>Type of Leave 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select form-select-lg" id="leave_type_id" name="leave_type_id" required>
                                                <option value="">-- Select Leave Type --</option>
                                                <?php foreach($leave_types as $leave_type): ?>
                                                    <option value="<?php echo $leave_type['id']; ?>" 
                                                            data-requires-attachment="<?php echo $leave_type['requires_attachment']; ?>"
                                                            data-description="<?php echo htmlspecialchars($leave_type['description']); ?>">
                                                        <?php echo htmlspecialchars($leave_type['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="alert alert-info mt-2" id="leave-description" style="display: none;"></div>
                                            <div class="alert alert-warning mt-2" id="attachment-note" style="display: none;">
                                                <i class="fas fa-paperclip me-2"></i>Supporting document is required for this leave type.
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="card bg-light border-0">
                                                <div class="card-body">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="is_half_day" name="is_half_day" value="1" style="width: 3em; height: 1.5em;">
                                                        <label class="form-check-label ms-2 fw-semibold" for="is_half_day">
                                                            <i class="fas fa-clock me-2"></i>Apply for Half Day Leave
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="start_date" class="form-label fw-semibold">
                                                <i class="fas fa-calendar-day me-2 text-success"></i>From Date 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control form-control-lg" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="end_date" class="form-label fw-semibold">
                                                <i class="fas fa-calendar-check me-2 text-danger"></i>To Date 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control form-control-lg" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="days" class="form-label fw-semibold">
                                                <i class="fas fa-calculator me-2 text-info"></i>Number of Days 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" class="form-control form-control-lg" id="days" name="days" step="0.5" min="0.5" required readonly>
                                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Auto-calculated</small>
                                        </div>
                                        
                                        <div class="col-12" id="half_day_period_field" style="display: none;">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-clock me-2 text-warning"></i>Select Half Day Period 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="card border-2 half-day-option">
                                                        <div class="card-body text-center">
                                                            <input class="form-check-input d-none" type="radio" name="half_day_period" id="first_half" value="first_half">
                                                            <label class="w-100 cursor-pointer" for="first_half">
                                                                <i class="fas fa-sun fa-3x text-warning mb-3"></i>
                                                                <h5 class="mb-0">First Half</h5>
                                                                <small class="text-muted">Morning Session</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card border-2 half-day-option">
                                                        <div class="card-body text-center">
                                                            <input class="form-check-input d-none" type="radio" name="half_day_period" id="second_half" value="second_half">
                                                            <label class="w-100 cursor-pointer" for="second_half">
                                                                <i class="fas fa-moon fa-3x text-primary mb-3"></i>
                                                                <h5 class="mb-0">Second Half</h5>
                                                                <small class="text-muted">Afternoon Session</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="alert alert-warning mt-2" id="balance-warning" style="display: none;"></div>
                                            <div class="alert alert-info mt-2" id="holiday-note" style="display: none;"></div>
                                            <div class="alert alert-warning mt-2" id="academic-event-warning" style="display: none;"></div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="reason" class="form-label fw-semibold">
                                                <i class="fas fa-comment-dots me-2 text-primary"></i>Purpose / Reason for Leave 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="reason" name="reason" rows="4" 
                                                      placeholder="Please provide a detailed reason for your leave application..." required></textarea>
                                            <div class="form-text">
                                                <span id="reason-count">0</span>/500 characters
                                            </div>
                                        </div>
                                        
                                        <div class="col-12" id="attachment-field" style="display: none;">
                                            <label for="attachment" class="form-label fw-semibold">
                                                <i class="fas fa-paperclip me-2 text-primary"></i>Supporting Document 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="file-upload-wrapper">
                                                <input type="file" class="form-control" id="attachment" name="attachment">
                                                <div class="file-upload-info mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG | Max size: 5MB
                                                    </small>
                                                </div>
                                            </div>
                                            <div id="document-preview" class="mt-3"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="step-actions">
                                        <button type="button" class="btn btn-lg btn-outline-secondary px-4" onclick="prevStep(1)">
                                            <i class="fas fa-arrow-left me-2"></i>Previous
                                        </button>
                                        <button type="button" class="btn btn-lg btn-primary px-5" onclick="nextStep(3)">
                                            Continue <i class="fas fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Step 3: Additional Information -->
                                <div class="form-step" id="step3">
                                    <div class="step-header mb-4">
                                        <h4 class="step-title"><i class="fas fa-clipboard-list me-2 text-primary"></i>Additional Information</h4>
                                        <p class="text-muted mb-0">Provide contact details and work arrangements during your leave</p>
                                    </div>
                                    
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="visit_address" class="form-label fw-semibold">
                                                <i class="fas fa-map-marker-alt me-2 text-danger"></i>Place / Address of Visit
                                                <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="visit_address" name="visit_address" rows="3" 
                                                      placeholder="Enter the address where you'll be during leave period..." required></textarea>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>Specify your location for emergency contact purposes
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="contact_number" class="form-label fw-semibold">
                                                <i class="fas fa-phone me-2 text-success"></i>Mobile No. during Leave Period
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="tel" class="form-control form-control-lg" id="contact_number" name="contact_number" 
                                                   placeholder="Enter contact number..." pattern="[0-9]{10,15}" required>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>Provide a reachable mobile number
                                            </div>
                                            
                                            <label for="mode_of_transport" class="form-label fw-semibold mt-4">
                                                <i class="fas fa-bus me-2 text-info"></i>Mode of Transport
                                            </label>
                                            <input type="text" class="form-control" id="mode_of_transport" name="mode_of_transport" 
                                                   placeholder="e.g., Personal vehicle, Public transport, Flight">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>Optional - If traveling for official work
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="work_adjustment" class="form-label fw-semibold">
                                                <i class="fas fa-tasks me-2 text-warning"></i>Work Adjustment During Leave 
                                                <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="work_adjustment" name="work_adjustment" rows="4" 
                                                      placeholder="Mention work arrangements, handover details, or coverage plans..." required></textarea>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>Describe how your work will be managed during your absence
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="step-actions">
                                        <button type="button" class="btn btn-lg btn-outline-secondary px-4" onclick="prevStep(2)">
                                            <i class="fas fa-arrow-left me-2"></i>Previous
                                        </button>
                                        <button type="submit" class="btn btn-lg btn-success px-5" id="submit-btn">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                                        </button>
                                    </div>
                                </div>
                                
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Progress Indicator Styles */
    .progress-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 2;
    }
    
    .progress-step-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #e9ecef;
        border: 3px solid #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #6c757d;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .progress-step-check {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #28a745;
        display: none;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }
    
    .progress-step.active .progress-step-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
        transform: scale(1.1);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .progress-step.completed .progress-step-icon {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }
    
    .progress-step.completed .progress-step-check {
        display: flex;
    }
    
    .progress-step.completed .progress-step-icon i:not(.fa-check) {
        display: none;
    }
    
    .progress-step-label {
        margin-top: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #6c757d;
        text-align: center;
    }
    
    .progress-step.active .progress-step-label {
        color: #667eea;
    }
    
    .progress-step.completed .progress-step-label {
        color: #28a745;
    }
    
    .progress-line {
        flex: 1;
        height: 3px;
        background: #dee2e6;
        margin: 0 10px;
        position: relative;
        top: -20px;
    }
    
    .progress-line.completed {
        background: #28a745;
    }
    
    /* Form Step Styles */
    .form-step {
        display: none;
        animation: fadeInUp 0.4s ease;
    }
    
    .form-step.active {
        display: block;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .step-header {
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
    }
    
    .step-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .step-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 2px solid #f0f0f0;
    }
    
    /* Form Control Enhancements */
    .form-control:focus,
    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label {
        color: #667eea;
    }
    
    /* Half Day Option Cards */
    .half-day-option {
        cursor: pointer;
        transition: all 0.3s ease;
        border-color: #dee2e6 !important;
    }
    
    .half-day-option:hover {
        border-color: #667eea !important;
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .half-day-option input:checked + label {
        color: #667eea;
    }
    
    .half-day-option:has(input:checked) {
        border-color: #667eea !important;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
    }
    
    /* File Upload Styling */
    .file-upload-wrapper {
        position: relative;
    }
    
    .file-upload-wrapper input[type="file"] {
        padding: 15px;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .file-upload-wrapper input[type="file"]:hover {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.05);
    }
    
    /* Utility Classes */
    .cursor-pointer {
        cursor: pointer;
    }
    
    .fw-semibold {
        font-weight: 600;
    }
    
    /* Button Enhancements */
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
    }
    
    /* Card Enhancements */
    .card {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .shadow-sm {
        box-shadow: 0 2px 10px rgba(0,0,0,0.08) !important;
    }
    
    /* Alert Enhancements */
    .alert {
        border-radius: 8px;
        border: none;
    }
    
    .alert-info {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        color: #0d47a1;
    }
    
    .alert-warning {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        color: #e65100;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .progress-container {
            flex-direction: column;
        }
        
        .progress-line {
            width: 3px;
            height: 30px;
            margin: 10px 0;
            top: 0;
        }
        
        .progress-step-icon {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
        
        .step-title {
            font-size: 1.25rem;
        }
        
        .step-actions {
            flex-direction: column;
            gap: 10px;
        }
        
        .step-actions button {
            width: 100%;
        }
    }
    
    /* Loading State */
    .btn-loading {
        position: relative;
        pointer-events: none;
    }
    
    .btn-loading::after {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-left: -8px;
        margin-top: -8px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spinner 0.6s linear infinite;
    }
    
    @keyframes spinner {
        to { transform: rotate(360deg); }
    }
    
    /* Invalid Field Styling */
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545 !important;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    
    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    
    .form-floating > .form-control.is-invalid ~ label,
    .form-floating > .form-select.is-invalid ~ label {
        color: #dc3545;
    }
</style>

<script>
    let currentStep = 1;
    const totalSteps = 3;
    
    // Step Navigation Functions
    function nextStep(step) {
        console.log('nextStep called, moving from', currentStep, 'to', step);
        
        if (!validateStep(currentStep)) {
            console.log('Validation failed for step', currentStep);
            return;
        }
        
        console.log('Validation passed, proceeding to next step');
        
        // Mark current step as completed
        const currentProgressStep = document.querySelector(`.progress-step[data-step="${currentStep}"]`);
        if (currentProgressStep) {
            currentProgressStep.classList.add('completed');
            console.log('Marked step', currentStep, 'as completed');
        }
        
        // Mark progress line as completed
        const progressLines = document.querySelectorAll('.progress-line');
        if (progressLines[currentStep - 1]) {
            progressLines[currentStep - 1].classList.add('completed');
        }
        
        // Hide current step
        const currentStepElement = document.getElementById(`step${currentStep}`);
        if (currentStepElement) {
            currentStepElement.classList.remove('active');
            console.log('Hidden step', currentStep);
        }
        
        // Show next step
        currentStep = step;
        const nextStepElement = document.getElementById(`step${currentStep}`);
        if (nextStepElement) {
            nextStepElement.classList.add('active');
            console.log('Showing step', currentStep);
        } else {
            console.error('Step element not found:', `step${currentStep}`);
        }
        
        // Update progress indicator
        updateProgressIndicator();
        
        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function prevStep(step) {
        console.log('prevStep called, moving from', currentStep, 'to', step);
        
        // Hide current step
        const currentStepElement = document.getElementById(`step${currentStep}`);
        if (currentStepElement) {
            currentStepElement.classList.remove('active');
        }
        
        // Show previous step
        currentStep = step;
        const prevStepElement = document.getElementById(`step${currentStep}`);
        if (prevStepElement) {
            prevStepElement.classList.add('active');
        }
        
        // Update progress indicator
        updateProgressIndicator();
        
        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function updateProgressIndicator() {
        // Remove active class from all steps
        document.querySelectorAll('.progress-step').forEach(step => {
            step.classList.remove('active');
        });
        
        // Add active class to current step
        const activeStep = document.querySelector(`.progress-step[data-step="${currentStep}"]`);
        if (activeStep) {
            activeStep.classList.add('active');
        }
    }
    
    function validateStep(step) {
        console.log('Validating step', step);
        
        // Step 1 validation - check designation field
        if (step === 1) {
            const designation = document.getElementById('designation');
            if (!designation.value || designation.value.trim() === '') {
                designation.classList.add('is-invalid');
                showNotification('Please update your profile with your designation before applying for leave.', 'warning');
                designation.focus();
                console.log('Step 1 validation failed: designation not set in user profile');
                return false;
            }
            designation.classList.remove('is-invalid');
            console.log('Step 1 validation passed');
            return true;
        }
        
        const currentStepElement = document.getElementById(`step${step}`);
        if (!currentStepElement) {
            console.error(`Step ${step} not found`);
            return false;
        }
        
        const inputs = currentStepElement.querySelectorAll('input[required]:not([readonly]), select[required], textarea[required]');
        console.log('Found', inputs.length, 'required inputs in step', step);
        
        let valid = true;
        let firstInvalidField = null;
        
        inputs.forEach(input => {
            if (!input.value || input.value.trim() === '') {
                input.classList.add('is-invalid');
                valid = false;
                if (!firstInvalidField) {
                    firstInvalidField = input;
                }
                console.log('Invalid field:', input.id || input.name);
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        // Special validation for step 2
        if (step === 2) {
            const isHalfDay = document.getElementById('is_half_day').checked;
            if (isHalfDay) {
                const firstHalf = document.getElementById('first_half');
                const secondHalf = document.getElementById('second_half');
                if (!firstHalf.checked && !secondHalf.checked) {
                    showNotification('Please select first half or second half for half day leave.', 'warning');
                    return false;
                }
            }
            
            // Validate dates
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const days = document.getElementById('days').value;
            
            if (!days || days <= 0) {
                showNotification('Please select valid dates.', 'warning');
                return false;
            }
        }
        
        if (!valid) {
            showNotification('Please fill in all required fields.', 'warning');
            if (firstInvalidField) {
                firstInvalidField.focus();
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        console.log('Validation result:', valid);
        return valid;
    }
    
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Character counter for reason textarea
        const reasonTextarea = document.getElementById('reason');
        const reasonCount = document.getElementById('reason-count');
        
        reasonTextarea.addEventListener('input', function() {
            const length = this.value.length;
            reasonCount.textContent = length;
            
            if (length > 500) {
                this.value = this.value.substring(0, 500);
                reasonCount.textContent = 500;
            }
        });
        
        // Leave type change handler
        document.getElementById('leave_type_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const requiresAttachment = selectedOption.getAttribute('data-requires-attachment');
            const description = selectedOption.getAttribute('data-description');
            const attachmentField = document.getElementById('attachment-field');
            const attachmentNote = document.getElementById('attachment-note');
            const attachmentInput = document.getElementById('attachment');
            const leaveDescription = document.getElementById('leave-description');
            
            // Show/hide description
            if (description && description.trim() !== '') {
                leaveDescription.innerHTML = '<i class="fas fa-info-circle me-2"></i>' + description;
                leaveDescription.style.display = 'block';
            } else {
                leaveDescription.style.display = 'none';
            }
            
            // Show/hide attachment field
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
        
        // Half day checkbox handler
        const isHalfDayCheckbox = document.getElementById('is_half_day');
        const halfDayPeriodField = document.getElementById('half_day_period_field');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const daysInput = document.getElementById('days');
        const firstHalfRadio = document.getElementById('first_half');
        const secondHalfRadio = document.getElementById('second_half');
        
        isHalfDayCheckbox.addEventListener('change', function() {
            if (this.checked) {
                halfDayPeriodField.style.display = 'block';
                firstHalfRadio.required = true;
                secondHalfRadio.required = true;
                
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
                calculateDays();
            }
            // Check for overlap when half-day is toggled
            checkLeaveOverlap();
        });
        
        // Half day option card click handlers
        document.querySelectorAll('.half-day-option').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                // Check for overlap when half-day period is selected
                checkLeaveOverlap();
            });
        });
        
        // Calculate days function
        function calculateDays() {
            const isHalfDay = isHalfDayCheckbox.checked;
            
            if (isHalfDay) {
                daysInput.value = '0.5';
                return;
            }
            
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (end >= start) {
                    const timeDiff = end.getTime() - start.getTime();
                    const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                    daysInput.value = daysDiff;
                } else {
                    daysInput.value = '';
                }
            } else {
                daysInput.value = '';
            }
        }
        
        // Check for overlapping leave applications
        function checkLeaveOverlap() {
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            
            if (!startDate || !endDate) {
                return;
            }
            
            // Clear previous warnings
            const existingWarning = document.getElementById('overlap-warning');
            if (existingWarning) {
                existingWarning.remove();
            }
            
            // Get half-day information
            const isHalfDay = isHalfDayCheckbox.checked;
            const firstHalf = document.getElementById('first_half');
            const secondHalf = document.getElementById('second_half');
            const halfDayPeriod = isHalfDay ? (firstHalf.checked ? 'first_half' : (secondHalf.checked ? 'second_half' : null)) : null;
            
            // Make AJAX request to check for overlaps
            fetch('../api/check_leave_overlap.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    is_half_day: isHalfDay,
                    half_day_period: halfDayPeriod
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.has_overlap && data.overlapping_leaves.length > 0) {
                    const leave = data.overlapping_leaves[0];
                    const halfDayInfo = leave.is_half_day ? ` (${leave.half_day_period})` : '';
                    const warningHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" id="overlap-warning" style="background-color: #f8d7da; border: 1px solid #f5c2c7; color: #842029;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Overlapping Leave Found!</strong><br>
                            You already have a <strong>${leave.status}</strong> leave application 
                            (<strong>${leave.leave_type}${halfDayInfo}</strong>) from 
                            <strong>${leave.start_date}</strong> to <strong>${leave.end_date}</strong>.
                            <br><small>Please choose different dates or cancel the existing application first.</small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    
                    // Insert warning after the days field
                    const daysField = document.getElementById('days').closest('.col-md-4');
                    daysField.insertAdjacentHTML('afterend', '<div class="col-12">' + warningHtml + '</div>');
                }
            })
            .catch(error => {
                console.error('Error checking leave overlap:', error);
            });
        }
        
        // Date change handlers
        startDateInput.addEventListener('change', function() {
            if (isHalfDayCheckbox.checked) {
                endDateInput.value = this.value;
                daysInput.value = '0.5';
            } else {
                calculateDays();
                endDateInput.setAttribute('min', this.value);
            }
            // Check for overlapping leaves
            checkLeaveOverlap();
        });
        
        endDateInput.addEventListener('change', function() {
            if (!isHalfDayCheckbox.checked) {
                calculateDays();
            }
            // Check for overlapping leaves
            checkLeaveOverlap();
        });
        
        // Phone number validation
        document.getElementById('contact_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
        });
        
        // File upload preview
        document.getElementById('attachment').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('document-preview');
            
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileName = file.name;
                const fileExt = fileName.split('.').pop().toLowerCase();
                
                let icon = 'fa-file';
                if (['pdf'].includes(fileExt)) icon = 'fa-file-pdf';
                else if (['doc', 'docx'].includes(fileExt)) icon = 'fa-file-word';
                else if (['jpg', 'jpeg', 'png'].includes(fileExt)) icon = 'fa-file-image';
                
                preview.innerHTML = `
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="fas ${icon} fa-2x me-3"></i>
                        <div>
                            <strong>${fileName}</strong><br>
                            <small>Size: ${fileSize} MB</small>
                        </div>
                    </div>
                `;
            } else {
                preview.innerHTML = '';
            }
        });
        
        // Form submission handler
        document.getElementById('leaveApplicationForm').addEventListener('submit', function(e) {
            // Check if there's an overlap warning visible
            const overlapWarning = document.getElementById('overlap-warning');
            if (overlapWarning) {
                e.preventDefault();
                showNotification('Cannot submit: You have an overlapping leave application. Please choose different dates.', 'danger');
                overlapWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        });
        
        // Remove invalid class on input
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                if (currentStep < totalSteps) {
                    nextStep(currentStep + 1);
                }
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
