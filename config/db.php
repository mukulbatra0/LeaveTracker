<?php
/**
 * Database Connection Configuration
 * Simple and clean database connection for ELMS
 * 
 * Note: Run database_setup.php first to initialize the database
 */

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

// Connect to MySQL database
try {
    $conn = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    
    // Set PDO attributes for better security and performance
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch(PDOException $e) {
    // Log the actual error for debugging
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show helpful error message
    if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        die("Database connection failed: " . $e->getMessage() . 
            "\n\nPlease ensure:\n" .
            "1. MySQL is running\n" .
            "2. Database '" . DB_NAME . "' exists\n" .
            "3. Run 'php database_setup.php' to initialize the database\n" .
            "4. Check your .env file configuration");
    }
    
    // Generic error for production
    die("Database connection failed. Please run the database setup script first.");
}
?>