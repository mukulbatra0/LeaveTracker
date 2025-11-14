<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user is an admin or HR admin
if ($role != 'admin' && $role != 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../dashboards/admin_dashboard.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update general settings
    if (isset($_POST['update_general_settings'])) {
        $institution_name = trim($_POST['institution_name']);
        $institution_address = trim($_POST['institution_address']);
        $institution_phone = trim($_POST['institution_phone']);
        $institution_email = trim($_POST['institution_email']);
        $fiscal_year_start = $_POST['fiscal_year_start'];
        $academic_year_start = $_POST['academic_year_start'];
        
        // Validate input
        $errors = [];
        
        if (empty($institution_name)) {
            $errors[] = "Institution name is required";
        }
        
        if (empty($institution_email)) {
            $errors[] = "Institution email is required";
        } elseif (!filter_var($institution_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // If no errors, update settings
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Update each setting individually
                $settings = [
                    'institution_name' => $institution_name,
                    'institution_address' => $institution_address,
                    'institution_phone' => $institution_phone,
                    'institution_email' => $institution_email,
                    'fiscal_year_start' => $fiscal_year_start,
                    'academic_year_start' => $academic_year_start
                ];
                
                foreach ($settings as $key => $value) {
                    // Check if setting exists
                    $check_sql = "SELECT COUNT(*) FROM system_settings WHERE setting_key = :key";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        // Update existing setting
                        $update_sql = "UPDATE system_settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $update_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $update_stmt->execute();
                    } else {
                        // Insert new setting
                        $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $insert_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $insert_stmt->execute();
                    }
                }
                
                // Add audit log
                $action = "Updated general system settings";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "General settings updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: system_config.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Update leave settings
    if (isset($_POST['update_leave_settings'])) {
        $default_approval_chain = $_POST['default_approval_chain'];
        $max_consecutive_leave_days = (int)$_POST['max_consecutive_leave_days'];
        $min_days_before_application = (int)$_POST['min_days_before_application'];
        $allow_weekend_holidays = isset($_POST['allow_weekend_holidays']) ? 1 : 0;
        $enable_document_upload = isset($_POST['enable_document_upload']) ? 1 : 0;
        $enable_leave_cancellation = isset($_POST['enable_leave_cancellation']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if ($max_consecutive_leave_days < 1) {
            $errors[] = "Maximum consecutive leave days must be at least 1";
        }
        
        if ($min_days_before_application < 0) {
            $errors[] = "Minimum days before application cannot be negative";
        }
        
        // If no errors, update settings
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Update each setting individually
                $settings = [
                    'default_approval_chain' => $default_approval_chain,
                    'max_consecutive_leave_days' => $max_consecutive_leave_days,
                    'min_days_before_application' => $min_days_before_application,
                    'allow_weekend_holidays' => $allow_weekend_holidays,
                    'enable_document_upload' => $enable_document_upload,
                    'enable_leave_cancellation' => $enable_leave_cancellation
                ];
                
                foreach ($settings as $key => $value) {
                    // Check if setting exists
                    $check_sql = "SELECT COUNT(*) FROM system_settings WHERE setting_key = :key";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        // Update existing setting
                        $update_sql = "UPDATE system_settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $update_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $update_stmt->execute();
                    } else {
                        // Insert new setting
                        $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $insert_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $insert_stmt->execute();
                    }
                }
                
                // Add audit log
                $action = "Updated leave system settings";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Leave settings updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: system_config.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Update notification settings
    if (isset($_POST['update_notification_settings'])) {
        $enable_email_notifications = isset($_POST['enable_email_notifications']) ? 1 : 0;
        $enable_sms_notifications = isset($_POST['enable_sms_notifications']) ? 1 : 0;
        $smtp_host = trim($_POST['smtp_host']);
        $smtp_port = trim($_POST['smtp_port']);
        $smtp_username = trim($_POST['smtp_username']);
        $smtp_password = trim($_POST['smtp_password']);
        $smtp_encryption = $_POST['smtp_encryption'];
        $sms_api_key = trim($_POST['sms_api_key']);
        $sms_api_secret = trim($_POST['sms_api_secret']);
        $sms_sender_id = trim($_POST['sms_sender_id']);
        
        // Validate input
        $errors = [];
        
        if ($enable_email_notifications) {
            if (empty($smtp_host)) {
                $errors[] = "SMTP host is required when email notifications are enabled";
            }
            
            if (empty($smtp_port)) {
                $errors[] = "SMTP port is required when email notifications are enabled";
            } elseif (!is_numeric($smtp_port)) {
                $errors[] = "SMTP port must be a number";
            }
            
            if (empty($smtp_username)) {
                $errors[] = "SMTP username is required when email notifications are enabled";
            }
        }
        
        if ($enable_sms_notifications) {
            if (empty($sms_api_key)) {
                $errors[] = "SMS API key is required when SMS notifications are enabled";
            }
            
            if (empty($sms_api_secret)) {
                $errors[] = "SMS API secret is required when SMS notifications are enabled";
            }
        }
        
        // If no errors, update settings
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Update each setting individually
                $settings = [
                    'enable_email_notifications' => $enable_email_notifications,
                    'enable_sms_notifications' => $enable_sms_notifications,
                    'smtp_host' => $smtp_host,
                    'smtp_port' => $smtp_port,
                    'smtp_username' => $smtp_username,
                    'smtp_encryption' => $smtp_encryption,
                    'sms_api_key' => $sms_api_key,
                    'sms_sender_id' => $sms_sender_id
                ];
                
                // Only update SMTP password if provided
                if (!empty($smtp_password)) {
                    $settings['smtp_password'] = $smtp_password;
                }
                
                // Only update SMS API secret if provided
                if (!empty($sms_api_secret)) {
                    $settings['sms_api_secret'] = $sms_api_secret;
                }
                
                foreach ($settings as $key => $value) {
                    // Check if setting exists
                    $check_sql = "SELECT COUNT(*) FROM system_settings WHERE setting_key = :key";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        // Update existing setting
                        $update_sql = "UPDATE system_settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $update_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $update_stmt->execute();
                    } else {
                        // Insert new setting
                        $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $insert_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $insert_stmt->execute();
                    }
                }
                
                // Add audit log
                $action = "Updated notification settings";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Notification settings updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: system_config.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Update security settings
    if (isset($_POST['update_security_settings'])) {
        $password_min_length = (int)$_POST['password_min_length'];
        $password_expiry_days = (int)$_POST['password_expiry_days'];
        $max_login_attempts = (int)$_POST['max_login_attempts'];
        $session_timeout_minutes = (int)$_POST['session_timeout_minutes'];
        $enforce_password_complexity = isset($_POST['enforce_password_complexity']) ? 1 : 0;
        $enable_two_factor_auth = isset($_POST['enable_two_factor_auth']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if ($password_min_length < 6) {
            $errors[] = "Password minimum length must be at least 6 characters";
        }
        
        if ($session_timeout_minutes < 5) {
            $errors[] = "Session timeout must be at least 5 minutes";
        }
        
        // If no errors, update settings
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Update each setting individually
                $settings = [
                    'password_min_length' => $password_min_length,
                    'password_expiry_days' => $password_expiry_days,
                    'max_login_attempts' => $max_login_attempts,
                    'session_timeout_minutes' => $session_timeout_minutes,
                    'enforce_password_complexity' => $enforce_password_complexity,
                    'enable_two_factor_auth' => $enable_two_factor_auth
                ];
                
                foreach ($settings as $key => $value) {
                    // Check if setting exists
                    $check_sql = "SELECT COUNT(*) FROM system_settings WHERE setting_key = :key";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        // Update existing setting
                        $update_sql = "UPDATE system_settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $update_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $update_stmt->execute();
                    } else {
                        // Insert new setting
                        $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':key', $key, PDO::PARAM_STR);
                        $insert_stmt->bindParam(':value', $value, PDO::PARAM_STR);
                        $insert_stmt->execute();
                    }
                }
                
                // Add audit log
                $action = "Updated security settings";
                $audit_sql = "INSERT INTO audit_logs (user_id, action, created_at) VALUES (:user_id, :action, NOW())";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $audit_stmt->bindParam(':action', $action, PDO::PARAM_STR);
                $audit_stmt->execute();
                
                $conn->commit();
                
                $_SESSION['alert'] = "Security settings updated successfully.";
                $_SESSION['alert_type'] = "success";
                header("Location: system_config.php");
                exit;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get current settings
function getSetting($conn, $key, $default = '') {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = :key";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':key', $key, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// General settings
$institution_name = getSetting($conn, 'institution_name', 'College Name');
$institution_address = getSetting($conn, 'institution_address', '');
$institution_phone = getSetting($conn, 'institution_phone', '');
$institution_email = getSetting($conn, 'institution_email', '');
$fiscal_year_start = getSetting($conn, 'fiscal_year_start', '04-01'); // Default: April 1
$academic_year_start = getSetting($conn, 'academic_year_start', '08-01'); // Default: August 1

// Leave settings
$default_approval_chain = getSetting($conn, 'default_approval_chain', 'department_head,dean,principal,hr_admin');
$max_consecutive_leave_days = getSetting($conn, 'max_consecutive_leave_days', '30');
$min_days_before_application = getSetting($conn, 'min_days_before_application', '3');
$allow_weekend_holidays = getSetting($conn, 'allow_weekend_holidays', '0');
$enable_document_upload = getSetting($conn, 'enable_document_upload', '1');
$enable_leave_cancellation = getSetting($conn, 'enable_leave_cancellation', '1');

// Notification settings
$enable_email_notifications = getSetting($conn, 'enable_email_notifications', '0');
$enable_sms_notifications = getSetting($conn, 'enable_sms_notifications', '0');
$smtp_host = getSetting($conn, 'smtp_host', '');
$smtp_port = getSetting($conn, 'smtp_port', '587');
$smtp_username = getSetting($conn, 'smtp_username', '');
$smtp_password = getSetting($conn, 'smtp_password', '');
$smtp_encryption = getSetting($conn, 'smtp_encryption', 'tls');
$sms_api_key = getSetting($conn, 'sms_api_key', '');
$sms_api_secret = getSetting($conn, 'sms_api_secret', '');
$sms_sender_id = getSetting($conn, 'sms_sender_id', '');

// Security settings
$password_min_length = getSetting($conn, 'password_min_length', '8');
$password_expiry_days = getSetting($conn, 'password_expiry_days', '90');
$max_login_attempts = getSetting($conn, 'max_login_attempts', '5');
$session_timeout_minutes = getSetting($conn, 'session_timeout_minutes', '30');
$enforce_password_complexity = getSetting($conn, 'enforce_password_complexity', '1');
$enable_two_factor_auth = getSetting($conn, 'enable_two_factor_auth', '0');

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-2 px-md-4">
    <div class="mobile-header d-block d-md-none mb-3">
        <div class="d-flex align-items-center justify-content-between">
            <h1 class="h4 mb-0">System Config</h1>
            <span class="badge bg-primary">Mobile</span>
        </div>
    </div>
    <h1 class="mt-4 d-none d-md-block">System Configuration</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">System Configuration</li>
    </ol>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-xl-12">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="nav-tabs-wrapper">
                        <ul class="nav nav-tabs card-header-tabs" id="configTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                    <i class="fas fa-cog me-1 d-none d-sm-inline"></i> 
                                    <span class="d-none d-sm-inline">General Settings</span>
                                    <span class="d-sm-none">General</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave" type="button" role="tab" aria-controls="leave" aria-selected="false">
                                    <i class="fas fa-calendar-alt me-1 d-none d-sm-inline"></i> 
                                    <span class="d-none d-sm-inline">Leave Settings</span>
                                    <span class="d-sm-none">Leave</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="notification-tab" data-bs-toggle="tab" data-bs-target="#notification" type="button" role="tab" aria-controls="notification" aria-selected="false">
                                    <i class="fas fa-bell me-1 d-none d-sm-inline"></i> 
                                    <span class="d-none d-sm-inline">Notification Settings</span>
                                    <span class="d-sm-none">Notifications</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                                    <i class="fas fa-shield-alt me-1 d-none d-sm-inline"></i> 
                                    <span class="d-none d-sm-inline">Security Settings</span>
                                    <span class="d-sm-none">Security</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="configTabsContent">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="update_general_settings" value="1">
                                <div class="row mb-3">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="institution_name" class="form-label">Institution Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control mobile-input" id="institution_name" name="institution_name" value="<?php echo htmlspecialchars($institution_name); ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="institution_email" class="form-label">Institution Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control mobile-input" id="institution_email" name="institution_email" value="<?php echo htmlspecialchars($institution_email); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="institution_phone" class="form-label">Institution Phone</label>
                                        <input type="text" class="form-control" id="institution_phone" name="institution_phone" value="<?php echo htmlspecialchars($institution_phone); ?>">
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="institution_address" class="form-label">Institution Address</label>
                                        <textarea class="form-control" id="institution_address" name="institution_address" rows="2"><?php echo htmlspecialchars($institution_address); ?></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="fiscal_year_start" class="form-label">Fiscal Year Start Date</label>
                                        <input type="text" class="form-control" id="fiscal_year_start" name="fiscal_year_start" value="<?php echo htmlspecialchars($fiscal_year_start); ?>" placeholder="MM-DD">
                                        <div class="form-text">Format: MM-DD (e.g., 04-01 for April 1)</div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="academic_year_start" class="form-label">Academic Year Start Date</label>
                                        <input type="text" class="form-control" id="academic_year_start" name="academic_year_start" value="<?php echo htmlspecialchars($academic_year_start); ?>" placeholder="MM-DD">
                                        <div class="form-text">Format: MM-DD (e.g., 08-01 for August 1)</div>
                                    </div>
                                </div>
                                <div class="mt-4 d-grid d-md-block">
                                    <button type="submit" name="update_general_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Leave Settings Tab -->
                        <div class="tab-pane fade" id="leave" role="tabpanel" aria-labelledby="leave-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="update_leave_settings" value="1">
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="default_approval_chain" class="form-label">Default Approval Chain</label>
                                        <select class="form-select" id="default_approval_chain" name="default_approval_chain">
                                            <option value="department_head,hr_admin" <?php echo $default_approval_chain == 'department_head,hr_admin' ? 'selected' : ''; ?>>Department Head → HR Admin</option>
                                            <option value="department_head,dean,hr_admin" <?php echo $default_approval_chain == 'department_head,dean,hr_admin' ? 'selected' : ''; ?>>Department Head → Dean → HR Admin</option>
                                            <option value="department_head,dean,principal,hr_admin" <?php echo $default_approval_chain == 'department_head,dean,principal,hr_admin' ? 'selected' : ''; ?>>Department Head → Dean → Principal → HR Admin</option>
                                            <option value="department_head,principal,hr_admin" <?php echo $default_approval_chain == 'department_head,principal,hr_admin' ? 'selected' : ''; ?>>Department Head → Principal → HR Admin</option>
                                        </select>
                                        <div class="form-text">Default approval workflow for leave applications</div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="max_consecutive_leave_days" class="form-label">Maximum Consecutive Leave Days</label>
                                        <input type="number" class="form-control" id="max_consecutive_leave_days" name="max_consecutive_leave_days" value="<?php echo htmlspecialchars($max_consecutive_leave_days); ?>" min="1">
                                        <div class="form-text">Maximum number of consecutive days a staff member can apply for leave</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="min_days_before_application" class="form-label">Minimum Days Before Application</label>
                                        <input type="number" class="form-control" id="min_days_before_application" name="min_days_before_application" value="<?php echo htmlspecialchars($min_days_before_application); ?>" min="0">
                                        <div class="form-text">Minimum number of days in advance a leave application must be submitted</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allow_weekend_holidays" name="allow_weekend_holidays" <?php echo $allow_weekend_holidays == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="allow_weekend_holidays">Include Weekends & Holidays in Leave Calculation</label>
                                        </div>
                                        <div class="form-text">If checked, weekends and holidays will be counted in leave days</div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_document_upload" name="enable_document_upload" <?php echo $enable_document_upload == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_document_upload">Enable Document Upload</label>
                                        </div>
                                        <div class="form-text">Allow staff to upload supporting documents with leave applications</div>
                                    </div>
                                    <div class="col-lg-4 col-md-12 col-sm-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_leave_cancellation" name="enable_leave_cancellation" <?php echo $enable_leave_cancellation == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_leave_cancellation">Enable Leave Cancellation</label>
                                        </div>
                                        <div class="form-text">Allow staff to cancel approved leave applications</div>
                                    </div>
                                </div>
                                <div class="mt-4 d-grid d-md-block">
                                    <button type="submit" name="update_leave_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Leave Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Notification Settings Tab -->
                        <div class="tab-pane fade" id="notification" role="tabpanel" aria-labelledby="notification-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="update_notification_settings" value="1">
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_email_notifications" name="enable_email_notifications" <?php echo $enable_email_notifications == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_email_notifications">Enable Email Notifications</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_sms_notifications" name="enable_sms_notifications" <?php echo $enable_sms_notifications == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_sms_notifications">Enable SMS Notifications</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mt-4 mb-3">Email Configuration</h5>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>">
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="text" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_port); ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_username); ?>">
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep current password">
                                        <div class="form-text">Leave blank to keep the current password</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="smtp_encryption" class="form-label">SMTP Encryption</label>
                                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                            <option value="tls" <?php echo $smtp_encryption == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo $smtp_encryption == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="none" <?php echo $smtp_encryption == 'none' ? 'selected' : ''; ?>>None</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <h5 class="mt-4 mb-3">SMS Configuration</h5>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="sms_api_key" class="form-label">SMS API Key</label>
                                        <input type="text" class="form-control" id="sms_api_key" name="sms_api_key" value="<?php echo htmlspecialchars($sms_api_key); ?>">
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="sms_api_secret" class="form-label">SMS API Secret</label>
                                        <input type="password" class="form-control" id="sms_api_secret" name="sms_api_secret" placeholder="Leave blank to keep current secret">
                                        <div class="form-text">Leave blank to keep the current API secret</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="sms_sender_id" class="form-label">SMS Sender ID</label>
                                        <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" value="<?php echo htmlspecialchars($sms_sender_id); ?>">
                                    </div>
                                </div>
                                
                                <div class="mt-4 d-grid d-md-block">
                                    <button type="submit" name="update_notification_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Security Settings Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="update_security_settings" value="1">
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                        <input type="number" class="form-control" id="password_min_length" name="password_min_length" value="<?php echo htmlspecialchars($password_min_length); ?>" min="6">
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="password_expiry_days" class="form-label">Password Expiry (Days)</label>
                                        <input type="number" class="form-control" id="password_expiry_days" name="password_expiry_days" value="<?php echo htmlspecialchars($password_expiry_days); ?>" min="0">
                                        <div class="form-text">Set to 0 for no expiry</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="max_login_attempts" class="form-label">Maximum Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" value="<?php echo htmlspecialchars($max_login_attempts); ?>" min="1">
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <label for="session_timeout_minutes" class="form-label">Session Timeout (Minutes)</label>
                                        <input type="number" class="form-control" id="session_timeout_minutes" name="session_timeout_minutes" value="<?php echo htmlspecialchars($session_timeout_minutes); ?>" min="5">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enforce_password_complexity" name="enforce_password_complexity" <?php echo $enforce_password_complexity == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enforce_password_complexity">Enforce Password Complexity</label>
                                        </div>
                                        <div class="form-text">Require uppercase, lowercase, numbers, and special characters</div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_two_factor_auth" name="enable_two_factor_auth" <?php echo $enable_two_factor_auth == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_two_factor_auth">Enable Two-Factor Authentication</label>
                                        </div>
                                        <div class="form-text">Require email verification code during login</div>
                                    </div>
                                </div>
                                <div class="mt-4 d-grid d-md-block">
                                    <button type="submit" name="update_security_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Security Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Responsive Helpers -->
<script src="../js/responsive-helpers.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get the tab from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        
        if (activeTab) {
            // Activate the tab from URL parameter
            const tabElement = document.querySelector(`#${activeTab}-tab`);
            if (tabElement) {
                const tab = new bootstrap.Tab(tabElement);
                tab.show();
            }
        }
        
        // Update URL when tab changes
        const tabElements = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabElements.forEach(tabElement => {
            tabElement.addEventListener('shown.bs.tab', function (event) {
                const id = event.target.id.replace('-tab', '');
                const url = new URL(window.location.href);
                url.searchParams.set('tab', id);
                window.history.replaceState(null, '', url);
            });
        });
        
        // Mobile-specific enhancements
        if (window.innerWidth <= 768) {
            // Add swipe gesture support for tabs
            let startX = 0;
            let currentTab = 0;
            const tabs = document.querySelectorAll('.nav-link');
            
            document.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
            });
            
            document.addEventListener('touchend', function(e) {
                const endX = e.changedTouches[0].clientX;
                const diff = startX - endX;
                
                if (Math.abs(diff) > 50) { // Minimum swipe distance
                    if (diff > 0 && currentTab < tabs.length - 1) {
                        // Swipe left - next tab
                        tabs[currentTab + 1].click();
                    } else if (diff < 0 && currentTab > 0) {
                        // Swipe right - previous tab
                        tabs[currentTab - 1].click();
                    }
                }
            });
            
            // Track current tab
            tabs.forEach((tab, index) => {
                tab.addEventListener('shown.bs.tab', function() {
                    currentTab = index;
                });
            });
        }
    });
</script>

<style>
/* Fix for nav-tabs buttons in card header - high specificity to override Bootstrap */
.card .card-header .nav-tabs .nav-link {
    background-color: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    border-radius: 8px 8px 0 0 !important;
    margin-right: 5px !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

.card .card-header .nav-tabs .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: #ffffff !important;
    transform: translateY(-1px) !important;
}

.card .card-header .nav-tabs .nav-link.active,
.card .card-header .nav-tabs .nav-link.active:focus,
.card .card-header .nav-tabs .nav-link.active:hover {
    background-color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: #1B3C53 !important;
    font-weight: 600 !important;
    transform: none !important;
}

.card .card-header .nav-tabs {
    border-bottom: none !important;
    margin-bottom: 0 !important;
}
</style>

<?php include '../includes/footer.php'; ?>