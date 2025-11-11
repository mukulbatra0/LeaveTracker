<?php
// Include database connection
require_once 'config/db.php';

try {
    echo "=== Fixing Database Structure ===\n\n";
    
    // 1. Check and fix leave_balances table structure
    echo "1. Checking leave_balances table...\n";
    
    // Check if table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'leave_balances'");
    if ($stmt->rowCount() == 0) {
        echo "Creating leave_balances table...\n";
        $create_sql = "CREATE TABLE leave_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            total_days DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            used_days DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            year YEAR NOT NULL DEFAULT (YEAR(CURDATE())),
            last_accrual_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_leave_year (user_id, leave_type_id, year)
        )";
        $conn->exec($create_sql);
        echo "✅ leave_balances table created\n";
    } else {
        echo "Table exists, checking columns...\n";
        
        // Get current columns
        $stmt = $conn->query("DESCRIBE leave_balances");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        // Check if we need to rename columns
        if (in_array('balance', $columns) && !in_array('total_days', $columns)) {
            echo "Renaming 'balance' to 'total_days'...\n";
            $conn->exec("ALTER TABLE leave_balances CHANGE COLUMN balance total_days DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            echo "✅ Column renamed\n";
        }
        
        if (in_array('used', $columns) && !in_array('used_days', $columns)) {
            echo "Renaming 'used' to 'used_days'...\n";
            $conn->exec("ALTER TABLE leave_balances CHANGE COLUMN used used_days DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            echo "✅ Column renamed\n";
        }
        
        // Add missing columns if needed
        if (!in_array('year', $columns)) {
            echo "Adding 'year' column...\n";
            $conn->exec("ALTER TABLE leave_balances ADD COLUMN year YEAR NOT NULL DEFAULT (YEAR(CURDATE()))");
            echo "✅ Year column added\n";
        }
    }
    
    // 2. Check leave_applications table
    echo "\n2. Checking leave_applications table...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'leave_applications'");
    if ($stmt->rowCount() > 0) {
        // Check columns
        $stmt = $conn->query("DESCRIBE leave_applications");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        if (in_array('created_at', $columns)) {
            echo "✅ leave_applications has 'created_at' column (good for replacing 'applied_at')\n";
        } else {
            echo "❌ Missing 'created_at' column\n";
        }
        
        // Add working_days column if missing
        if (!in_array('working_days', $columns)) {
            echo "Adding 'working_days' column...\n";
            $conn->exec("ALTER TABLE leave_applications ADD COLUMN working_days DECIMAL(10,2) DEFAULT NULL AFTER days");
            echo "✅ working_days column added\n";
        }
    } else {
        echo "❌ leave_applications table doesn't exist\n";
    }
    
    // 3. Check leave_types table for missing columns
    echo "\n3. Checking leave_types table...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'leave_types'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("DESCRIBE leave_types");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        // Add missing columns
        if (!in_array('color', $columns)) {
            echo "Adding 'color' column...\n";
            $conn->exec("ALTER TABLE leave_types ADD COLUMN color VARCHAR(7) DEFAULT '#007bff'");
            echo "✅ color column added\n";
        }
        
        if (!in_array('default_days', $columns)) {
            echo "Adding 'default_days' column...\n";
            $conn->exec("ALTER TABLE leave_types ADD COLUMN default_days INT DEFAULT 0");
            echo "✅ default_days column added\n";
        }
        
        if (!in_array('is_active', $columns)) {
            echo "Adding 'is_active' column...\n";
            $conn->exec("ALTER TABLE leave_types ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
            echo "✅ is_active column added\n";
        }
    }
    
    echo "\n=== Database structure fix completed! ===\n";
    echo "The 'applied_at' column error should now be resolved.\n";
    echo "All references to 'applied_at' have been changed to 'created_at' in the code.\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>