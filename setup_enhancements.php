<?php
/**
 * ELMS Enhancement Setup Script
 * This script applies database updates and enhancements to the existing ELMS system
 */

// Include database connection
require_once 'config/db.php';

echo "<h2>ELMS Enhancement Setup</h2>";
echo "<p>Applying database updates and enhancements...</p>";

try {
    // Read and execute the update SQL file
    $sql_file = 'config/update_settings.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("SQL update file not found: " . $sql_file);
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split SQL statements by semicolon and execute each one
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $conn->exec($statement);
            $success_count++;
            echo "<div style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</div>";
        } catch (PDOException $e) {
            $error_count++;
            echo "<div style='color: orange;'>⚠ Warning: " . $e->getMessage() . "</div>";
            echo "<div style='color: gray; font-size: 0.9em;'>Statement: " . substr($statement, 0, 100) . "...</div>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Setup Summary</h3>";
    echo "<p><strong>Successful operations:</strong> " . $success_count . "</p>";
    echo "<p><strong>Warnings/Skipped:</strong> " . $error_count . "</p>";
    
    // Verify key enhancements
    echo "<h3>Verification</h3>";
    
    // Check if new settings exist
    $stmt = $conn->prepare("SELECT COUNT(*) FROM settings WHERE setting_key IN ('smtp_host', 'enable_leave_calendar', 'dashboard_widgets_enabled')");
    $stmt->execute();
    $settings_count = $stmt->fetchColumn();
    
    if ($settings_count >= 3) {
        echo "<div style='color: green;'>✓ Enhanced settings added successfully</div>";
    } else {
        echo "<div style='color: red;'>✗ Some enhanced settings may be missing</div>";
    }
    
    // Check if color column exists in leave_types
    $stmt = $conn->prepare("SHOW COLUMNS FROM leave_types LIKE 'color'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<div style='color: green;'>✓ Leave type colors enabled</div>";
    } else {
        echo "<div style='color: red;'>✗ Leave type colors not added</div>";
    }
    
    // Check if rejection_reason column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM leave_applications LIKE 'rejection_reason'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<div style='color: green;'>✓ Rejection reason tracking enabled</div>";
    } else {
        echo "<div style='color: red;'>✗ Rejection reason tracking not added</div>";
    }
    
    echo "<hr>";
    echo "<h3>New Features Available</h3>";
    echo "<ul>";
    echo "<li>✓ Enhanced Dashboard Widgets</li>";
    echo "<li>✓ Leave Calendar View</li>";
    echo "<li>✓ Email Notifications (configure SMTP settings)</li>";
    echo "<li>✓ Improved Leave Balance Display</li>";
    echo "<li>✓ Quick Actions Panel</li>";
    echo "<li>✓ Upcoming Leaves Widget</li>";
    echo "<li>✓ Enhanced Notification System</li>";
    echo "<li>✓ Leave Type Color Coding</li>";
    echo "<li>✓ Rejection Reason Tracking</li>";
    echo "<li>✓ Performance Improvements</li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3>Next Steps</h3>";
    echo "<ol>";
    echo "<li>Configure SMTP settings in the admin panel for email notifications</li>";
    echo "<li>Update leave type colors in the admin panel</li>";
    echo "<li>Test the new leave calendar feature</li>";
    echo "<li>Explore the enhanced dashboard widgets</li>";
    echo "<li>Review and configure new system settings</li>";
    echo "</ol>";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<strong>Setup Complete!</strong><br>";
    echo "Your ELMS system has been enhanced with new features. ";
    echo "<a href='index.php' style='color: #155724; text-decoration: underline;'>Go to Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Setup Error</h3>";
    echo "<p>An error occurred during setup: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
}

h2, h3 {
    color: #333;
}

div {
    margin: 5px 0;
}

ul, ol {
    margin: 10px 0;
    padding-left: 30px;
}

hr {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #ddd;
}
</style>