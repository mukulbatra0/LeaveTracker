<?php
/**
 * Migration Script: Add designation column to leave_applications table
 * 
 * This script adds the designation column to the leave_applications table
 * if it doesn't already exist.
 * 
 * Usage: Run this file directly in your browser or via CLI
 *        php migrations/run_designation_migration.php
 */

// Include database connection
require_once __DIR__ . '/../config/db.php';

echo "Starting migration: Add designation column to leave_applications table\n";
echo str_repeat("=", 70) . "\n\n";

try {
    // Check if the column already exists
    $check_sql = "SELECT COUNT(*) as count 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'leave_applications' 
                  AND column_name = 'designation'";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        echo "✓ Column 'designation' already exists in leave_applications table.\n";
        echo "  No migration needed.\n\n";
    } else {
        echo "→ Adding 'designation' column to leave_applications table...\n";
        
        // Add the designation column
        $alter_sql = "ALTER TABLE `leave_applications` 
                      ADD COLUMN `designation` VARCHAR(100) NULL AFTER `reason`";
        
        $conn->exec($alter_sql);
        
        echo "✓ Successfully added 'designation' column.\n\n";
        
        // Update table comment
        $comment_sql = "ALTER TABLE `leave_applications` 
                        COMMENT = 'Leave applications table with designation field'";
        $conn->exec($comment_sql);
        
        echo "✓ Updated table comment.\n\n";
    }
    
    echo str_repeat("=", 70) . "\n";
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "\n✗ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Please check:\n";
    echo "1. Database connection is working\n";
    echo "2. You have ALTER privileges on the database\n";
    echo "3. The leave_applications table exists\n\n";
    exit(1);
}
?>
