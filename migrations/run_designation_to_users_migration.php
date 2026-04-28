<?php
/**
 * Migration Script: Add Designation Column to Users Table
 * This script adds the designation field to the users table
 */

require_once '../config/db.php';

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Designation to Users - Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .migration-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .step { padding: 10px; margin: 10px 0; border-left: 4px solid #007bff; background: #f8f9fa; }
        .badge { font-size: 14px; }
    </style>
</head>
<body>
    <div class="migration-container">
        <h2 class="mb-4">🔄 Database Migration: Add Designation to Users Table</h2>
        <p class="text-muted">This migration adds the designation column to the users table.</p>
        <hr>

        <?php
        try {
            echo "<h4>Starting Migration...</h4>";
            
            // Check if designation column exists in users table
            $check = $conn->query("SHOW COLUMNS FROM users LIKE 'designation'");
            if ($check->rowCount() == 0) {
                $conn->exec("ALTER TABLE users ADD COLUMN designation VARCHAR(100) NULL AFTER employment_type");
                echo "<div class='step'><span class='badge bg-success'>✓ ADDED</span> <code>designation</code> column to users table</div>";
            } else {
                echo "<div class='step'><span class='badge bg-info'>ℹ SKIPPED</span> <code>designation</code> column already exists in users table</div>";
            }
            
            echo "<hr>";
            echo "<div class='alert alert-success mt-4'>";
            echo "<h5>✅ Migration Completed Successfully!</h5>";
            echo "<p class='mb-0'>The designation column has been added to the users table. Users can now have their designation stored in their profile.</p>";
            echo "</div>";
            
            echo "<div class='alert alert-info mt-3'>";
            echo "<h6>Next Steps:</h6>";
            echo "<ul class='mb-0'>";
            echo "<li>Update user profiles to add designation information</li>";
            echo "<li>The leave application form will now auto-populate designation from user profile</li>";
            echo "</ul>";
            echo "</div>";
            
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger mt-4'>";
            echo "<h5>❌ Migration Failed</h5>";
            echo "<p class='mb-0'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        ?>
        
        <div class="mt-4">
            <a href="../index.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>
