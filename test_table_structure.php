<?php
// Include database connection
require_once 'config/db.php';

try {
    echo "Checking leave_balances table structure...\n";
    
    // Check table structure
    $stmt = $conn->prepare("DESCRIBE leave_balances");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current leave_balances table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\nChecking leave_applications table structure...\n";
    
    // Check leave_applications table structure
    $stmt = $conn->prepare("DESCRIBE leave_applications");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current leave_applications table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>