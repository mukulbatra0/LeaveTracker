<?php
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
define('DB_SERVER', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USERNAME', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'elms_db');

// Attempt to connect to MySQL database
try {
    // First test connection to MySQL server
    $test_conn = new PDO("mysql:host=" . DB_SERVER, DB_USERNAME, DB_PASSWORD);
    $test_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $stmt = $test_conn->prepare("SHOW DATABASES LIKE ?");
    $stmt->execute([DB_NAME]);
    
    if ($stmt->rowCount() === 0) {
        // Create database if it doesn't exist
        $test_conn->exec("CREATE DATABASE `" . DB_NAME . "`");
    }
    
    // Now connect to the specific database
    $conn = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Set emulate prepares to false for better security
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch(PDOException $e) {
    // Log the actual error for debugging
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show more specific error in development
    if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        die("Database connection failed: " . $e->getMessage() . "\nHost: " . DB_SERVER . "\nDatabase: " . DB_NAME . "\nUser: " . DB_USERNAME);
    }
    
    // Generic error for production
    die("Database connection failed. Please check your database configuration and ensure MySQL is running.");
}
?>