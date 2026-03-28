<?php
/**
 * Migration Script: Add Contact Fields to Leave Applications
 * 
 * This script adds visit_address and contact_number columns to the leave_applications table
 * 
 * Run this file once by accessing it through your browser:
 * http://localhost/LeaveTracker/migrations/run_contact_fields_migration.php
 */

// Include database connection
require_once '../config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Add Contact Fields</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Database Migration: Add Contact Fields to Leave Applications</h1>";

try {
    // Check if columns already exist
    $checkSql = "SHOW COLUMNS FROM leave_applications LIKE 'visit_address'";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<div class='info'><strong>Info:</strong> Column 'visit_address' already exists. Skipping migration.</div>";
    } else {
        echo "<div class='info'><strong>Step 1:</strong> Adding 'visit_address' column...</div>";
        
        // Add visit_address column
        $sql1 = "ALTER TABLE `leave_applications` 
                 ADD COLUMN `visit_address` TEXT NULL AFTER `work_adjustment`";
        $conn->exec($sql1);
        
        echo "<div class='success'><strong>Success:</strong> Column 'visit_address' added successfully!</div>";
    }
    
    // Check if contact_number column exists
    $checkSql2 = "SHOW COLUMNS FROM leave_applications LIKE 'contact_number'";
    $stmt2 = $conn->prepare($checkSql2);
    $stmt2->execute();
    
    if ($stmt2->rowCount() > 0) {
        echo "<div class='info'><strong>Info:</strong> Column 'contact_number' already exists. Skipping migration.</div>";
    } else {
        echo "<div class='info'><strong>Step 2:</strong> Adding 'contact_number' column...</div>";
        
        // Add contact_number column
        $sql2 = "ALTER TABLE `leave_applications` 
                 ADD COLUMN `contact_number` VARCHAR(20) NULL AFTER `visit_address`";
        $conn->exec($sql2);
        
        echo "<div class='success'><strong>Success:</strong> Column 'contact_number' added successfully!</div>";
    }
    
    echo "<div class='success'><strong>Migration Completed Successfully!</strong></div>";
    
    // Show updated table structure
    echo "<h2>Updated Table Structure:</h2>";
    $descSql = "DESCRIBE leave_applications";
    $descStmt = $conn->prepare($descSql);
    $descStmt->execute();
    $columns = $descStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo str_pad("Field", 30) . str_pad("Type", 30) . str_pad("Null", 10) . str_pad("Key", 10) . "Extra\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($columns as $column) {
        echo str_pad($column['Field'], 30) . 
             str_pad($column['Type'], 30) . 
             str_pad($column['Null'], 10) . 
             str_pad($column['Key'], 10) . 
             $column['Extra'] . "\n";
    }
    echo "</pre>";
    
    echo "<div class='info'><strong>Next Steps:</strong> You can now use the leave application form with the new contact fields!</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>Error:</strong> " . $e->getMessage() . "</div>";
    echo "<div class='info'><strong>Troubleshooting:</strong> Make sure your database connection is working and you have ALTER privileges.</div>";
}

echo "</body></html>";
?>
