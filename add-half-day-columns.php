<?php
/**
 * Migration script to add half day leave columns to leave_applications table
 */

require_once 'config/db.php';

try {
    echo "Starting migration to add half day leave columns...\n";
    
    // Check if columns already exist
    $check_sql = "SHOW COLUMNS FROM leave_applications LIKE 'is_half_day'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        echo "Columns already exist. No migration needed.\n";
        exit;
    }
    
    // Add is_half_day column
    $alter_sql1 = "ALTER TABLE leave_applications 
                   ADD COLUMN is_half_day TINYINT(1) DEFAULT 0 AFTER attachment";
    $conn->exec($alter_sql1);
    echo "Added is_half_day column.\n";
    
    // Add half_day_period column
    $alter_sql2 = "ALTER TABLE leave_applications 
                   ADD COLUMN half_day_period ENUM('first_half', 'second_half') NULL AFTER is_half_day";
    $conn->exec($alter_sql2);
    echo "Added half_day_period column.\n";
    
    echo "\nMigration completed successfully!\n";
    echo "The leave_applications table now supports half day leave functionality.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
