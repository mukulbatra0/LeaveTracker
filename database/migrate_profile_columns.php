<?php
// Database migration script to add profile-related columns
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting database migration for profile columns...\n";
    
    // Check if columns already exist before adding them
    $columns_to_add = [
        'profile_picture' => "VARCHAR(255) NULL COMMENT 'Path to user profile picture'",
        'phone' => "VARCHAR(20) NULL COMMENT 'User phone number'",
        'address' => "TEXT NULL COMMENT 'User address'",
        'emergency_contact' => "VARCHAR(255) NULL COMMENT 'Emergency contact information'"
    ];
    
    foreach ($columns_to_add as $column_name => $column_definition) {
        // Check if column exists
        $check_sql = "SHOW COLUMNS FROM users LIKE '$column_name'";
        $result = $conn->query($check_sql);
        
        if ($result->rowCount() == 0) {
            // Column doesn't exist, add it
            $alter_sql = "ALTER TABLE users ADD COLUMN $column_name $column_definition";
            $conn->exec($alter_sql);
            echo "✓ Added column: $column_name\n";
        } else {
            echo "- Column already exists: $column_name\n";
        }
    }
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>