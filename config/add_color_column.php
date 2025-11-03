<?php
/**
 * Script to add color column to leave_types table
 */

// Include database connection
require_once 'db.php';

try {
    echo "Adding color column to leave_types table...\n";
    
    // Add the missing column
    $alter_sql = "ALTER TABLE leave_types ADD COLUMN IF NOT EXISTS color VARCHAR(7) DEFAULT '#3498db' AFTER default_days";
    $conn->exec($alter_sql);
    
    echo "✅ Added color column successfully.\n";
    
    // Update existing leave types with different colors
    echo "Setting color values for existing leave types...\n";
    
    $update_queries = [
        "UPDATE leave_types SET color = '#e74c3c' WHERE LOWER(name) LIKE '%sick%' OR LOWER(name) LIKE '%medical%'",
        "UPDATE leave_types SET color = '#f39c12' WHERE LOWER(name) LIKE '%annual%' OR LOWER(name) LIKE '%vacation%'",
        "UPDATE leave_types SET color = '#9b59b6' WHERE LOWER(name) LIKE '%maternity%'",
        "UPDATE leave_types SET color = '#3498db' WHERE LOWER(name) LIKE '%paternity%'",
        "UPDATE leave_types SET color = '#2ecc71' WHERE LOWER(name) LIKE '%casual%'",
        "UPDATE leave_types SET color = '#95a5a6' WHERE LOWER(name) LIKE '%conference%'",
        "UPDATE leave_types SET color = '#34495e' WHERE LOWER(name) LIKE '%sabbatical%'",
        "UPDATE leave_types SET color = '#1abc9c' WHERE LOWER(name) LIKE '%invigilation%'",
        "UPDATE leave_types SET color = '#e67e22' WHERE LOWER(name) LIKE '%bereavement%'",
        "UPDATE leave_types SET color = '#7f8c8d' WHERE LOWER(name) LIKE '%unpaid%'",
        "UPDATE leave_types SET color = '#3498db' WHERE color IS NULL OR color = ''" // Fallback
    ];
    
    foreach ($update_queries as $query) {
        $conn->exec($query);
    }
    
    echo "✅ Updated color values for existing leave types.\n";
    
    // Verify the changes
    $verify_sql = "SELECT name, color FROM leave_types";
    $stmt = $conn->prepare($verify_sql);
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent leave types with colors:\n";
    foreach ($leave_types as $type) {
        echo "- {$type['name']}: {$type['color']}\n";
    }
    
    echo "\n✅ Color column setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>