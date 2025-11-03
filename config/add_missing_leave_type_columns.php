<?php
/**
 * Script to add all missing columns to leave_types table
 */

// Include database connection
require_once 'db.php';

try {
    echo "Adding missing columns to leave_types table...\n";
    
    // List of columns to add
    $columns_to_add = [
        'accrual_method' => "VARCHAR(50) DEFAULT 'annual' AFTER default_days",
        'carry_forward_days' => "INT DEFAULT 0 AFTER accrual_method",
        'max_consecutive_days' => "INT DEFAULT 0 AFTER carry_forward_days"
    ];
    
    foreach ($columns_to_add as $column_name => $column_definition) {
        try {
            $alter_sql = "ALTER TABLE leave_types ADD COLUMN IF NOT EXISTS $column_name $column_definition";
            $conn->exec($alter_sql);
            echo "✅ Added $column_name column successfully.\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ️  Column $column_name already exists.\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Update existing records with sensible defaults
    echo "\nSetting default values for existing leave types...\n";
    
    $update_queries = [
        // Set accrual method based on leave type
        "UPDATE leave_types SET accrual_method = 'annual' WHERE accrual_method IS NULL OR accrual_method = ''",
        
        // Set carry forward days based on leave type
        "UPDATE leave_types SET carry_forward_days = 5 WHERE (LOWER(name) LIKE '%annual%' OR LOWER(name) LIKE '%vacation%') AND carry_forward_days = 0",
        "UPDATE leave_types SET carry_forward_days = 0 WHERE LOWER(name) LIKE '%sick%' AND carry_forward_days = 0",
        "UPDATE leave_types SET carry_forward_days = 0 WHERE LOWER(name) LIKE '%maternity%' AND carry_forward_days = 0",
        "UPDATE leave_types SET carry_forward_days = 0 WHERE LOWER(name) LIKE '%paternity%' AND carry_forward_days = 0",
        "UPDATE leave_types SET carry_forward_days = 3 WHERE LOWER(name) LIKE '%casual%' AND carry_forward_days = 0",
        
        // Set max consecutive days based on leave type
        "UPDATE leave_types SET max_consecutive_days = 30 WHERE (LOWER(name) LIKE '%annual%' OR LOWER(name) LIKE '%vacation%') AND max_consecutive_days = 0",
        "UPDATE leave_types SET max_consecutive_days = 15 WHERE LOWER(name) LIKE '%sick%' AND max_consecutive_days = 0",
        "UPDATE leave_types SET max_consecutive_days = 90 WHERE LOWER(name) LIKE '%maternity%' AND max_consecutive_days = 0",
        "UPDATE leave_types SET max_consecutive_days = 15 WHERE LOWER(name) LIKE '%paternity%' AND max_consecutive_days = 0",
        "UPDATE leave_types SET max_consecutive_days = 5 WHERE LOWER(name) LIKE '%casual%' AND max_consecutive_days = 0",
        "UPDATE leave_types SET max_consecutive_days = 15 WHERE max_consecutive_days = 0" // Fallback
    ];
    
    foreach ($update_queries as $query) {
        $conn->exec($query);
    }
    
    echo "✅ Updated default values for existing leave types.\n";
    
    // Verify the changes
    $verify_sql = "SELECT name, default_days, accrual_method, carry_forward_days, max_consecutive_days FROM leave_types LIMIT 5";
    $stmt = $conn->prepare($verify_sql);
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nSample leave types with all columns:\n";
    foreach ($leave_types as $type) {
        echo "- {$type['name']}: {$type['default_days']} days, {$type['accrual_method']}, CF: {$type['carry_forward_days']}, Max: {$type['max_consecutive_days']}\n";
    }
    
    echo "\n✅ All missing columns added successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>