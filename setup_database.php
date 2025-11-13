<?php
/**
 * Database Setup Script
 * This script will check and create the necessary database structure
 */

require_once 'config/db.php';

function executeSQL($conn, $sql, $description) {
    try {
        $conn->exec($sql);
        echo "✓ $description\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ $description - Error: " . $e->getMessage() . "\n";
        return false;
    }
}

function checkTable($conn, $tableName) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

echo "=== Database Setup for Leave Tracker ===\n\n";

// Check current database
try {
    $stmt = $conn->query("SELECT DATABASE() as db_name");
    $current_db = $stmt->fetch()['db_name'];
    echo "Current database: $current_db\n\n";
} catch (PDOException $e) {
    echo "Error checking database: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if essential tables exist
$required_tables = [
    'users', 'departments', 'leave_types', 'leave_applications', 
    'leave_approvals', 'leave_balances', 'notifications'
];

echo "Checking required tables:\n";
$missing_tables = [];
foreach ($required_tables as $table) {
    if (checkTable($conn, $table)) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' missing\n";
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    echo "\nMissing tables detected. Creating database structure...\n\n";
    
    // Create tables
    $create_tables_sql = "
    -- Create departments table
    CREATE TABLE IF NOT EXISTS `departments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `code` varchar(20) NOT NULL,
      `head_id` int(11) DEFAULT NULL,
      `description` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create users table
    CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `employee_id` varchar(20) NOT NULL,
      `first_name` varchar(50) NOT NULL,
      `last_name` varchar(50) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` enum('staff','head_of_department','director','admin') NOT NULL,
      `department_id` int(11) NOT NULL,
      `position` varchar(100) NOT NULL,
      `phone` varchar(20) DEFAULT NULL,
      `profile_image` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      PRIMARY KEY (`id`),
      UNIQUE KEY `employee_id` (`employee_id`),
      UNIQUE KEY `email` (`email`),
      KEY `department_id` (`department_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create leave_types table
    CREATE TABLE IF NOT EXISTS `leave_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `description` text,
      `max_days` int(11) NOT NULL,
      `applicable_to` set('staff','head_of_department','director','admin') NOT NULL,
      `color` varchar(7) DEFAULT '#007bff',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create leave_applications table
    CREATE TABLE IF NOT EXISTS `leave_applications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `leave_type_id` int(11) NOT NULL,
      `start_date` date NOT NULL,
      `end_date` date NOT NULL,
      `days` decimal(10,2) NOT NULL,
      `reason` text NOT NULL,
      `attachment` varchar(255) DEFAULT NULL,
      `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `leave_type_id` (`leave_type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create leave_approvals table
    CREATE TABLE IF NOT EXISTS `leave_approvals` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `leave_application_id` int(11) NOT NULL,
      `approver_id` int(11) NOT NULL,
      `approver_level` enum('head_of_department','director','admin') NOT NULL,
      `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `comments` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `leave_application_id` (`leave_application_id`),
      KEY `approver_id` (`approver_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create leave_balances table
    CREATE TABLE IF NOT EXISTS `leave_balances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `leave_type_id` int(11) NOT NULL,
      `year` int(4) NOT NULL,
      `total_days` decimal(10,2) NOT NULL DEFAULT '0.00',
      `used_days` decimal(10,2) NOT NULL DEFAULT '0.00',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_leave_year` (`user_id`,`leave_type_id`,`year`),
      KEY `leave_type_id` (`leave_type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create notifications table
    CREATE TABLE IF NOT EXISTS `notifications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `title` varchar(255) NOT NULL,
      `message` text NOT NULL,
      `related_to` varchar(50) DEFAULT NULL,
      `related_id` int(11) DEFAULT NULL,
      `is_read` tinyint(1) NOT NULL DEFAULT '0',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Create audit_logs table
    CREATE TABLE IF NOT EXISTS `audit_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `action` varchar(100) NOT NULL,
      `entity_type` varchar(50) NOT NULL,
      `entity_id` int(11) DEFAULT NULL,
      `details` text,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `entity_type` (`entity_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $statements = array_filter(array_map('trim', explode(';', $create_tables_sql)));
    foreach ($statements as $statement) {
        if (!empty($statement) && !str_starts_with(trim($statement), '--')) {
            executeSQL($conn, $statement, "Creating table structure");
        }
    }
}

// Insert basic data
echo "\nInserting basic data...\n";

// Insert departments
executeSQL($conn, "
INSERT IGNORE INTO `departments` (`name`, `code`, `description`) VALUES
('Computer Science', 'CSE', 'Department of Computer Science and Engineering'),
('Mathematics', 'MATH', 'Department of Mathematics'),
('Physics', 'PHY', 'Department of Physics'),
('Administration', 'ADMIN', 'Administrative Department')
", "Inserting departments");

// Insert leave types
executeSQL($conn, "
INSERT IGNORE INTO `leave_types` (`name`, `description`, `max_days`, `applicable_to`, `color`) VALUES
('Annual Leave', 'Annual vacation leave', 21, 'staff,head_of_department,director,admin', '#28a745'),
('Sick Leave', 'Medical leave for illness', 10, 'staff,head_of_department,director,admin', '#dc3545'),
('Personal Leave', 'Personal time off', 5, 'staff,head_of_department,director,admin', '#ffc107'),
('Emergency Leave', 'Emergency situations', 3, 'staff,head_of_department,director,admin', '#fd7e14')
", "Inserting leave types");

// Insert test users
executeSQL($conn, "
INSERT IGNORE INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department_id`, `position`, `phone`, `status`) VALUES
('DIR001', 'Dr. Alexandra', 'Thompson', 'director@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'director', 4, 'Director of Academic Affairs', '+1-555-0300', 'active'),
('ADM001', 'System', 'Administrator', 'admin@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'admin', 4, 'System Administrator', '+1-555-0400', 'active'),
('HOD001', 'Dr. Richard', 'Parker', 'hod@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'head_of_department', 1, 'Head of Computer Science', '+1-555-0500', 'active'),
('STF001', 'Alice', 'Johnson', 'staff@college.edu', '\$2y\$10\$WBdPOc2trvu6G6hYalw.9.l3S3DEOOd25b96ow3SMBgnV0ZN.ls2q', 'staff', 1, 'Assistant Professor', '+1-555-0600', 'active')
", "Inserting test users");

// Create leave balances for staff user
executeSQL($conn, "
INSERT IGNORE INTO `leave_balances` (`user_id`, `leave_type_id`, `year`, `total_days`, `used_days`) 
SELECT 
    u.id,
    lt.id,
    YEAR(CURDATE()),
    lt.max_days,
    0
FROM users u
CROSS JOIN leave_types lt
WHERE u.employee_id = 'STF001'
AND u.role = 'staff'
", "Creating leave balances for staff");

// Verify setup
echo "\nVerifying setup...\n";

try {
    // Check users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    echo "✓ Users in database: $user_count\n";
    
    // Check departments
    $stmt = $conn->query("SELECT COUNT(*) as count FROM departments");
    $dept_count = $stmt->fetch()['count'];
    echo "✓ Departments in database: $dept_count\n";
    
    // Check leave types
    $stmt = $conn->query("SELECT COUNT(*) as count FROM leave_types");
    $leave_count = $stmt->fetch()['count'];
    echo "✓ Leave types in database: $leave_count\n";
    
    // Show test users
    echo "\nTest Users Created:\n";
    echo "==================\n";
    $stmt = $conn->query("
        SELECT 
            employee_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            email,
            role
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
    
    while ($user = $stmt->fetch()) {
        echo sprintf("%-8s | %-25s | %-25s | %s\n", 
            $user['employee_id'],
            $user['full_name'],
            $user['email'],
            ucwords(str_replace('_', ' ', $user['role']))
        );
    }
    
    echo "\nPassword for all test users: password123\n";
    echo "\n✓ Database setup completed successfully!\n";
    echo "\nYou can now:\n";
    echo "1. Visit test_users.php to see all test accounts\n";
    echo "2. Login with any of the test accounts to test the workflow\n";
    echo "3. Start testing the leave approval process\n";
    
} catch (Exception $e) {
    echo "Error during verification: " . $e->getMessage() . "\n";
}
?>