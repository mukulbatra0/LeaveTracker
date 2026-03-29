<?php
/**
 * Migration: Create password_reset_otps table
 * This table stores OTPs for admin-initiated password resets
 */

require_once __DIR__ . '/../config/db.php';

try {
    // Create the password_reset_otps table
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        admin_id INT NOT NULL,
        otp_code VARCHAR(6) NOT NULL,
        is_verified TINYINT(1) DEFAULT 0,
        attempts INT DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_otp (user_id, otp_code),
        INDEX idx_expires (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "✅ Table 'password_reset_otps' created successfully.\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
?>
