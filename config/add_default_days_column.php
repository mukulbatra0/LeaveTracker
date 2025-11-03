<?php
/**
 * Script to add default_days column to leave_types table
 */

// Include database connection
require_once 'db.php';

try {
    echo "Adding default_days column to leave_types table...\n";
    
    // Add the missing column
    $alter_sql = "ALTER TABLE leave_types ADD COLUMN IF NOT EXISTS default_days INT DEFAULT 0 AFTER name";
    $conn->exec($alter_sql);
    
    echo "✅ Added default_days column successfully.\n";
    
    // Update existing leave types with sensible default values
    echo "Setting default values for existing leave types...\n";
    
    $update_queries = [
        "UPDATE leave_types SET default_days = 21 WHERE LOWER(name) LIKE '%annual%' OR LOWER(name) LIKE '%vacation%'",
        "UPDATE leave_types SET default_days = 10 WHERE LOWER(name) LIKE '%sick%'",
        "UPDATE leave_types SET default_days = 90 WHERE LOWER(name) LIKE '%maternity%'",
        "UPDATE leave_types SET default_days = 15 WHERE LOWER(name) LIKE '%paternity%'",
        "UPDATE leave_types SET default_days = 12 WHERE LOWER(name) LIKE '%casual%'",
        "UPDATE leave_types SET default_days = 10 WHERE default_days = 0" // Fallback for any remaining
    ];
    
    foreach ($update_queries as $query) {
        $conn->exec($query);
    }
    
    echo "✅ Updated default values for existing leave types.\n";
    
    // Verify the changes
    $verify_sql = "SELECT name, default_days FROM leave_types";
    $stmt = $conn->prepare($verify_sql);
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent leave types with default days:\n";
    foreach ($leave_types as $type) {
        echo "- {$type['name']}: {$type['default_days']} days\n";
    }
    
    echo "\n✅ Setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>