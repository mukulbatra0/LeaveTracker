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

// Check if leave ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "Invalid leave application.";
    $_SESSION['alert_type'] = "danger";
    header('Location: my_leaves.php');
    exit;
}

$leave_id = $_GET['id'];

// Get leave application details
$leave_sql = "SELECT la.*, lt.name as leave_type_name, lt.requires_attachment
              FROM leave_applications la
              JOIN leave_types lt ON la.leave_type_id = lt.id
              WHERE la.id = :id AND la.user_id = :user_id";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
$leave_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$leave_stmt->execute();

if ($leave_stmt->rowCount() == 0) {
    $_SESSION['alert'] = "Leave application not found or you don't have permission to edit it.";
    $_SESSION['alert_type'] = "danger";
    header('Location: my_leaves.php');
    exit;
}

$leave_data = $leave_stmt->fetch();

// Check if leave status is pending
if ($leave_data['status'] != 'pending') {
    $_SESSION['alert'] = "Only pending leave applications can be edited.";
    $_SESSION['alert_type'] = "warning";
    header('Location: my_leaves.php');
    exit;
}

// Get user details for policy-based filtering
$user_details_sql = "SELECT staff_type, gender, employment_type FROM users WHERE id = :user_id";
$user_details_stmt = $conn->prepare($user_details_sql);
$user_details_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$user_details_stmt->execute();
$user_data = $user_details_stmt->fetch();

$user_staff_type = $user_data['staff_type'] ?? 'teaching';
$user_gender = $user_data['gender'] ?? 'male';
$user_employment_type = $user_data['employment_type'] ?? 'full_time';

// Get available leave types
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
        header('Location: edit_leave.php?id=' . $leave_id);
        exit;
    }
    
    // Validate dates
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date);
    $current_timestamp = strtotime(date('Y-m-d'));
    
    if ($start_timestamp < $current_timestamp) {
        $_SESSION['alert'] = "Start date cannot be in the past.";
        $_SESSION['alert_type'] = "danger";
        header('Location: edit_leave.php?id=' . $leave_id);
        exit;
    }
    
    if ($end_timestamp < $start_timestamp) {
        $_SESSION['alert'] = "End date cannot be before start date.";
        $_SESSION['alert_type'] = "danger";
        header('Location: edit_leave.php?id=' . $leave_id);
        exit;
    }
    
    // Validate days
    if (!is_numeric($days) || $days <= 0) {
        $_SESSION['alert'] = "Invalid number of days.";
        $_SESSION['alert_type'] = "danger";
        header('Location: edit_leave.php?id=' . $leave_id);
        exit;
    }
    
    // Validate half day settings
    if ($is_half_day) {
        if ($days != 0.5) {
            $_SESSION['alert'] = "Half day leave must be exactly 0.5 days.";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
        if (!in_array($half_day_period, ['first_half', 'second_half'])) {
            $_SESSION['alert'] = "Please select first half or second half for half day leave.";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
        if ($start_date != $end_date) {
            $_SESSION['alert'] = "Half day leave must have same start and end date.";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
    }
    
    // Check for overlapping leave applications (excluding current leave)
    $overlap_sql = "SELECT la.id, la.start_date, la.end_date, la.status, la.is_half_day, la.half_day_period, lt.name as leave_type_name
                    FROM leave_applications la
                    JOIN leave_types lt ON la.leave_type_id = lt.id
                    WHERE la.user_id = :user_id 
                    AND la.id != :current_leave_id
                    AND la.status NOT IN ('rejected', 'cancelled')
                    AND (
                        (la.start_date <= :end_date AND la.end_date >= :start_date)
                    )";
    $overlap_stmt = $conn->prepare($overlap_sql);
    $overlap_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $overlap_stmt->bindParam(':current_leave_id', $leave_id, PDO::PARAM_INT);
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
            
            $_SESSION['alert'] = "You already have a {$overlap_status} leave application ({$overlap_type}{$overlap_detail}) from {$overlap_start} to {$overlap_end} that overlaps with the selected dates.";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
    }
    
    // Check leave balance (considering the current leave's days)
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
        // Add back the current leave's days to the balance for validation
        $available_balance = $balance + $leave_data['days'];
        
        if ($days > $available_balance) {
            $_SESSION['alert'] = "Insufficient leave balance. You have {$available_balance} days available (including current leave).";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
    }
    
    // Handle file upload if required
    $attachment = $leave_data['attachment']; // Keep existing attachment by default
    
    if ($requires_attachment && isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
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
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
        
        // Validate file size
        if ($file_size > $max_size) {
            $_SESSION['alert'] = "File is too large. Maximum size is {$max_size} bytes.";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
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
            // Delete old attachment if exists
            if ($leave_data['attachment'] && file_exists($upload_dir . $leave_data['attachment'])) {
                unlink($upload_dir . $leave_data['attachment']);
            }
            $attachment = $new_file_name;
        } else {
            $_SESSION['alert'] = "Failed to upload file.";
            $_SESSION['alert_type'] = "danger";
            header('Location: edit_leave.php?id=' . $leave_id);
            exit;
        }
    } elseif ($requires_attachment && !$leave_data['attachment']) {
        $_SESSION['alert'] = "Attachment is required for this leave type.";
        $_SESSION['alert_type'] = "danger";
        header('Location: edit_leave.php?id=' . $leave_id);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update leave application
        $update_sql = "UPDATE leave_applications 
                      SET leave_type_id = :leave_type_id, 
                          start_date = :start_date, 
                          end_date = :end_date, 
                          days = :days, 
                          reason = :reason, 
                          designation = :designation, 
                          attachment = :attachment, 
                          is_half_day = :is_half_day, 
                          half_day_period = :half_day_period, 
                          mode_of_transport = :mode_of_transport, 
                          work_adjustment = :work_adjustment, 
                          visit_address = :visit_address, 
                          contact_number = :contact_number,
                          updated_at = NOW()
                      WHERE id = :id AND user_id = :user_id AND status = 'pending'";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $update_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
        $update_stmt->bindParam(':days', $days, PDO::PARAM_STR);
        $update_stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $update_stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $update_stmt->bindParam(':attachment', $attachment, PDO::PARAM_STR);
        $update_stmt->bindParam(':is_half_day', $is_half_day, PDO::PARAM_INT);
        $update_stmt->bindParam(':half_day_period', $half_day_period, PDO::PARAM_STR);
        $update_stmt->bindParam(':mode_of_transport', $mode_of_transport, PDO::PARAM_STR);
        $update_stmt->bindParam(':work_adjustment', $work_adjustment, PDO::PARAM_STR);
        $update_stmt->bindParam(':visit_address', $visit_address, PDO::PARAM_STR);
        $update_stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
        $update_stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Log the action
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $log_sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                  VALUES (:user_id, 'update', 'leave_applications', :entity_id, 'Leave application updated', :ip_address, :user_agent)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':entity_id', $leave_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $log_stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
        $log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['alert'] = "Leave application updated successfully.";
        $_SESSION['alert_type'] = "success";
        header('Location: my_leaves.php');
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        $_SESSION['alert'] = "Error: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header('Location: edit_leave.php?id=' . $leave_id);
        exit;
    }
}

// Get user details for display
$user_details_sql = "SELECT u.first_name, u.last_name, u.email, u.role, d.name as department_name 
                    FROM users u 
                    LEFT JOIN departments d ON u.department_id = d.id 
                    WHERE u.id = :user_id";
$user_details_stmt = $conn->prepare($user_details_sql);
$user_details_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$user_details_stmt->execute();
$user_details = $user_details_stmt->fetch();

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
                        <h2 class="mb-1"><i class="fas fa-edit me-2 text-warning"></i>Edit Leave Application</h2>
                        <p class="text-muted mb-0">Update your leave request details</p>
                    </div>
                    <a href="my_leaves.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to My Leaves
                    </a>
                </div>
            </div>

            <div class="alert alert-info shadow-sm">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> You can only edit leave applications that are in <strong>pending</strong> status.
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-lg-5">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $leave_id; ?>" method="post" enctype="multipart/form-data" id="editLeaveForm">
                        
                        <!-- Applicant Information -->
                        <div class="mb-5">
                            <h4 class="mb-3"><i class="fas fa-user-circle me-2 text-primary"></i>Applicant Information</h4>
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
                                        <select class="form-select" id="designation" name="designation" required>
                                            <option value="">-- Select Designation --</option>
                                            <option value="Assistant Professor" <?php echo $leave_data['designation'] == 'Assistant Professor' ? 'selected' : ''; ?>>Assistant Professor</option>
                                            <option value="Associate Professor" <?php echo $leave_data['designation'] == 'Associate Professor' ? 'selected' : ''; ?>>Associate Professor</option>
                                        </select>
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
                                               value="<?php echo date('d/m/Y', strtotime($leave_data['created_at'])); ?>" 
                                               readonly>
                                        <label for="application_date"><i class="fas fa-calendar me-2"></i>Original Application Date</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave Details -->
                        <div class="mb-5">
                            <h4 class="mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Leave Details</h4>
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
                                                    data-description="<?php echo htmlspecialchars($leave_type['description']); ?>"
                                                    <?php echo $leave_data['leave_type_id'] == $leave_type['id'] ? 'selected' : ''; ?>>
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
                                                <input class="form-check-input" type="checkbox" id="is_half_day" name="is_half_day" value="1" 
                                                       <?php echo $leave_data['is_half_day'] ? 'checked' : ''; ?>
                                                       style="width: 3em; height: 1.5em;">
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
                                    <input type="date" class="form-control form-control-lg" id="start_date" name="start_date" 
                                           value="<?php echo $leave_data['start_date']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label fw-semibold">
                                        <i class="fas fa-calendar-check me-2 text-danger"></i>To Date 
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control form-control-lg" id="end_date" name="end_date" 
                                           value="<?php echo $leave_data['end_date']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="days" class="form-label fw-semibold">
                                        <i class="fas fa-calculator me-2 text-info"></i>Number of Days 
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="days" name="days" 
                                           value="<?php echo $leave_data['days']; ?>" step="0.5" min="0.5" required readonly>
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Auto-calculated</small>
                                </div>
                                
                                <div class="col-12" id="half_day_period_field" style="display: <?php echo $leave_data['is_half_day'] ? 'block' : 'none'; ?>;">
                                    <label class="form-label fw-semibold">
                                        <i class="fas fa-clock me-2 text-warning"></i>Select Half Day Period 
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="card border-2 half-day-option <?php echo $leave_data['half_day_period'] == 'first_half' ? 'selected' : ''; ?>">
                                                <div class="card-body text-center">
                                                    <input class="form-check-input d-none" type="radio" name="half_day_period" id="first_half" value="first_half"
                                                           <?php echo $leave_data['half_day_period'] == 'first_half' ? 'checked' : ''; ?>>
                                                    <label class="w-100 cursor-pointer" for="first_half">
                                                        <i class="fas fa-sun fa-3x text-warning mb-3"></i>
                                                        <h5 class="mb-0">First Half</h5>
                                                        <small class="text-muted">Morning Session</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card border-2 half-day-option <?php echo $leave_data['half_day_period'] == 'second_half' ? 'selected' : ''; ?>">
                                                <div class="card-body text-center">
                                                    <input class="form-check-input d-none" type="radio" name="half_day_period" id="second_half" value="second_half"
                                                           <?php echo $leave_data['half_day_period'] == 'second_half' ? 'checked' : ''; ?>>
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
                                              placeholder="Please provide a detailed reason for your leave application..." required><?php echo htmlspecialchars($leave_data['reason']); ?></textarea>
                                    <div class="form-text">
                                        <span id="reason-count"><?php echo strlen($leave_data['reason']); ?></span>/500 characters
                                    </div>
                                </div>
                                
                                <div class="col-12" id="attachment-field" style="display: <?php echo $leave_data['requires_attachment'] ? 'block' : 'none'; ?>;">
                                    <label for="attachment" class="form-label fw-semibold">
                                        <i class="fas fa-paperclip me-2 text-primary"></i>Supporting Document 
                                        <?php if ($leave_data['requires_attachment']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if ($leave_data['attachment']): ?>
                                        <div class="alert alert-success mb-2">
                                            <i class="fas fa-file-pdf me-2"></i>
                                            Current attachment: <strong><?php echo htmlspecialchars($leave_data['attachment']); ?></strong>
                                            <br><small class="text-muted">Upload a new file to replace the existing one</small>
                                        </div>
                                    <?php endif; ?>
                                    
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
                        </div>

                        <!-- Additional Information -->
                        <div class="mb-5">
                            <h4 class="mb-3"><i class="fas fa-clipboard-list me-2 text-primary"></i>Additional Information</h4>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="visit_address" class="form-label fw-semibold">
                                        <i class="fas fa-map-marker-alt me-2 text-danger"></i>Place / Address of Visit
                                        <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" id="visit_address" name="visit_address" rows="3" 
                                              placeholder="Enter the address where you'll be during leave period..." required><?php echo htmlspecialchars($leave_data['visit_address']); ?></textarea>
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
                                           value="<?php echo htmlspecialchars($leave_data['contact_number']); ?>"
                                           placeholder="Enter contact number..." pattern="[0-9]{10,15}" required>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>Provide a reachable mobile number
                                    </div>
                                    
                                    <label for="mode_of_transport" class="form-label fw-semibold mt-4">
                                        <i class="fas fa-bus me-2 text-info"></i>Mode of Transport
                                    </label>
                                    <input type="text" class="form-control" id="mode_of_transport" name="mode_of_transport" 
                                           value="<?php echo htmlspecialchars($leave_data['mode_of_transport']); ?>"
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
                                              placeholder="Mention work arrangements, handover details, or coverage plans..." required><?php echo htmlspecialchars($leave_data['work_adjustment']); ?></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>Describe how your work will be managed during your absence
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                            <a href="my_leaves.php" class="btn btn-lg btn-outline-secondary px-5">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-lg btn-warning px-5" id="submit-btn">
                                <i class="fas fa-save me-2"></i>Update Leave Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
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
    
    .half-day-option:has(input:checked),
    .half-day-option.selected {
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
    .btn-warning {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        border: none;
        color: #000;
        transition: all 0.3s ease;
    }
    
    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(255, 193, 7, 0.4);
        color: #000;
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
    // Get form elements
    const isHalfDayCheckbox = document.getElementById('is_half_day');
    const halfDayPeriodField = document.getElementById('half_day_period_field');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const daysInput = document.getElementById('days');
    const firstHalfRadio = document.getElementById('first_half');
    const secondHalfRadio = document.getElementById('second_half');
    const leaveTypeSelect = document.getElementById('leave_type_id');
    const attachmentField = document.getElementById('attachment-field');
    const attachmentNote = document.getElementById('attachment-note');
    const leaveDescription = document.getElementById('leave-description');
    const reasonTextarea = document.getElementById('reason');
    const reasonCount = document.getElementById('reason-count');
    
    // Half day checkbox handler
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
        checkLeaveOverlap();
    });
    
    // Half day option card click handlers
    document.querySelectorAll('.half-day-option').forEach(card => {
        card.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Remove selected class from all cards
            document.querySelectorAll('.half-day-option').forEach(c => c.classList.remove('selected'));
            // Add selected class to clicked card
            this.classList.add('selected');
            
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
        checkLeaveOverlap();
    });
    
    endDateInput.addEventListener('change', function() {
        if (!isHalfDayCheckbox.checked) {
            calculateDays();
        }
        checkLeaveOverlap();
    });
    
    // Leave type change handler
    leaveTypeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const requiresAttachment = selectedOption.getAttribute('data-requires-attachment') === '1';
        const description = selectedOption.getAttribute('data-description');
        
        if (requiresAttachment) {
            attachmentField.style.display = 'block';
            attachmentNote.style.display = 'block';
        } else {
            attachmentField.style.display = 'none';
            attachmentNote.style.display = 'none';
        }
        
        if (description && description !== '') {
            leaveDescription.textContent = description;
            leaveDescription.style.display = 'block';
        } else {
            leaveDescription.style.display = 'none';
        }
    });
    
    // Trigger leave type change on page load to show/hide attachment field
    if (leaveTypeSelect.value) {
        leaveTypeSelect.dispatchEvent(new Event('change'));
    }
    
    // Phone number validation
    document.getElementById('contact_number').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
    });
    
    // Reason character count
    reasonTextarea.addEventListener('input', function() {
        const length = this.value.length;
        reasonCount.textContent = length;
        
        if (length > 500) {
            this.value = this.value.substring(0, 500);
            reasonCount.textContent = '500';
        }
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
    document.getElementById('editLeaveForm').addEventListener('submit', function(e) {
        // Check if there's an overlap warning visible
        const overlapWarning = document.getElementById('overlap-warning');
        if (overlapWarning) {
            e.preventDefault();
            alert('Cannot submit: You have an overlapping leave application. Please choose different dates.');
            overlapWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
    });
    
    // Remove invalid class on input
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
