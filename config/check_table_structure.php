<?php
/**
 * Script to check table structures and fix missing columns
 */

// Include database connection
require_once 'db.php';

try {
    echo "Checking leave_types table structure...\n";
    
    // Check current structure of leave_types table
    $stmt = $conn->prepare("DESCRIBE leave_types");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current leave_types table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    // Check if default_days column exists
    $has_default_days = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'default_days') {
            $has_default_days = true;
            break;
        }
    }
    
    if (!$has_default_days) {
        echo "\n❌ Missing 'default_days' column in leave_types table.\n";
        echo "Adding default_days column...\n";
        
        $alter_sql = "ALTER TABLE leave_types ADD COLUMN default_days INT DEFAULT 0 AFTER name";
        $conn->exec($alter_sql);
        
        echo "✅ Added default_days column successfully.\n";
        
        // Update existing leave types with default values
        echo "Setting default values for existing leave types...\n";
        $update_sql = "UPDATE leave_types SET default_days = CASE 
                       WHEN LOWER(name) LIKE '%annual%' OR LOWER(name) LIKE '%vacation%' THEN 21
                       WHEN LOWER(name) LIKE '%sick%' THEN 10
                       WHEN LOWER(name) LIKE '%maternity%' THEN 90
                       WHEN LOWER(name) LIKE '%paternity%' THEN 15
                       WHEN LOWER(name) LIKE '%casual%' THEN 12
                       ELSE 10
                       END";
        $conn->exec($update_sql);
        
        echo "✅ Updated default values for existing leave types.\n";
    } else {
        echo "\n✅ default_days column already exists.\n";
    }
    
    // Show final structure
    echo "\nFinal leave_types table structure:\n";
    $stmt = $conn->prepare("DESCRIBE leave_types");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>