<?php
/**
 * Quick script to disable email notifications
 * Useful for development environments like XAMPP where SMTP is not configured
 */

// Include database connection
require_once 'db.php';

try {
    // Check if settings table exists (could be system_settings or settings)
    $tables_to_check = ['system_settings', 'settings'];
    $table_found = false;
    $table_name = '';
    
    foreach ($tables_to_check as $table) {
        try {
            $check_sql = "SELECT COUNT(*) FROM $table LIMIT 1";
            $conn->query($check_sql);
            $table_name = $table;
            $table_found = true;
            break;
        } catch (PDOException $e) {
            // Table doesn't exist, try next one
            continue;
        }
    }
    
    if (!$table_found) {
        echo "❌ No settings table found. Please run setup_system_settings.php first.\n";
        exit(1);
    }
    
    echo "📧 Disabling email notifications in $table_name table...\n";
    
    // Update the email notification setting
    $update_sql = "UPDATE $table_name SET setting_value = '0' WHERE setting_key = 'enable_email_notifications'";
    $stmt = $conn->prepare($update_sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Email notifications have been disabled successfully.\n";
    } else {
        // Try to insert the setting if it doesn't exist
        $insert_sql = "INSERT INTO $table_name (setting_key, setting_value, setting_description) VALUES ('enable_email_notifications', '0', 'Enable or disable email notifications (1=enabled, 0=disabled)')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->execute();
        echo "✅ Email notification setting created and disabled.\n";
    }
    
    // Verify the change
    $verify_sql = "SELECT setting_value FROM $table_name WHERE setting_key = 'enable_email_notifications'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->execute();
    $current_value = $verify_stmt->fetchColumn();
    
    echo "📋 Current email notification status: " . ($current_value == '1' ? 'ENABLED' : 'DISABLED') . "\n";
    echo "💡 You can now approve/reject leave applications without email errors.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>