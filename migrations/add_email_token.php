<?php
/**
 * Migration: Add email_token column to leave_approvals table
 * This enables one-click approve/reject from email
 * 
 * Run: http://localhost/LeaveTracker/migrations/add_email_token.php
 */

require_once '../config/db.php';

echo "<!DOCTYPE html><html><head><title>Migration - Add Email Token</title>
<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
    h1 { color: #333; }
</style>
</head><body>
<h1>Migration: Add Email Token to Leave Approvals</h1>";

try {
    // Check if column exists
    $checkSql = "SHOW COLUMNS FROM leave_approvals LIKE 'email_token'";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<div class='info'><strong>Info:</strong> Column 'email_token' already exists. Skipping.</div>";
    } else {
        $sql = "ALTER TABLE `leave_approvals` 
                ADD COLUMN `email_token` VARCHAR(64) NULL AFTER `comments`,
                ADD COLUMN `token_used` TINYINT(1) DEFAULT 0 AFTER `email_token`,
                ADD COLUMN `token_expires_at` DATETIME NULL AFTER `token_used`";
        $conn->exec($sql);
        echo "<div class='success'><strong>Success:</strong> Columns 'email_token', 'token_used', 'token_expires_at' added!</div>";
        
        // Add index for fast token lookup
        $conn->exec("ALTER TABLE `leave_approvals` ADD INDEX `idx_email_token` (`email_token`)");
        echo "<div class='success'><strong>Success:</strong> Index on email_token created!</div>";
    }
    
    echo "<div class='success'><strong>Migration Completed!</strong></div>";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>Error:</strong> " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
