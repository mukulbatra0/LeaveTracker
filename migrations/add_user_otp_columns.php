<?php
/**
 * Migration: Add OTP columns to users table
 * These columns store OTPs for user-initiated password changes
 */

require_once __DIR__ . '/../config/db.php';

try {
    // Check if columns already exist
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'");
    $otp_code_exists = $stmt->rowCount() > 0;
    
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_expiry'");
    $otp_expiry_exists = $stmt->rowCount() > 0;
    
    if (!$otp_code_exists) {
        $conn->exec("ALTER TABLE users ADD COLUMN otp_code VARCHAR(6) NULL AFTER password");
        echo "✅ Column 'otp_code' added to users table.\n";
    } else {
        echo "ℹ️  Column 'otp_code' already exists in users table.\n";
    }
    
    if (!$otp_expiry_exists) {
        $conn->exec("ALTER TABLE users ADD COLUMN otp_expiry DATETIME NULL AFTER otp_code");
        echo "✅ Column 'otp_expiry' added to users table.\n";
    } else {
        echo "ℹ️  Column 'otp_expiry' already exists in users table.\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Users can now use OTP verification for password changes.\n";
    
} catch (PDOException $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
