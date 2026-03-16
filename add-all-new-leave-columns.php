<?php
/**
 * Comprehensive migration script to add all new columns to leave_applications table
 * - is_half_day (for half day leave)
 * - half_day_period (first_half or second_half)
 * - mode_of_transport (for official work during leave)
 * - work_adjustment (work arrangements during leave)
 */

require_once 'config/db.php';

try {
    echo "Starting comprehensive migration for leave_applications table...\n\n";
    
    $columns_added = 0;
    $columns_skipped = 0;
    
    // Check and add is_half_day column
    $check_sql = "SHOW COLUMNS FROM leave_applications LIKE 'is_half_day'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $alter_sql = "ALTER TABLE leave_applications 
                      ADD COLUMN is_half_day TINYINT(1) DEFAULT 0 AFTER attachment";
        $conn->exec($alter_sql);
        echo "✓ Added is_half_day column.\n";
        $columns_added++;
    } else {
        echo "- is_half_day column already exists.\n";
        $columns_skipped++;
    }
    
    // Check and add half_day_period column
    $check_sql = "SHOW COLUMNS FROM leave_applications LIKE 'half_day_period'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $alter_sql = "ALTER TABLE leave_applications 
                      ADD COLUMN half_day_period ENUM('first_half', 'second_half') NULL AFTER is_half_day";
        $conn->exec($alter_sql);
        echo "✓ Added half_day_period column.\n";
        $columns_added++;
    } else {
        echo "- half_day_period column already exists.\n";
        $columns_skipped++;
    }
    
    // Check and add mode_of_transport column
    $check_sql = "SHOW COLUMNS FROM leave_applications LIKE 'mode_of_transport'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $alter_sql = "ALTER TABLE leave_applications 
                      ADD COLUMN mode_of_transport VARCHAR(255) NULL AFTER half_day_period";
        $conn->exec($alter_sql);
        echo "✓ Added mode_of_transport column.\n";
        $columns_added++;
    } else {
        echo "- mode_of_transport column already exists.\n";
        $columns_skipped++;
    }
    
    // Check and add work_adjustment column
    $check_sql = "SHOW COLUMNS FROM leave_applications LIKE 'work_adjustment'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $alter_sql = "ALTER TABLE leave_applications 
                      ADD COLUMN work_adjustment TEXT NULL AFTER mode_of_transport";
        $conn->exec($alter_sql);
        echo "✓ Added work_adjustment column.\n";
        $columns_added++;
    } else {
        echo "- work_adjustment column already exists.\n";
        $columns_skipped++;
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Migration completed successfully!\n";
    echo "Columns added: $columns_added\n";
    echo "Columns skipped (already exist): $columns_skipped\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "The leave_applications table now supports:\n";
    echo "  • Half-day leave (first half or second half)\n";
    echo "  • Mode of transport for official work\n";
    echo "  • Work adjustment during leave period\n\n";
    
    echo "You can now safely delete this migration file.\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
