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
        header('Location: ./modules/apply_leave.php');
        exit;
    }
    
    // Validate dates
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date);
    $current_timestamp = strtotime(date('Y-m-d'));
    
    if ($start_timestamp < $current_timestamp) {
        $_SESSION['alert'] = "Start date cannot be in the past.";
        $_SESSION['alert_type'] = "danger";
        header('Location: ./modules/apply_leave.php');
        exit;
    }
    
    if ($end_timestamp < $start_timestamp) {
        $_SESSION['alert'] = "End date cannot be before start date.";
        $_SESSION['alert_type'] = "danger";
        header('Location: ./modules/apply_leave.php');
        exit;
    }
    
    // Validate days
    if (!is_numeric($days) || $days <= 0) {
        $_SESSION['alert'] = "Invalid number of days.";
        $_SESSION['alert_type'] = "danger";
        header('Location: ./modules/apply_leave.php');
        exit;
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
            header('Location: ./modules/apply_leave.php');
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
            header('Location: ./modules/apply_leave.php');
            exit;
        }
    }
    
    // Handle file upload if required
    $attachment = null;
    if ($requires_attachment) {
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] == UPLOAD_ERR_NO_FILE) {
            $_SESSION['alert'] = "Attachment is required for this leave type.";
            $_SESSION['alert_type'] = "danger";
            header('Location: ./modules/apply_leave.php');
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
            header('Location: ./modules/apply_leave.php');
            exit;
        }
        
        // Validate file size
        if ($file_size > $max_size) {
            $_SESSION['alert'] = "File is too large. Maximum size is {$max_size} bytes.";
            $_SESSION['alert_type'] = "danger";
            header('Location: ./modules/apply_leave.php');
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
            header('Location: ./modules/apply_leave.php');
            exit;
        }
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert leave application
        $insert_sql = "INSERT INTO leave_applications (user_id, leave_type_id, start_date, end_date, days, reason, attachment) 
                      VALUES (:user_id, :leave_type_id, :start_date, :end_date, :days, :reason, :attachment)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $insert_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
        $insert_stmt->bindParam(':days', $days, PDO::PARAM_STR);
        $insert_stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $insert_stmt->bindParam(':attachment', $attachment, PDO::PARAM_STR);
        $insert_stmt->execute();
        
        $leave_application_id = $conn->lastInsertId();
        
        // Get department head for approval
        $dept_id = $_SESSION['department_id'];
        $dept_head_sql = "SELECT head_id FROM departments WHERE id = :dept_id";
        $dept_head_stmt = $conn->prepare($dept_head_sql);
        $dept_head_stmt->bindParam(':dept_id', $dept_id, PDO::PARAM_INT);
        $dept_head_stmt->execute();
        $dept_head = $dept_head_stmt->fetch();
        
        if ($dept_head && $dept_head['head_id']) {
            // Create approval record for department head
            $approval_sql = "INSERT INTO leave_approvals (leave_application_id, approver_id, approver_level) 
                            VALUES (:leave_application_id, :approver_id, 'department_head')";
            $approval_stmt = $conn->prepare($approval_sql);
            $approval_stmt->bindParam(':leave_application_id', $leave_application_id, PDO::PARAM_INT);
            $approval_stmt->bindParam(':approver_id', $dept_head['head_id'], PDO::PARAM_INT);
            $approval_stmt->execute();
            
            // Create notification for department head
            $notification_sql = "INSERT INTO notifications (user_id, title, message, related_to, related_id) 
                                VALUES (:user_id, 'Leave Approval Required', 'A new leave application requires your approval.', 'leave_application', :related_id)";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bindParam(':user_id', $dept_head['head_id'], PDO::PARAM_INT);
            $notification_stmt->bindParam(':related_id', $leave_application_id, PDO::PARAM_INT);
            $notification_stmt->execute();
            
            // Send email notification to department head
            $dept_head_email_sql = "SELECT email, first_name, last_name FROM users WHERE id = :id";
            $dept_head_email_stmt = $conn->prepare($dept_head_email_sql);
            $dept_head_email_stmt->bindParam(':id', $dept_head['head_id'], PDO::PARAM_INT);
            $dept_head_email_stmt->execute();
            $dept_head_info = $dept_head_email_stmt->fetch();
            
            if ($dept_head_info) {
                $applicant_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
                $leave_type_name_sql = "SELECT name FROM leave_types WHERE id = :id";
                $leave_type_name_stmt = $conn->prepare($leave_type_name_sql);
                $leave_type_name_stmt->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
                $leave_type_name_stmt->execute();
                $leave_type_name = $leave_type_name_stmt->fetchColumn();
                
                $emailNotification->sendLeaveApplicationNotification(
                    $dept_head_info['email'],
                    $applicant_name,
                    $leave_type_name,
                    $start_date,
                    $end_date,
                    $leave_application_id
                );
            }
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
        header('Location: ./modules/apply_leave.php');
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
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
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
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="days" class="form-label">Number of Days <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="days" name="days" step="0.5" min="0.5" required readonly>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i> This will be calculated automatically based on your date selection.
                                </div>
                                <div class="alert alert-warning mt-2" id="balance-warning" style="display: none;"></div>
                                <div class="alert alert-info mt-2" id="holiday-note" style="display: none;"></div>
                                <div class="alert alert-warning mt-2" id="academic-event-warning" style="display: none;"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for Leave <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3" id="attachment-field" style="display: none;">
                                <label for="attachment" class="form-label">Supporting Document <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="attachment" name="attachment" onchange="previewDocument(this)">
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i> Allowed file types: pdf, doc, docx, jpg, jpeg, png. Maximum size: 5MB.
                                </div>
                                <div id="document-preview" class="mt-3"></div>
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
        
        // Calculate days automatically
        function calculateDays() {
            var startDate = document.getElementById('start_date').value;
            var endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                var start = new Date(startDate);
                var end = new Date(endDate);
                
                if (end >= start) {
                    var timeDiff = end.getTime() - start.getTime();
                    var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                    document.getElementById('days').value = daysDiff;
                } else {
                    document.getElementById('days').value = '';
                }
            } else {
                document.getElementById('days').value = '';
            }
        }
        
        // Bind date change events
        document.getElementById('start_date').addEventListener('change', function() {
            calculateDays();
            document.getElementById('end_date').setAttribute('min', this.value);
        });
        
        document.getElementById('end_date').addEventListener('change', calculateDays);
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>