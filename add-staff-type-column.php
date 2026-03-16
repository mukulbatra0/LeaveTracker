<?php
/**
 * Migration Script: Add staff_type column to users table
 * This script adds a staff_type column to distinguish between teaching and non-teaching staff
 */

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Database configuration
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'leave_management';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    // Create PDO connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n\n";
    
    // Check if staff_type column already exists
    $check_sql = "SHOW COLUMNS FROM users LIKE 'staff_type'";
    $check_stmt = $conn->query($check_sql);
    
    if ($check_stmt->rowCount() > 0) {
        echo "✓ Column 'staff_type' already exists in users table.\n";
        echo "No changes needed.\n";
    } else {
        echo "Adding 'staff_type' column to users table...\n";
        
        // Add staff_type column
        $alter_sql = "ALTER TABLE users 
                      ADD COLUMN staff_type VARCHAR(20) DEFAULT NULL 
                      AFTER employee_id";
        $conn->exec($alter_sql);
        
        echo "✓ Column 'staff_type' added successfully!\n\n";
        
        // Optional: Set default value for existing users
        echo "Do you want to set a default staff type for existing users? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        
        if (trim($line) == 'y' || trim($line) == 'Y') {
            echo "Enter default staff type (teaching/non_teaching): ";
            $default_type = trim(fgets($handle));
            
            if ($default_type === 'teaching' || $default_type === 'non_teaching') {
                $update_sql = "UPDATE users SET staff_type = :staff_type WHERE staff_type IS NULL";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':staff_type', $default_type, PDO::PARAM_STR);
                $update_stmt->execute();
                
                $affected = $update_stmt->rowCount();
                echo "✓ Updated $affected existing user(s) with staff_type = '$default_type'\n";
            } else {
                echo "Invalid staff type. Skipping default value update.\n";
            }
        }
        
        fclose($handle);
    }
    
    echo "\n=== Migration completed successfully! ===\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
