<?php
/**
 * Script to add is_active column to leave_types table
 */

// Include database connection
require_once 'db.php';

try {
    echo "Adding is_active column to leave_types table...\n";
    
    // Add the missing column
    $alter_sql = "ALTER TABLE leave_types ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER max_consecutive_days";
    $conn->exec($alter_sql);
    
    echo "✅ Added is_active column successfully.\n";
    
    // Update existing leave types to be active by default
    echo "Setting all existing leave types to active...\n";
    
    $update_sql = "UPDATE leave_types SET is_active = 1 WHERE is_active IS NULL";
    $conn->exec($update_sql);
    
    echo "✅ Updated is_active values for existing leave types.\n";
    
    // Verify the changes
    $verify_sql = "SELECT name, is_active FROM leave_types";
    $stmt = $conn->prepare($verify_sql);
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent leave types with is_active status:\n";
    foreach ($leave_types as $type) {
        $status = $type['is_active'] ? 'Active' : 'Inactive';
        echo "- {$type['name']}: {$status}\n";
    }
    
    echo "\n✅ is_active column setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>