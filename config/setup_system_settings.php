<?php
/**
 * Setup script to create system_settings table
 * Run this script to fix the missing system_settings table error
 */

// Include database connection
require_once 'db.php';

try {
    echo "Creating system_settings table...\n";
    
    // Create system_settings table
    $create_table_sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->exec($create_table_sql);
    echo "✓ system_settings table created successfully.\n";
    
    // Insert default settings
    echo "Inserting default settings...\n";
    
    $default_settings = [
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
        ['maintenance_message', 'System is under maintenance. Please try again later.', 'Message displayed during maintenance mode'],
        ['institution_name', 'Your Institution Name', 'Name of the institution'],
        ['institution_address', 'Your Institution Address', 'Address of the institution'],
        ['institution_phone', '+1234567890', 'Phone number of the institution'],
        ['institution_email', 'info@institution.com', 'Email address of the institution'],
        ['default_approval_chain', 'department_head,dean,principal,hr_admin', 'Default approval workflow for leave applications'],
        ['max_consecutive_leave_days', '30', 'Maximum consecutive leave days allowed'],
        ['min_days_before_application', '3', 'Minimum days before leave start date to apply'],
        ['enable_weekend_leaves', '0', 'Allow leave applications on weekends (1=enabled, 0=disabled)'],
        ['enable_holiday_leaves', '0', 'Allow leave applications on holidays (1=enabled, 0=disabled)']
    ];
    
    $insert_sql = "INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    
    $inserted_count = 0;
    foreach ($default_settings as $setting) {
        $stmt->execute($setting);
        if ($stmt->rowCount() > 0) {
            $inserted_count++;
        }
    }
    
    echo "✓ Inserted $inserted_count default settings.\n";
    
    // Verify table creation
    $verify_sql = "SELECT COUNT(*) FROM system_settings";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->execute();
    $count = $verify_stmt->fetchColumn();
    
    echo "✓ Verification: system_settings table now contains $count records.\n";
    echo "✓ Setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>