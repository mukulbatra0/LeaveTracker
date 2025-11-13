<?php
/**
 * Database Diagnostic Script
 * Check current database state and configuration
 */

require_once 'config/db.php';

echo "=== Database Diagnostic ===\n\n";

try {
    // Check database connection
    echo "✓ Database connection successful\n";
    
    // Get current database name
    $stmt = $conn->query("SELECT DATABASE() as db_name");
    $current_db = $stmt->fetch()['db_name'];
    echo "Current database: $current_db\n\n";
    
    // Check tables
    echo "Checking tables:\n";
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "✗ No tables found in database!\n";
        echo "\nTo fix this, run: php setup_database.php\n";
    } else {
        foreach ($tables as $table) {
            echo "✓ $table\n";
        }
    }
    
    // Check users if table exists
    if (in_array('users', $tables)) {
        echo "\nUsers in database:\n";
        $stmt = $conn->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) as name, email, role FROM users ORDER BY role");
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo "✗ No users found\n";
        } else {
            foreach ($users as $user) {
                echo "- {$user['employee_id']}: {$user['name']} ({$user['email']}) - {$user['role']}\n";
            }
        }
    }
    
    // Check departments if table exists
    if (in_array('departments', $tables)) {
        echo "\nDepartments in database:\n";
        $stmt = $conn->query("SELECT code, name FROM departments");
        $departments = $stmt->fetchAll();
        
        if (empty($departments)) {
            echo "✗ No departments found\n";
        } else {
            foreach ($departments as $dept) {
                echo "- {$dept['code']}: {$dept['name']}\n";
            }
        }
    }
    
    // Check leave types if table exists
    if (in_array('leave_types', $tables)) {
        echo "\nLeave types in database:\n";
        $stmt = $conn->query("SELECT name, max_days FROM leave_types");
        $leave_types = $stmt->fetchAll();
        
        if (empty($leave_types)) {
            echo "✗ No leave types found\n";
        } else {
            foreach ($leave_types as $type) {
                echo "- {$type['name']}: {$type['max_days']} days\n";
            }
        }
    }
    
    echo "\n";
    
    if (empty($tables)) {
        echo "RECOMMENDATION: Run 'php setup_database.php' to create the complete database structure.\n";
    } elseif (empty($users)) {
        echo "RECOMMENDATION: Run 'php add_test_users.php' to add test users.\n";
    } else {
        echo "✓ Database appears to be set up correctly!\n";
        echo "You can now test the leave approval workflow.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "\nCheck your database configuration in config/db.php and .env file\n";
}
?>