<?php
/**
 * Migration script to add missing profile columns to users table
 */

require_once 'db.php';

try {
    echo "Starting migration to add profile columns...\n";
    
    // Check if address column exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = ? 
                           AND TABLE_NAME = 'users' 
                           AND COLUMN_NAME = 'address'");
    $stmt->execute([DB_NAME]);
    $address_exists = $stmt->fetchColumn() > 0;
    
    // Check if emergency_contact column exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = ? 
                           AND TABLE_NAME = 'users' 
                           AND COLUMN_NAME = 'emergency_contact'");
    $stmt->execute([DB_NAME]);
    $emergency_contact_exists = $stmt->fetchColumn() > 0;
    
    // Add address column if it doesn't exist
    if (!$address_exists) {
        $conn->exec("ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL AFTER phone");
        echo "✓ Added 'address' column to users table\n";
    } else {
        echo "✓ 'address' column already exists\n";
    }
    
    // Add emergency_contact column if it doesn't exist
    if (!$emergency_contact_exists) {
        $conn->exec("ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(255) DEFAULT NULL AFTER address");
        echo "✓ Added 'emergency_contact' column to users table\n";
    } else {
        echo "✓ 'emergency_contact' column already exists\n";
    }
    
    echo "\nMigration completed successfully!\n";
    echo "The profile update functionality should now work properly.\n";
    
} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>