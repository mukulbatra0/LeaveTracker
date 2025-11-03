<?php
/**
 * Test database connection to diagnose the issue
 */

echo "Testing database connection...\n";

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
$db_name = $_ENV['DB_NAME'] ?? 'leavetracker_db';

echo "Database Configuration:\n";
echo "Host: $db_host\n";
echo "User: $db_user\n";
echo "Password: " . (empty($db_pass) ? '(empty)' : '(set)') . "\n";
echo "Database: $db_name\n\n";

// Test connection without database first
try {
    echo "Testing connection to MySQL server...\n";
    $conn_test = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $conn_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ MySQL server connection successful!\n";
    
    // Check if database exists
    echo "Checking if database '$db_name' exists...\n";
    $stmt = $conn_test->prepare("SHOW DATABASES LIKE ?");
    $stmt->execute([$db_name]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Database '$db_name' exists!\n";
        
        // Test connection to specific database
        echo "Testing connection to database '$db_name'...\n";
        $conn_db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $conn_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Database connection successful!\n";
        
        // Test a simple query
        $stmt = $conn_db->prepare("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = ?");
        $stmt->execute([$db_name]);
        $result = $stmt->fetch();
        echo "✅ Database has {$result['table_count']} tables.\n";
        
    } else {
        echo "❌ Database '$db_name' does not exist!\n";
        echo "Creating database '$db_name'...\n";
        $conn_test->exec("CREATE DATABASE `$db_name`");
        echo "✅ Database '$db_name' created successfully!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    
    // Check if it's a specific error
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "\n🔧 Possible solutions:\n";
        echo "1. Check if MySQL/XAMPP is running\n";
        echo "2. Verify username and password in .env file\n";
        echo "3. Make sure MySQL is running on port 3306\n";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "\n🔧 Possible solutions:\n";
        echo "1. Start XAMPP/MySQL service\n";
        echo "2. Check if MySQL is running on localhost:3306\n";
    }
}
?>