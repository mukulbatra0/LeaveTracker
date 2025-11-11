<?php
// Include database connection
require_once 'config/db.php';

try {
    echo "=== Database Structure Analysis and Fix ===\n\n";
    
    // Check if leave_applications table exists and has the right columns
    echo "1. Checking leave_applications table...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'leave_applications'");
    if ($stmt->rowCount() > 0) {
        echo "✅ leave_applications table exists\n";
        
        // Check columns
        $stmt = $conn->query("DESCRIBE leave_applications");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
            echo "   - {$row['Field']} ({$row['Type']})\n";
        }
        
        // Check if applied_at column exists
        if (!in_array('applied_at', $columns)) {
            echo "ℹ️  'applied_at' column missing (this is expected - we use 'created_at')\n";
        }
        
        if (!in_array('created_at', $columns)) {
            echo "❌ 'created_at' column missing - this needs to be added!\n";
        } else {
            echo "✅ 'created_at' column exists\n";
        }
    } else {
        echo "❌ leave_applications table does not exist!\n";
    }
    
    echo "\n2. Checking leave_balances table...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'leave_balances'");
    if ($stmt->rowCount() > 0) {
        echo "✅ leave_balances table exists\n";
        
        // Check columns
        $stmt = $conn->query("DESCRIBE leave_balances");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
            echo "   - {$row['Field']} ({$row['Type']})\n";
        }
        
        // Check for the correct column names
        $has_total_days = in_array('total_days', $columns);
        $has_used_days = in_array('used_days', $columns);
        $has_balance = in_array('balance', $columns);
        $has_used = in_array('used', $columns);
        
        if ($has_total_days && $has_used_days) {
            echo "✅ Correct column names (total_days, used_days) found\n";
        } elseif ($has_balance && $has_used) {
            echo "⚠️  Old column names (balance, used) found - need to rename\n";
            
            // Rename columns
            echo "Renaming columns...\n";
            $conn->exec("ALTER TABLE leave_balances CHANGE COLUMN balance total_days DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            $conn->exec("ALTER TABLE leave_balances CHANGE COLUMN used used_days DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            echo "✅ Columns renamed successfully\n";
        } else {
            echo "❌ Column structure is inconsistent\n";
        }
    } else {
        echo "❌ leave_balances table does not exist!\n";
        
        // Create the table with correct structure
        echo "Creating leave_balances table...\n";
        $create_sql = "CREATE TABLE leave_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            year YEAR NOT NULL DEFAULT (YEAR(CURDATE())),
            total_days DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            used_days DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_leave_year (user_id, leave_type_id, year)
        )";
        
        $conn->exec($create_sql);
        echo "✅ leave_balances table created successfully\n";
    }
    
    echo "\n=== Fix completed! ===\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>