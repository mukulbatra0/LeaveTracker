<?php
/**
 * Migration script to add mode_of_transport and work_adjustment columns to leave_applications table
 */

require_once 'config/db.php';

try {
    echo "Starting migration to add transport and work adjustment columns...\n";
    
    // Check if columns already exist
    $check_sql = "SHOW COLUMNS FROM leave_applications LIKE 'mode_of_transport'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        echo "Columns already exist. No migration needed.\n";
        exit;
    }
    
    // Add mode_of_transport column
    $alter_sql1 = "ALTER TABLE leave_applications 
                   ADD COLUMN mode_of_transport VARCHAR(255) NULL AFTER half_day_period";
    $conn->exec($alter_sql1);
    echo "Added mode_of_transport column.\n";
    
    // Add work_adjustment column
    $alter_sql2 = "ALTER TABLE leave_applications 
                   ADD COLUMN work_adjustment TEXT NULL AFTER mode_of_transport";
    $conn->exec($alter_sql2);
    echo "Added work_adjustment column.\n";
    
    echo "\nMigration completed successfully!\n";
    echo "The leave_applications table now supports mode of transport and work adjustment fields.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
