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

// Check if user has permission to access this page (HR Admin only)
if ($role !== 'hr_admin') {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header("Location: ../index.php");
    exit;
}

// Initialize variables
$settings = [];
$errors = [];

// Get current system settings
try {
    $stmt = $conn->prepare("SELECT * FROM system_settings");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

// If settings table doesn't exist or is empty, create default settings
if (empty($settings)) {
    try {
        // Check if table exists
        $stmt = $conn->prepare("SHOW TABLES LIKE 'system_settings'");
        $stmt->execute();
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Create table
            $conn->exec("CREATE TABLE system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
        }
        
        // Insert default settings
        $defaultSettings = [
            ['system_name', 'Employee Leave Management System', 'System name displayed in header and emails'],
            ['system_email', 'elms@example.com', 'System email used for sending notifications'],
            ['max_file_size', '5', 'Maximum file size for attachments in MB'],
            ['allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png', 'Comma-separated list of allowed file extensions'],
            ['enable_email_notifications', '1', 'Enable or disable email notifications (1=enabled, 0=disabled)'],
            ['enable_document_upload', '1', 'Enable or disable document uploads (1=enabled, 0=disabled)'],
            ['leave_approval_levels', '3', 'Number of approval levels required (1-3)'],
            ['fiscal_year_start', '01-01', 'Start date of fiscal year (MM-DD)'],
            ['fiscal_year_end', '12-31', 'End date of fiscal year (MM-DD)'],
            ['leave_carry_forward_limit', '10', 'Maximum days that can be carried forward to next year'],
            ['leave_apply_min_days_before', '3', 'Minimum days before leave start date to apply'],
            ['pagination_limit', '10', 'Number of items per page in tables'],
            ['session_timeout', '30', 'Session timeout in minutes'],
            ['maintenance_mode', '0', 'System maintenance mode (1=enabled, 0=disabled)'],
            ['maintenance_message', 'System is under maintenance. Please try again later.', 'Message displayed during maintenance mode']
        ];
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
        
        // Reload settings
        $stmt = $conn->prepare("SELECT * FROM system_settings");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $_SESSION['alert'] = "Default system settings have been created.";
        $_SESSION['alert_type'] = "success";
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Update settings
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($_POST as $key => $value) {
            // Skip non-setting fields
            if ($key === 'submit') continue;
            
            // Validate and sanitize input
            $value = trim($value);
            
            // Special validation for certain settings
            switch ($key) {
                case 'max_file_size':
                    if (!is_numeric($value) || $value <= 0) {
                        $errors[] = "Maximum file size must be a positive number.";
                        continue 2; // Skip to next iteration of outer loop
                    }
                    break;
                    
                case 'leave_approval_levels':
                    if (!in_array($value, ['1', '2', '3'])) {
                        $errors[] = "Approval levels must be between 1 and 3.";
                        continue 2;
                    }
                    break;
                    
                case 'leave_carry_forward_limit':
                case 'leave_apply_min_days_before':
                case 'pagination_limit':
                case 'session_timeout':
                    if (!is_numeric($value) || $value < 0) {
                        $errors[] = "$key must be a non-negative number.";
                        continue 2;
                    }
                    break;
                    
                case 'enable_email_notifications':
                case 'enable_document_upload':
                case 'maintenance_mode':
                    $value = $value ? '1' : '0';
                    break;
            }
            
            // Update setting
            $stmt->execute([$value, $key]);
            
            // Update local settings array
            $settings[$key] = $value;
        }
        
        if (empty($errors)) {
            $conn->commit();
            
            // Log the action
            $action = "Updated system settings";
            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
            
            $_SESSION['alert'] = "System settings updated successfully.";
            $_SESSION['alert_type'] = "success";
        } else {
            $conn->rollBack();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">System Configuration</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">System Configuration</li>
    </ol>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['alert']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert'], $_SESSION['alert_type']); ?>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-cogs me-1"></i>
            System Settings
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="accordion" id="settingsAccordion">
                    <!-- General Settings -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingGeneral">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral" aria-expanded="true" aria-controls="collapseGeneral">
                                General Settings
                            </button>
                        </h2>
                        <div id="collapseGeneral" class="accordion-collapse collapse show" aria-labelledby="headingGeneral" data-bs-parent="#settingsAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="system_name" class="form-label">System Name</label>
                                        <input type="text" class="form-control" id="system_name" name="system_name" value="<?php echo htmlspecialchars($settings['system_name'] ?? ''); ?>" required>
                                        <div class="form-text">Name displayed in header and emails</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="system_email" class="form-label">System Email</label>
                                        <input type="email" class="form-control" id="system_email" name="system_email" value="<?php echo htmlspecialchars($settings['system_email'] ?? ''); ?>" required>
                                        <div class="form-text">Email used for sending notifications</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="pagination_limit" class="form-label">Pagination Limit</label>
                                        <input type="number" class="form-control" id="pagination_limit" name="pagination_limit" value="<?php echo htmlspecialchars($settings['pagination_limit'] ?? '10'); ?>" min="5" max="100" required>
                                        <div class="form-text">Number of items per page in tables</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>" min="5" max="240" required>
                                        <div class="form-text">User session timeout in minutes</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="maintenance_mode">Maintenance Mode</label>
                                        </div>
                                        <div class="form-text">Enable maintenance mode (only admins can access)</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="maintenance_message" class="form-label">Maintenance Message</label>
                                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="2"><?php echo htmlspecialchars($settings['maintenance_message'] ?? ''); ?></textarea>
                                        <div class="form-text">Message displayed during maintenance mode</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Leave Settings -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingLeave">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLeave" aria-expanded="false" aria-controls="collapseLeave">
                                Leave Settings
                            </button>
                        </h2>
                        <div id="collapseLeave" class="accordion-collapse collapse" aria-labelledby="headingLeave" data-bs-parent="#settingsAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="leave_approval_levels" class="form-label">Approval Levels</label>
                                        <select class="form-select" id="leave_approval_levels" name="leave_approval_levels">
                                            <option value="1" <?php echo ($settings['leave_approval_levels'] ?? '3') == '1' ? 'selected' : ''; ?>>1 - Department Head Only</option>
                                            <option value="2" <?php echo ($settings['leave_approval_levels'] ?? '3') == '2' ? 'selected' : ''; ?>>2 - Department Head & Dean</option>
                                            <option value="3" <?php echo ($settings['leave_approval_levels'] ?? '3') == '3' ? 'selected' : ''; ?>>3 - Department Head, Dean & Principal</option>
                                        </select>
                                        <div class="form-text">Number of approval levels required</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="leave_apply_min_days_before" class="form-label">Minimum Days Before</label>
                                        <input type="number" class="form-control" id="leave_apply_min_days_before" name="leave_apply_min_days_before" value="<?php echo htmlspecialchars($settings['leave_apply_min_days_before'] ?? '3'); ?>" min="0" max="30" required>
                                        <div class="form-text">Minimum days before leave start date to apply</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fiscal_year_start" class="form-label">Fiscal Year Start (MM-DD)</label>
                                        <input type="text" class="form-control" id="fiscal_year_start" name="fiscal_year_start" value="<?php echo htmlspecialchars($settings['fiscal_year_start'] ?? '01-01'); ?>" pattern="[0-1][0-9]-[0-3][0-9]" required>
                                        <div class="form-text">Start date of fiscal year (MM-DD)</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fiscal_year_end" class="form-label">Fiscal Year End (MM-DD)</label>
                                        <input type="text" class="form-control" id="fiscal_year_end" name="fiscal_year_end" value="<?php echo htmlspecialchars($settings['fiscal_year_end'] ?? '12-31'); ?>" pattern="[0-1][0-9]-[0-3][0-9]" required>
                                        <div class="form-text">End date of fiscal year (MM-DD)</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="leave_carry_forward_limit" class="form-label">Carry Forward Limit</label>
                                        <input type="number" class="form-control" id="leave_carry_forward_limit" name="leave_carry_forward_limit" value="<?php echo htmlspecialchars($settings['leave_carry_forward_limit'] ?? '10'); ?>" min="0" max="100" required>
                                        <div class="form-text">Maximum days that can be carried forward to next year</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Settings -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDocument">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDocument" aria-expanded="false" aria-controls="collapseDocument">
                                Document Settings
                            </button>
                        </h2>
                        <div id="collapseDocument" class="accordion-collapse collapse" aria-labelledby="headingDocument" data-bs-parent="#settingsAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_document_upload" name="enable_document_upload" value="1" <?php echo ($settings['enable_document_upload'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_document_upload">Enable Document Upload</label>
                                        </div>
                                        <div class="form-text">Allow users to upload supporting documents</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="max_file_size" class="form-label">Maximum File Size (MB)</label>
                                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" value="<?php echo htmlspecialchars($settings['max_file_size'] ?? '5'); ?>" min="1" max="20" required>
                                        <div class="form-text">Maximum file size for attachments in MB</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                                        <input type="text" class="form-control" id="allowed_file_types" name="allowed_file_types" value="<?php echo htmlspecialchars($settings['allowed_file_types'] ?? 'pdf,doc,docx,jpg,jpeg,png'); ?>" required>
                                        <div class="form-text">Comma-separated list of allowed file extensions</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingNotification">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNotification" aria-expanded="false" aria-controls="collapseNotification">
                                Notification Settings
                            </button>
                        </h2>
                        <div id="collapseNotification" class="accordion-collapse collapse" aria-labelledby="headingNotification" data-bs-parent="#settingsAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_email_notifications" name="enable_email_notifications" value="1" <?php echo ($settings['enable_email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_email_notifications">Enable Email Notifications</label>
                                        </div>
                                        <div class="form-text">Send email notifications for leave applications and approvals</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="submit" class="btn btn-primary">Save Settings</button>
                    <a href="/dashboards/hr_admin_dashboard.php" class="btn btn-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>