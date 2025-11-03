<?php
session_start();

echo "Testing admin connection...\n";

// Set test session variables
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "Session variables set.\n";

try {
    // Include database connection
    require_once 'config/db.php';
    echo "✅ Database connection successful!\n";
    
    // Test a simple query
    $stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ Query successful! Found {$result['user_count']} users.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>