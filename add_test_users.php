<?php
/**
 * Script to add test users including a Director for testing leave approval workflow
 * Run this script once to set up test users
 */

require_once 'config/db.php';

try {
    echo "Adding test users for leave approval workflow...\n\n";
    
    // Check if tables exist first
    $tables_exist = true;
    $required_tables = ['users', 'departments', 'leave_types'];
    
    foreach ($required_tables as $table) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                $tables_exist = false;
                echo "Error: Table '$table' does not exist.\n";
            }
        } catch (PDOException $e) {
            $tables_exist = false;
            echo "Error checking table '$table': " . $e->getMessage() . "\n";
        }
    }
    
    if (!$tables_exist) {
        echo "\nPlease run 'php setup_database.php' first to create the database structure.\n";
        exit(1);
    }
    
    // Insert test users directly
    $users_sql = "
    INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
    ('DIR001', 'Dr. Alexandra', 'Thompson', 'director@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'director', 1, 'Director of Academic Affairs', '+1-555-0300', 'active'),
    ('ADM001', 'System', 'Administrator', 'admin@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'admin', 1, 'System Administrator', '+1-555-0400', 'active'),
    ('HOD001', 'Dr. Richard', 'Parker', 'hod@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'head_of_department', 1, 'Head of Computer Science', '+1-555-0500', 'active'),
    ('STF001', 'Alice', 'Johnson', 'staff@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 1, 'Assistant Professor', '+1-555-0600', 'active')
    ";
    
    try {
        $conn->exec($users_sql);
        echo "Test users inserted successfully.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "Test users already exist.\n";
        } else {
            echo "Error inserting users: " . $e->getMessage() . "\n";
        }
    }
    
    // Verify the users were created
    echo "Test users created successfully!\n\n";
    echo "Login Credentials (Password: password123 for all):\n";
    echo "================================================\n";
    
    $stmt = $conn->query("
        SELECT 
            employee_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            email,
            role,
            position,
            status
        FROM users 
        WHERE employee_id IN ('DIR001', 'ADM001', 'HOD001', 'STF001')
        ORDER BY 
            CASE role 
                WHEN 'admin' THEN 1
                WHEN 'director' THEN 2
                WHEN 'head_of_department' THEN 3
                WHEN 'staff' THEN 4
            END
    ");
    
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-8s | %-20s | %-25s | %-18s | %s\n", 
            $user['employee_id'],
            $user['full_name'],
            $user['email'],
            ucwords(str_replace('_', ' ', $user['role'])),
            $user['position']
        );
    }
    
    echo "\n";
    echo "Testing Workflow:\n";
    echo "================\n";
    echo "1. Login as STF001 (staff@college.edu) - Submit leave application\n";
    echo "2. Login as HOD001 (hod@college.edu) - Approve as Head of Department\n";
    echo "3. Login as DIR001 (director@college.edu) - Final approval as Director\n";
    echo "4. Login as ADM001 (admin@college.edu) - Admin override capabilities\n\n";
    
    // Check if departments exist
    $dept_stmt = $conn->query("SELECT COUNT(*) as count FROM departments");
    $dept_count = $dept_stmt->fetch()['count'];
    
    if ($dept_count == 0) {
        echo "Warning: No departments found. You may need to run the main database setup first.\n";
    }
    
    // Check if leave types exist
    $leave_stmt = $conn->query("SELECT COUNT(*) as count FROM leave_types");
    $leave_count = $leave_stmt->fetch()['count'];
    
    if ($leave_count == 0) {
        echo "Warning: No leave types found. You may need to run the main database setup first.\n";
    }
    
    echo "Setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>