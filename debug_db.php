<?php
// Include database connection
require_once 'config/db.php';

try {
    echo "Testing database connection...\n";
    
    // Test connection
    $stmt = $conn->query("SELECT 1");
    echo "✅ Database connection successful!\n\n";
    
    // Show all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Available tables:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\n";
    
    // Check if leave_balances table exists and its structure
    if (in_array('leave_balances', $tables)) {
        echo "leave_balances table structure:\n";
        $stmt = $conn->query("DESCRIBE leave_balances");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- {$row['Field']} ({$row['Type']}) - {$row['Null']} - {$row['Key']}\n";
        }
    } else {
        echo "❌ leave_balances table does not exist!\n";
    }
    
    echo "\n";
    
    // Check leave_applications table structure
    if (in_array('leave_applications', $tables)) {
        echo "leave_applications table structure:\n";
        $stmt = $conn->query("DESCRIBE leave_applications");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- {$row['Field']} ({$row['Type']}) - {$row['Null']} - {$row['Key']}\n";
        }
    } else {
        echo "❌ leave_applications table does not exist!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>